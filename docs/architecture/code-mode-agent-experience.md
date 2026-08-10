# Code Mode Agent Experience

Status: implemented with the QuickJS hard cut.

Code Mode should feel like a small, typed, synchronous SDK—not an embedded
general-purpose computer. The agent gets fast discovery, exact contracts,
bounded execution, repairable errors, and honest effect information while the
host retains every permission, credential, workspace, and side-effect boundary.

## Golden path

```text
intent
  -> code_search_docs when the namespace is unknown
  -> code_read_doc for exact parameters, effects, and returns
  -> code_exec(mode="validate") for unfamiliar source
  -> one minimal read-only probe when a return shape remains unclear
  -> code_exec(mode="execute") for bounded deterministic work
  -> inspect return/output and effect status
  -> verify state before retrying any ambiguous write
```

The system prompt teaches this workflow directly. The four tool names share one
`code_` prefix so they are easy for a model to select and difficult to confuse
with direct domain tools.

## Discoverability

`CodeApiDocGenerator` derives the permission-visible namespace tree from the
same `ToolRegistry` used by runtime dispatch. Integration-core contributes
parameter definitions, effect metadata, return metadata, account aliases, and
package-owned supplementary docs. Static guides under `resources/code-docs`
cover runtime behavior rather than duplicating provider signatures.

The important consequence is that documentation cannot grant authority or
advertise a callable absent from the current workspace catalog. Unknown paths
are ranked against that same map and return fully qualified suggestions such as
`app.chat.send_channel_message`.

## JavaScript contract

- Source is a function body, so top-level `return` is valid.
- Calls are synchronous: `app.docs.get({...})`, never `await`.
- Named object arguments are preferred. Positional calls follow the published
  parameter order but are less self-documenting.
- Results are JSON-compatible values: null, booleans, numbers, strings, arrays,
  and plain objects.
- `console.log`, `console.info`, `console.warn`, and `console.error` are standard;
  `print` and `dump` are compact aliases.
- There is no filesystem, network, process, environment, module loader, Promise
  job loop, host clock authority, or bytecode interface.
- `app.*` exists only when the host explicitly supplies `CodeBridge`. The
  developer console remains capability-empty.

## Validation before effects

Validation compiles source in the target profile without running the user body
or exposing capabilities. Script automation create/update performs this check
before persistence and pins successful scripts to `quickjs-v1`.

At execution time integration-core normalizes the single named object or
positional arguments and rejects known mistakes before provider dispatch:

- missing required parameters;
- unknown parameter names;
- primitive type mismatches;
- invalid enum values;
- published number and string/array bounds;
- mixing a named object with positional values.

The provider remains the final validator when a package does not publish a
schema. The bridge must not invent a restrictive contract from missing metadata.

## Errors that teach repair

Every runtime failure has a stable shape:

| Field | Agent meaning |
|---|---|
| `type` | Stable category such as `syntax_error`, `unknown_function`, `invalid_arguments`, `timeout`, or `tool_error` |
| `message` | Secret-redacted, bounded explanation |
| `line` / `column` | Location in the user's source body, not the native wrapper |
| `suggestion` | Concrete next action |
| `retryable` | Whether rerunning the whole body cannot duplicate known or ambiguous writes |
| `effectStatus` | `none`, `succeeded`, or `unknown` for writes before failure |

Syntax errors point back to validation. Unknown calls point to docs and include
ranked real paths. Size errors tell the agent to narrow or page reads. Time,
memory, and stack errors recommend bounded loops, smaller batches, or iteration.

Provider errors are redacted at the app bridge before entering model output,
task traces, the console, or automation history. Raw credentials and bearer
tokens remain host-owned.

## Effect-aware retry

Every capability call records:

```json
{
  "index": 1,
  "path": "chat.send_channel_message",
  "status": "ok",
  "effect": "write",
  "effectStatus": "succeeded",
  "retryable": true,
  "durationMs": 12.4
}
```

`retryable` on an individual successful call describes that call, while the
execution summary answers the important question: may the complete program be
rerun? Once a write succeeds—or a provider fails after a write was dispatched—
the complete execution is non-retryable. An agent must read current state before
choosing a compensating action. Pre-dispatch validation failures have no effect
and can be repaired safely.

## Bounded cognition and output

Server-owned profiles cap source, JS CPU, memory, stack, console bytes/log count,
return bytes, callback count, callback response bytes, per-call wall time, and
total callback wall time. Agents cannot supply native resource controls.

This improves experience as much as safety: a runaway transformation becomes a
typed timeout, an oversized query becomes a paging instruction, and excessive
logging becomes one visible truncation sentinel rather than silently consuming
the conversation context.

## Traces without prompt pollution

`CodeExec` returns concise console output, return value, structured repair text,
execution ID, and timing to the model. Rich logs, memory, effects, and capability
calls travel through `lastExecutionMetadata()` to the synchronous
`CheckpointToolCall` listener. There are no hidden HTML comments or delimiter
markers in model-visible output.

Task traces render JavaScript, structured errors, wall/CPU/peak-memory metrics,
and the capability ledger. “Open in Code Console” transfers only source through
one-shot session storage; it does not transfer credentials or capabilities.

## Automation cutover

Persisted scripts carry `script_runtime`. New scripts are compiled and pinned to
`quickjs-v1`. Legacy scripts are preserved verbatim, disabled, and annotated
with an operator-facing migration record. Editing and validating the rewritten
JavaScript pins the new runtime. There is no heuristic source translation and no
dual-runtime fallback.

## Evaluation gates

The regression corpus must keep these outcomes true:

1. a model can discover a function from intent and read an exact signature;
2. a valid multi-step read-only program returns structured data;
3. syntax locations point to the user body;
4. a misspelled function suggests a real permission-visible path;
5. bad arguments cause zero provider invocations;
6. a failed write is `unknown` and prevents blind full-program retry;
7. secrets are absent from model output and durable trace metadata;
8. validation never executes user code or capabilities;
9. time, memory, stack, callback, result, source, and output bounds fail cleanly;
10. the console has no `app.*` authority;
11. trace metadata requires no hidden output marker;
12. legacy automations cannot run until manually rewritten and pinned;
13. all package JavaScript examples compile in the released native runtime;
14. active source, routes, prompts, UI, tests, and package contracts contain no
    legacy runtime identifiers.

Focused PHPUnit tests protect the native boundary, bridge, CodeExec contract,
automation lifecycle, tool catalog guidance, and trace side channel. The sibling
integrations repository validates every fenced JavaScript example and its shared
runtime tests execute representative snippets through `QuickJS\Sandbox`.
