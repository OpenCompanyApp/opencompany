# Agent Run Queue Control Plane

Status: Implemented in the current working tree.
Verified: 2026-08-09 against local code and applied local migrations. This is
not a claim that the work is committed, deployed, or fully production-rehearsed.

OpenCompany intentionally uses one physical Laravel queue, currently the
`default` queue. Reliability and tenant isolation live above that queue in a
durable application control plane. Queue rows transport work; they are not the
canonical record of what the agent is doing.

## Ownership

| Record/component | Owns | Does not own |
|---|---|---|
| `jobs` / `failed_jobs` | Physical reservation, lease, delivery attempt | Workspace-visible run state or safe operator replay |
| `tasks` | Human-visible unit of work, progress, result, lifecycle | Physical queue attempts |
| `agent_runs` | Logical identity, workspace, semantic attempts, heartbeat, cancellation, terminal state, dispatch key | Tool side effects or display timeline |
| `agent_tool_executions` | Pre-call claim, ordered idempotency signature, encrypted replay result, ambiguity | Human-facing tool history |
| `task_steps` | Completed tool/progress evidence shown to humans and retry context | Pre-call crash safety |
| `messages.agent_run_id` | Exactly correlated visible response/error delivery | General conversation membership |

The one-to-one `Task` / `AgentRunRecord` pairing is the central invariant. A
Laravel queue retry reuses that pair; it never invents a new logical run.

## Execution flow

```text
request / schedule / Telegram lane
  -> create or resolve Task + AgentRunRecord
  -> enqueue adapter on default queue
  -> bind exact workspace, fail closed if missing
  -> acquire shared per-agent lock across every adapter type
  -> reject terminal, paused, offline, or cancelled work
  -> begin one semantic attempt and bind ActiveAgentRun
  -> execute model, Lua, or subagent runtime
       -> pre-call AgentToolExecution claim
       -> mutation / external request
       -> encrypted result or ambiguous outcome
       -> completed TaskStep checkpoint
  -> deliver one Message correlated by agent_run_id
  -> commit Task and AgentRunRecord terminal state
```

Chat, assigned tasks, prompt automations, script automations, and direct
subagent calls use the same per-agent cache lock key. Different agents may run
concurrently; one agent cannot run two logical jobs at once.

## Retries and timing

- Physical overlap releases are bounded by a 24-hour `retryUntil` deadline.
  They do not consume the logical provider retry budget.
- Chat and assigned-task runs allow three semantic attempts. Automations allow
  two. Only classified transient provider/infrastructure failures retry.
- Long model jobs have a 1,800-second timeout. Database/Redis queue leases and
  Supervisor shutdown grace are 1,900 seconds, so a job is not re-reserved while
  its original worker is still allowed to run.
- Hard timeouts fail the physical job. The failed callback and stale reconciler
  converge the Task and AgentRunRecord to a terminal state.
- Synchronous queue-insert failure returns a claimed task to `paused` / `waiting`
  with the dispatch error instead of leaving an active task without a job.

## Side-effect safety

`RunGuardedTool` and Lua bridge calls write `agent_tool_executions` before
entering mutating code. Calls are ordered per logical run and surface. This
preserves two distinct cases:

- The same completed call encountered by a retry returns its encrypted stored
  result without executing again.
- A call left running, a call that threw after the request may have been sent,
  or a retry that diverges at the same logical sequence becomes `ambiguous` and
  is not repeated automatically.

This is conservative at-most-once protection. It does not claim distributed
exactly-once behavior because OpenCompany cannot atomically commit its database
with every third-party API. Ambiguous outcomes require inspection or a new
operator-authorized logical run.

Completed provider tool calls are also persisted as TaskSteps. On a retry,
those steps are injected into model context; the provider tool sequence begins
after the persisted checkpoints. A completed ledger call in the small window
before its TaskStep was written is instead replayed from the ledger.

## Cancellation and pause

`ActiveAgentRun` checkpoints at model boundaries, stream events, tool calls,
and Lua bridge calls. Cancellation is durable on the run record, so a delayed
or duplicate queue reservation cannot resurrect it. Pause moves the Task and
run to `paused` / `waiting`; resume claims the task under a row lock, restores a
queueable run, and dispatches one adapter.

Terminal state always wins over agent availability. An offline agent cannot
turn a cancelled or failed run back into waiting state.

## Automations and Telegram

Every automation occurrence has a UUID dispatch key. The due command locks the
schedule row and creates its Task/AgentRunRecord before enqueueing. If the
scheduler dies between that durable claim and queue insertion, the reconciler
redispatches the same key instead of creating a duplicate run.

Telegram lane admission and handoff lock the channel, use a temporary dispatch
claim, and dispatch outside the transaction. Stale claims are recoverable. Task
pause/resume/cancel commands go through `ManageTasks`, so web, Telegram, Task,
and AgentRun states emit the same canonical `TaskUpdated` event.

## Workspace and operator boundaries

- Queue adapters bind the serialized workspace before any application work and
  clear it in `finally`, including failed-job callbacks.
- Caller-supplied agent, requester, channel, parent-task, and list-item IDs are
  validated against the active workspace.
- Direct messages are unique by workspace and canonical participant key. A
  legacy row is repaired only through a channel proven to belong to the active
  workspace.
- Settings diagnostics read workspace-scoped `agent_runs`; they do not expose
  raw global `jobs`, `failed_jobs`, or exception payloads.
- “Retry failed jobs” creates a new logical run in the active workspace. “Flush”
  dismisses the workspace alert while retaining audit history.

## Reconciliation and observability

`agent-runs:reconcile` runs every five minutes and:

- fails genuinely stale running/retrying runs after the worker lease window;
- recovers stale queued automation dispatches from their durable dispatch key;
- recovers stale Telegram lane claims; and
- reconciles terminal Task/AgentRunRecord mismatches without reviving work.

Queue lifecycle events publish a global worker heartbeat in cache. Workspace
queue health reports logical queued/running/waiting/retrying/failed counts,
oldest open time, semantic attempts, recent bounded errors, and that heartbeat.

## Intentional limitation

One physical queue means there is no workload-class priority or reserved
capacity: broad saturation can delay chat, automation, broadcasts, and other
jobs together. Per-agent serialization, durable logical records, wall-clock
retry windows, and recovery prevent corruption or silent loss, but they do not
provide latency isolation. Splitting queues remains a future operational choice,
not part of this implementation.

## Known boundaries and follow-up

- The legacy HTTP direct-message creation path in `DirectMessageController`
  does not yet use `DirectMessageResolver`. It remains a P0 exception to the
  workspace/participant-validation statement above and must be removed or
  routed through the resolver.
- This control plane protects queued agent execution and tool side effects. It
  does not close the human channel-membership, document ACL, approval-role, or
  settings-authorization gaps tracked in `docs/ROADMAP.md`.
- Direct subagent calls use durable child task/run records, but dependency
  orchestration still executes branches sequentially. Concurrent branch
  scheduling, queue delivery modes, auto-dissolution, and a complete child-run
  operator UI are future work.
