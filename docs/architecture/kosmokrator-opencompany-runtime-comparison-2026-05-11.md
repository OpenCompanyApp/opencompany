# KosmoKrator / OpenCompany Runtime Comparison

> Read-only comparison of `/Users/rutger/Projects/kosmokrator` and `/Users/rutger/Sites/opencompany`.
> Purpose: identify KosmoKrator runtime capabilities that should influence OpenCompany's agent, integration, model, MCP, and orchestration architecture.

Status: Historical comparison. Provider/model ownership recommendations that mention Prism Relay were superseded on 2026-05-24 by the app-owned OpenCompany AI Runtime described in `ai-provider-runtime-architecture.md`.

## Executive Summary

KosmoKrator is technologically ahead as a reusable agent runtime. OpenCompany is stronger as a product system.

The right direction is not to copy KosmoKrator wholesale into OpenCompany. The right direction is to pull KosmoKrator's runtime patterns into OpenCompany while preserving OpenCompany's workspace, user, channel, task, approval, document, identity, and integration product model.

KosmoKrator should be treated as the runtime reference. OpenCompany should remain the collaborative company operating system that uses that runtime.

## Current Shape

### KosmoKrator

KosmoKrator is runtime-first. Its core architecture is built around reusable agent execution across terminal, headless, gateway, SDK, and ACP surfaces.

Important files:

- `/Users/rutger/Projects/kosmokrator/src/Agent/AgentSessionBuilder.php`
- `/Users/rutger/Projects/kosmokrator/src/Agent/AgentLoop.php`
- `/Users/rutger/Projects/kosmokrator/src/Agent/SubagentOrchestrator.php`
- `/Users/rutger/Projects/kosmokrator/src/Tool/Permission/PermissionEvaluator.php`
- `/Users/rutger/Projects/kosmokrator/src/Integration/IntegrationManager.php`
- `/Users/rutger/Projects/kosmokrator/src/Mcp/McpRuntime.php`
- `/Users/rutger/Projects/kosmokrator/src/LLM/ProviderCatalog.php`
- `/Users/rutger/Projects/kosmokrator/src/LLM/ModelCatalog.php`
- `/Users/rutger/Projects/kosmokrator/src/Sdk/Agent.php`
- `/Users/rutger/Projects/kosmokrator/src/Acp/AcpAgentServer.php`

Its strongest runtime traits:

- Centralized runtime assembly.
- Clear permission policy chain.
- Typed subagent orchestration.
- Integration runtime with capability and credential handling.
- MCP runtime with config, client, Lua, and gateway concepts.
- Provider/model catalog with relay metadata and live discovery.
- SDK and ACP access to the same agent loop.
- Context budgeting, pruning, compaction, deduplication, and truncation in one runtime pipeline.

### OpenCompany

OpenCompany is product-first. Its agent runtime is embedded in the Laravel workspace application.

Important files:

- `/Users/rutger/Sites/opencompany/app/Agents/OpenCompanyAgent.php`
- `/Users/rutger/Sites/opencompany/app/Jobs/AgentRespondJob.php`
- `/Users/rutger/Sites/opencompany/app/Agents/Tools/ToolRegistry.php`
- `/Users/rutger/Sites/opencompany/app/Http/Controllers/Api/IntegrationController.php`
- `/Users/rutger/Sites/opencompany/app/Services/AgentPermissionService.php`
- `/Users/rutger/Sites/opencompany/app/Services/Memory/ConversationCompactionService.php`
- `/Users/rutger/Sites/opencompany/app/Services/Mcp/McpClient.php`

Its strongest product traits:

- Multi-workspace ownership.
- Human and agent membership.
- Channels, tasks, docs, tables, files, approvals, identities, and notifications.
- Workspace-scoped integration credentials.
- Agent personalities and identity files.
- Real collaborative product UI.
- Database-backed tasks, messages, memories, integration settings, and approval records.

OpenCompany should keep those product strengths. The improvement opportunity is mostly in separating runtime concerns from product concerns.

## Main Transfer Opportunities

### 1. Unified Agent Runtime Assembly

KosmoKrator has a single runtime assembly path through `AgentSessionBuilder`. It wires provider selection, LLM clients, tool registry, permissions, settings, context pipeline, session persistence, subagents, MCP, integrations, and renderers.

OpenCompany currently spreads that responsibility across `OpenCompanyAgent`, `AgentRespondJob`, `ToolRegistry`, `DynamicProviderResolver`, memory services, provider config, MCP services, and integration controllers.

Recommended OpenCompany direction:

- Add `App\Agents\Runtime\AgentRunBuilder`.
- Add `App\Agents\Runtime\AgentRun`.
- Add a runtime event stream/result object.
- Let `AgentRespondJob` orchestrate queue/message/task state, but stop making it responsible for runtime assembly details.

Priority: `P0`

### 2. Permission Policy Chain

KosmoKrator's permission system is structured as a chain:

- Blocked path checks.
- Deny pattern checks.
- Project boundary checks.
- Session grants.
- Rule checks.
- Mode overrides.
- Guardian/Argus/Prometheus behavior.

OpenCompany already has product-level permissions through `AgentPermissionService` and approval records, but tool execution does not have the same clear policy pipeline.

Recommended OpenCompany direction:

- Keep database-backed workspace permissions.
- Add an `OpenCompanyPermissionEvaluator` that evaluates hard denies, workspace boundaries, channel/task/file scope, integration read/write mode, session grants, approval requirements, and model/tool budget rules in one place.
- Make approval wrapping a result of policy evaluation rather than scattered behavior.

Priority: `P1`

### 3. Subagent Orchestration

KosmoKrator has a real subagent orchestrator:

- Typed subagents.
- Dependency chains.
- Groups.
- Background execution.
- Retry handling.
- Concurrency limits.
- Idle watchdog.
- Stats and lifecycle state.
- Output spooling.

OpenCompany has a stronger product model for collaboration between actual agents and humans, especially through channels, tasks, DMs, and `ContactAgent`. But it does not yet have KosmoKrator-style deterministic orchestration for multi-agent work.

Recommended OpenCompany direction:

- Keep OpenCompany's task/channel/DM model.
- Add an execution-level orchestrator underneath it.
- Track dependencies, group concurrency, retry state, current tool, last activity, token usage, and output references.
- Start with delegated worker runs before trying to expose the whole system as user-facing swarm UI.

Priority: `P1`

### 4. Integration Runtime Extraction

KosmoKrator's `IntegrationManager` cleanly separates:

- Discoverable providers.
- Locally runnable providers.
- CLI/API configurable providers.
- Active providers.
- Credential resolution.
- Multi-account aliases.
- Capability checks.
- Read/write permission mode.
- Tool catalog generation.
- Lua invocation.

OpenCompany has recently improved its catalog surface, but `IntegrationController` still contains too much hardcoded provider behavior, model fetching, connection testing, Google-specific credential handling, webhook behavior, and runtime branching.

Recommended OpenCompany direction:

- Add `App\Services\Integrations\IntegrationDirectory`.
- Add `App\Services\Integrations\IntegrationRuntime`.
- Add `App\Services\Integrations\IntegrationConnectionTester`.
- Move connection testing, provider activation, tool availability, credential-state checks, and model/provider probing out of `IntegrationController`.
- Reuse the same runtime from the UI, agent tools, Lua bridge, tests, and future CLI/admin surfaces.

Priority: `P0`

This is the best first implementation target because it directly addresses the existing hardcoded integration controller problem and recent provider/integration bugs.

### 5. MCP Runtime

KosmoKrator has a portable MCP runtime:

- Multiple config sources.
- Client management.
- Tool catalog generation.
- Permission checks.
- Lua invocation.
- Runtime-only servers.
- Server gateway concepts.
- Normalized results.

OpenCompany has MCP support, but it is more product-model and HTTP/SSE-provider oriented. It should become a runtime service rather than only another tool provider shape.

Recommended OpenCompany direction:

- Add a first-class `McpRuntime`.
- Support config import/export.
- Support stdio MCP servers where appropriate.
- Add trust and secret handling.
- Expose MCP through Lua under an `app.mcp.*` namespace.
- Allow direct MCP calls through the same permission/runtime pipeline as integration tools.

Priority: `P1`

### 6. Provider And Model Catalog

KosmoKrator had a stronger provider/model abstraction at the time of this audit. OpenCompany now has an app-owned catalog/runtime layer with discovered model inventory, pricing, context windows, capabilities, auth modes, and provider/model UI options.

OpenCompany currently has provider resolution and settings logic, but too much model/provider behavior is scattered between `DynamicProviderResolver`, integration settings, relay-backed Laravel AI drivers, and integration controller branches.

Recommended OpenCompany direction:

- Add `App\Services\Ai\ProviderCatalog`.
- Add `App\Services\Ai\ModelCatalog`.
- Back them with app-owned AI catalog metadata and workspace integration settings.
- Remove duplicated provider/model lists and capability assumptions from controllers.
- Use this catalog for admin UI, agent brain config, model testing, provider fallback, prompt budget, pricing display, and live diagnostics.

Priority: `P0`

### 7. SDK / ACP Runtime Access

KosmoKrator exposes the same runtime through SDK and ACP surfaces. That gives it a clean separation between "agent runtime" and "place where the runtime is used."

OpenCompany does not yet have a comparable internal runtime API. Its agent execution path is still heavily tied to queue jobs and web product state.

Recommended OpenCompany direction:

- First add an internal SDK-like runtime interface.
- Use it from queue jobs, live tests, automation endpoints, integration tests, and future external clients.
- Only consider ACP after the internal runtime abstraction is stable.

Priority: `P2`

### 8. Context Pipeline

KosmoKrator's context path is one runtime pipeline:

- Context budget.
- Preflight checks.
- Output truncation.
- Tool result deduplication.
- Context pruning.
- LLM compaction.
- Memory extraction.
- Protected context preservation.
- Fallback trimming.
- Token accounting.

OpenCompany already has strong pieces, including `ConversationCompactionService`, `ContextBudget`, `ContextPruner`, and prompt splitting. But the behavior is spread out and partly job/service specific.

Recommended OpenCompany direction:

- Keep the existing OpenCompany memory services.
- Put them behind a single `AgentContextPipeline`.
- Make prompt budget, pruning, compaction, protected context, retry context, and memory extraction explicit runtime steps.
- Store compaction and pruning decisions in a way that can be inspected from admin/debug UI.

Priority: `P1`

### 9. Settings And Diagnostics

KosmoKrator has a typed settings schema and runtime configuration discipline. OpenCompany has workspace settings, integration settings, agent config, and app settings, but no single schema-like diagnostic surface for runtime behavior.

Recommended OpenCompany direction:

- Add a runtime settings catalog.
- Keep values in OpenCompany's database-backed settings.
- Use schema metadata to drive validation, admin UI, defaults, and diagnostics.
- Add a provider/integration/runtime doctor view.

Priority: `P2`

### 10. Gateway Architecture

KosmoKrator's gateway code separates gateway runtime, sessions, approval stores, message stores, and pending input handling.

OpenCompany has Telegram and external channel product integration, but parts of the behavior are coupled into webhook/controller/job flow.

Recommended OpenCompany direction:

- Use KosmoKrator's gateway architecture as a reference for external channel runtime boundaries.
- Keep OpenCompany's product records as source of truth.
- Extract gateway session/input/approval mechanics where they start to duplicate across Telegram, Discord, and future external channels.

Priority: `P2`

### 11. Runtime Observability

KosmoKrator's TUI is not directly useful for OpenCompany, but its event model is useful.

OpenCompany should adopt the observability pattern, not the terminal UI:

- Run phase.
- Current model.
- Current tool.
- Permission wait.
- Approval wait.
- Prompt budget.
- Token usage.
- Integration call state.
- MCP call state.
- Subagent tree.
- Error and retry state.

Priority: `P1`

## Suggested Target Architecture

The target shape should be:

```text
OpenCompany product layer
  Workspaces, users, agents, channels, tasks, docs, approvals, identities, UI

OpenCompany agent runtime layer
  AgentRunBuilder
  AgentRun
  AgentContextPipeline
  OpenCompanyPermissionEvaluator
  ToolExecutionPipeline
  Runtime events/results

Shared/provider runtime layer
  ProviderCatalog
  ModelCatalog
  IntegrationRuntime
  IntegrationDirectory
  IntegrationConnectionTester
  McpRuntime

Shared packages
  integration-core
  integration packages
```

Where behavior belongs:

| Behavior | Preferred owner |
| --- | --- |
| Workspace membership, tasks, channels, docs, approvals | OpenCompany app |
| Runtime assembly and agent run lifecycle | OpenCompany runtime service |
| Provider metadata, model metadata, pricing, context windows | OpenCompany app-owned AI runtime catalog |
| Integration tool contracts and package docs | `integration-core` / integration packages |
| Integration enablement and credentials | OpenCompany workspace DB |
| Integration execution and testing | OpenCompany integration runtime |
| MCP client/tool execution | OpenCompany MCP runtime, potentially informed by KosmoKrator |
| Terminal TUI | KosmoKrator only |
| ACP/external editor protocol | Later, after internal runtime stabilizes |

## P0/P1 Implementation Plan

This plan covers only the `P0` and `P1` recommendations from this comparison. The goal is to make OpenCompany's runtime more like KosmoKrator's without disrupting OpenCompany's product model.

### P0 Workstream 1: Integration Runtime Extraction

Problem:

- `IntegrationController` currently owns catalog merging, config schema handling, static provider behavior, MCP display, Google shared credential behavior, testing, webhooks, model fetching, and provider-specific branching.
- Agent tools, Lua, integration UI, catalog endpoints, and tests do not have one shared integration runtime surface.

Target services:

- `App\Services\Integrations\IntegrationDirectory`
- `App\Services\Integrations\IntegrationRuntime`
- `App\Services\Integrations\IntegrationConnectionTester`
- `App\Services\Integrations\IntegrationConfigResolver`
- `App\Services\Integrations\IntegrationAccountResolver`

Responsibilities:

| Service | Responsibility |
| --- | --- |
| `IntegrationDirectory` | Merge static integrations, package providers, built-in tool providers, and MCP-backed providers into one normalized catalog |
| `IntegrationRuntime` | Execute integration tools through one workspace/account-aware path |
| `IntegrationConnectionTester` | Test configured and no-key integrations consistently |
| `IntegrationConfigResolver` | Load, mask, default, and normalize config schemas and config values |
| `IntegrationAccountResolver` | Resolve default and named accounts, including shared credential families like Google |

Implementation steps:

1. Add value objects for normalized catalog entries.
   - `IntegrationDescriptor`
   - `IntegrationConfigField`
   - `IntegrationRuntimeAccount`
   - `IntegrationConnectionTestResult`

2. Move `IntegrationController::index()` catalog assembly into `IntegrationDirectory`.
   - Preserve current JSON shape first.
   - Keep controller output stable.
   - Add unit tests that compare representative static, package, built-in, and MCP entries.

3. Move `showConfig()` schema/config logic into `IntegrationConfigResolver`.
   - Preserve masked secret behavior.
   - Preserve OAuth field handling.
   - Preserve Google shared credential prefill, but make it data-driven instead of controller-specific.

4. Move integration test behavior into `IntegrationConnectionTester`.
   - Public/no-key integrations should test without credentials.
   - Keyed integrations should fail with an explicit missing credential result.
   - Provider/model tests should return structured provider/model/auth diagnostics.

5. Route Lua and agent integration execution through `IntegrationRuntime`.
   - Keep existing `OpenCompanyScriptToolInvoker` behavior initially.
   - Replace direct provider/tool construction with runtime calls once parity tests pass.

6. Slim `IntegrationController`.
   - Controller should only authenticate, resolve workspace/account, call services, and return JSON.
   - No provider-specific branching should remain unless it is truly an HTTP/API concern.

Validation:

- Feature tests for integration catalog response shape.
- Unit tests for `IntegrationDirectory` normalization.
- Unit tests for config masking/defaulting.
- Connection tests for a no-key integration such as WorldBank.
- Connection tests for at least one keyed integration with missing credentials.
- Regression tests for Google shared client credentials.
- Lua invocation test through one package integration.

Exit criteria:

- `IntegrationController` no longer manually merges every integration type.
- WorldBank-style public integrations can be cataloged and tested without API keys.
- Agent/Lua/UI use the same runtime service for integration availability and execution.

### P0 Workstream 2: Provider And Model Catalog

Problem:

- Provider/model behavior is split across `DynamicProviderResolver`, `IntegrationSetting`, relay-backed Laravel AI providers, static integration config, and controller logic.
- Model names, auth modes, default URLs, and capability assumptions can drift.

Target services:

- `App\Services\Ai\ProviderCatalog`
- `App\Services\Ai\ModelCatalog`
- `App\Services\Ai\ProviderConfigResolver`
- `App\Services\Ai\ModelConnectionTester`

Responsibilities:

| Service | Responsibility |
| --- | --- |
| `ProviderCatalog` | Normalize provider metadata from app-owned AI catalog data, workspace settings, app config, OAuth providers, and configured local providers |
| `ModelCatalog` | Normalize model list, default model, context window, modalities, pricing, and capability metadata |
| `ProviderConfigResolver` | Produce Laravel AI provider config for a workspace/provider |
| `ModelConnectionTester` | Run provider/model probes with structured diagnostics |

Implementation steps:

1. Wrap app-owned AI catalog metadata behind `ProviderCatalog`.
   - Keep `app/Domain/Ai/Catalog` as metadata source of truth.
   - Add OpenCompany workspace setting overlay.
   - Add app/env fallback overlay.

2. Move `DynamicProviderResolver` config mutation into `ProviderConfigResolver`.
   - Keep `DynamicProviderResolver` public API stable.
   - Make it delegate provider lookup/default model/config registration to the new services.

3. Move default model selection into `ModelCatalog`.
   - Use DB-stored models first where valid.
   - Fall back to relay provider defaults.
   - Return explicit errors for unknown providers/models.

4. Move controller model-fetch/probe behavior into `ModelConnectionTester`.
   - Return structured status: `ok`, `missing_credentials`, `provider_error`, `model_not_found`, `network_error`.
   - Include provider, model, auth mode, base URL, and sanitized error details.

5. Use `ProviderCatalog` in integration UI/catalog paths.
   - UI should not need to know whether a provider is relay-backed, OAuth-backed, local, env-backed, or DB-backed.

Validation:

- Unit tests for provider canonicalization.
- Unit tests for default model selection.
- Unit tests for missing provider credentials.
- Regression test for `z:glm-5.1` resolution.
- Regression test for env/config fallback providers.
- Regression test for OAuth-style providers such as Codex.

Exit criteria:

- Provider/model metadata has one OpenCompany service layer.
- `DynamicProviderResolver` is mostly a compatibility adapter.
- Integration/model UI and agent brain resolution use the same provider/model catalog.

### P0 Workstream 3: Agent Runtime Builder

Problem:

- `AgentRespondJob` currently creates tasks, manages status, starts typing indicators, creates `OpenCompanyAgent`, captures prompt/context/tool state, handles retries, records steps, delivers messages, and handles errors.
- Runtime assembly is too coupled to the chat queue path.

Target classes:

- `App\Agents\Runtime\AgentRunBuilder`
- `App\Agents\Runtime\AgentRunOptions`
- `App\Agents\Runtime\AgentRun`
- `App\Agents\Runtime\AgentRunResult`
- `App\Agents\Runtime\AgentRuntimeEvent`

Responsibilities:

| Class | Responsibility |
| --- | --- |
| `AgentRunBuilder` | Build a runnable agent execution from agent, channel, task, message, provider, tools, context, and options |
| `AgentRunOptions` | Max turns, timeout, retry mode, delivery mode, tool policy mode, model override |
| `AgentRun` | Execute the prompt and emit runtime events |
| `AgentRunResult` | Final response, usage, tool calls, errors, context snapshot, delivery metadata |
| `AgentRuntimeEvent` | Phase/tool/model/context/permission/progress event payload |

Implementation steps:

1. Add classes with no behavior change.
   - Initially wrap existing `OpenCompanyAgent::for(...)` and `prompt(...)`.
   - Keep `AgentRespondJob` behavior identical.

2. Move prompt/context capture into `AgentRun`.
   - System prompt, volatile prompt context, messages, context budget, tool registry state.

3. Move retry setup into `AgentRunBuilder`.
   - `resumeFrom($taskId)` should be selected by options rather than directly in the job.

4. Make `AgentRespondJob` consume `AgentRunResult`.
   - Job remains responsible for task/message lifecycle and external delivery.
   - Runtime owns model/tool/context execution.

5. Add a live-test harness.
   - It should send a real message to an agent through the same runtime path.
   - It should record provider, model, runtime events, final response, and integration tool-call evidence.

Validation:

- Existing agent response feature tests still pass.
- Retry behavior remains unchanged.
- Task creation/completion behavior remains unchanged.
- Runtime can be invoked from a test without going through the full queued job.

Exit criteria:

- Queue job no longer directly assembles runtime internals.
- Same agent runtime can be used by chat, tests, automation endpoints, and later gateways.

### P1 Workstream 1: Permission Evaluation Pipeline

Problem:

- OpenCompany has product permissions and approval wrappers, but execution policy is not expressed as one ordered chain.

Target classes:

- `App\Agents\Runtime\Permissions\OpenCompanyPermissionEvaluator`
- `App\Agents\Runtime\Permissions\PermissionDecision`
- `App\Agents\Runtime\Permissions\Checks\WorkspaceBoundaryCheck`
- `App\Agents\Runtime\Permissions\Checks\ToolPermissionCheck`
- `App\Agents\Runtime\Permissions\Checks\IntegrationPermissionCheck`
- `App\Agents\Runtime\Permissions\Checks\SessionGrantCheck`
- `App\Agents\Runtime\Permissions\Checks\ApprovalRequirementCheck`

Implementation steps:

1. Introduce `PermissionDecision`.
   - Decisions: `allow`, `deny`, `ask`, `approval_required`.
   - Include reason, policy source, and suggested approval payload.

2. Wrap current `AgentPermissionService` behind evaluator checks.
   - Keep current DB permissions as source of truth.

3. Add workspace boundary checks.
   - Channels, tasks, files, docs, integrations, and external identities must belong to the active workspace.

4. Add integration read/write policy checks.
   - Use package tool metadata/capabilities where available.

5. Route approval wrapping through evaluator decisions.
   - `ApprovalWrappedTool` should become an execution wrapper selected by policy, not a separate path with duplicated policy assumptions.

Validation:

- Unit tests for each check.
- Feature tests for allowed, denied, and approval-required tool calls.
- Regression tests for integration read/write permissions.
- Regression tests for cross-workspace denial.

Exit criteria:

- Runtime can explain why a tool was allowed, denied, or sent to approval.
- Approval behavior and tool permission behavior are inspectable from one decision object.

### P1 Workstream 2: Agent Context Pipeline

Problem:

- OpenCompany has useful memory/context services, but they are not yet one explicit runtime pipeline.

Target classes:

- `App\Agents\Runtime\Context\AgentContextPipeline`
- `App\Agents\Runtime\Context\AgentContextSnapshot`
- `App\Agents\Runtime\Context\AgentContextPlan`
- `App\Agents\Runtime\Context\ToolResultDeduplicator`
- `App\Agents\Runtime\Context\ToolOutputTruncator`

Implementation steps:

1. Wrap existing `ContextBudget`, `ContextPruner`, `ConversationCompactionService`, and prompt splitting behind `AgentContextPipeline`.

2. Add an explicit context snapshot object.
   - System prompt sections.
   - Volatile prompt context.
   - Messages.
   - Tool outputs.
   - Token budget estimate.
   - Provider/model context window.

3. Add context plan output.
   - What was kept.
   - What was pruned.
   - What was compacted.
   - What was protected.
   - Why fallback trimming happened.

4. Add tool output truncation policy.
   - Store full payload in DB/object storage if needed.
   - Keep preview in prompt context.

5. Add tool result deduplication.
   - Start with repeated read/search/integration payloads.
   - Avoid KosmoKrator's filesystem-specific stale-read behavior unless OpenCompany has an equivalent file mutation signal.

Validation:

- Unit tests for budget planning.
- Unit tests for pruning protected recent user turns.
- Unit tests for truncating large integration payloads.
- Unit tests for repeated tool result deduplication.
- Feature test for compaction preserving protected context.

Exit criteria:

- Agent runtime can produce a context plan before calling the model.
- Prompt/context behavior is inspectable and testable outside the queue job.

### P1 Workstream 3: Runtime Observability

Problem:

- OpenCompany stores task steps and broadcasts status, but runtime events are not yet detailed enough to debug model/tool/integration behavior reliably.

Target classes/tables:

- `AgentRuntimeEvent`
- Optional `agent_run_events` table if task steps are not sufficient.
- Runtime event broadcasting through existing task/channel events.

Event types:

- `run.started`
- `context.planned`
- `model.resolved`
- `provider.configured`
- `tool.started`
- `tool.completed`
- `tool.failed`
- `integration.called`
- `mcp.called`
- `permission.waiting`
- `approval.created`
- `subagent.started`
- `subagent.completed`
- `run.completed`
- `run.failed`

Implementation steps:

1. Add event objects in the new runtime layer.
2. Emit events from `AgentRun`.
3. Persist critical events to task context or a dedicated table.
4. Broadcast user-visible events through existing task/status channels.
5. Add admin/debug display later; do not block runtime work on UI.

Validation:

- Tests that a normal run emits start/context/model/completed events.
- Tests that a tool call emits start/completed events.
- Tests that provider failure emits structured failure event.

Exit criteria:

- A failed live run can be diagnosed from structured runtime events without reading raw logs first.

### P1 Workstream 4: MCP Runtime Upgrade

Problem:

- MCP exists in OpenCompany, but it is not yet as runtime-oriented as KosmoKrator's MCP stack.

Target classes:

- `App\Services\Mcp\McpRuntime`
- `App\Services\Mcp\McpToolCatalog`
- `App\Services\Mcp\McpPermissionEvaluator`
- `App\Services\Mcp\McpResultNormalizer`
- `App\Services\Mcp\McpConfigImporter`

Implementation steps:

1. Create `McpRuntime` around existing MCP client/model behavior.
2. Normalize MCP tool catalog entries into the same shape as integration tools.
3. Add MCP permission checks through the runtime permission pipeline.
4. Add result normalization for agent/Lua use.
5. Add config import/export later, after runtime calls are stable.

Validation:

- Unit tests for catalog normalization.
- Feature test for enabled MCP server tool availability.
- Tool-call test through Lua/runtime path.

Exit criteria:

- MCP calls use the same runtime, permission, catalog, and observability patterns as integrations.

### P1 Workstream 5: Subagent Execution Orchestration

Problem:

- OpenCompany can ask/delegate/notify between agents, but it lacks execution-level dependency and concurrency orchestration.

Target classes:

- `App\Agents\Runtime\Subagents\SubagentOrchestrator`
- `App\Agents\Runtime\Subagents\SubagentRun`
- `App\Agents\Runtime\Subagents\SubagentStats`
- `App\Agents\Runtime\Subagents\SubagentDependencyGraph`

Implementation steps:

1. Do not start with UI.
2. Add a backend-only orchestrator for delegated worker runs.
3. Map worker runs to OpenCompany tasks where they need persistence.
4. Track status, dependency state, last activity, current tool, model, token usage, error, and output references.
5. Add cancellation and idle timeout after basic execution works.

Validation:

- Unit tests for dependency graph ordering.
- Unit tests for concurrency limits.
- Feature test for delegated run completing and reporting result.
- Failure test for child run error propagation.

Exit criteria:

- OpenCompany can execute a small dependency graph of agent runs with inspectable status and failure handling.

## Cross-Cutting Rules For P0/P1

- Keep controller response shapes stable until the frontend is migrated.
- Prefer adapters around existing services before replacing behavior.
- Keep workspace scoping explicit in every runtime service.
- Do not move OpenCompany product ownership into shared packages.
- Keep provider/model metadata in the app-owned AI runtime unless a future non-Prism shared package is explicitly introduced.
- Move integration contract behavior toward `integration-core` or package providers where generic.
- Keep credentials and enablement in OpenCompany's database.
- Add tests before deleting old branches from controllers or jobs.

## Suggested PR Sequence

1. `IntegrationDirectory` extraction with no response-shape changes.
2. `IntegrationConfigResolver` and `IntegrationConnectionTester`.
3. `ProviderCatalog` and `ModelCatalog`.
4. `DynamicProviderResolver` delegation to the catalog/config resolver.
5. `AgentRunBuilder` wrapping current `OpenCompanyAgent`.
6. `AgentRespondJob` migration to `AgentRunResult`.
7. Permission evaluator pipeline.
8. Context pipeline wrapper and context plan output.
9. Runtime event emission.
10. MCP runtime normalization.
11. Backend-only subagent orchestrator.

## First Slice Recommendation

Start with the smallest P0 slice that reduces hardcoding without changing behavior:

1. Create `IntegrationDirectory`.
2. Move only `IntegrationController::index()` assembly into it.
3. Keep the JSON response identical.
4. Add tests for static, configurable package, non-configurable package, and MCP entries.
5. Then move `showConfig()` into `IntegrationConfigResolver`.

This gives a safe entry point and creates the service seam needed for the rest of the P0 work.

### Phase 1: Integration And Provider Hardening

Goal: remove hardcoded provider/integration behavior from controller paths.

Deliverables:

- `IntegrationDirectory`
- `IntegrationRuntime`
- `IntegrationConnectionTester`
- `ProviderCatalog`
- `ModelCatalog`
- Controller slim-down for integration list/config/test/model operations
- Focused tests for key no-key and keyed integration paths, including WorldBank-style public integrations

Why first:

- It addresses the current hardcoded integration controller problem.
- It reduces provider bugs such as wrong model names or stale relay assumptions.
- It gives agents, UI, tests, and future automation one runtime surface.

### Phase 2: Agent Runtime Builder

Goal: stop letting queue jobs and controllers assemble runtime behavior directly.

Deliverables:

- `AgentRunBuilder`
- `AgentRunOptions`
- `AgentRunResult`
- Runtime event stream
- Queue-job integration through the builder
- Live-test harness that can send a real message to an agent and inspect runtime events

### Phase 3: Permission And Context Pipeline

Goal: make execution policy and context shaping explicit.

Deliverables:

- `OpenCompanyPermissionEvaluator`
- `ToolExecutionPipeline`
- `AgentContextPipeline`
- Consolidated prompt budget, pruning, compaction, protected context, and retry behavior
- Debug/admin visibility into decisions

### Phase 4: Subagents, MCP, And External Runtime Access

Goal: add advanced runtime capabilities once the base execution path is clean.

Deliverables:

- Subagent execution stats and dependency/group orchestration
- MCP runtime upgrade
- Internal SDK-like agent runner
- ACP evaluation after internal APIs are stable

## Things To Avoid

Do not port the TUI. The event and observability model is useful; terminal UI code is not.

Do not port YAML credentials directly. OpenCompany should keep workspace-scoped database credentials and product permissions.

Do not copy filesystem/project-root permission assumptions blindly. OpenCompany needs equivalent boundaries for workspace files, docs, channels, tasks, integrations, external identities, and user-visible data.

Do not expose ACP or a public SDK before the internal runtime API is clean. That would freeze the wrong abstraction.

Do not make OpenCompany a terminal coding agent. KosmoKrator is the runtime reference, not the product direction.

## Bottom Line

KosmoKrator has the better agent runtime architecture. OpenCompany has the better company/product architecture.

The highest-value move is to extract the hardcoded OpenCompany integration and provider behavior into runtime services first, then introduce a central agent run builder, then standardize permissions, context, MCP, and subagent orchestration behind that runtime.

The first practical implementation target should be the integration/model hardcoding problem:

- `App\Services\Integrations\IntegrationDirectory`
- `App\Services\Integrations\IntegrationRuntime`
- `App\Services\Integrations\IntegrationConnectionTester`
- `App\Services\Ai\ProviderCatalog`
- `App\Services\Ai\ModelCatalog`

That gets OpenCompany the most immediate benefit from KosmoKrator without disturbing the product model.
