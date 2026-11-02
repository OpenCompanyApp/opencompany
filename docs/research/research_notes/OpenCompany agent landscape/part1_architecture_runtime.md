# OpenCompany Architecture and Agent Runtime: Local Code Audit (part 1)

Scope: local checkout `/home/ruttydm/Projects/opencompany`, branch `feat/vfs`, audited 2026-11-02. All citations are repo-relative paths (path:line where useful). "Verified" means I read the code or ran a read-only command. "Docs-only" means I only saw the claim in docs. I made no changes to the repo apart from this notes file. `php artisan route:list --json` did not return parseable output in this environment, so route totals are not re-verified.

## 1. Overall architecture (domains, routes, models, frontend, queues, scheduling, workspace scoping)

### Takeaway
OpenCompany is a Laravel 12 monolith. It has DDD-style `app/Domain/*` use cases, a thin `app/Agents` runtime layer built on the official `laravel/ai` SDK (^0.6.7), and an Inertia/Vue 3 frontend. Execution runs through 11 queued jobs plus 6 scheduler entries. The inventory counts in `docs/CURRENT_STATE.md` match the files on disk.

### Cited Findings
- Stack and dependencies: `composer.json` requires `php ^8.4`, `laravel/framework ^12.0`, `laravel/ai ^0.6.7`, `laravel/octane`, `laravel/reverb`, `laravel/wayfinder`, `inertiajs/inertia-laravel ^2.0`, and `opencompany/chatogrator ^1.1`. It also requires about 18 `opencompanyapp/integration-*` packages through path repositories pointing at `../integrations/{catalog,bundle,core,packages/*}` with `symlink: false` — composer.json:8-40. License field: `AGPL-3.0-only`.
- `../integrations/packages` contains 639 package directories. `vendor/opencompanyapp` holds 585 installed entries (`ls | wc -l`), so `integration-bundle` evidently pulls in most of the catalog.
- Domain layout: `app/Domain/{AgentRuntime, Agents, Ai, Authorization, Automations, Calendar, Chat, Collaboration, Integrations, Knowledge, Vfs, Web, Work}`. `app/Domain/Chat` contains only `Telegram/Application` (10 services, 10,512 LOC). `TelegramWebhookPipeline.php` alone is 5,716 lines.
- Other top-level dirs: `app/Agents` (runtime + tools), `app/Ai` (Gateways: `CachingTextGateway`, `CodexTextGateway`, `PromptCachePolicy`, `UnsupportedTextGateway`; `Providers/CohereTextProvider`), and `app/Domain/Ai` (`Catalog`, `Codex` OAuth, `Embeddings`, `Gateway`, `Runtime/OpenCompanyAiProviderFactory`, `Usage/UsageRecorder`/`CostCalculator`). There is also a large `app/Services` layer (Memory, Mcp, Chat, Lua*, Approval*).
- Counts verified against CURRENT_STATE's snapshot (`docs/CURRENT_STATE.md` "Snapshot" table):

  | Item | Count (match/mismatch) |
  |---|---|
  | Model files in `app/Models` | 58 (match) |
  | Migrations in `database/migrations` | 111 (match) |
  | Vue pages in `resources/js/Pages` | 40 (match) |
  | Vue components in `resources/js/Components` | 152 (match) |
  | Tests | 92 Feature / 15 Unit / 14 Browser (match) |
  | Jobs | 11 top-level (match) |
  | Controllers under `app/Http/Controllers` | 67 (CURRENT_STATE says "54 API controllers"; not contradictory) |
  | Route-file lines | 849 total; `routes/api.php` is 435 lines |
- Jobs (`app/Jobs`): `AgentRespondJob`, `ExecuteAgentTaskJob`, `RunAutomationJob`, `RunScriptAutomationJob`, `AgentResumeFromSleepJob`, `CompactConversationJob`, `IndexDocumentJob`, `RefreshMcpToolsJob`, `ReconcileOpenRouterUsage`, `EnrichTelegramMediaJob`, `SendApprovalToTelegramJob`. Concerns: `SetsWorkspaceContext` and the new `SerializesAgentExecution`.
- Scheduler (`routes/console.php`):
  - `agent:resume-waiting` every minute
  - `agent-runs:reconcile` every 5 minutes, `withoutOverlapping`
  - `automation:run-due` every minute
  - `telegram:send-digests` every minute
  - `RefreshMcpToolsJob` hourly
  - `telescope:prune` daily
  - Daily cleanup of chart/SVG PNGs
- Workspace scoping:
  - Trait `app/Models/Concerns/BelongsToWorkspace.php:25` defines `scopeForWorkspace`. 35 model files reference `forWorkspace`/`BelongsToWorkspace`.
  - `app/Http/Middleware/ResolveWorkspace.php:24` short-circuits for agent users (`if ($user->isAgent()) return $next($request);`). Agents carry `workspace_id` directly.
  - Queued jobs rebind via `SetsWorkspaceContext` (e.g. `app/Jobs/AgentRespondJob.php` handle/failed).
- Frontend (`resources/js`): `Pages` (Chat, Tasks, Docs, Tables, Lists, Calendar, Files, Approvals, Automation{Create,Edit}, Agent/Show, Developer/{LuaConsole,Tools}, Telegram, Workspace, Org, Workload, Settings, Integrations, …). `Components/{agents,automation,chat,docs,tables,tasks,…,shared}`. Wayfinder-generated `routes/`, `actions/`, `wayfinder/`.
- Default database connection is `env('DB_CONNECTION','sqlite')` (`config/database.php:19`). Memory/pgvector, however, requires PostgreSQL (migrations `2022_08_03_000000_create_vector_extension.php`, `2026_02_16_000001_create_document_chunks_table.php`, `2026_02_17_000001_add_search_vector_to_document_chunks.php`).

### Inferences
- The architecture is a "modular monolith in transition". Newer code lives in `app/Domain/*/Application` use cases. Much behavior still sits in `app/Services` and very large classes (`TelegramWebhookPipeline` at 5.7k LOC, `RespondToChatMessage` at 772 LOC, `ToolRegistry` at 856 LOC). This matches ROADMAP's "Maintainability" items (`docs/ROADMAP.md`, P1 "Split response completion…").
- Production effectively requires PostgreSQL, because of pgvector and tsvector, even though the framework default is sqlite.

### Gaps
- I could not reproduce the route counts (375 total / 267 API). The `route:list --json` invocation returned nothing parseable here, probably because of a boot/DB dependency.
- I did not audit `resources/js` component-level behavior or `routes/web.php` page wiring in depth.

## 2. Agent runtime: entry points, run loop, streaming, provider/model resolution, prompt assembly

### Takeaway
Every LLM turn goes through `AgentRunBuilder` → `AgentRun::run()/stream()` → `OpenCompanyAgent`, which implements the `laravel/ai` SDK `Agent/Conversational/HasTools` contracts. The tool loop itself is the Laravel AI SDK's.

OpenCompany adds four layers around it:
- A durable run ledger (`agent_runs`)
- Per-agent serialization
- Heartbeat/cancel/pause checkpoints
- An idempotent tool ledger

Chat is streamed to the UI; assigned tasks and prompt automations are not. The system prompt is assembled from per-agent identity documents (`IDENTITY.md`, `INSTRUCTIONS.md`, `MEMORY.md`) plus live channel/task context.

### Cited Findings
- **Entry points** (dispatch sites):
  - Human chat: `app/Http/Controllers/Api/MessageController.php:135,183` and `DmController.php:124` dispatch `AgentRespondJob`.
  - Telegram: `app/Domain/Chat/Telegram/Application/TelegramWebhookPipeline.php:530,1542`.
  - Generic chat adapters: `app/Services/Chat/ChatBridge.php:140`.
  - Approval resume: `app/Services/ApprovalExecutionService.php:163,234,283`.
  - Agent-to-agent: `app/Agents/Tools/Agents/ContactAgent.php:183,233,259`.
  - Task assignment/resume: `app/Domain/Work/Application/ManageTasks.php:379,389,406` dispatch `AgentRespondJob`/`ExecuteAgentTaskJob`.
  - Automations: `app/Console/Commands/RunDueAutomations.php:97` and `ManageAutomations.php:130,169` dispatch `RunAutomationJob`.
  - Stale-run recovery: `app/Console/Commands/ReconcileAgentRuns.php:192,276`.
- **Three callers use `AgentRunBuilder`:**
  - `RespondToChatMessage.php:194` uses `->stream()` at :230.
  - `ExecuteAssignedTask.php:103` uses `->run()` at :111.
  - `ExecutePromptAutomation.php:121` uses `->run()` at :149.
  - `AgentChatService.php:19`, `MemoryFlushService.php:69` and the dev command `TestAgentComms.php:142` construct `OpenCompanyAgent::for()` directly and bypass the run wrapper.
- **AgentRunBuilder** (`app/Agents/Runtime/AgentRunBuilder.php`):
  - Clones the agent user.
  - Optionally overrides `behavior_mode` in memory to `autonomous|supervised|strict` for that run only.
  - Sets `withMaxSteps($options->maxTurns)`.
  - Enables checkpoint resume (`resumeFrom($taskId)`) only when a task ID exists.
- **AgentRun** (`app/Agents/Runtime/AgentRun.php`):
  - Emits in-memory `AgentRuntimeEvent`s: `run.started`, `context.planned`, `model.resolved`, `run.completed|failed|cancelled|paused`.
  - Binds a per-run `SystemPromptBag` into the container and always forgets it in `finally` (:52, :132). The comments call out Octane/queue container reuse.
  - On `FinishReason::Length` it joins partial step text rather than returning empty.
  - `stream()` (:116) falls back to `run()` when the catalog says the model does not support streaming (`supportsStreaming()` :212).
  - Events are persisted into `task.context['runtime_events']` by `RespondToChatMessage`, not into a separate table.
- **OpenCompanyAgent** (`app/Agents/OpenCompanyAgent.php`, 642 LOC):
  - Declares `#[MaxTokens(16_384)]` (:42).
  - `systemPrompts()` splits a stable prompt from a volatile prompt (via `PromptFrameBuilder::splitSections`) for prompt caching.
  - `buildSections()` (:210):
    - If identity docs exist, it emits a header and then `IDENTITY.md` + `INSTRUCTIONS.md` (:238).
    - `MEMORY.md`, peer cards and the memory-system prompt are added only when the channel type is in `['dm','agent','external']` (:247).
    - Then it adds time, channel context, task context (the task context instructs the model to call `update_task()` first), and an "Apps" catalog from `ToolRegistry::getAppCatalog`.
    - With no identity files it falls back to a generic "You are {name}, a helpful AI assistant" header.
  - `messages()` loads channel history through `ChannelConversationLoader`. On retry, `injectCheckpointedSteps()` (:353) replays completed `TaskStep`s marked `metadata->checkpointed` as assistant tool-call + tool-result pairs, then dedups and prunes them.
- **Tool exposure** (`OpenCompanyAgent::tools()` :404):
  - Returns registry tools + `OrchestrateSubagents` + up to `config('agents.subagents.max_tools', 8)` `GenericSubagent` tools (:439).
  - Each tool is wrapped in `RunGuardedTool` only when an `ActiveAgentRun` is bound (:422).
- **Code-first tool surface:**
  - `ToolRegistry::DIRECT_TOOL_GROUPS = ['tasks','system','agents','memory','lua','web','vfs']` (`app/Agents/Tools/ToolRegistry.php:35`). `getToolsForAgent()` skips every other group (:299-337).
  - So chat, docs, tables, lists, calendar, files, workspace-admin, integration-package tools and MCP tools are **not** native LLM tools. They are reached through `lua_exec` (the `app.*` bridge) or the VFS.
  - The prompt reinforces this ("To generate images/PDFs: use lua_exec with app.svg, app.integrations.mermaid…", :281).
- **Provider/model resolution** (`app/Agents/Providers/DynamicProviderResolver.php`):
  - `agent.brain` has the form `provider:model`. If unset it uses `config('ai.default_for_agents')`, which defaults to `'z'` (`config/ai.php:19`).
  - Missing model → workspace `IntegrationSetting` `models` config first, else `ModelCatalog::defaultModel`.
  - The provider is canonicalized via `ProviderCatalog`, then `ProviderConfigResolver::resolve($provider,$model,$workspaceId)`.
  - Hardcoded legacy mapping: `'glm-coding' => ['z', … 'glm-5.1']` (:86). This is a small exception to the repo's own "no hardcoded provider/model IDs" rule in AGENTS.md.
- **Provider catalog:** `app/Domain/Ai/Catalog/generated/providers.php` defines 22 providers: anthropic, openai, gemini, deepseek, groq, mistral, xai, ollama, openrouter, perplexity, codex, cohere, z-api, z, kimi, kimi-coding, minimax, minimax-cn, mimo, mimo-api, stepfun-plan, alibaba. This matches CURRENT_STATE's "22". The catalog deliberately does not enumerate models; unknown models fall back to provider metadata.
- **Timeout inconsistency:**
  - `OpenCompanyAgent::timeout()` uses `config('ai.request_timeout', 600)`.
  - `config/ai.php:25` sets `env('AI_REQUEST_TIMEOUT', env('PRISM_REQUEST_TIMEOUT', 30))`.
  - So the effective default is 30s, and a legacy `PRISM_*` env name is still read.
- **Durable run control plane** (uncommitted; `?? app/Models/AgentRunRecord.php`, migrations `2026_08_09_000001/000002`):
  - `AgentRunRecord` kinds: `chat`, `assigned_task`, `prompt_automation`, `script_automation`, `subagent`.
  - Statuses: `queued`, `running`, `waiting`, `retrying`, `completed`, `failed`, `cancelled` (`app/Models/AgentRunRecord.php:23-45`).
  - `ActiveAgentRun::checkpoint()` (`app/Agents/Runtime/ActiveAgentRun.php`) is throttled to 5s. It throws `AgentRunCancelled` on a cancel request or a terminal task, throws `AgentRunPaused` on a paused task, and otherwise heartbeats.
  - Jobs use `WithoutOverlapping("agent-execution:{agentId}")->shared()->releaseAfter(5)` (`app/Jobs/Concerns/SerializesAgentExecution.php`), with `tries=0`, `maxRunAttempts=3` and `timeout=1800`. A `retryUntil` window comes from `config/agent_runtime.php` (default 86400s).
  - `ReconcileAgentRuns` requeues or fails runs with stale heartbeats (`stale_running_after_seconds` 2100, `stale_queued_after_seconds` 86400).
- **Idempotent tool ledger:** `app/Domain/AgentRuntime/Application/IdempotentToolExecutor.php`, via `RunGuardedTool`. It claims a row in `agent_tool_executions` before running a tool. A crashed or throwing call is marked "ambiguous" and never auto-repeated; a completed identical retry reuses the encrypted result. The docblock explicitly calls this "at-most-once safety, not … exactly-once".
- **Streaming to the UI:**
  - `RespondToChatMessage::broadcastChatStreamEvent` (:517) forwards only `stream_start`/deltas/`stream_end`/`stream_failed` over Reverb. The comment says this avoids websocket frame-limit failures from large tool results.
  - The OpenAI-compatible AI Gateway does **not** stream: `app/Http/Controllers/Api/AiGateway/ChatCompletionsController.php:28-33` returns `unsupported_streaming`.
- **Tests:** `tests/Feature/AgentRunControlPlaneTest.php` (18 tests, uncommitted) and `tests/Feature/OpenCompanyAgentTest.php`. CURRENT_STATE reports 99 passed / 5 failed in focused verification on 2026-08-09 (docs claim; not re-run by me).

### Inferences
- Status: **SHIPPED (committed core) + PARTIAL (uncommitted control plane)**.
  - The chat/task/automation run path with identity-file prompts and checkpoint resume is mature.
  - The `agent_runs` ledger, heartbeat/cancel, reconciliation and idempotent tool ledger are real code with tests, but they are uncommitted on `feat/vfs` (ROADMAP P1 #1 "Finish and commit the agent-run queue control plane").
- Some LLM calls bypass `AgentRun` bookkeeping: memory flush (`MemoryFlushService.php:69`) and `AgentChatService`. Their usage/events may not appear in `runtime_events`.
- Because only 7 groups are native tools, the model's practical capability depends heavily on `lua_exec` working. In the local environment it does not (see section 7).
- Streaming exists only on the chat path. Assigned tasks and automations get no incremental output.

### Gaps
- I did not trace the `laravel/ai` SDK's internal tool loop (max steps defaults, parallel tool calls). OpenCompany passes `maxTurns` only when `AgentRunOptions` sets it; the three main callers do not set it, so the SDK default applies.
- I did not open `PromptFrameBuilder`/`PromptCachePolicy` in detail, so the prompt-cache breakpoint placement is unverified.
- `config/agents.php` does not exist, so `agents.subagents.max_tools` always falls back to 8. Possibly intended to be configurable.

## 3. Memory systems (docs vs code)

### Takeaway
Memory is real and substantial.
- **Long-term memory:** agent-owned markdown documents (`MEMORY.md`, `topics/*.md`, `logs/YYYY-MM-DD.md`, `peers/*`) with four tools.
- **Retrieval:** document chunking + pgvector embeddings + PostgreSQL full-text search, then reranking.
- **Short-term memory:** conversation compaction with a pre-compaction "memory flush".

The docs mostly match the code. "Dream" consolidation, temporal decay and dedup are absent from code, as CURRENT_STATE says.

### Cited Findings
- `app/Services/Memory` has 16 classes (2,221 LOC): `ChunkingService`, `EmbeddingService`, `DocumentIndexingService`, `RerankingService`, `ConversationCompactionService`, `CompactionMemoryExtractor`, `CompactionPlan`, `ContextBudget`, `ContextPruner`, `MemoryFlushService`, `MemoryScopeGuard`, `ModelContextRegistry`, `OutputTruncator`, `PromptFrameBuilder`, `TokenEstimator`, `ToolResultDeduplicator`.
- Tools: `app/Agents/Tools/Memory/{SaveMemory,RecallMemory,EditMemory,ForgetMemory}.php`.
  - They are registered via `MemoryToolProvider`, and `memory` is a direct tool group.
  - Each tool refuses at call time when `MemoryScopeGuard::canUseMemoryTools()` fails, i.e. in public channels (`SaveMemory.php:30`, `RecallMemory.php:33`, `EditMemory.php:30`, `ForgetMemory.php:30`).
  - The prompt describes core/topic/log/peer targets (`OpenCompanyAgent.php` `buildMemoryPrompt`).
- `config/memory.php`:
  - Embeddings default to `openai` `text-embedding-3-small` at 1536 dimensions.
  - Chunking is 512/64.
  - Hybrid weights are semantic 0.7 / keyword 0.3, with min similarity 0.5.
  - Reranking is enabled by default via **Ollama** `dengcao/Qwen3-Reranker-0.6B:Q8_0` (:66).
  - There is a hardcoded `context_windows` table of model-prefix → token sizes (:85). This is another hardcoded model list outside the provider catalog.
- Migrations: `create_vector_extension`, `create_document_chunks_table`, `create_embedding_cache_table`, `remove_vector_dimension_constraints`, `add_search_vector_to_document_chunks`.
- Compaction:
  - `CompactConversationJob` is dispatched from `ChannelConversationLoader.php:84`, `ChatBridge.php:313` and `TelegramWebhookPipeline.php:4593`.
  - `ConversationCompactionService` uses `CompactionMemoryExtractor`.
  - The pre-compaction flush is wired in `RespondToChatMessage::handle` (via `MemoryFlushService::shouldFlush/flush`).
  - `docs/planning/memory-implementation.md` says it is hooked into `AgentRespondJob`; the code location is now `RespondToChatMessage`, which the job calls. Minor doc drift.
- `docs/planning/memory-implementation.md` header: "Status: Complete (all 6 phases implemented)". Code supports this: every listed file exists.
- `docs/planning/memory-systems.md`: "Status: Research / roadmap". It covers graph memory, temporal decay, Mem0-style auto-extract and skill distillation.
  - `rg -i 'dream|temporal.?decay|consolidat' app` returned no hits.
  - CURRENT_STATE: "Dream consolidation, temporal decay, deduplication across extracted memory logs, and vector-optional writes remain planned."
- Tests: `tests/Feature/Services/Memory/{ConversationCompactionServiceTest, MemoryFlushServiceTest, MemoryScopeGuardTest}.php`, `tests/Feature/Tools/{Save,Recall,Edit,Forget}MemoryTest.php`, `tests/Feature/Agents/OpenCompanyAgentMemoryTest.php`.

### Inferences
- **SHIPPED:** document-backed LTM tools, hybrid retrieval, compaction, and the pre-compaction flush.
- **PLANNED-ONLY:** dream/consolidation, decay, cross-agent shared memory, a skill system (`docs/planning/skill-system.md`, and CURRENT_STATE "Explicitly not current"), and vector-optional mode (`docs/planning/vector-optional.md`).
- Reranking defaults to a local Ollama model. Unless deployments configure Ollama, reranking either fails gracefully or adds latency; I did not verify which.

### Gaps
- I did not verify how `RerankingService` behaves when Ollama is unavailable.
- I did not verify the RRF weighting implementation inside `DocumentIndexingService`.

## 4. Delegation / subagents

### Takeaway
Two delegation mechanisms coexist:
1. **Synchronous in-run subagents.** Each peer agent is exposed as a `GenericSubagent` tool, up to 8 per run, plus an `orchestrate_subagents` dependency-plan tool. These create child `Task` + `AgentRunRecord(kind=subagent)` rows.
2. **Asynchronous `contact_agent`** with `ask|delegate|notify`. It queues an `AgentRespondJob` for the target in a DM channel; the parent holds its response until subtasks finish.

The orchestrator is strictly sequential. Dependency outputs are **not** passed to dependents. The nested-delegation depth guard exists but is never called.

### Cited Findings
- `GenericSubagent` (`app/Agents/Runtime/Subagents/GenericSubagent.php`):
  - Implements `Agent, CanActAsTool, Conversational, HasTools`. The tool name is `subagent_{slug}_{id}`.
  - Before running it refuses paused/offline targets and checks that the channel belongs to the target's workspace.
  - It takes `Cache::lock("laravel-queue-overlap:agent-execution:{id}",1900)` (:77), the same key the job middleware uses, and fails fast if that agent is already running.
  - It creates a child task with `source=agent_delegation` and `parent_task_id`, plus `AgentRunRecord::KIND_SUBAGENT` with maxTries 1 (:109). It swaps `ActiveAgentRun` to the child and restores the parent in `finally`.
  - Context is isolated: `messages()` returns `[]` (:170). `instructions()` = generic preamble + all identity docs.
  - `tools()` (:178) returns only registry tools. Subagents do not get `GenericSubagent`/`OrchestrateSubagents`, so synchronous nesting is depth 1. They *do* get `contact_agent`, because `agents` is a direct group.
- `OrchestrateSubagents` (`app/Agents/Runtime/Subagents/OrchestrateSubagents.php`):
  - Accepts `planJson` `[{id, agentId, task, dependsOn[]}]`.
  - Validates that each target is in the same workspace, is not a system agent and is not self.
- `SubagentOrchestrator::runSequential` (`SubagentOrchestrator.php:26`):
  - Runs in topological order (`SubagentDependencyGraph` DFS). The graph has no cycle error; cycles are silently broken by the `seen` set.
  - Calls `GenericSubagent::for(...)->prompt($run->task)` (:51) with **only the run's own task text**. Upstream outputs are not injected, and a failed dependency does not skip its dependents.
- CURRENT_STATE confirms "Dependency plans remain sequential; there is no concurrent branch scheduler, full child-run UI, queue delivery modes, or auto-dissolution."
- `ContactAgent` (`app/Agents/Tools/Agents/ContactAgent.php`):
  - Same-workspace check, and blocks system agents and self.
  - Permission via `AgentPermissionService::canContactAgent`, which can create an access request or an approval.
  - `ask` creates a `SOURCE_AGENT_ASK` subtask; `delegate` creates `SOURCE_AGENT_DELEGATION`. Both dispatch `AgentRespondJob`.
  - The parent's `RespondToChatMessage` checks `awaiting_delegation_ids`/active subtasks, adds an "Awaiting results from …" step and `markWaiting()`s the run. `handleDelegationCallback` (:587) later aggregates results and re-dispatches the parent (:665).
- Depth guard is dead code:
  - `AgentCommunicationService::incrementDepth/decrementDepth/currentDepth` (`app/Services/AgentCommunicationService.php:23-39`); the docblock mentions "depth guards around nested delegation".
  - `rg incrementDepth` finds **no callers**. Only the dev command `TestAgentComms.php:132` prints "Current depth … / max 3".
  - The counter is also a static in-process variable, so it could not track depth across queued jobs anyway.
- Tests: `tests/Feature/OpenCompanyAgentTest.php` and `tests/Feature/AgentRunControlPlaneTest.php` reference `GenericSubagent`/`OrchestrateSubagents`.

### Inferences
- **SHIPPED:** async delegation (contact_agent ask/delegate/notify with hold-and-callback).
- **PARTIAL:** synchronous subagents/orchestration. They work and have child run records, but they are sequential and do not pass outputs between steps, so `dependsOn` only orders execution.
- There is no effective limit on async delegation chains (A→B→C…) apart from the per-agent execution locks and approval/permission gates. Ping-pong loops between agents look possible. This is an inference from the dead depth guard; I did not test it.
- A synchronous subagent call holds the parent's queue worker for the child's whole LLM loop. The parent is protected by the 1800s job timeout, but the child has no separate timeout beyond `ai.request_timeout`.

### Gaps
- I did not verify UI exposure of child runs. CURRENT_STATE says there is no full child-run UI.

## 5. Tools, VFS, MCP, integrations

### Takeaway
- **Tools:** 17 built-in tool providers covering ~170 tool classes, plus about 585 installed integration packages through `opencompanyapp/integration-core`'s `ToolProviderRegistry`.
- **MCP:** a client only (HTTP JSON-RPC with SSE response parsing; no stdio), with tools proxied as `mcp_{server}__{tool}`.
- **VFS:** the main in-flight feature. A permission-aware virtual filesystem over workspace data with a bounded Unix-like shell of 91 command specs (83 implemented, 8 recognized-but-rejected) and 15 `vfs_*` direct tools.

### Cited Findings
- **Built-in providers** (`app/Agents/Tools/Providers`): Agents, Automations, BuiltIn (base), Calendar, Chat, Docs, Files, Lists, Lua, Memory, Svg, System, Tables, Tasks, Vfs, Web, Workspace.
- **Tool classes by directory:** Calendar 7, Chat 14, Docs 14, Files 10, Lists 15, Tables 20, Tasks 5, Workspace 44 (including MCP server management, agent creation, identity-file edits, integration config), Memory 4, Lua 4, Web 2 (`WebSearchTool`, `WebFetchTool`), System 4, Agents 1, Charts 1, Vfs 15.
- **Integration enablement** (`app/Domain/Authorization/Application/AgentPermissionResolver.php:129-143`):
  - Integration apps = `ToolRegistry::INTEGRATION_APPS` (`['telegram']`) + package providers where `isIntegration()`.
  - Filtered to workspace-enabled `IntegrationSetting`s, except that `mcp_*` apps are always eligible.
- **MCP** (`app/Services/Mcp/*`, 10 classes, 803 LOC):
  - `McpClient` uses `Http::withHeaders` POSTs (:108) and `parseSseResponse` (:149), with a session ID. No stdio transport was found.
  - `McpToolProvider` app name is `mcp_{slug}` (:37); tool slug is `mcp_{slug}__{snake_tool}` (:130).
  - `McpPermissionEvaluator` delegates to `OpenCompanyPermissionEvaluator`.
  - `RefreshMcpToolsJob` runs hourly.
  - Agents can manage servers via the Workspace tools `AddMcpServer`, `DiscoverMcpTools`, `TestMcpServer`, `UpdateMcpServer`, `RemoveMcpServer`, `ListMcpServers`.
  - Tests: `McpProxyToolTest`, `McpSchemaTranslatorTest`, `McpServerControllerTest`, `McpToolProviderTest`.
  - CURRENT_STATE: "OpenCompany is not a hosted MCP server." ROADMAP P2 #5: "Harden MCP URL/redirect/response limits and provenance."
- **VFS core** (`app/Domain/Vfs`: 83 PHP files, 12,654 LOC):
  - `Core` (31 concerns): shell parsing/redirections/groups, jq, text filters, find, archive (tar/zip/gzip), binary inspection, discovery, mutation, plus `VfsBudget`, `VfsOperationLog`, `VfsPage`/`VfsPageCursor`/`VfsReadPage`, and `VfsCommandSpec`/`Registry`/`Help`.
  - `OpenCompany` (33 concerns + `Policy/VfsMutationPolicy` + `Approvals/VfsApprovalRequestFactory` + `VfsApprovedExecutionContext`).
- **VFS roots:** `agents, docs, files, tasks, lists, channels, tables, tools, automations, approvals, workspace` (`app/Domain/Vfs/OpenCompany/Concerns/ResolvesVfsPaths.php:52`). There is no `/skills`, `/artifacts` or `/memory` root, consistent with CURRENT_STATE.
- **VFS command registry** (verified by loading `VfsCommandSpecRegistry::all()`):
  - 91 specs, 83 with `status=implemented`.
  - 8 `recognized_unsupported`: bash, curl, history, patch, python, python3, sleep, wget. Each comes with a redirect message such as "Use web_fetch…" or "Use vfs_patch…" (`app/Domain/Vfs/Core/VfsCommandSpecRegistry.php:124-141`).
- **VFS direct tools** (`app/Agents/Tools/Providers/VfsToolProvider.php:55-165`): `vfs_exec, vfs_patch, vfs_write, vfs_stat, vfs_ls, vfs_read, vfs_rg, vfs_find, vfs_search, vfs_exists, vfs_mkdir, vfs_cp, vfs_mv, vfs_rm, vfs_count`. `vfs_exec` declares `canMutateWith => vfs_write`.
- **VFS tests:**

  | File | Tests | Notes |
  |---|---|---|
  | `tests/Feature/Vfs/VfsFeatureTest.php` | 49 | +1057 lines in the diff |
  | `VfsPaginationTest.php` | 13 | new |
  | `VfsCapabilityTruthTest.php` | 5 | new |

  ROADMAP P1 #7 notes one stale `rows.ndjson` assertion.

### Inferences
- **SHIPPED:** built-in tools, the integration package catalog plumbing, and the MCP HTTP client/proxy.
- **PARTIAL:** the VFS (code and tests are extensive but uncommitted; its pagination-index migration is pending locally per CURRENT_STATE) and MCP hardening.
- Every per-domain CRUD tool (docs, tables, etc.) is non-direct and is reached through `lua_exec` `app.*` or VFS paths. The VFS is becoming the primary agent surface for workspace data, and the per-domain tools act as the Lua/approval backend.
- A catalog entry does not prove an integration works; CURRENT_STATE says so explicitly.

### Gaps
- I did not count integration tool definitions. CURRENT_STATE claims "591 integrations / about 41,000 tool definitions"; on disk I counted 639 package dirs and 585 vendor entries, and the tool total is unverified.
- I did not inspect `../integrations/core` internals (ToolProvider contract, credential handling).

## 6. Approvals and permission enforcement

### Takeaway
Approval gating is per tool and enforced at tool-registration time: tools whose decision is `approval_required` are wrapped in `ApprovalWrappedTool`.

The wrapper never executes inline. It creates an `ApprovalRequest` with the serialized parameters, and the tool runs later in `ApprovalExecutionService` after a human approves (atomic pending→approved claim plus a permission recheck).

Policy is resolved in this order: workspace boundary → integration enablement → session grant → per-tool permission (explicit deny > exempt > explicit allow/requires_approval > behavior mode) → approval requirement.

### Cited Findings
- **Evaluator order** (`app/Agents/Runtime/Permissions/OpenCompanyPermissionEvaluator.php`):
  - Order: `WorkspaceBoundaryCheck` → `IntegrationPermissionCheck` → `SessionGrantCheck` (only an allow short-circuits) → `ToolPermissionCheck` → `ApprovalRequirementCheck`.
  - Comments mark the invariant: "Workspace boundaries are absolute… a session grant… must never widen access."
  - Used by `ToolRegistry.php:719`, `OpenCompanyLuaToolInvoker.php:37` and `McpPermissionEvaluator`.
- **Tool permission** (`AgentPermissionResolver::resolveToolPermission`, `app/Domain/Authorization/Application/AgentPermissionResolver.php:51-71`):
  - An explicit `deny` row wins.
  - `APPROVAL_EXEMPT_TOOLS` (:32-41) are always allowed: `wait_for_approval, wait, update_task, add_task_step, update_task_step, set_task_status, create_task_step, contact_agent`.
  - Next comes an explicit row's `requires_approval`.
  - Otherwise behavior mode decides (:312-318): `strict` → all tools need approval; `supervised` → only `write`-type tools; `autonomous` (default) → none.
- **`ApprovalWrappedTool`** (`app/Agents/Tools/System/ApprovalWrappedTool.php`):
  - Appends "(Note: This tool requires human approval…)" to the description.
  - Budget heuristic: params named amount/budget/cost/spend/bid/price/*_budget. If below `AppSetting budget_approval_threshold` (:51) it auto-approves, executes, and writes an `approved` audit row.
  - Otherwise it creates a `pending` `ApprovalRequest` (type `action|budget`, `tool_execution_context={tool_slug,parameters}`).
  - If `agent.must_wait_for_approval` (:74), it sets `awaiting_approval_id` and tells the model execution will pause. Otherwise the model may call `wait_for_approval` or continue.
- **Resolution** (`app/Services/ApprovalExecutionService.php:46-89`):
  - Atomic `UPDATE … WHERE status='pending'` claim, then execution.
  - `executeApprovedTool` rebinds the workspace from the agent, re-instantiates the tool, and **rechecks only `resolveToolPermission`** (:114). It does not recheck the full evaluator, so there is no fresh workspace/integration-enablement check.
  - It runs VFS replays inside `VfsApprovedExecutionContext` and posts the result as a channel message. Only an agent parked on this approval is resumed via `AgentRespondJob`.
  - Rejections resume a waiting agent with a denial message (:283).
- **HTTP surface:** `routes/api.php:230-233` (`GET/POST/PATCH /approvals`).
  - `ApprovalController::update` scopes with `whereHas('channel', workspace_id = current)` (`app/Http/Controllers/Api/ApprovalController.php:85`) and checks no approver role.
  - `ApprovalRequest` has no `workspace_id` column (`app/Models/ApprovalRequest.php:30-42` fillable).
  - Telegram has its own approval renderer/flow (`TelegramApprovalRenderer`, `SendApprovalToTelegramJob`).
- **Known gaps acknowledged in docs** (CURRENT_STATE "Confirmed incomplete authorization work" #6): "Approval resolution lacks a dedicated approver capability and a no-self-approval rule." The code agrees: no approver-role or self-approval check appears in `ApprovalController::update` or `ApprovalExecutionService::resolve`.
- **Tests:** `ApprovalWrappedToolTest`, `ApprovalResumeTest`, `WaitForApprovalTest`, `AgentPermissionServiceTest`, and VFS approval cases inside `VfsFeatureTest`.

### Inferences
- Status: **SHIPPED** for agent-side gating, with **PARTIAL** human-side authorization.
- Workspace scoping depends on `channel_id`. Some approvals are created without one (e.g. `ContactAgent`'s `ApprovalRequest::create` sets no `channel_id`), and those would appear unreachable through `/api/approvals` because of `whereHas('channel')`. This is plausible but unconfirmed; Telegram or other paths may still resolve them.
- The default behavior mode is `autonomous`, so out of the box agents need **no** approvals unless an admin sets supervised/strict or per-tool `requires_approval`.
- Approval wrapping happens when the tool is registered. Lua-invoked tools are gated separately in `OpenCompanyLuaToolInvoker`, so gating has two parallel code paths that must stay consistent.

### Gaps
- I did not read `WorkspaceBoundaryCheck`, `SessionGrantCheck` or the Lua invoker's approval handling line by line.
- I did not verify what the uncommitted `app/Domain/Vfs/OpenCompany/Policy/VfsMutationPolicy.php` decides for each mount.

## 7. Code-mode / scripting runtimes (Lua, mruby, QuickJS)

### Takeaway
Lua (Luau) is the only wired scripting runtime. It depends on a custom PHP extension class `Lua\Sandbox` that is **not loaded in this local environment** (`class_exists` is false), though the Dockerfile installs it. mruby and QuickJS have **no code** in this repo: mruby is a planning document only, and QuickJS lives in separate worktrees.

### Cited Findings
- `app/Services/LuaSandboxService.php` imports `Lua\Sandbox` and instantiates it at :28 (default 32 MB memory, 30s CPU). It adds a protected-bridge-name check and JSON globals.
- `LuaBridge`, `OpenCompanyLuaToolInvoker` (permission-evaluated tool calls) and `LuaApiDocGenerator` sit alongside it in `app/Services`.
- Consumers:
  - Tool `lua_exec` (`app/Agents/Tools/Lua/LuaExec.php`, wired at `app/Agents/Tools/Providers/LuaToolProvider.php:88`)
  - Doc tools `LuaListDocs`/`LuaReadDoc`/`LuaSearchDocs`
  - `POST /api/lua/execute` (`routes/api.php:341`, `LuaConsoleController`) and the `Developer/LuaConsole.vue` page
  - Script automations (`ExecuteScriptAutomation`, `RunScriptAutomationJob`)
- Runtime check: `php -r 'class_exists("Lua\\Sandbox")'` returned `false`; `extension_loaded('lua')`/`('luasandbox')` are both false.
- `Dockerfile:57-61` downloads `liblua_sandbox-linux-x86_64.so` from `OpenCompanyApp/lua-sandbox` release `v1.0.0` ("Lua Sandbox PHP extension (Luau runtime)").
- CURRENT_STATE/ROADMAP agree: 4 of 5 local test failures are "blocked by the missing local `Lua\Sandbox` class" (ROADMAP P1 #4).
- `docs/planning/lua-scripting.md`: "Status: Planning with partial implementation". Endpoint/webhook triggers, artifact builders and `generate_lua_script` remain design targets.
- `docs/planning/mruby-only-code-mode-implementation.md`: "Status: Planning", verified 2026-09-08.
  - Decision: replace Lua/Luau **and** the proposed QuickJS runtime with one restricted Ruby profile `opencompany-code-v1` on a pinned mruby guest, run as a PHP subprocess adapter.
  - It states that `../opencompany-quickjs` (branch `feat/quickjs`) and `../quickjs-sandbox` exist separately, and that the Bowerbird Ruby guest is "unimplemented".
- `rg -i 'mruby|quickjs' app config composer.json` returned no hits.

### Inferences
- **SHIPPED (in Docker builds):** Lua/Luau code mode. **Broken locally:** without the extension, any `lua_exec` call or script automation fails at class load.
- **PLANNED-ONLY:** mruby. **Out of tree / unmerged:** QuickJS.
- The planned mruby replacement would touch the central path. The code-first design routes most non-direct tools (docs, tables, integrations, MCP) through `lua_exec`, so the scripting runtime is critical-path, not an add-on.

### Gaps
- I did not verify the `../opencompany-quickjs` worktree state (out of scope; the doc's description is from 2026-09-08).
- I did not verify that Lua tests pass in Docker.

## 8. Telegram and other external channels

### Takeaway
Telegram is a deep, app-owned runtime with heavy test coverage. Slack, Discord, Teams and Google Chat go through the generic `opencompany/chatogrator` adapters (`ChatAdapterFactory` / `ChatBridge` / `SyncToChat`) with a declared capability table. They have no Telegram-equivalent product layer, and no Discord gateway listener exists in this repo.

### Cited Findings
- `app/Domain/Chat/Telegram/Application/`: `TelegramWebhookPipeline` (5,716 LOC), `TelegramNotificationRouter`, `TelegramDigestService`, `TelegramOperationsService`, `TelegramOutboundSync`, `TelegramSetupService`, `TelegramMiniAppVerifier`, `TelegramApprovalRenderer`, `TelegramCardRenderer`, `TelegramRateLimitException`.
- Jobs: `EnrichTelegramMediaJob`, `SendApprovalToTelegramJob`. Schedule: `telegram:send-digests` every minute.
- Routes: `routes/api.php:61` `POST /webhooks/chat/{adapter}` (canonical); `:62` legacy `/webhooks/telegram` marked "Retired legacy endpoint"; `:64-67` Mini App endpoints (workspaces/session/panel/action). 26 Telegram-related route lines in total.
- The pipeline dispatches agent runs (:530, :1542), automations (:4190) and compaction (:4593). `TelegramNotificationRouter` listens to `TaskUpdated` (`app/Providers/AppServiceProvider.php:99`).
- Tests: `tests/Feature/ChatWebhookControllerTest.php` is 8,565 lines with 1,375 "telegram" mentions. Also `TelegramWorkspaceToolsTest`, `ChatBridgeTest`, `ApprovalResumeTest` and `tests/Unit/ChatProviderCapabilitiesTest.php`.
- Other adapters (`app/Services/Chat/ChatAdapterFactory.php:25-51`): `telegram => null` (app-owned), `slack => SlackAdapter`, `discord => DiscordAdapter` (bot_token, public_key, application_id, gateway_secret), `teams => TeamsAdapter`, `google_chat => GoogleChatAdapter`.
- `app/Services/Chat/ChatProviderCapabilities.php` declares per-provider capability flags. Its docblock says some adapters "throw NotImplementedError for history or mutation methods".
- `docs/discord.md`: "Historical sidecar proposal… Discord now flows through the generic Chatogrator path… the bespoke Discord sidecar/service/listener/controller… no longer [exists]".
- `docs/external-channel-sync.md`: phases 1, 2 and 4 are "Done"; phase 3 (channel discovery) is "Partly done".
- No Discord-specific tests exist beyond `ChatWebhookControllerTest` mentions.

### Inferences
- **SHIPPED:** Telegram, the most product-complete channel.
- **PARTIAL:** Slack, Discord, Teams and Google Chat. They are configurable via Chatogrator, but behavior depends on the adapter and is not live-verified (CURRENT_STATE says "not Telegram-equivalent").
- Discord's regular (non-interaction) message ingress needs a Gateway websocket. The `gateway_secret` config implies an external forwarder is expected; none exists in this repo. This is inference only.

### Gaps
- I did not test Slack or Discord end to end, and I did not read the chatogrator package source in `tmp/chatogrator` or vendor.

## 9. Docs vs code discrepancies (CURRENT_STATE.md, ROADMAP.md) and the in-flight `feat/vfs` work

### Takeaway
`docs/CURRENT_STATE.md` and `docs/ROADMAP.md` are themselves new, uncommitted files. They are notably honest: inventory counts match disk, and the stated limits (sequential subagents, no gateway streaming, no dream memory, Lua sandbox missing locally, incomplete human RBAC) are confirmed by the code.

Discrepancies I found are mostly omissions:
- Dead depth guard
- Dependency outputs not passed between subagent steps
- Hardcoded model/provider lists
- Prism-era env fallback
- Approvals without a `workspace_id`

The branch carries a large uncommitted change set: run control plane + VFS + licensing.

### Cited Findings
- **Working tree:** `git status --short` shows 183 entries; `git diff --stat` shows "131 files changed, 8814 insertions(+), 1816 deletions(-)" for tracked files.
  - VFS subset: `app/Domain/Vfs app/Agents/Tools/Vfs tests/Feature/Vfs` = 58 files, +5392/−972.
  - Runtime subset: `app/Agents app/Domain/AgentRuntime app/Jobs app/Console app/Domain/Automations app/Domain/Chat app/Models app/Services` = 40 files, +1824/−610.
- **Notable untracked additions:**
  - Run control plane: `ActiveAgentRun`, `AgentRunCancelled`, `AgentRunPaused`, `AmbiguousAgentToolExecution`, `RunGuardedTool`, `ReconcileAgentRuns`, `IdempotentToolExecutor`, `RetryableRunFailure`, `AutomationRunContext`, `ResolveAutomationRun`, `SerializesAgentExecution`, `PrepareAgentToolExecution` listener, `AgentRunRecord`, `AgentToolExecution`, `config/agent_runtime.php`.
  - Migrations: `2026_08_09_000001_create_agent_runs_and_scope_direct_messages`, `_000002_create_agent_tool_executions_table`, `_000003_add_vfs_pagination_indexes`.
  - VFS:
    - `ExecutesVfsArchiveCommands`, `ExecutesVfsBinaryInspectionCommands`, `ExecutesVfsDiscoveryCommands`
    - `VfsCommandHelp`/`Spec`/`SpecRegistry`, `VfsPage`/`PageCursor`/`ReadPage`
    - `Vfs/OpenCompany/Approvals/`, `Policy/`, `BuildsVfsReadOnlyMetadata`, `EvaluatesVfsMutationPolicy`, `PaginatesVfsMountQueries`
    - `VfsAtlasCommand`
  - Docs: `CURRENT_STATE`, `ROADMAP`, `docs/README.md`, plus architecture/planning docs including `agent-run-queue-control-plane.md` and `mruby-only-code-mode-implementation.md`.
  - Tests: `AgentRunControlPlaneTest`, `VfsCapabilityTruthTest`, `VfsPaginationTest`.
  - Licensing: `LICENSE_EE.md` deleted; `LICENSE.md` +704 lines; `CONTRIBUTING.md` and `TRADEMARKS.md` added.
- **Largest modified tracked files:**

  | File | Changed lines |
  |---|---|
  | `tests/Feature/Vfs/VfsFeatureTest.php` | +1057 |
  | `ExecutesVfsTextFilters.php` | 497 |
  | `VfsFind.php` | 340 |
  | `MountsVfsTables.php` | 328 |
  | `ExecutesVfsFindCommands.php` | 287 |
  | `MountsVfsChannels.php` | 273 |
  | `ManageTasks.php` | 236 |
  | `ExecutePromptAutomation.php` | 219 |
  | `ExecuteScriptAutomation.php` | 186 |
  | `RespondToChatMessage.php` | 140 |
  | `ExecuteAssignedTask.php` | 127 |
  | `GenericSubagent.php` | 113 |
- **Branch history:**
  - `feat/vfs` is 46 commits ahead of `main` and 0 behind (`git rev-list --count main..HEAD` / `HEAD..main`).
  - The latest commits are `4c62c17 Secure approvals and workspace provisioning`, `693a5eb Add VFS baseline and planning docs` and `93895a1 Implement Telegram chat runtime`. 290 commits total in HEAD history.
  - All of the run control plane and most of the VFS command work listed above is uncommitted on top of `4c62c17`.
- **CURRENT_STATE claims confirmed in code:**
  - 22 providers, 58 models, 111 migrations, 40 pages, 152 components, 11 jobs, 121 tests
  - Gateway streaming not implemented (`ChatCompletionsController.php:28-33`)
  - Subagent plans sequential (`SubagentOrchestrator::runSequential`)
  - No `/skills` or `/artifacts` mount (`ResolvesVfsPaths.php:52`)
  - Not an MCP server (client-only `McpClient`)
  - Approvals lack approver capability / no-self-approval
  - Automations are schedule-only (the only `trigger_type` literal in app code is `'schedule'`; `RunDueAutomations` scans `next_run_at`)
  - Lua sandbox missing locally (`class_exists` false)
- **Discrepancies and omissions:**
  1. CURRENT_STATE says "a dependency orchestrator aggregates results". It does aggregate final outputs, but it does not feed upstream outputs into dependent steps (`SubagentOrchestrator.php:51`).
  2. A delegation depth guard is described in `AgentCommunicationService`'s docblock, but it is never invoked (`rg incrementDepth` shows no callers). Neither CURRENT_STATE nor ROADMAP mentions this.
  3. `docs/planning/memory-implementation.md` places the flush hook "in `AgentRespondJob`"; it now lives in `RespondToChatMessage::handle`.
  4. Repo rules (AGENTS.md) forbid hardcoding provider/model IDs outside catalogs, yet `DynamicProviderResolver.php:86` (`glm-coding → z/glm-5.1`), `config/memory.php:85` (context-window table) and `config/memory.php:66` (Ollama reranker model) hardcode them.
  5. AGENTS.md says "Do not reintroduce Prism". There is no Prism package, but `config/ai.php:25` still reads `PRISM_REQUEST_TIMEOUT`, and ROADMAP notes a stale `prism-codex` CI clone.
  6. The runtime timeout default disagrees between `OpenCompanyAgent::timeout()` (600) and `config/ai.php` (30).
  7. CURRENT_STATE lists "Approvals… atomic resolution, post-approval permission recheck". True, but the recheck is tool-level only (`ApprovalExecutionService.php:114`), not the full workspace/integration evaluator.
- **TODO/stub scan** (`rg -i 'TODO|FIXME|not implemented|NotImplemented|placeholder|stub'` over `app routes config`) found little:
  - `WorkspaceMemberController.php:73,118` "TODO: Send invitation email" and "Re-send invitation email": invitations do not send email.
  - `ModelConnectionTester.php:33` "Test not implemented for this integration" (HTTP 501).
  - `UnsupportedTextGateway.php:14` "Gateway placeholder for catalog providers that cannot execute through Laravel AI".
  - The gateway streaming stub.
  - No `NotImplemented` throws in app code.

### Inferences
- Feature classification summary:

  | Feature | Status | Notes |
  |---|---|---|
  | Chat runtime with streaming | SHIPPED | |
  | Identity-file prompts | SHIPPED | |
  | Checkpoint resume | SHIPPED | |
  | Assigned-task execution | SHIPPED | |
  | Scheduled prompt automations | SHIPPED | |
  | Memory (LTM tools, hybrid search, compaction, flush) | SHIPPED | |
  | Telegram | SHIPPED | |
  | MCP HTTP client | SHIPPED | |
  | Approvals (agent side) | SHIPPED | |
  | Provider catalog / Codex OAuth / AI Gateway (non-streaming) | SHIPPED | |
  | Async delegation (`contact_agent`) | SHIPPED | |
  | Durable run control plane | PARTIAL | Uncommitted |
  | VFS shell / pagination | PARTIAL | Uncommitted; index migration pending |
  | Sync subagent orchestration | PARTIAL | Sequential, no output passing |
  | Lua code mode | PARTIAL | Works only with the extension; broken locally |
  | Script automations | PARTIAL | Depend on Lua |
  | Slack/Discord/Teams/Google Chat | PARTIAL | Via Chatogrator |
  | Human RBAC | PARTIAL | 8 gaps listed in CURRENT_STATE |
  | Gateway streaming | STUBBED | |
  | Delegation depth guard | STUBBED | Dead code |
  | Invitation emails | STUBBED | TODO |
  | mruby code mode | PLANNED-ONLY | |
  | QuickJS | PLANNED-ONLY / out of tree | |
  | Dream/decay memory | PLANNED-ONLY | |
  | Skills | PLANNED-ONLY | |
  | Hosted MCP / Connect | PLANNED-ONLY | |
  | Artifacts/Lab, endpoint automations | PLANNED-ONLY | |
  | Event/poll automation triggers | PLANNED-ONLY | |
  | Visual workflows | PLANNED-ONLY | |
  | Embedded browser | PLANNED-ONLY | |
  | Enterprise identity | PLANNED-ONLY | |
- The in-flight branch converges on two themes:
  - **Durable, crash-safe agent execution:** run records, heartbeat, cancel/pause, serialization, an at-most-once tool ledger, and stale-run reconciliation.
  - **The VFS as the agent's primary bounded workspace interface:** a command-spec registry as the single source for help/docs, cursor pagination, budgets, mutation policy and approval replay.

### Gaps
- I did not run the test suite, per the "run only directly relevant tests" guidance and the read-only constraint, so the "99 passed / 5 failed" figure is a docs claim only.
- I did not check whether the pending `2026_08_09_000003` migration is applied in the local DB (that requires a DB query or `migrate:status`).
