# Sub-Agent Swarm Gaps & Frankenstein Analysis

**Date:** 2026-03-31  
**Scope:** What KosmoKrator needs to run 3,000+ agent swarms (e.g., tax treaty collection/analysis), and what can be ported from Claude Code (`tmp/claude-src`) and OpenCode (`tmp/opencode`).

---

## Executive Summary

KosmoKrator's sub-agent system works well for dozens of agents but cannot scale to thousands. Three critical gaps block a 3K-agent swarm: no concurrency cap (rate limit death), no retry logic (429 kills agents permanently), and background agent cancellation (parent loop kills running agents). All three have proven patterns in Claude Code and OpenCode that translate directly to PHP.

---

## Current Sub-Agent System — What Works

- **Amp async** futures provide genuine concurrency
- **Dependency resolution** correctly awaits and injects results between agents
- **Group semaphores** serialize agents within named groups (`LocalSemaphore(1)`)
- **Tool scoping** correctly restricts tools per agent type (explore=read-only, plan=read-only, general=read-write)
- **Agent type hierarchy** enforces permission narrowing (General→Explore, General→Plan)
- **Context overflow recovery** auto-trims and retries

## Today's Session Stats (from logs)

| Metric | Value |
|--------|-------|
| Agents spawned | 153 |
| Agents completed | 149 |
| Agents cancelled | 4 |
| Headless errors | 4 |
| Rate-limit (429) errors | 30 |
| Context overflows (auto-recovered) | 1 |
| Max depth reached | 2 (of 3 configured) |
| Longest agent | 425s, 110 tool calls |

---

## Critical Gaps

### Gap 1: No Global Concurrency Cap

**Problem:** The orchestrator spawns unlimited `Amp\async()` futures. With hundreds of concurrent LLM calls, the API returns 429s faster than agents can complete.

**Evidence:** 30 rate-limit errors in a single day with only 153 agents.

### Gap 2: No Retry Logic for Failed Agents

**Problem:** A 429 rate-limit kill on agent #12 is a dead agent — its treaty is lost. `SubagentStats` records the failure but nothing retries.

**Evidence:** 4 agents completed with `tokens_in: 0` — the LLM call never returned before cancellation.

### Gap 3: Background Agent Cancellation Fragility

**Problem:** Background agents share the parent's `Cancellation` token via `NullRenderer`. When the parent loop advances (e.g., processing a bash command), running background agents can be killed.

**Evidence:** The dependency chain test showed `step-2-analyzer` cancelled at round 1 with `tokens_in: 0`, followed by `step-3-reporter` also cancelled.

### Gap 4: No Persistence or Checkpointing

**Problem:** Sub-agent results are in-memory strings. Kill the process at agent 1,847 and you lose everything. No resume capability exists.

### Gap 5: No Cost Tracking Aggregation

**Problem:** `SubagentStats` tracks tokens per agent but there's no USD cost accumulation across the swarm. No way to enforce a budget.

### Gap 6: No Progress Dashboard

**Problem:** No aggregated view of swarm progress. For a multi-hour run, you're flying blind.

### Gap 7: No Structured Result Storage

**Problem:** Results are plain strings injected into conversation history. Extracting structured data from 3K string blobs is impractical.

### Gap 8: Memory Pressure

**Problem:** Each sub-agent holds conversation history. The parent's history grows as results are injected. Context overflow triggers `trimOldest()` which loses earlier results.

---

## Frankenstein Sources

### Source 1: Claude Code (`tmp/claude-src`)

Claude Code's TypeScript agent system. Key files analyzed:

| File | What It Contains |
|------|-----------------|
| `services/api/withRetry.ts` | Exponential backoff with jitter, `Retry-After` header parsing, retry decision tree by HTTP status |
| `utils/generators.ts` | Concurrent generator pool with configurable cap |
| `utils/task/diskOutput.ts` | Async write queue per task, 5GB disk cap, session-scoped output directory |
| `utils/sessionRestore.ts` | Full session restore from transcript files |
| `cost-tracker.ts` | Per-model token/cost accumulation with USD calculation |
| `services/tools/StreamingToolExecutor.ts` | Concurrency-safe tool execution (read-only parallel, writes exclusive) |
| `tools/AgentTool/runAgent.ts:524-528` | Async agents get **unlinked AbortController** — survive parent cancel |
| `coordinator/coordinatorMode.ts` | 370-line coordinator system prompt (research→synthesis→implementation→verification) |
| `tools/AgentTool/agentToolUtils.ts` | Progress tracking, agent lifecycle (completed/killed/failed), notification format |
| `utils/toolResultStorage.ts` | Persists oversized tool results to disk with preview |

### Source 2: OpenCode (`tmp/opencode`)

Open-source Claude Code alternative (TypeScript/Bun, Effect-TS). Key files analyzed:

| File | What It Contains |
|------|-----------------|
| `packages/opencode/src/session/retry.ts` | Clean retry system with `retry-after-ms` header support, `retryable()` error classifier |
| `packages/opencode/src/util/queue.ts` | `work(concurrency, items, fn)` — dead-simple N-worker pool. Also `AsyncQueue<T>` |
| `packages/opencode/src/tool/task.ts` | Sub-agent tool that creates child sessions, supports resume via `task_id` |
| `packages/opencode/src/tool/batch.ts` | Parallel tool execution up to 25 concurrent calls |
| `packages/opencode/src/session/index.ts:244-303` | Per-message cost calculation with `Decimal` precision |
| `packages/opencode/src/snapshot/index.ts` | Git-based filesystem checkpointing |
| `packages/opencode/src/storage/db.ts` | SQLite persistence for sessions/messages |
| `packages/opencode/src/cli/cmd/stats.ts` | CLI stats: total cost, cost/day, per-model breakdown |

---

## Porting Plan — Component by Component

### 1. Global Concurrency Semaphore

**Source pattern:** OpenCode `util/queue.ts` `work()` function + Claude Code `utils/generators.ts` `all()` with `concurrencyCap`.

**Implementation in KosmoKrator:**

```php
// In SubagentOrchestrator, add a class-level semaphore
private LocalSemaphore $globalSemaphore;

public function __construct(/* ... */) {
    $this->globalSemaphore = new LocalSemaphore(10); // max 10 concurrent agents
}

// In spawnAgent(), wrap the Amp\async() block:
$lock = $this->globalSemaphore->acquire();
// ... inside async, after agent completes:
$lock->release();
```

**Effort:** ~20 lines. We already use `LocalSemaphore(1)` for group constraints — just need one more instance for the global cap.

**Configuration:** Should be configurable via `subagent_max_concurrency` setting.

---

### 2. Retry Logic with Exponential Backoff

**Source pattern:** OpenCode `session/retry.ts` (cleaner, ~105 lines) + Claude Code `withRetry.ts:530-548` (jitter formula).

**Key code to port:**

```
delay formula: min(base * 2^attempt + random_jitter, maxDelay)
retry-after-ms header: use directly if present
retry-after header: parse as seconds or HTTP date
retryable errors: 429, 529/overloaded, 408, 5xx
non-retryable: context overflow
```

**Implementation in KosmoKrator:** Enhance `RetryableLlmClient` with:
- `Retry-After` and `Retry-After-Ms` header parsing
- Jittered exponential backoff (base 500ms, max 32s)
- Per-agent retry (wrap the `runHeadless()` call in the orchestrator)
- Unattended mode: indefinite retries with 5-min max backoff

**Effort:** ~100 lines.

**Constants from Claude Code:**
- `BASE_DELAY_MS = 500`
- `DEFAULT_MAX_RETRIES = 10`
- `MAX_529_RETRIES = 3` (before model fallback)

---

### 3. Background Agent Decoupled Cancellation

**Source pattern:** Claude Code `tools/AgentTool/runAgent.ts:524-528`:
```typescript
const agentAbortController = isAsync
    ? new AbortController()      // NEW controller for async agents
    : toolUseContext.abortController;  // shared for sync agents
```

**Implementation in KosmoKrator:** In `SubagentFactory.php`, when creating `NullRenderer` for background agents, pass `null` for the cancellation closure instead of the parent's token. Add a process-level signal handler for Ctrl+C that sets a separate `Cancellation` shared by all agents.

**Effort:** ~5 lines in factory + ~20 lines for signal handler.

**Current code location:** `src/Agent/SubagentFactory.php:49` and `src/UI/NullRenderer.php:45-52`.

---

### 4. Disk-Based Result Persistence & Resume

**Source pattern:** Claude Code `utils/task/diskOutput.ts` — `DiskTaskOutput` class with async write queue, session-scoped directory, `O_NOFOLLOW` security.

**Implementation in KosmoKrator:**

```
Storage layout:
~/.kosmokrator/tasks/{sessionId}/{agentId}.jsonl

Each line is a JSON event:
  {"ts":"...","event":"started","task":"..."}
  {"ts":"...","event":"tool_call","tool":"grep"}
  {"ts":"...","event":"progress","tokens_in":1234}
  {"ts":"...","event":"completed","result":"...","tokens_total":5678}

Resume logic:
1. On swarm start, scan output directory for completed agent IDs
2. Load their results from disk
3. Skip those agents when scheduling new work
4. Continue from where we left off
```

**Key security from Claude Code:** Use `O_NOFOLLOW` equivalent (check not a symlink before write) to prevent sandbox attacks.

**Effort:** ~200 lines (writer class + resume scanner).

---

### 5. Cost Tracking Aggregation

**Source pattern:** Claude Code `cost-tracker.ts:250-323` — `addToTotalSessionCost()` with per-model USD calculation.

**Implementation in KosmoKrator:**

```php
class SwarmCostTracker {
    private array $perModel = []; // model → {input, output, cost_usd}
    private float $budgetUsd;
    
    public function add(string $model, int $in, int $out): void {
        $cost = ModelCatalog::calculateCost($model, $in, $out);
        // accumulate, check budget
    }
    
    public function getSummary(): SwarmCostSummary {
        // completed/total, total cost, per-model breakdown, ETA
    }
}
```

**Dependency:** Needs `ModelCatalog` to know per-token prices for each model. Currently KosmoKrator has `ModelCatalog` but may need pricing data added.

**Effort:** ~80 lines.

---

### 6. Progress Dashboard

**Source pattern:** Claude Code `tools/AgentTool/agentToolUtils.ts:538-593` — progress tracker with token counts, tool use, activity descriptions.

**Implementation in KosmoKrator:** Aggregate existing `SubagentStats` into a `SwarmProgress` view:

```
┌─ Swarm Progress ─────────────────────────────┐
│ Completed: 1,247 / 3,000 (41.6%)             │
│ Failed:     23 (retried: 18)                  │
│ Running:    10                                │
│ Tokens:     2.4M in / 312K out               │
│ Cost:       $47.12                            │
│ Elapsed:    34m 12s                           │
│ ETA:        ~48m                              │
└───────────────────────────────────────────────┘
```

**Effort:** ~150 lines (aggregator + renderer).

---

### 7. Tool Result Size Persistence

**Source pattern:** Claude Code `utils/toolResultStorage.ts:55-78` — `getPersistenceThreshold()` per tool.

**Implementation in KosmoKrator:** Before injecting sub-agent result into parent's conversation history:
1. Check `strlen($result)` against threshold (e.g., 100KB for agent results)
2. If exceeded, write to `~/.kosmokrator/results/{agentId}.txt`
3. Replace with summary: `[Result persisted to disk: {path} ({size})]`
4. Parent LLM can use `file_read` if it needs the full result

**Effort:** ~100 lines.

---

### 8. Concurrency-Safe Tool Execution

**Source pattern:** Claude Code `StreamingToolExecutor.ts:129-135`:
```typescript
private canExecuteTool(isConcurrencySafe: boolean): boolean {
    const executing = this.tools.filter(t => t.status === 'executing')
    return executing.length === 0 
        || (isConcurrencySafe && executing.every(t => t.isConcurrencySafe))
}
```

**Implementation in KosmoKrator:** Add `isConcurrencySafe(): bool` to `ToolInterface`. Read-only tools (`file_read`, `glob`, `grep`) return `true`. Destructive tools (`bash`, `file_edit`, `file_write`) return `false`. In `AgentLoop`'s tool dispatch, serialize non-safe tools.

**Effort:** ~60 lines.

---

### 9. Coordinator Mode

**Source pattern:** Claude Code `coordinator/coordinatorMode.ts:111-368` — 370-line system prompt defining the coordinator role.

**Key concepts to port:**
- Phases: Research (parallel workers) → Synthesis (coordinator) → Implementation (workers) → Verification (workers)
- Workers can't see coordinator's conversation (self-contained prompts)
- `<task-notification>` XML format for delivering results
- `SendMessage` tool for continuing a running worker
- Parallelism guidance: "Launch independent workers concurrently"

**Implementation in KosmoKrator:** Add a `--coordinator` flag that swaps the system prompt and enables the coordinator tool set. Pure prompt engineering — no code architecture changes needed.

**Effort:** ~50 lines (flag + prompt template).

---

## Priority Matrix

| Priority | Component | Effort | Impact | Source |
|----------|-----------|--------|--------|--------|
| **P0** | Global concurrency semaphore | ~20 lines | Prevents rate limit death | OpenCode `queue.ts` |
| **P0** | Retry with backoff + headers | ~100 lines | Survives rate limits | OpenCode `retry.ts` |
| **P0** | Decoupled cancellation | ~25 lines | Stops losing background agents | Claude `runAgent.ts:527` |
| **P1** | Disk result persistence + resume | ~200 lines | Survives crashes, enables resume | Claude `diskOutput.ts` |
| **P1** | Cost tracking aggregation | ~80 lines | Budget visibility | Claude `cost-tracker.ts` |
| **P2** | Progress dashboard | ~150 lines | Operational visibility | Claude `agentToolUtils.ts` |
| **P2** | Tool result size persistence | ~100 lines | Memory pressure relief | Claude `toolResultStorage.ts` |
| **P3** | Concurrency-safe tools | ~60 lines | Race condition prevention | Claude `StreamingToolExecutor.ts` |
| **P3** | Coordinator mode | ~50 lines | Better orchestration | Claude `coordinatorMode.ts` |

**Total estimated effort:** ~785 lines for all components.

---

## Test Results Summary

The following tests were run against the current sub-agent system:

| Test | Result | Key Finding |
|------|--------|-------------|
| Basic hello world (1 agent, await) | Pass | 1 round, 2.6s, clean |
| Parallel agents (3x background) | Pass | All spawned within 146ms, completed independently |
| Nested sub-sub-agents (depth 2) | Pass | Concurrent children at depth 2, results flow back |
| Dependency chain (3 steps, background) | Partial | Dependency graph works but agents cancelled mid-chain |
| Background vs await comparison | Pass | Both modes functional, await blocks, background async |

### Key Log Patterns Observed

- Agent spawn-to-completion ratio: 153/149 (97.4% success rate)
- Cancellations occur when parent loop advances during background agent execution
- Rate limits spike when multiple agents make concurrent API calls
- Context overflow auto-recovery works (trim + retry)
- No memory leaks observed across 153 agents in a single session
- Dependency resolution correctly cascades: step-1 completes → step-2 starts → step-3 starts

---

## Files in KosmoKrator That Would Change

| File | Changes |
|------|---------|
| `src/Agent/SubagentOrchestrator.php` | Add global semaphore, wrap spawn in retry logic, disk output hooks |
| `src/Agent/SubagentFactory.php` | Decouple cancellation for background agents |
| `src/Agent/SubagentStats.php` | Add cost fields, persist to disk |
| `src/LLM/RetryableLlmClient.php` | Add `Retry-After` header parsing, jittered backoff |
| `src/UI/NullRenderer.php` | Accept `null` cancellation for background agents |
| `src/Agent/AgentLoop.php` | Add tool result size persistence before injection |
| `src/Tool/ToolInterface.php` | Add `isConcurrencySafe(): bool` |
| `src/Tool/ToolRegistry.php` | Scope concurrency-safe filtering |
| `src/Command/AgentCommand.php` | Add `--coordinator` flag, global concurrency config |
| New: `src/Agent/SwarmProgress.php` | Progress aggregator |
| New: `src/Agent/DiskTaskOutput.php` | Per-agent result writer |
| New: `src/Agent/SwarmCostTracker.php` | Cost accumulation |

---

## External Source File Index

### Claude Code (`tmp/claude-src/`)

```
services/api/withRetry.ts              — Retry engine (822 lines)
services/api/errors.ts                 — Error classification
services/tools/StreamingToolExecutor.ts — Concurrency-safe tool execution (519 lines)
utils/generators.ts                    — Concurrent generator pool (80 lines)
utils/task/diskOutput.ts               — Disk output with write queue (457 lines)
utils/task/framework.ts                — Task lifecycle management (308 lines)
utils/sessionRestore.ts                — Session restore from transcripts (550+ lines)
utils/toolResultStorage.ts             — Oversized result persistence
utils/forkedAgent.ts                   — Sub-agent context creation
cost-tracker.ts                        — Cost tracking (323 lines)
costHook.ts                            — Cost persistence hook
Task.ts                                — Task types and state machine (125 lines)
coordinator/coordinatorMode.ts         — Coordinator system prompt (369 lines)
tools/AgentTool/runAgent.ts            — Agent execution lifecycle (860 lines)
tools/AgentTool/agentToolUtils.ts      — Progress tracking, lifecycle management
tools/AgentTool/agentMemory.ts         — Agent memory scoping
Tool.ts                                — Tool interface and contracts (695 lines)
tools.ts                               — Tool registry and assembly
constants/tools.ts                     — Tool allowlists per agent type
```

### OpenCode (`tmp/opencode/`)

```
packages/opencode/src/session/retry.ts           — Retry system (105 lines)
packages/opencode/src/util/queue.ts              — Worker pool + async queue (30 lines)
packages/opencode/src/tool/task.ts               — Sub-agent tool (164 lines)
packages/opencode/src/tool/batch.ts              — Parallel tool execution
packages/opencode/src/session/index.ts           — Session management, cost calculation
packages/opencode/src/session/prompt.ts          — Core agent loop
packages/opencode/src/session/retry.ts           — Retry policies
packages/opencode/src/agent/agent.ts             — Agent type definitions
packages/opencode/src/agent/prompt/explore.txt   — Explore agent system prompt
packages/opencode/src/snapshot/index.ts          — Git-based checkpointing
packages/opencode/src/storage/db.ts              — SQLite persistence
packages/opencode/src/cli/cmd/stats.ts           — CLI stats command
```
