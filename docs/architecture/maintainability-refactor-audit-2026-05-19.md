# Maintainability Refactor Audit - 2026-05-19

## Scope

This audit reviewed the current OpenCompany app structure after the first DDD modular-monolith pass. It focused on maintainability, readability, refactor boundaries, and code that is likely to become harder to change as the product grows.

The goal is not pure DDD. The useful target for this Laravel app is a pragmatic modular monolith:

- HTTP controllers validate requests and shape responses.
- Jobs bind queue/workspace context and delegate immediately.
- Domain application services own use cases and invariants.
- Domain value objects own small risky concepts.
- Package-owned integration/runtime behavior stays in `../integrations`, `tmp/prism-relay`, or other package sources instead of being patched into `app/`.

## Current Structure

Backend code is currently organized around these main areas:

- `app/Domain`: new application/domain layer for agent runtime, agents, authorization, automations, calendar, collaboration, integrations, knowledge, and work.
- `app/Agents`: agent runtime, tool providers, direct model-visible tools, and permission checks.
- `app/Ai`: AI provider routing and gateway adapters.
- `app/Services`: older app services for documents, files, chat bridges, integrations, MCP, memory, Lua, Telegram, avatars, and approval execution.
- `app/Http/Controllers/Api`: API transport layer. Some controllers are now thin, while older feature controllers still own application logic.
- `app/Jobs`: queue adapters. Several runtime jobs are now thin, but older jobs still own operational logic.
- `resources/js`: Inertia/Vue UI, shared components, composables, pages, and local feature components.

## Implemented During This Audit

### Calendar Controller Split

`CalendarEventController` was the cleanest immediate refactor candidate because it mixed transport, persistence, recurrence expansion, ICS export, ICS import parsing, and remote URL import.

Implemented:

- Added `app/Domain/Calendar/Application/ManageCalendarEvents.php`.
- Moved event list/show/create/update/delete use cases into the domain service.
- Moved recurrence expansion into the domain service.
- Moved ICS export/import parsing and escaping into the domain service.
- Kept HTTP validation, file upload handling, URL fetching, and response headers in the controller.
- Added focused domain tests for create/update/export, recurrence expansion, workspace scoping, and ICS import.

Before:

```php
class CalendarEventController extends Controller
{
    public function index(Request $request) { /* query + recurrence expansion */ }
    public function export(Request $request) { /* ICS rendering */ }
    private function importIcsContent(string $content) { /* parser + writes */ }
}
```

After:

```php
class CalendarEventController extends Controller
{
    public function index(Request $request, ManageCalendarEvents $calendar)
    {
        return $calendar->list(workspace(), ...);
    }
}
```

## Highest-Value Refactor Opportunities

### 1. Split `RespondToChatMessage` Again

`app/Domain/AgentRuntime/Application/RespondToChatMessage.php` is still about 560 lines. The previous pass extracted task resolution, duplicate-delivery checks, and message delivery, but it still owns too much runtime detail.

Recommended next splits:

- `CompleteChatResponseTask`: token metrics, token breakdown, task completion, and context cleanup.
- `CompleteDelegationCallback`: parent task lookup, agent-to-agent result posting, pending delegation cleanup, parent task resume.
- `SendAgentErrorMessage`: final-attempt user error delivery.
- `TelegramTypingIndicator`: external-channel typing side effect.

Why: this is the most fragile runtime path. Smaller services would make retry behavior and post-delivery non-retry guarantees easier to test.

### 2. Move Message Sending Into a Chat Domain Service

`MessageController` still owns message creation, attachment linking, channel timestamps, broadcasting, DM auto-triggering, mention detection, manual compaction, reactions, threads, pinning, and attachment uploads.

Recommended services:

- `SendChannelMessage`
- `TriggerAgentResponsesForMessage`
- `ManageMessageReactions`
- `ManageMessageThreads`
- `CompactChannelConversation`

Why: message creation is a core product workflow and currently spreads queue dispatch, broadcast, and workspace assumptions through the controller.

### 3. Extract Settings Danger Actions

`SettingController` is a mixed admin operations surface. It handles app settings, queue management, agent status resets, memory resets, MCP refresh, Telegram diagnostics, document reindexing, embedding resets, health checks, and log tailing.

Recommended services:

- `UpdateWorkspaceSettings`
- `RunDangerZoneAction`
- `GatherWorkspaceDiagnostics`
- `RefreshWorkspaceMcpTools`
- `ReindexWorkspaceDocuments`

Why: this controller is hard to review because benign reads and destructive operations live together. Extracting actions also makes authorization and audit logging easier to add.

### 4. Preserve Explicit Workspace Inputs In New Domain Services

Several new domain services still call `workspace()` internally:

- `ManageAutomations`
- `ManageIntegrationSettings`
- `ManageMcpServers`
- `ManageTasks`

Recommended direction: pass `Workspace $workspace` and actor/user IDs explicitly for all public use cases.

Why: explicit workspace input makes queue usage, tests, and package boundaries safer. It also prevents hidden coupling to `ResolveWorkspace` middleware.

### 5. Introduce Read Models For Heavy API Responses

Some controllers return Eloquent models with `toArray()` compatibility overrides and relationship-loaded models. This is convenient but couples API shape to persistence shape.

Recommended first targets:

- Agent detail
- Task detail/run history
- Automation run history
- Calendar event payloads
- Integration directory/config payloads

Why: read models make UI payloads stable while persistence can evolve.

### 6. Normalize Tool Provider Metadata Once

`ToolRegistry` is still one of the largest files and owns provider merge, tool normalization, permission wrapping, catalog building, direct/Lua exposure policy, and cache state.

Recommended splits:

- `EffectiveToolCatalog`
- `ToolMetadataNormalizer`
- `ToolPermissionWrapper`
- `LuaToolCatalogAdapter`

Why: package integration growth will make this class harder to reason about. Splits should stay app-local unless generic metadata behavior belongs in `../integrations/core`.

### 7. Extract Document Tree Layout Objects

`AgentDocumentService` owns identity tree creation, legacy lookup, memory index updates, topic listing, and recursive delete behavior. It is valuable but dense.

Recommended splits:

- `AgentDocumentTree`
- `AgentIdentityFiles`
- `AgentMemoryIndex`

Why: identity and memory are core agent invariants. Naming the tree layout explicitly reduces accidental breakage.

### 8. Separate External Chat Bridges By Provider Boundary

`TelegramWebhookController`, `TelegramService`, and `ChatBridge` contain provider-specific runtime behavior. Telegram is app-local today, but the direction should be clearer:

- generic bridge contracts in app/domain;
- Telegram specifics in a Telegram adapter;
- future Discord/Slack behavior should not copy controller logic.

Why: external channels are likely to multiply, and provider fan-out is a classic duplication risk.

## Missed Opportunities From The Previous Pass

- Domain services were introduced, but not all of them received explicit workspace/actor inputs.
- Some application services still dispatch jobs directly. This is acceptable pragmatically, but a dispatch port would make use cases easier to test.
- Runtime token bookkeeping stayed inside chat response orchestration.
- API resources/read models remain inconsistent: some endpoints return Eloquent models directly, others return explicit resources or arrays.
- Tool and integration metadata still have legacy static entries mixed with package metadata.
- Browser/UI-facing payload compatibility is still partly handled by model `toArray()` overrides.

## Suggested Refactor Sequence

1. Finish explicit workspace/actor signatures for the new domain services.
2. Split `RespondToChatMessage` post-delivery and delegation behavior.
3. Move message sending and agent-triggering out of `MessageController`.
4. Extract setting danger actions and diagnostics.
5. Split `ToolRegistry` along catalog/normalization/wrapping responsibilities.
6. Introduce read models for endpoints that currently leak model persistence shape.
7. Revisit provider/package boundaries only when behavior is generic enough for `../integrations` or `tmp/prism-relay`.

## Validation Expectations

Each refactor should keep tests focused:

- domain service tests for use-case behavior and workspace boundaries;
- controller tests only for validation/status/response shape;
- job tests only for context binding and delegation to application services;
- package-boundary tests in the owning package when behavior moves out of OpenCompany.
