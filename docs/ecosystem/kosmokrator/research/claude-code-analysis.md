# Claude Code Source Analysis & KosmoKrator Comparison

> **Generated**: 2025-03-31
> **Source**: `tmp/claude-src/` (Claude Code TypeScript source, 1,903 files, 33MB)
> **Target**: KosmoKrator PHP agent (`src/`, ~100 files)

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Agent Loop & Query Engine](#2-agent-loop--query-engine)
3. [Streaming & LLM Integration](#3-streaming--llm-integration)
4. [Tool System](#4-tool-system)
5. [Tool Implementations — Deep Comparison](#5-tool-implementations--deep-comparison)
6. [Subagent & Multi-Agent System](#6-subagent--multi-agent-system)
7. [Context Management & Compaction](#7-context-management--compaction)
8. [Token Estimation & Budget](#8-token-estimation--budget)
9. [Permission System](#9-permission-system)
10. [Hook System](#10-hook-system)
11. [Memory System](#11-memory-system)
12. [Skills System](#12-skills-system)
13. [System Prompt Assembly](#13-system-prompt-assembly)
14. [Session & State Management](#14-session--state-management)
15. [Task System](#15-task-system)
16. [UI & Rendering](#16-ui--rendering)
17. [Cost Tracking](#17-cost-tracking)
18. [Command / Slash Command System](#18-command--slash-command-system)
19. [Keybinding System](#19-keybinding-system)
20. [MCP Integration](#20-mcp-integration)
21. [Feature Comparison Matrix](#21-feature-comparison-matrix)
22. [Concrete Thresholds & Constants](#22-concrete-thresholds--constants)
23. [Inspiration Roadmap](#23-inspiration-roadmap)
24. [Appendix: File Reference](#24-appendix-file-reference)

---

## 1. Architecture Overview

### Side-by-Side

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Language** | TypeScript (Bun runtime) | PHP 8.4 |
| **UI Framework** | React/Ink (custom reconciler, Yoga flexbox layout) | Symfony TUI + ANSI fallback |
| **Async Model** | Node async/await, async generators, streaming | Amp fibers (cooperative multitasking) |
| **DI Container** | Manual wiring + React context | Laravel Illuminate Container |
| **LLM Client** | Direct Anthropic SDK + SSE streaming | Prism PHP (multi-provider) + custom Amp HTTP |
| **Persistence** | JSON session files (one per session) | SQLite (WAL mode) |
| **Config** | JSON settings + CLAUDE.md hierarchy | YAML (multi-level merge) + KOSMOKRATOR.md |
| **Tool Count** | ~50+ built-in + unlimited via MCP | ~10 built-in |
| **Codebase Size** | 1,903 files / 33MB | ~100 files / ~500KB |
| **Build** | Bun binary bundle | PHAR (via box) |

### Entry Point Flow

**Claude Code:**
```
main.tsx → Commander.js CLI → init() → REPL screen (React/Ink)
  → QueryEngine.submitMessage() → query() async generator
    → queryLoop() while(true) → API stream → tool execution → loop
```

**KosmoKrator:**
```
bin/kosmokrator → Kernel → AgentCommand → AgentLoop.run()
  → while(true) → LlmClient.chat() → executeToolCalls() → loop
```

Both follow the same fundamental pattern: a REPL that iterates LLM calls and tool executions until the model stops requesting tools. The key structural differences are in streaming, concurrency, and extensibility.

---

## 2. Agent Loop & Query Engine

### Claude Code: QueryEngine + query()

The agent loop is split into two layers:

**QueryEngine** (`QueryEngine.ts`, 46KB):
- Owns the session: `mutableMessages[]`, conversation state, tool permission callbacks
- `submitMessage()` is an **async generator** that yields `SDKMessage` types
- Manages compact boundaries, permission tracking, and transcript recording
- One QueryEngine per conversation; subagents get their own instances

**query()** (`query.ts`, 68KB):
- The inner `queryLoop()` is a `while(true)` loop (line 307)
- Each iteration represents one LLM turn:
  1. Apply context compression (snip → microcompact → context collapse → autocompact)
  2. Build system prompt + user context + system context
  3. Stream API call via `queryModelWithStreaming()`
  4. Extract `tool_use` blocks **while streaming** (line 829)
  5. Feed blocks to `StreamingToolExecutor` which starts execution immediately
  6. Collect results, normalize messages
  7. Check stop conditions: no tool_use, max turns, budget exhausted, abort signal, error
  8. Continue loop or return `Terminal` reason

**State machine** (`query.ts` line 202):
```typescript
type State = {
  messages: Message[]
  toolUseContext: ToolUseContext
  autoCompactTracking: AutoCompactTrackingState | undefined
  maxOutputTokensRecoveryCount: number
  hasAttemptedReactiveCompact: boolean
  turnCount: number
  transition: Continue | undefined
}
```

### KosmoKrator: AgentLoop

**AgentLoop.php** (904 lines):
- Single class handling both interactive (`run()`) and headless (`runHeadless()`) modes
- `run()` method: add user message → pre-flight context check → refresh system prompt → call LLM → execute tools → deduplicate/prune → loop or stop
- `runHeadless()`: simplified version for subagents (no UI, no session persistence, no compaction)
- Context overflow: up to 3 trim attempts (compact → trim oldest → trim oldest)
- Auto-compaction check after each response

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Streaming** | Async generator yields events token-by-token | Blocking `chat()` returns complete response |
| **Tool start timing** | Tools start executing while LLM still streams | Tools execute after full response received |
| **Loop state** | Explicit `State` type with transitions | Implicit via class properties |
| **Recovery** | 5+ recovery strategies (collapse drain, reactive compact, max-output escalation, stop hooks) | 3 trim attempts |
| **Turn tracking** | Explicit `turnCount`, budget tracking | No turn or budget tracking |

### Adoptable Patterns

1. **Streaming responses**: Add SSE streaming to `AsyncLlmClient` for real-time text display. The Anthropic API returns `text_delta` events that can be yielded to the renderer as they arrive.

2. **Recovery escalation chain**: Claude Code has a sophisticated recovery tree when the LLM stops without finishing:
   - Context collapse drain (cheap, preserves detail)
   - Reactive compact (full LLM summarization)
   - Max output token escalation (8k → 64k retry)
   - Multi-turn recovery (up to 3 "resume" attempts)

   KosmoKrator only has trim/compact. Adding max-output escalation and a "resume where you left off" retry would help with long responses that hit the output limit.

3. **Explicit state machine**: Wrapping loop state in an immutable `State` type makes the loop more predictable and debuggable.

---

## 3. Streaming & LLM Integration

### Claude Code: SSE Streaming Pipeline

**API Call** (`claude.ts` lines 1778-1846):
```typescript
const result = await anthropic.beta.messages.create(
  { ...params, stream: true },
  { signal, headers: { [CLIENT_REQUEST_ID_HEADER]: clientRequestId } }
).withResponse()
```

**SSE Event Loop** (`claude.ts` lines 1940-2304):
Iterates raw stream events (NOT the SDK's `BetaMessageStream` helper):
- `message_start` → Initialize partial message, usage tracking
- `content_block_start` → Initialize text/tool_use/thinking blocks
- `content_block_delta` → Accumulate `input_json_delta` / `text_delta` / `thinking_delta`
- `content_block_stop` → Yield completed `AssistantMessage` with finished block
- `message_delta` → Update usage, stop_reason, cost; mutate last yielded message

**Idle Timeout Watchdog** (`claude.ts` lines 1877-1928):
- Default: 90 seconds (`STREAM_IDLE_TIMEOUT_MS`)
- Configurable via `CLAUDE_STREAM_IDLE_TIMEOUT_MS` env var
- Resets on each chunk; fires if no events for timeout period

**Streaming Fallback** (`claude.ts` lines 2464-2569):
- On streaming error (not user abort): retries as non-streaming request
- Max 64k tokens for non-streaming (`MAX_NON_STREAMING_TOKENS`)
- Tombstone messages invalidate partially-streamed content

### KosmoKrator: Blocking HTTP Client

**AsyncLlmClient.php** (291 lines):
- Builds JSON POST payload, sends via Amp HTTP client
- **Transfer timeout**: 600s, **Inactivity timeout**: 300s
- Returns complete `LlmResponse` with text, toolCalls, token counts
- Retry handled by `RetryableLlmClient` wrapper (exponential backoff)

### Gap Analysis

KosmoKrator's biggest UX gap is the lack of streaming. Users see nothing until the full response arrives. Adding streaming would require:
1. SSE parsing in `AsyncLlmClient` (read chunked response body)
2. A `StreamingResponse` type that yields partial text/tool_use blocks
3. Renderer updates to display partial text as it arrives
4. Tool execution that can start before streaming completes (optional, advanced)

The Anthropic API's streaming format is well-documented and PHP's Amp HTTP client supports streaming response bodies natively via `$response->getBody()->read()`.

---

## 4. Tool System

### Claude Code: Tool Architecture

**Tool interface** (`Tool.ts`, 30KB):
```typescript
Tool<Input, Output, Progress> = {
  name: string
  description(input): string
  prompt(): string                    // Contributes to system prompt
  inputSchema: Zod schema
  outputSchema: Zod schema
  call(input, context, canUseTool, parentMessage, onProgress): Promise<{data: Output}>
  checkPermissions(input, context): Promise<PermissionResult>
  validateInput(input, context): Promise<ValidationResult>
  isConcurrencySafe(input): boolean   // Can run in parallel
  isReadOnly(): boolean               // No side effects
  shouldDefer: boolean                // Deferred loading via ToolSearch
  alwaysLoad: boolean                 // Always in prompt even with ToolSearch
}
```

**Tool registration** (`tools.ts`):
- `getAllBaseTools()` returns ~50+ tools with conditional loading via feature flags
- `getTools()` applies permission filters and mode-specific filtering
- `assembleToolPool()` merges built-in + MCP tools, deduplicates (built-ins win), sorts for prompt-cache stability
- Deferred tools have `shouldDefer: true` — only their names appear in the prompt until `ToolSearchTool` fetches their schemas

**Concurrent execution** (`StreamingToolExecutor.ts`, 531 lines):
- `isConcurrencySafe` flag per tool determines parallel eligibility
- Concurrent-safe tools run in parallel; non-concurrent tools get exclusive access
- Tools queued as `tool_use` blocks arrive from streaming; execution starts immediately
- Bash errors abort sibling tools via `siblingAbortController`
- Three-level abort hierarchy: query → sibling → per-tool

### KosmoKrator: Tool Architecture

**ToolInterface** (simple contract):
```php
interface ToolInterface {
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function requiredParameters(): array;
    public function execute(array $args): string;
}
```

**ToolRegistry** (93 lines):
- `register()`, `get()`, `all()`, `toPrismTools()`
- `scoped(AgentContext $context)` — filters by agent type, excludes subagent tool

**Concurrent execution** (`AgentLoop::partitionConcurrentGroups()`):
- Conservative file-conflict detection:
  - Bash + any write tool → sequential
  - Multiple writes to same file → sequential
  - Read + write to same file → sequential
  - No conflicts → one concurrent group
- Within groups: `Amp\async()` for parallel execution
- Across groups: sequential `await()`

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Tool count** | ~50+ built-in + MCP | ~10 built-in |
| **Interface richness** | Input/output schemas, progress, permissions, prompts | Simple name/description/parameters/execute |
| **Concurrency model** | Per-tool `isConcurrencySafe` flag | File-conflict detection heuristic |
| **Deferred loading** | ToolSearch for large tool sets | N/A |
| **Progress reporting** | `onProgress` callback with typed events | None |
| **System prompt contribution** | Each tool can inject via `prompt()` | None |

### Adoptable Patterns

1. **`isConcurrencySafe()` method**: Add to `ToolInterface`. Simpler and more reliable than file-conflict heuristics. `file_read`, `glob`, `grep` are always safe; `bash`, `file_edit`, `file_write` are not.

2. **`isReadOnly()` method**: Useful for plan/explore mode filtering and permission shortcuts.

3. **`prompt()` method**: Let tools contribute usage instructions to the system prompt dynamically. The SubagentTool could explain its type hierarchy, the GrepTool could document its output modes.

4. **Progress callbacks**: Enable streaming output from long-running tools (especially Bash). The TUI renderer could show real-time stdout.

5. **Tool output persistence**: Claude Code saves outputs >100K chars to disk with a preview + path reference. KosmoKrator already has `OutputTruncator` doing this (saves to `~/.kosmokrator/data/truncations/`), so this is parity.

---

## 5. Tool Implementations — Deep Comparison

### BashTool

| Feature | Claude Code | KosmoKrator |
|---------|-------------|-------------|
| **Execution** | Bun `exec()` with AbortSignal | Symfony Process |
| **Timeout** | Default ~30s, configurable per-call | 120s configurable |
| **Background tasks** | Auto-background after 15s+; foreground task → background migration mid-execution | Not supported |
| **Sandbox** | SandboxManager integration (optional, can be disabled via `dangerouslyDisableSandbox`) | None |
| **Output capture** | `EndTruncatingAccumulator` (preserves start, truncates end) | Line + byte truncation |
| **Search detection** | `isSearchOrReadBashCommand()` splits on operators, classifies each part | None |
| **Security** | Zsh builtins blocklist (`zmodload`, `sysopen`, `ztcp`, etc.), sed parser, shell operator analysis | Shell metacharacter regex, mutative pattern list |

**Adoptable**: Zsh builtins blocklist is a strong hardening measure. Add to `GuardianEvaluator`:
```php
private const ZSH_DANGEROUS = ['zmodload', 'emulate', 'sysopen', 'sysread', 'syswrite', 'sysseek', 'zpty', 'ztcp', 'zsocket', 'zf_rm', 'zf_mv', 'zf_ln', 'zf_chmod', 'zf_chown', 'zf_mkdir', 'zf_rmdir', 'zf_chgrp'];
```

### FileEditTool

| Feature | Claude Code | KosmoKrator |
|---------|-------------|-------------|
| **Match algorithm** | `findActualString()` with quote normalization (curly ↔ straight) | Exact `str_replace()` |
| **Concurrent edit detection** | `readFileState` Map with mtime + content hash verification | None |
| **Line ending preservation** | Normalizes to `\n` on read, restores original on write | None |
| **Encoding** | UTF-8 + UTF-16LE detection | UTF-8 only |
| **Diff output** | `getPatchForEdit()` unified diff | `(-N, +M)` line count |
| **File size limit** | 1 GiB max | No explicit limit |

**Adoptable**: File state tracking is very valuable. When the LLM reads a file and later edits it, verifying the file hasn't changed in between prevents silent data corruption. Implementation: maintain a `readFileState: Map<string, {content: string, mtime: int}>` in `AgentLoop`, check on edit.

### FileReadTool

| Feature | Claude Code | KosmoKrator |
|---------|-------------|-------------|
| **Large file handling** | Range reads without loading whole file | Stream-read line-by-line above 10MB |
| **Deduplication** | Same-range reads return `file_unchanged` stub if mtime matches | None |
| **PDF support** | Page-range extraction, token-aware compression | None |
| **Image support** | Format detection, resize/downsample with token limits, base64 | None |
| **Notebook support** | `.ipynb` cell parsing with outputs | None |
| **Dangerous paths** | Block `/dev/zero`, `/dev/random`, `/proc/self/fd/*` | None |

**Adoptable**: PDF and image support would be valuable additions. PHP libraries: `smalot/pdfparser` for PDFs, `intervention/image` for image processing. Dangerous path blocking is a good security hardening.

### GrepTool

| Feature | Claude Code | KosmoKrator |
|---------|-------------|-------------|
| **Backend** | Ripgrep via args array | Ripgrep (preferred) or grep |
| **Output modes** | `content`, `files_with_matches`, `count` with pagination (head_limit + offset) | Single mode, max 50 matches |
| **Multiline** | `-U --multiline-dotall` flag | Not supported |
| **VCS exclusion** | Automatic `.git`, `.svn`, `.hg` exclusion | Via ripgrep defaults |
| **Sorting** | Files sorted by mtime descending | Not sorted |
| **Default limit** | 250 results (`DEFAULT_HEAD_LIMIT`) | 50 results |

**Adoptable**: Output modes (especially `files_with_matches` for quick scanning), multiline support, and higher default limits. The pagination pattern (offset + head_limit) is useful for browsing large result sets.

### WebFetchTool (Claude Code only)

```typescript
// Permission: preapproved hosts auto-allow, others need approval
// Content: domain:hostname used for permission matching
// Pipeline: fetch → HTML → markdown → optional Haiku summarization
// Cache: 15-minute URL result cache
// Large content: persisted to disk with size annotation
```

**Adoptable as new tool**: Use `league/html-to-markdown` or `readability-php` for HTML → markdown conversion. The preapproved host pattern is good UX (GitHub, MDN, StackOverflow, etc. don't need approval).

### WebSearchTool (Claude Code only)

```typescript
// Uses native Anthropic web_search_20250305 server tool
// Sends a sub-query to the API with web_search tool schema
// Max 8 searches per request (hardcoded)
// Results: title + URL pairs + text summaries
```

**Adoptable as new tool**: Integrate a search API (Tavily, Brave Search, SerpAPI). The implementation pattern of using an LLM sub-call with a server tool is interesting but can be simplified to a direct API call for third-party search providers.

### ToolSearchTool (Claude Code only)

**Deferred tool loading** for managing large tool sets:
- Tools with `shouldDefer: true` only show their names in the prompt
- LLM calls `ToolSearch` with a query to fetch full schemas
- Search algorithm: keyword scoring on tool name parts + description + searchHint
- Direct selection: `select:ToolName1,ToolName2` for exact fetches
- MCP tool name parsing: `mcp__github__list_repos` → keywords `[github, list, repos]`

**Adoptable**: Becomes important when KosmoKrator adds MCP support (potentially dozens of external tools). Not needed at current tool count (~10).

---

## 6. Subagent & Multi-Agent System

### Claude Code: AgentTool

**Spawning modes** (`AgentTool.tsx`, lines 686-1200):

1. **Synchronous**: Run agent inline, block parent, return result
2. **Asynchronous**: Launch background agent, return immediately, inject result when done
3. **Remote**: Teleport to CCR environment (cloud execution)
4. **Auto-background**: Start synchronous, auto-migrate to background after 120s

**Agent types** (built-in):
- **General Purpose**: Full read/write access
- **Explore**: Read-only code exploration
- **Plan**: Read-only architecture & design
- **Verification**: Adversarial testing (tries to break the implementation)
- **Claude Code Guide**: Documentation specialist
- **Fork**: Inherits parent's system prompt (cache-sharing optimization)
- **Custom**: Loaded from `~/.claude/agents/` as markdown with frontmatter

**Worktree isolation** (`EnterWorktreeTool`):
```typescript
const worktreeInfo = await createAgentWorktree(slug);
// Agent works in isolated git branch
// On completion: check for changes
//   - No changes → clean up worktree
//   - Has changes → preserve with branch name
```

**Agent communication**:
- `SendMessageTool`: Agents send messages to each other by ID
- `TaskNotification` XML in user messages (coordinator pattern)
- Scratchpad directory for durable cross-worker state

### KosmoKrator: SubagentOrchestrator

**SubagentOrchestrator.php** (224 lines):
- Manages agent futures using Amp fibers
- Dependency resolution: agents wait for dependencies before starting
- Group-based sequential execution via `LocalSemaphore(1)`
- Background mode: results stored in `pendingResults`, injected when parent checks

**SubagentFactory.php** (163 lines):
- Creates fresh `AgentLoop` instances with scoped tool registry
- Builds system prompt: base + type suffix + environment context
- If `canSpawn()`: registers recursive SubagentTool
- Mode mapping: General→Edit, Explore→Ask, Plan→Plan
- Hardcoded subagent pruner: `ContextPruner(20_000, 10_000)`

**AgentContext.php** (54 lines):
- Immutable context traveling down the tree
- `canSpawn()`: `depth < maxDepth - 1`
- `childContext()`: validates type inheritance, increments depth

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Agent types** | 7 built-in + custom from files | 3 (General, Explore, Plan) |
| **Custom agents** | `~/.claude/agents/` markdown files | Not supported |
| **Verification agent** | Adversarial tester with strict output format | Not supported |
| **Worktree isolation** | Git worktree per agent | Not supported |
| **Inter-agent messaging** | SendMessageTool | Dependency results appended to task |
| **Auto-backgrounding** | After 120s, migrate sync→async | Not supported |
| **Agent colors** | Unique color per agent in UI | Not supported |
| **Max depth** | Configurable (default 3) | Configurable (default 3) |
| **Coordinator mode** | Multi-worker orchestration with task notifications | Not supported |

### Adoptable Patterns

1. **Custom agent definitions**: Load from `~/.kosmokrator/agents/` as markdown with frontmatter:
   ```yaml
   ---
   name: reviewer
   description: Code review specialist
   type: explore
   model: inherit
   when-to-use: When the user asks for a code review
   ---
   You are a code review specialist. Focus on...
   ```

2. **Verification agent**: An adversarial testing agent that tries to break implementations. Very powerful for quality assurance. System prompt enforces: run commands (don't just read code), structured output format with Command/Output/Result blocks, explicit VERDICT line.

3. **Worktree isolation**: Create a `GitWorktreeTool` that creates temporary worktrees for experimental work. PHP's `Process` class can run `git worktree add/remove`.

4. **Auto-backgrounding**: After N seconds of a synchronous subagent running, automatically migrate to background mode. Requires the Amp fiber to support mid-execution mode switch.

5. **Agent color assignment**: Assign unique colors from `Theme` palette per agent depth/ID. Small UX win for visual differentiation.

---

## 7. Context Management & Compaction

### Claude Code: 5-Layer Strategy

Claude Code has five layers of context pressure relief, applied in order:

#### Layer 1: Microcompaction (cache-based)
- Uses Anthropic API's `cache_edits` to delete individual tool results without invalidating the cached prompt prefix
- Per-tool-result targeting: FILE_READ, SHELL, GREP, GLOB, WEB_SEARCH, WEB_FETCH, FILE_EDIT, FILE_WRITE results clearable
- Model-specific: only Claude Sonnet/Opus support cache editing
- Main thread only (subagents excluded)

#### Layer 2: Time-based Microcompaction
- Trigger: `(now - lastAssistantMessage) > 60 minutes` (server cache TTL)
- Action: Clear tool results except 5 most recent
- Sentinel: `'[Old tool result content cleared]'`
- Rationale: after 60min the server cache is cold anyway, so clearing stale results costs nothing

#### Layer 3: Context Collapse (feature-gated)
- Advanced selective message archiving that preserves granular detail longer
- Commit point: 90% of effective context
- Blocking spawn threshold: 95%
- When enabled, auto-compaction is disabled to prevent racing

#### Layer 4: Auto-Compaction (LLM summarization)
- **Threshold**: `effectiveContextWindow - 13,000` tokens (~93% of usable window)
- **Effective window**: `contextWindowSize - min(maxOutputTokens, 20,000)` (reserves summary output budget)
- **Circuit breaker**: Stops after 3 consecutive failures
- **Post-compaction restoration**:
  - Re-attach up to 5 recently-read files (50K token budget, 5K per file)
  - Re-inject recently-used skills (25K token budget, 5K per skill)
  - Preserve async agent attachments and plan mode state

#### Layer 5: Session Memory Compaction
- Background extraction that summarizes old conversation segments
- Config: min 10K tokens preserved, max 40K, min 5 text-block messages
- Preserves API invariants (tool_use/tool_result pairing, thinking block grouping)

### KosmoKrator: 3-Layer Strategy

#### Layer 1: ToolResultDeduplicator
Three-tier deduplication:
1. **Exact match**: Same tool, args, result hash → `'[Superseded — identical result]'`
2. **Stale after edit**: File read superseded by write + later re-read → `'[Superseded — file was re-read after modification]'`
3. **Subset subsumption**: Grep on file subsumed by later full file_read → `'[Superseded — content included in later file_read]'`

#### Layer 2: ContextPruner
- Protects last 2 user turns (40K tokens default)
- Replaces older tool results with `'[Old tool result content cleared]'`
- Only prunes if savings >= 20K tokens
- Applied after deduplication

#### Layer 3: ContextCompactor
- **Threshold**: 60% of context window (configurable)
- LLM summarization with dedicated compaction prompt
- Keeps last 3 user turns (configurable)
- Formats messages for compaction (truncates each to 2000 chars, total cap 100K chars)
- Also extracts durable memories (project, user, decision types) from summary

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Layers** | 5 | 3 |
| **Trigger threshold** | ~93% of usable window | 60% of context window |
| **Post-compaction restoration** | 5 files (50K), skills (25K), agent state | None |
| **Cache-aware compaction** | Yes (cache_edits API, time-based clearing) | No |
| **Compaction prompt** | Detailed 9-section prompt with structured output | Simple summarization prompt |
| **Memory extraction** | Separate background agent after each turn | During compaction only |
| **Circuit breaker** | 3 consecutive failures → stop | None |
| **Deduplication** | Basic (per tool name) | Advanced 3-tier (exact, stale, subsumption) |

### Adoptable Patterns

1. **Post-compaction file restoration** (HIGH PRIORITY): After compacting, re-read and attach the most recently-read files. This prevents the common failure mode where the agent "forgets" what files it was working with after compaction.
   ```php
   // In ContextCompactor::compact():
   $recentFiles = $this->extractRecentFileReads($history, limit: 5, tokenBudget: 50000);
   foreach ($recentFiles as $file) {
       $content = substr(file_get_contents($file), 0, 5000 * 4); // ~5K tokens
       $summary .= "\n\n## Recently read: {$file}\n```\n{$content}\n```";
   }
   ```

2. **Post-compaction instruction re-injection**: Re-inject KOSMOKRATOR.md instructions after compaction since they may have been summarized away.

3. **Circuit breaker**: Stop auto-compacting after 3 consecutive failures. Add a `$consecutiveCompactFailures` counter to `AgentLoop`.

4. **Raise compaction threshold**: 60% is conservative. Claude Code uses ~93%. Consider raising to 75-80% to preserve more context before compacting.

5. **Time-based result clearing**: If a conversation has been idle for >60 minutes, clear old tool results on resume (they're stale anyway). Simple timestamp check in `AgentLoop::preFlightContextCheck()`.

---

## 8. Token Estimation & Budget

### Claude Code

**Estimation formula** (`tokenEstimation.ts`):
- Text: `length / 4` bytes per token (default)
- JSON files: `length / 2` (denser tokenization)
- Images/documents: 2000 tokens flat estimate
- Message-level padding: `ceil(total * 4/3)` (33% conservative multiplier)

**Budget tracking** (`tokenBudget.ts`):
```
COMPLETION_THRESHOLD = 0.9      // Stop at 90% budget
DIMINISHING_THRESHOLD = 500     // Tokens per turn threshold
Detection: 3+ continuations AND last 2 deltas both < 500 tokens
```

**Continuation logic**:
- Continue if: under 90% budget AND making progress
- Stop if: diminishing returns (3+ turns, <500 tokens/turn) OR any prior continuation
- Nudge messages tell the LLM remaining budget

### KosmoKrator

**Estimation formula** (`TokenEstimator.php`):
- Text: `ceil(mb_strlen($text) / 4)` — 4 characters per token
- No file-type-specific adjustment
- No padding multiplier
- No budget tracking or continuation logic

### Adoptable Patterns

1. **JSON-specific estimation**: Use `length / 2` for JSON content (important for tool results which are often JSON).

2. **Conservative padding**: Apply a 1.33x multiplier to total estimates. Token estimation is inherently imprecise; padding prevents unexpected overflows.

3. **Budget tracking**: Optional feature for cost-conscious users. Track cumulative tokens per turn, stop if diminishing returns detected.

4. **Diminishing returns detection**: If the agent has been running for 3+ turns and the last 2 turns produced <500 tokens each, it's likely stuck in a loop. Inject a "you seem stuck, consider wrapping up" nudge.

---

## 9. Permission System

### Claude Code: Multi-Source Rules

**Permission modes**:
- `default` — Prompt for all 'ask' decisions
- `acceptEdits` — Auto-allow file edits in CWD, prompt elsewhere
- `bypassPermissions` — Auto-allow all (except deny rules and safety checks)
- `auto` — AI classifier decides (ANT-only, uses transcript/bash classifier)
- `plan` — Shows action plan instead of executing
- `dontAsk` — Silently deny all 'ask' decisions

**Rule sources** (8 levels, priority order):
`policySettings > flagSettings > projectSettings > localSettings > userSettings > cliArg > command > session`

**Rule format**: `ToolName(content)` with wildcard support:
- `Bash(npm *)` — glob pattern, matches any npm command
- `Bash(npm:*)` — legacy prefix syntax
- `Bash(curl https://\*.com)` — escaped asterisk
- `mcp__server1__*` — MCP server-level rule
- `Agent(Explore)` — deny specific agent type

**Evaluation order** (`permissions.ts` lines 1158-1320):
1. Check DENY rules (absolute, no override)
2. Check entire tool ASK rule
3. Call `tool.checkPermissions()` (tool-specific logic)
4. Check mode (bypass, acceptEdits, etc.)
5. Check ALLOW rules
6. Convert passthrough to ask
7. Apply dontAsk → deny conversion
8. Apply auto → classifier
9. Fall back to permission prompt

**Session grants**: In-memory, non-persisted rules stored in `alwaysAllowRules['session']`. Discarded when session ends.

**Denial tracking** (auto mode):
- `consecutiveDenials >= 3` OR `totalDenials >= 20` → fall back to user prompting
- Reset consecutive on allow, increment both on deny

### KosmoKrator: 3-Mode System

**Permission modes** (`PermissionMode`):
- `Guardian` — Heuristic auto-approve (safe reads + project-scoped writes + safe bash)
- `Argus` — Always ask
- `Prometheus` — Auto-approve everything

**Rule evaluation** (`PermissionEvaluator.php` lines 20-71):
1. Blocked paths check (absolute deny)
2. Session grants check
3. Rule evaluation (first matching rule wins)
4. Mode-specific handling:
   - Prometheus: auto-approve Ask
   - Guardian: delegate to `GuardianEvaluator::shouldAutoApprove()`
   - Argus: return Ask

**Guardian heuristics** (`GuardianEvaluator.php`):
- Always safe: `file_read`, `glob`, `grep`, task tools, memory tools
- File writes safe if inside project root
- Bash safe if: no shell metacharacters (`/[;&|`$><\n]/`) AND not matching mutative patterns
- Mutative patterns: `rm`, `mv`, `git commit`, `npm install`, `docker`, `kubectl`, etc.

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Modes** | 6 | 3 |
| **Rule sources** | 8 levels with priority | Config + session grants |
| **Wildcard rules** | Glob patterns (`npm *`) | Static pattern matching |
| **AI classifier** | Yes (auto mode) | No |
| **Safety checks** | Bypass-immune (always prompt for .git/, .claude/, shell configs) | Blocked paths only |
| **Denial tracking** | Consecutive + total limits | None |
| **Zsh builtins** | Blocked (`zmodload`, `sysopen`, `ztcp`, etc.) | Not blocked |

### Adoptable Patterns

1. **Wildcard permission rules** (HIGH PRIORITY): Add glob pattern matching to `PermissionRule::matches()`. This enables rules like "allow all git commands" (`Bash(git *)`) or "allow all npm scripts" (`Bash(npm run *)`).

2. **Bypass-immune safety checks**: Always prompt for operations on `.git/`, `.kosmokrator/`, shell config files (`.bashrc`, `.zshrc`, `.profile`), regardless of permission mode.

3. **Zsh builtins blocklist**: Add to `GuardianEvaluator`. These builtins can bypass sandboxing:
   ```php
   private const ZSH_DANGEROUS = [
       'zmodload', 'emulate', 'sysopen', 'sysread', 'syswrite',
       'sysseek', 'zpty', 'ztcp', 'zsocket',
       'zf_rm', 'zf_mv', 'zf_ln', 'zf_chmod', 'zf_chown',
       'zf_mkdir', 'zf_rmdir', 'zf_chgrp',
   ];
   ```

4. **`dontAsk` mode equivalent**: Useful for fully automated/CI pipelines where there's no user to prompt. Silently deny rather than hanging.

---

## 10. Hook System

### Claude Code: Shell Command Hooks

Claude Code supports external shell commands that execute in response to agent events.

**Hook event types** (`types/hooks.ts`):
- `PreToolUse` — Before tool execution (can block, modify input, add context)
- `PostToolUse` — After tool success
- `PostToolUseFailure` — After tool failure
- `PermissionDenied` — Auto-mode classifier denied
- `PermissionRequest` — Permission prompt triggered
- `Notification` — Notification event
- `SessionStart` — Session initialization
- `UserPromptSubmit` — User message submitted
- `FileChanged` — Watched file changed
- `CwdChanged` — Working directory changed
- `SubagentStart` — Subagent spawned
- `WorktreeCreate` — Worktree created

**Hook output** (PreToolUse example):
```typescript
{
  permissionDecision?: 'approve' | 'block',
  permissionDecisionReason?: string,
  updatedInput?: Record<string, unknown>,
  additionalContext?: string,
}
```

**Timeout**: 10 minutes for tool hooks, 1.5 seconds for session-end hooks.

**Configuration**: In `settings.json`:
```json
{
  "hooks": {
    "PreToolUse": [{
      "matcher": { "tool_name": "Bash" },
      "command": "~/.claude/hooks/lint-bash.sh"
    }]
  }
}
```

### KosmoKrator: No Hook System

KosmoKrator has no equivalent hook system. Permission evaluation is the closest analog, but it doesn't support external command execution or input modification.

### Adoptable Pattern

A hook system is very powerful for customization without code changes. Implementation:

```yaml
# ~/.kosmokrator/hooks.yaml
hooks:
  PreToolUse:
    - matcher: { tool_name: "bash" }
      command: "~/.kosmokrator/hooks/validate-bash.sh"
      timeout: 60
  PostToolUse:
    - matcher: { tool_name: "file_edit" }
      command: "~/.kosmokrator/hooks/format-on-save.sh"
  UserPromptSubmit:
    - command: "~/.kosmokrator/hooks/log-prompt.sh"
```

The hook receives JSON on stdin (tool name, input, context) and outputs JSON to stdout (approve/block/modify). This enables linting, formatting, logging, and custom approval workflows.

---

## 11. Memory System

### Claude Code: File-Based Persistent Memory

**Directory structure**:
```
~/.claude/projects/<slug>/memory/
├── MEMORY.md          (index, max 200 lines / 25KB, always loaded)
├── user_role.md       (individual memory files with frontmatter)
├── feedback_testing.md
└── project_goal.md
```

**Memory frontmatter format**:
```markdown
---
name: {{memory name}}
description: {{one-line hook for relevance matching}}
type: {{user | feedback | project | reference}}
---
{{content — for feedback/project: rule/fact, **Why:** line, **How to apply:** line}}
```

**Memory types** (4 categories):
1. **user**: Role, goals, preferences, knowledge level
2. **feedback**: Guidance on approach (corrections AND confirmations)
3. **project**: Ongoing work, goals, deadlines (not derivable from code)
4. **reference**: Pointers to external systems (Linear, Grafana, Slack)

**What NOT to save**: Code patterns, git history, debugging recipes, CLAUDE.md content, ephemeral task details.

**Extraction**: Background agent runs after each turn (feature-gated):
- Max 5 turns per extraction
- Tool restrictions: Read, Grep, Glob, read-only Bash, Edit/Write to memory dir only
- Throttled: every N turns (default 1)
- Pre-injects manifest of existing memories to avoid duplicates
- Analytics tracked: tokens, files written, duration

**Memory mechanics prompt**: A detailed instruction set injected into the system prompt that teaches the LLM how to proactively save, update, and recall memories. This is the mechanism that makes the LLM autonomously manage its own memory.

### KosmoKrator: SQLite-Based Memory

**Storage**: `memories` table in SQLite database
- Columns: id, type, title, content, project, session_id, created_at
- Types: `project`, `user`, `decision`, `compaction`

**Tools**: `MemorySaveTool`, `MemorySearchTool`

**Extraction**: During compaction only (in `ContextCompactor::extractMemories()`)
- Calls LLM with `MEMORY_EXTRACTION_PROMPT`
- Parses JSON array: `[{type, title, content}]`
- Validates types, saves to repository

**Injection**: `MemoryInjector::format()` groups by type into markdown sections

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Storage** | File-based (git-trackable, human-editable) | SQLite rows |
| **Index** | MEMORY.md always loaded in context | All memories injected in system prompt |
| **Types** | 4 (user, feedback, project, reference) | 4 (user, project, decision, compaction) |
| **Extraction trigger** | After each turn (background) | During compaction only |
| **Memory mechanics prompt** | Yes (teaches LLM to proactively save) | No |
| **Relevance decay** | Age tracking, staleness warnings | None |
| **Team sync** | Multi-agent memory sharing (feature-gated) | None |

### Adoptable Patterns

1. **Memory mechanics prompt** (HIGH PRIORITY): The single most impactful addition. Claude Code's memory prompt teaches the LLM:
   - What types of information to save
   - When to save (corrections, confirmations, learning about user)
   - What NOT to save (code patterns, git history, debugging recipes)
   - How to save (file format, MEMORY.md index)
   - When to access memories
   - When to verify before recommending

   KosmoKrator should inject an equivalent prompt section that teaches the LLM to use `memory_save` and `memory_search` proactively.

2. **Post-turn extraction**: Don't wait for compaction to extract memories. Run a lightweight extraction after each turn (or every N turns) to capture feedback and decisions before they're compacted away.

3. **Feedback type**: Rename `decision` to `feedback` and add explicit guidance about saving both corrections AND confirmations. The body structure `rule → Why → How to apply` is very effective.

4. **Reference type**: Add for external system pointers (Jira boards, Grafana dashboards, Slack channels).

---

## 12. Skills System

### Claude Code: Loadable Prompt Templates

**BundledSkillDefinition** (`bundledSkills.ts`):
```typescript
{
  name: string
  description: string
  aliases?: string[]
  whenToUse?: string
  argumentHint?: string
  allowedTools?: string[]
  model?: string
  context?: 'inline' | 'fork'    // fork = isolated sub-agent
  agent?: string
  files?: Record<string, string>  // Reference files extracted to disk
  getPromptForCommand: (args, context) => Promise<ContentBlockParam[]>
}
```

**User-defined skills**: Markdown files in `~/.claude/skills/` or `.claude/skills/`:
```markdown
---
name: review
description: Review code changes for quality
allowed-tools: file_read, grep, glob
context: fork
model: inherit
---
Review the current git diff for bugs, security issues, and code quality...
```

**Skill execution**: Via `SkillTool` — either inline (added to conversation) or forked (isolated sub-agent with own context).

**Bundled skills include**: `/commit`, `/review-pr`, `/simplify`, `/loop`, `/debug`, `/remember`, `/verify`, `/schedule`, `/claude-api`, `/keybindings`, `/update-config`, and many more.

### KosmoKrator: Slash Commands

KosmoKrator has slash commands (`/mode`, `/sessions`, `/resume`, `/settings`, etc.) but these are UI commands, not LLM-driven skills. There's no equivalent of loadable prompt templates.

### Adoptable Pattern

A skills system bridges the gap between slash commands and full agent modes:

```php
// ~/.kosmokrator/skills/review/SKILL.md
// ---
// name: review
// description: Review code changes
// allowed-tools: file_read, grep, glob
// context: fork
// ---
// Review the current git diff for bugs...

class SkillLoader {
    public function loadFromDirectory(string $dir): array;
    public function execute(Skill $skill, string $args, AgentLoop $agent): string;
}
```

Skills invoked via `/review` would either inject the prompt inline or fork a subagent with the skill's prompt and tool restrictions. This is a powerful extensibility mechanism that users can customize without touching code.

---

## 13. System Prompt Assembly

### Claude Code: Multi-Part Prompt

The system prompt is assembled from multiple sources:

**Static sections** (`prompts.ts`):
1. **Intro**: "You are an interactive agent that helps users with software engineering tasks..."
2. **System**: Tool execution, permission modes, hooks, context compression
3. **Doing tasks**: Engineering best practices, code quality, no unnecessary changes
4. **Executing actions with care**: Reversibility, blast radius, confirmation for risky actions
5. **Using your tools**: Dedicated tools over Bash, parallel calls, task management
6. **Tone and style**: No emojis, concise, file_path:line_number references

**Dynamic sections**:
- Tool-specific guidance (Agent, Skills, ToolSearch)
- Verification agent contract (if enabled)
- Memory mechanics prompt (if auto-memory enabled)

**Context layers** (`queryContext.ts`):
- `defaultSystemPrompt[]` — Static prompt array
- `userContext.claudeMd` — CLAUDE.md files from directory hierarchy
- `userContext.currentDate` — "Today's date is YYYY-MM-DD"
- `systemContext.gitStatus` — Branch, status, recent commits

**Cache boundary** (`SYSTEM_PROMPT_DYNAMIC_BOUNDARY`):
Everything before this marker is globally cacheable. Everything after is session-specific.

### KosmoKrator: Prompt Assembly

**AgentCommand.php** (lines 131-134):
```php
$systemPrompt = $basePrompt;          // From config
$systemPrompt .= MemoryInjector::format($memories);
$systemPrompt .= InstructionLoader::gather();
$systemPrompt .= EnvironmentContext::gather();
```

**AgentLoop** refreshes system prompt each turn:
```php
$prompt = $this->baseSystemPrompt;
$prompt .= $this->mode->systemPromptSuffix();
$prompt .= $this->formatTaskContext();
```

### Key Differences

| Aspect | Claude Code | KosmoKrator |
|--------|-------------|-------------|
| **Base prompt size** | ~914 lines, very detailed | Configurable, shorter |
| **Tool prompt contributions** | Each tool can inject via `prompt()` | None |
| **Memory mechanics** | Full teaching prompt for auto-memory | None |
| **Cache boundary** | Explicit marker for API caching | None |
| **Dynamic refresh** | Memoized context (cached per conversation) | Refreshed each turn |
| **Git status** | Branch, status (2000 char cap), 5 recent commits | Branch, root |

### Adoptable Patterns

1. **Memory mechanics prompt injection**: Add a dedicated section teaching the LLM how to use `memory_save` and `memory_search` proactively.

2. **Tool prompt contributions**: Add `systemPromptContribution(): ?string` to `ToolInterface`. The SubagentTool could explain type hierarchy and usage patterns.

3. **Richer git context**: Include `git status --short` (capped at 2000 chars) and last 5 commit messages in the system prompt. Gives the LLM better awareness of the project state.

---

## 14. Session & State Management

### Claude Code: File-Based Sessions

- One JSON file per session, written fire-and-forget via `recordTranscript()`
- `history.jsonl` for conversation history (max 100 entries)
- Pasted content stored externally when >1KB
- Session resume via message deserialization from log files
- Remote session support via WebSocket (`/v1/sessions/ws/{id}/subscribe`)

### KosmoKrator: SQLite Sessions

- `sessions` table: id, project, title, model, created_at, updated_at
- `messages` table: role, content, tool_calls, tool_results, tokens
- `settings` table: scope-based KV store (global, project-specific)
- `memories` table: type, title, content, project, session_id

### Assessment

KosmoKrator's SQLite approach is actually superior for:
- Atomic writes (WAL mode)
- Efficient queries (session listing, message search)
- Structured data (vs JSON parsing)
- Concurrent access safety

No changes needed here. SQLite is the right choice.

---

## 15. Task System

### Claude Code

**Task types**: `local_bash`, `local_agent`, `remote_agent`, `in_process_teammate`, `local_workflow`, `monitor_mcp`, `dream`

**Task statuses**: `pending`, `running`, `completed`, `failed`, `killed`

**Tools**: TaskCreateTool, TaskUpdateTool, TaskListTool, TaskGetTool, TaskOutputTool, TaskStopTool

**Features**:
- Blocking relationships (addBlocks, addBlockedBy)
- Owner assignment for multi-agent teams
- Mailbox communication for teammates
- Auto-expand UI on task create/update
- Task completion hooks

### KosmoKrator

**Task statuses**: `Pending`, `InProgress`, `Completed`, `Cancelled`

**Tools**: TaskCreateTool, TaskUpdateTool, TaskListTool, TaskGetTool

**Features**:
- Parent-child relationships
- Blocking relationships (bidirectional)
- Auto-complete parents when all children terminal
- Tree rendering (text + ANSI)
- In-memory storage (no persistence)

### Assessment

KosmoKrator's task system is well-designed and covers the essential features. Claude Code's additions (task types, owner assignment, mailbox communication) are mostly relevant for multi-agent teams, which is a future feature. No immediate changes needed.

---

## 16. UI & Rendering

### Claude Code: React/Ink Custom Framework

Claude Code has essentially built a **custom terminal GUI framework**:
- Custom React reconciler for terminal rendering
- Yoga-based flexbox layout engine
- Double-buffered frame rendering with diff optimization
- Mouse tracking (mode-1003), hit testing, text selection
- Bidirectional text support
- Scrollable containers, buttons, OSC 8 hyperlinks
- Keyboard chord parsing with configurable bindings
- Search highlighting across screen buffer
- Alternate screen mode (full-screen)

This is approximately **10,000+ lines of UI infrastructure**.

### KosmoKrator: Symfony TUI + ANSI

- **TuiRenderer**: Symfony TUI widgets (PlanApprovalWidget, QuestionWidget, CollapsibleWidget, etc.)
- **AnsiRenderer**: Pure ANSI escape codes, readline input, MarkdownToAnsi for formatting
- **Theme**: Shared color palette, tool icons, planetary symbols
- **MarkdownToAnsi**: CommonMark + GFM extensions, Tempest Highlighter for code blocks

### Assessment

KosmoKrator's dual-renderer approach is pragmatic and effective. Trying to replicate Claude Code's custom Ink framework would be massive effort for marginal gain. Symfony TUI provides adequate interactivity.

### Adoptable Patterns

1. **Cost display**: Show running cost in the context bar. Use `ModelCatalog` pricing data:
   ```
   $cost = ($tokensIn / 1_000_000) * $inputPrice + ($tokensOut / 1_000_000) * $outputPrice;
   ```

2. **Collapsible tool output**: Claude Code collapses search/read tool results into summaries ("Found 3 files in 12ms"). KosmoKrator has `CollapsibleWidget` in TUI mode — ensure it's used for all tool results.

3. **Thinking duration display**: Show "Thinking... (2.3s)" when the LLM is processing. Claude Code shows thinking state for minimum 2 seconds, then displays the duration.

---

## 17. Cost Tracking

### Claude Code

**Formula** (`modelCost.ts`):
```
cost = (input / 1M) * inputPrice
     + (output / 1M) * outputPrice
     + (cacheRead / 1M) * cacheReadPrice
     + (cacheCreation / 1M) * cacheWritePrice
     + webSearchRequests * webSearchPrice
```

**Pricing tiers** (per 1M tokens):
| Model | Input | Output |
|-------|-------|--------|
| Sonnet 4.x | $3 | $15 |
| Opus 4.0/4.1 | $15 | $75 |
| Opus 4.5 | $5 | $25 |
| Opus 4.6 (fast) | $30 | $150 |
| Haiku 3.5 | $0.80 | $4 |
| Haiku 4.5 | $1 | $5 |

**Display**: On exit, shows total cost, API duration, wall duration, lines added/removed, per-model breakdown.

### KosmoKrator

KosmoKrator has `ModelCatalog` with pricing data and tracks `sessionTokensIn`/`sessionTokensOut` in `AgentLoop`, but doesn't calculate or display USD cost.

### Adoptable Pattern

Add cost calculation and display:
```php
$cost = ($this->sessionTokensIn / 1_000_000) * $this->models->inputPrice($model)
      + ($this->sessionTokensOut / 1_000_000) * $this->models->outputPrice($model);
$this->ui->showStatus(sprintf('Session cost: $%.4f', $cost));
```

---

## 18. Command / Slash Command System

### Claude Code: ~100+ Commands

Categories:
- **Prompt commands**: Invoke model with skill prompt (`/commit`, `/review`, `/simplify`, `/loop`)
- **Action commands**: Immediate execution (`/clear`, `/exit`, `/config`, `/model`, `/compact`)
- **Internal commands**: Developer-only (`/breakCache`, `/mockLimits`, `/debugToolCall`)

Command availability filtered by: feature flags, user type (ant/external), subscription level.

### KosmoKrator: ~15 Commands

- `/mode`, `/clear`, `/compact`, `/sessions`, `/resume`, `/new`, `/quit`
- `/memories`, `/forget`, `/settings`
- `/guardian`, `/argus`, `/prometheus`
- `/tasks-clear`, `/theogony`, `/seed`

### Assessment

KosmoKrator has the essential commands. Additional commands can be added incrementally as features are implemented (skills, MCP, etc.).

---

## 19. Keybinding System

### Claude Code

Fully configurable keybindings via `~/.claude/keybindings.json`:
- Context-aware: Global, Chat, Autocomplete, Confirmation, Help, Transcript, etc.
- Actions: `app:interrupt`, `app:exit`, `app:toggleTodos`, `app:toggleTranscript`, etc.
- Chord support: `ctrl+k ctrl+s` (multi-key sequences)
- Special keys: `esc`, `return`, `space`, arrows
- User bindings merged with defaults

### KosmoKrator

No keybinding customization.

### Adoptable Pattern

Medium priority. Add `~/.kosmokrator/keybindings.yaml` for common actions:
```yaml
keybindings:
  chat:
    submit: ctrl+return
    cancel: ctrl+c
    mode_cycle: shift+tab
```

---

## 20. MCP Integration

### Claude Code

Full Model Context Protocol support:
- Transport types: `stdio`, `sse`, `http`, `ws`, `sdk`
- OAuth token refresh for authenticated servers
- Tool integration: each MCP tool becomes a `mcp__server__action` tool
- Resource listing and reading
- Skill builders from MCP resources
- Channel permissions per server
- Config scopes: local, user, project, dynamic, enterprise, managed

### KosmoKrator

No MCP support.

### Adoptable Pattern

MCP integration is a HIGH PRIORITY addition. PHP MCP client libraries exist. Start with `stdio` transport (simplest) to connect to local MCP servers. Each server's tools register into the `ToolRegistry` with the `mcp__server__action` naming convention.

---

## 21. Feature Comparison Matrix

| Feature | Claude Code | KosmoKrator | Gap |
|---------|:-----------:|:-----------:|:---:|
| **Core agent loop** | Full | Full | - |
| **Streaming responses** | Full | None | HIGH |
| **Tool system** | 50+ tools | 10 tools | MEDIUM |
| **Subagent system** | Full + custom | Full (3 types) | LOW |
| **Context compaction** | 5 layers | 3 layers | MEDIUM |
| **Post-compact restoration** | Full | None | HIGH |
| **Token budget tracking** | Full | None | LOW |
| **Permission system** | 6 modes + wildcards | 3 modes | MEDIUM |
| **Hook system** | Full (12 event types) | None | MEDIUM |
| **Memory system** | File-based + extraction | SQLite + compaction-only | MEDIUM |
| **Memory mechanics prompt** | Full | None | HIGH |
| **Skills system** | Full (bundled + user) | None | HIGH |
| **MCP integration** | Full | None | HIGH |
| **Web fetch** | Full | None | HIGH |
| **Web search** | Full | None | HIGH |
| **PDF/Image reading** | Full | None | MEDIUM |
| **Cost tracking display** | Full | Partial (no display) | LOW |
| **Keybinding customization** | Full | None | LOW |
| **Git worktree isolation** | Full | None | MEDIUM |
| **Custom agent definitions** | Full | None | MEDIUM |
| **Verification agent** | Full | None | MEDIUM |
| **Voice mode** | Full | None | LOW |
| **Remote sessions** | Full | None | LOW |
| **Deferred tool loading** | Full | None | LOW |
| **File state tracking (edits)** | Full | None | MEDIUM |
| **Session persistence** | JSON files | SQLite | KosmoKrator better |
| **Config system** | JSON | YAML (multi-level) | KosmoKrator better |
| **Dual renderer** | React/Ink | Symfony TUI + ANSI | Parity |
| **Task system** | Full + teams | Full (in-memory) | Parity |
| **Mythology theming** | None | Full | KosmoKrator unique |

---

## 22. Concrete Thresholds & Constants

### Claude Code

| Constant | Value | Location |
|----------|-------|----------|
| Auto-compact buffer | 13,000 tokens | `autoCompact.ts` |
| Auto-compact threshold | ~93% of effective window | Calculated |
| Warning threshold buffer | 20,000 tokens | `autoCompact.ts` |
| Max compaction output | 20,000 tokens | `autoCompact.ts` |
| Max compaction failures | 3 consecutive | `autoCompact.ts` |
| Post-compact file budget | 50,000 tokens | `compact.ts` |
| Post-compact file cap | 5,000 tokens/file | `compact.ts` |
| Post-compact max files | 5 | `compact.ts` |
| Post-compact skill budget | 25,000 tokens | `compact.ts` |
| Post-compact skill cap | 5,000 tokens/skill | `compact.ts` |
| Session memory min tokens | 10,000 | `sessionMemoryCompact.ts` |
| Session memory max tokens | 40,000 | `sessionMemoryCompact.ts` |
| Session memory min messages | 5 | `sessionMemoryCompact.ts` |
| Budget completion threshold | 90% | `tokenBudget.ts` |
| Diminishing returns threshold | 500 tokens/turn | `tokenBudget.ts` |
| Diminishing detection | 3+ continuations | `tokenBudget.ts` |
| Time-based MC gap | 60 minutes | `timeBasedMCConfig.ts` |
| Time-based MC keep recent | 5 tool results | `timeBasedMCConfig.ts` |
| Text token estimate | length / 4 | `tokenEstimation.ts` |
| JSON token estimate | length / 2 | `tokenEstimation.ts` |
| Message token padding | 4/3x multiplier | `tokenEstimation.ts` |
| Image/document tokens | 2,000 flat | `tokenEstimation.ts` |
| Stream idle timeout | 90,000 ms | `claude.ts` |
| Agent auto-background | 120,000 ms | `AgentTool.tsx` |
| Bash progress threshold | 2,000 ms | `BashTool.tsx` |
| Grep default head limit | 250 results | `GrepTool.ts` |
| WebSearch max uses | 8 per request | `WebSearchTool.ts` |
| WebFetch cache TTL | 15 minutes | `WebFetchTool.ts` |
| MEMORY.md max lines | 200 | `memdir.ts` |
| MEMORY.md max bytes | 25,000 | `memdir.ts` |
| Memory scan max files | 200 | `memoryScan.ts` |
| History max items | 100 | `history.ts` |
| Denial max consecutive | 3 | `denialTracking.ts` |
| Denial max total | 20 | `denialTracking.ts` |
| Tool hook timeout | 10 minutes | `hooks.ts` |
| Session-end hook timeout | 1,500 ms | `hooks.ts` |

### KosmoKrator

| Constant | Value | Location |
|----------|-------|----------|
| Compact threshold | 60% of context window | `ContextCompactor.php` |
| Compact keep recent | 3 user turns | `ContextCompactor.php` |
| Compact max format chars | 100,000 | `ContextCompactor.php` |
| Pruner protect tokens | 40,000 | `ContextPruner.php` |
| Pruner min savings | 20,000 | `ContextPruner.php` |
| Subagent pruner protect | 20,000 | `SubagentFactory.php` |
| Subagent pruner min savings | 10,000 | `SubagentFactory.php` |
| Token estimate | 4 chars/token | `TokenEstimator.php` |
| Output max lines | 2,000 | `OutputTruncator.php` |
| Output max bytes | 50,000 | `OutputTruncator.php` |
| Truncation cleanup age | 86,400s (1 day) | `OutputTruncator.php` |
| Bash timeout | 120s | Configurable |
| Grep timeout | 30s | `GrepTool.php` |
| Grep max matches | 50 | `GrepTool.php` |
| HTTP transfer timeout | 600s | `AsyncLlmClient.php` |
| HTTP inactivity timeout | 300s | `AsyncLlmClient.php` |
| Retry cap | 300s | `AsyncLlmClient.php` |
| File read large threshold | 10 MB | `FileReadTool.php` |
| File read max lines | 5,000 | `FileReadTool.php` |
| Memory warning | 50 MB | `AgentLoop.php` |
| Context overflow retries | 3 | `AgentLoop.php` |
| Subagent max depth | 3 | Configurable |
| Guardian shell metachar pattern | `/[;&\|`$><\n]/` | `GuardianEvaluator.php` |
| Pre-flight check | 80% of context | `AgentLoop.php` |

---

## 23. Inspiration Roadmap

### Tier 1 — High Impact, Moderate Effort

| # | Feature | Effort | Impact | Notes |
|---|---------|--------|--------|-------|
| 1 | **Streaming LLM responses** | Medium | Very High | SSE streaming in AsyncLlmClient, renderer updates for partial text |
| 2 | **WebFetch tool** | Low | High | URL → markdown via `league/html-to-markdown`, preapproved hosts |
| 3 | **WebSearch tool** | Low | High | Integrate Tavily/Brave/SerpAPI |
| 4 | **Post-compaction file restoration** | Low | High | Re-attach 5 recently-read files after compaction |
| 5 | **Memory mechanics prompt** | Low | High | Teach LLM to proactively use memory_save/memory_search |
| 6 | **Skills system** | Medium | High | Loadable markdown prompts from ~/.kosmokrator/skills/ |

### Tier 2 — Medium Impact, Moderate Effort

| # | Feature | Effort | Impact | Notes |
|---|---------|--------|--------|-------|
| 7 | **MCP client integration** | High | High | PHP MCP client for external tool servers |
| 8 | **Wildcard permission rules** | Low | Medium | Glob patterns in PermissionRule (e.g., `Bash(git *)`) |
| 9 | **Hook system** | Medium | Medium | PreToolUse/PostToolUse shell command hooks |
| 10 | **Custom agent definitions** | Low | Medium | ~/.kosmokrator/agents/ markdown files |
| 11 | **Git worktree isolation** | Medium | Medium | EnterWorktreeTool for safe experimentation |
| 12 | **File state tracking** | Low | Medium | Track read files, detect concurrent edits on edit |
| 13 | **Cost display** | Low | Medium | USD cost in context bar |
| 14 | **Post-compaction instruction re-injection** | Low | Medium | Re-inject KOSMOKRATOR.md after compaction |
| 15 | **Verification agent type** | Medium | Medium | Adversarial testing agent |
| 16 | **Deferred tool loading** | Medium | Medium | ToolSearch for MCP tool sets |

### Tier 3 — Nice to Have

| # | Feature | Effort | Impact | Notes |
|---|---------|--------|--------|-------|
| 17 | **Diminishing returns detection** | Low | Low | Stop after 3+ turns with <500 tokens/turn |
| 18 | **Compaction circuit breaker** | Low | Low | Stop after 3 consecutive failures |
| 19 | **Zsh builtins blocklist** | Low | Low | Block zmodload, sysopen, ztcp etc. |
| 20 | **Agent auto-backgrounding** | Medium | Low | Background long-running subagents after N seconds |
| 21 | **Agent color assignment** | Low | Low | Unique colors per subagent |
| 22 | **Configurable keybindings** | Medium | Low | ~/.kosmokrator/keybindings.yaml |
| 23 | **PDF/Image reading** | Medium | Low | smalot/pdfparser, intervention/image |
| 24 | **GrepTool output modes** | Low | Low | files_with_matches, count, content modes |
| 25 | **Multiline grep** | Low | Low | -U --multiline-dotall flag |
| 26 | **dontAsk permission mode** | Low | Low | Silent deny for CI/automation |
| 27 | **Bypass-immune safety checks** | Low | Low | Always prompt for .git/, .kosmokrator/, shell configs |
| 28 | **Tool `prompt()` contributions** | Low | Low | Tools inject system prompt sections |

### Tier 4 — Future / Research

| # | Feature | Effort | Impact | Notes |
|---|---------|--------|--------|-------|
| 29 | AI permission classifier | High | Medium | Auto-approve safe tool calls via LLM |
| 30 | Remote sessions | High | Low | WebSocket-based remote agent control |
| 31 | Voice mode | High | Low | STT/TTS integration |
| 32 | Plugin system | High | Medium | Loadable plugins with custom tools and UI |
| 33 | Context collapse | High | Medium | Advanced granular preservation |
| 34 | Cache-aware compaction | Medium | Medium | Requires Anthropic cache_edits API |

---

## 24. Appendix: File Reference

### Claude Code Key Files

| File | Size | Purpose |
|------|------|---------|
| `main.tsx` | 4,683 lines | Application entry point |
| `QueryEngine.ts` | 46KB | Session state, submitMessage() |
| `query.ts` | 68KB | Main loop, API streaming, tool execution |
| `Tool.ts` | 30KB | Tool interface and factory |
| `tools.ts` | — | Tool registration and discovery |
| `query/tokenBudget.ts` | — | Budget tracking and continuation |
| `services/compact/autoCompact.ts` | — | Auto-compaction triggers |
| `services/compact/compact.ts` | — | Compaction algorithm |
| `services/compact/microCompact.ts` | — | Cache-based microcompaction |
| `services/tokenEstimation.ts` | 16KB | Token estimation formulas |
| `services/tools/StreamingToolExecutor.ts` | 531 lines | Concurrent streaming executor |
| `tools/BashTool/BashTool.tsx` | 1,143 lines | Shell execution |
| `tools/FileEditTool/FileEditTool.ts` | 625 lines | String replacement |
| `tools/FileReadTool/FileReadTool.ts` | 1,183 lines | File reading |
| `tools/GrepTool/GrepTool.ts` | — | Ripgrep integration |
| `tools/WebFetchTool/WebFetchTool.ts` | — | URL fetching |
| `tools/WebSearchTool/WebSearchTool.ts` | — | Web search |
| `tools/AgentTool/AgentTool.tsx` | — | Subagent spawning |
| `tools/ToolSearchTool/ToolSearchTool.ts` | — | Deferred tool discovery |
| `skills/bundledSkills.ts` | — | Skill registry |
| `skills/loadSkillsDir.ts` | — | Skill file loader |
| `memdir/memdir.ts` | — | Memory entrypoint |
| `memdir/memoryTypes.ts` | 272 lines | Memory type taxonomy |
| `services/extractMemories/extractMemories.ts` | — | Background extraction |
| `services/extractMemories/prompts.ts` | 154 lines | Extraction prompts |
| `constants/prompts.ts` | 914 lines | System prompt |
| `context.ts` | — | Context assembly |
| `utils/permissions/permissions.ts` | — | Permission evaluation |
| `types/hooks.ts` | — | Hook types |
| `cost-tracker.ts` | — | Cost tracking |
| `state/AppStateStore.ts` | — | Application state |
| `history.ts` | 465 lines | Session history |
| `commands.ts` | 25KB | Command registry |
| `keybindings/schema.ts` | — | Keybinding configuration |

### KosmoKrator Key Files

| File | Size | Purpose |
|------|------|---------|
| `src/Agent/AgentLoop.php` | 904 lines | Core REPL |
| `src/Agent/ConversationHistory.php` | 200 lines | Message buffer |
| `src/Agent/ContextCompactor.php` | 250 lines | LLM summarization |
| `src/Agent/ContextPruner.php` | 129 lines | Tool result pruning |
| `src/Agent/ToolResultDeduplicator.php` | 189 lines | 3-tier deduplication |
| `src/Agent/TokenEstimator.php` | 75 lines | Token estimation |
| `src/Agent/OutputTruncator.php` | 87 lines | Output size limiting |
| `src/Agent/SubagentOrchestrator.php` | 224 lines | Multi-agent management |
| `src/Agent/SubagentFactory.php` | 163 lines | Agent creation |
| `src/Agent/AgentContext.php` | 54 lines | Immutable context |
| `src/Agent/EnvironmentContext.php` | 179 lines | Environment detection |
| `src/Agent/InstructionLoader.php` | 113 lines | Instruction discovery |
| `src/Agent/MemoryInjector.php` | 76 lines | Memory formatting |
| `src/LLM/AsyncLlmClient.php` | 291 lines | Async HTTP client |
| `src/LLM/RetryableLlmClient.php` | — | Retry wrapper |
| `src/Tool/ToolRegistry.php` | 93 lines | Tool management |
| `src/Tool/Permission/PermissionEvaluator.php` | 135 lines | Permission system |
| `src/Tool/Permission/GuardianEvaluator.php` | 152 lines | Heuristic safety |
| `src/Tool/Coding/BashTool.php` | 76 lines | Shell execution |
| `src/Tool/Coding/FileEditTool.php` | 73 lines | File editing |
| `src/Tool/Coding/FileReadTool.php` | 117 lines | File reading |
| `src/Tool/Coding/GrepTool.php` | 94 lines | Text search |
| `src/Tool/Coding/SubagentTool.php` | 171 lines | Subagent spawning |
| `src/Session/SessionManager.php` | 290 lines | Session lifecycle |
| `src/Session/MemoryRepository.php` | 144 lines | Memory storage |
| `src/Task/TaskStore.php` | — | Task management |
| `src/Command/AgentCommand.php` | 340 lines | Main entry point |
| `src/Command/SlashCommandRegistry.php` | 83 lines | Command dispatch |
| `src/ConfigLoader.php` | 116 lines | YAML config |
| `src/Kernel.php` | 382 lines | DI container |

---

## Key Takeaway

KosmoKrator's **core architecture is solid and well-designed**. The agent loop, subagent orchestration with dependency graphs, 3-tier deduplication, permission modes with Guardian heuristics, and dual renderer are all production-quality implementations that compare well to Claude Code's equivalents.

The main gaps are in **breadth** rather than **depth**:
- **Tools**: Web fetch, web search, MCP, PDF/image reading
- **Streaming**: Real-time LLM response display
- **Context recovery**: Post-compaction file/instruction restoration
- **Extensibility**: Skills, hooks, custom agent definitions
- **Memory**: Proactive extraction and mechanics prompt

These can all be added incrementally without architectural changes. The Claude Code source provides exact thresholds, algorithms, and prompt templates that can be adapted for PHP implementation.
