# Domain-Driven Design Architecture for OpenCompany

Date: 2026-05-19
Status: Proposed target architecture

## Summary

OpenCompany would benefit from a domain-driven, modular-monolith architecture, but not from a full "pure DDD" rewrite. The app already has real domain pressure: workspace isolation, human and agent identities, tool permissions, approval flows, integration accounts, MCP servers, memory, documents, chat, automations, and long-running agent work.

The useful move is to make those product boundaries explicit. Keep Laravel, Eloquent, queues, Inertia, and the existing package ecosystem. Move workflow rules out of broad controllers, jobs, and generic services into context-owned application actions and domain policies.

The target architecture should optimize for:

- Clear workspace and security boundaries.
- Small use-case classes instead of workflow-heavy controllers and jobs.
- Context-owned invariants around agent creation, permissions, approvals, memory scope, task lifecycle, and integration access.
- Package boundaries that keep provider, bridge, registry, and integration-runtime behavior out of app-local code unless the behavior is OpenCompany-specific.
- A migration path that can happen one context at a time without changing the database first.

## Current Shape

The current backend is mostly organized by framework layer:

```text
app/
  Agents/
  Ai/
  Http/Controllers/Api/
  Jobs/
  Models/
  Services/
```

This is workable for a smaller Laravel app, but OpenCompany now has enough product rules that technical folders hide ownership. Examples from the current code:

- `app/Http/Controllers/Api/AgentController.php` owns agent creation, brain validation, `User` creation, identity document tree creation, avatar generation, default DM channel creation, direct-message records, and general-channel membership.
- `app/Services/AgentPermissionService.php` owns tool, channel, folder, file-folder, integration, and inter-agent permission resolution.
- `app/Agents/Runtime/Permissions/OpenCompanyPermissionEvaluator.php` already models a domain policy well by composing workspace, integration, session-grant, tool, and approval checks in a security-sensitive order.
- `app/Jobs/AgentRespondJob.php` owns chat-triggered task creation/reuse, retry idempotency, agent status transitions, runtime execution, delivery, Telegram typing indicators, token metrics, and post-run memory flushing.
- `app/Agents/OpenCompanyAgent.php` owns identity prompt assembly, channel context, private memory context, provider resolution, and tool catalog exposure.
- `app/Services/AgentDocumentService.php` owns a large amount of agent identity, memory-tree, peer-card, deletion, and prompt-document behavior.
- `app/Http/Controllers/Api/IntegrationController.php` mixes configuration, connection testing, provider model refreshes, OAuth account linking, Telegram webhook setup, and model catalog surfaces.

The data model already shows natural domains:

- Workspace membership and tenant boundaries: `workspaces`, `workspace_members`, `workspace_invitations`, `users.workspace_id`.
- Agents and runtime identity: `users` with `type=agent`, `brain`, `docs_folder_id`, `behavior_mode`, `manager_id`, `awaiting_approval_id`, `awaiting_delegation_ids`.
- Permissions and approvals: `agent_permissions`, `approval_requests`, policy settings, session grants.
- Collaboration: `channels`, `channel_members`, `messages`, `direct_messages`, reactions, attachments, external channel fields.
- Work execution: `tasks`, `task_steps`, task sources, automation runs, delegation parent/child tasks.
- Lists/project tracking: `list_items`, list statuses, list comments, collaborators.
- Knowledge and memory: `documents`, document versions/comments/attachments, `document_chunks`, `embedding_cache`, `conversation_summaries`.
- Integrations and external runtime: `integration_settings`, `mcp_servers`, `prism_api_keys`, package catalogs.
- Workspace resources: `workspace_files`, `workspace_disks`, calendar, tables.

## Architecture Recommendation

Use a modular monolith with bounded contexts under `app/Domain`. Each context owns its application use cases, domain policies, infrastructure adapters, and read models/resources where useful. Keep cross-cutting Laravel infrastructure under `app/Support`, `app/Providers`, and `app/Http/Middleware`.

Recommended shape:

```text
app/
  Domain/
    Workspaces/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Identity/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Agents/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    AgentRuntime/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Authorization/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Collaboration/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Knowledge/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Work/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Automations/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    Integrations/
      Application/
      Domain/
      Infrastructure/
      Presentation/

    WorkspaceResources/
      Application/
      Domain/
      Infrastructure/
      Presentation/

  Http/
    Middleware/

  Models/        # transitional only, can stay while contexts are introduced
  Providers/
  Support/
```

Inside each context:

```text
Application/
  CreateAgent.php
  CreateAgentInput.php
  AgentDetailQuery.php

Domain/
  AgentProfile.php
  AgentBrain.php
  AgentBehaviorMode.php
  AgentLifecyclePolicy.php
  Events/AgentCreated.php

Infrastructure/
  EloquentAgentRepository.php
  EloquentAgentIdentityDocuments.php

Presentation/
  Controllers/AgentController.php
  Requests/StoreAgentRequest.php
  Resources/AgentResource.php
```

This does not require replacing Eloquent with abstract repositories everywhere. Use repositories only where they protect invariants or hide package/external details. Direct Eloquent is fine for simple read models and CRUD with no business rule.

## Bounded Contexts

### 1. Workspaces

Owns tenant identity, workspace membership, invitations, roles, active workspace resolution contracts, and workspace-scoped query guarantees.

Current code:

- `app/Models/Workspace.php`
- `app/Models/WorkspaceMember.php`
- `app/Models/WorkspaceInvitation.php`
- `app/Http/Middleware/ResolveWorkspace.php`
- `app/Http/Middleware/EnsureWorkspaceAdmin.php`
- `app/Models/Concerns/BelongsToWorkspace.php`

Target use cases:

- `CreateWorkspace`
- `InviteWorkspaceMember`
- `AcceptWorkspaceInvitation`
- `ChangeWorkspaceMemberRole`
- `ResolveActiveWorkspace`
- `AssertWorkspaceMembership`

Key invariants:

- Humans join workspaces through `workspace_members`.
- Agents belong directly to one workspace through `users.workspace_id`.
- Any model with `workspace_id` must be queried through explicit workspace scope or a context query object.
- Jobs must bind workspace context explicitly, never rely on stale container/session state.

### 2. Identity

Owns human users, agent users as identity records, external identities, profile data, presence, and identity-provider linkage. It should not own agent runtime behavior.

Current code:

- `app/Models/User.php`
- `app/Models/UserExternalIdentity.php`
- profile controllers and resources
- chat webhook external identity linking

Target use cases:

- `LinkExternalIdentity`
- `ResolveExternalIdentity`
- `UpdatePresence`
- `UpdateProfile`

Key invariants:

- A `User` can be a human or an agent, but runtime-specific rules belong to `Agents` or `AgentRuntime`.
- External identities are identity mappings, not permission grants by themselves.

### 3. Agents

Owns agent profile, brain selection, manager hierarchy, identity documents as agent-owned prompt material, agent avatar, sleeping/waiting state, and high-level lifecycle.

Current code:

- `app/Http/Controllers/Api/AgentController.php`
- `app/Services/AgentDocumentService.php`
- `app/Services/AgentAvatarService.php`
- `app/Agents/Providers/AgentBrainValidator.php`
- agent-related workspace tools

Target use cases:

- `CreateAgent`
- `UpdateAgentProfile`
- `DeleteAgent`
- `SetAgentManager`
- `SleepAgent`
- `WakeAgent`
- `UpdateAgentIdentityFile`
- `ReadAgentIdentityFiles`

Before:

```php
public function store(Request $request): JsonResponse
{
    $this->brainValidator->validate($validated['brain'], workspace()->id);

    $agent = User::create([...]);
    $folder = $this->agentDocumentService->createAgentDocumentStructure($agent, $identity);
    $agent->update(['docs_folder_id' => $folder->id]);
    $this->agentAvatarService->generate($agent);

    Channel::create([...]);
    DirectMessage::create([...]);

    return response()->json($agent->fresh(), 201);
}
```

After:

```php
public function store(StoreAgentRequest $request, CreateAgent $createAgent): JsonResponse
{
    $agent = $createAgent->handle(
        workspace: workspace(),
        creator: $request->user(),
        input: CreateAgentInput::fromRequest($request),
    );

    return response()->json(AgentResource::make($agent), 201);
}
```

```php
final class CreateAgent
{
    public function handle(Workspace $workspace, User $creator, CreateAgentInput $input): User
    {
        return DB::transaction(function () use ($workspace, $creator, $input) {
            $this->brainValidator->validate($input->brain, $workspace->id);

            $agent = $this->agents->create($workspace, $creator, $input);
            $this->events->dispatch(new AgentCreated($agent, $creator, $input->identity));

            return $agent;
        });
    }
}
```

Listeners can then create identity documents, generate avatar, and create the default DM thread. If these side effects must be atomic, keep them inside the transaction through an `AgentProvisioner` instead of queueing them.

Key invariants:

- Brain validation happens before any persistent side effect.
- Agent creation creates all minimum runtime dependencies or rolls back.
- Agent identity documents are system documents and cannot be deleted through ordinary document APIs.
- Manager hierarchy is an agent coordination rule, not a generic user relationship rule.

### 4. AgentRuntime

Owns one agent turn, prompt frame assembly, context planning, tool catalog exposure, subagent orchestration, runtime events, task execution, retry/idempotency, token metrics, and provider/model routing for an agent run.

Current code:

- `app/Agents/OpenCompanyAgent.php`
- `app/Agents/Runtime/*`
- `app/Jobs/AgentRespondJob.php`
- `app/Jobs/ExecuteAgentTaskJob.php`
- `app/Agents/Runtime/Subagents/*`
- `app/Agents/Conversations/ChannelConversationLoader.php`
- `app/Agents/Providers/DynamicProviderResolver.php`

Target use cases:

- `RunAgentTurn`
- `RespondToChatMessage`
- `ExecuteAssignedTask`
- `ResumeAgentTask`
- `PlanAgentContext`
- `RecordRuntimeEvents`
- `DeliverAgentResponse`
- `FlushAgentMemoryAfterRun`

Before:

```php
AgentRespondJob
  finds or creates task
  checks retry delivery state
  updates agent status
  starts typing indicators
  builds AgentRun
  captures context
  calls model
  creates message
  stores metrics
  updates task
  flushes memory
```

After:

```php
final class RespondToChatMessage
{
    public function handle(ChatMessageId $messageId, AgentId $agentId, ChannelId $channelId): AgentResponseDelivery
    {
        $conversation = $this->chat->loadIncomingMessage($messageId, $channelId);
        $task = $this->tasks->findOrCreateChatTask($conversation, $agentId);

        if ($this->delivery->alreadyDelivered($task, $conversation)) {
            return $this->delivery->repairBookkeeping($task);
        }

        return $this->runner->runAndDeliver($task, $conversation);
    }
}
```

The queue job becomes a thin adapter:

```php
public function handle(RespondToChatMessage $respond): void
{
    $this->setWorkspaceContext($this->agent->workspace_id);
    $respond->handle($this->userMessage->id, $this->agent->id, $this->channelId);
}
```

Key invariants:

- Every live model call goes through `AgentRun` or its successor.
- Prompt-bag state is bound only for one run and always cleaned up.
- Once a response is delivered, retries repair bookkeeping but do not deliver a duplicate answer.
- Runtime must never widen workspace, integration, memory, or channel access.
- Subagent orchestration uses task parent/child links as durable execution evidence.

### 5. Authorization

Owns human authorization, agent permissions, runtime permission evaluation, approval requirements, session grants, resource access decisions, and policy settings. This should be the most explicit context because mistakes here become tenant leaks or unsafe tool execution.

Current code:

- `app/Services/AgentPermissionService.php`
- `app/Agents/Runtime/Permissions/*`
- `app/Models/AgentPermission.php`
- `app/Models/ApprovalRequest.php`
- `app/Services/ApprovalExecutionService.php`
- `app/Policies/AgentPolicy.php`
- `resources/js/Components/settings/PoliciesSettings.vue`
- `AppSetting::defaults()['policies']`

Target use cases:

- `EvaluateToolAccess`
- `EvaluateIntegrationAccess`
- `EvaluateChannelAccess`
- `EvaluateDocumentAccess`
- `RequestApproval`
- `ApproveRequest`
- `RejectRequest`
- `GrantSessionCapability`
- `UpdateAgentPermissionSet`
- `ResolveEffectivePolicy`

Target domain policies:

```text
Authorization/
  Domain/
    PermissionDecision.php
    ToolAccessPolicy.php
    IntegrationAccessPolicy.php
    WorkspaceBoundaryPolicy.php
    ApprovalPolicy.php
    SessionGrantPolicy.php
    AgentContactPolicy.php
    MemoryScopePolicy.php
```

Key invariants:

- Workspace boundary checks run before session grants.
- Integration enablement runs before session grants.
- Explicit denies win over approval and behavior-mode defaults.
- Approval can authorize an action inside an existing boundary; it cannot create a new workspace or integration boundary.
- Policy UI/config must have a single runtime enforcement path before it is presented as active governance.

### 6. Collaboration

Owns channels, messages, DMs, reactions, pins, typing, external chat adapters at the app edge, and synchronization of external messages into workspace conversations.

Current code:

- `app/Models/Channel.php`
- `app/Models/Message.php`
- `app/Models/DirectMessage.php`
- `app/Http/Controllers/Api/MessageController.php`
- `app/Http/Controllers/Api/ChannelController.php`
- `app/Http/Controllers/Api/DmController.php`
- `app/Services/Chat/*`
- `app/Services/TelegramService.php`
- `app/Http/Controllers/Api/TelegramWebhookController.php`
- `tmp/chatogrator`

Target use cases:

- `CreateChannel`
- `AddChannelMember`
- `SendMessage`
- `EditMessage`
- `PinMessage`
- `CreateDirectMessageThread`
- `IngestExternalMessage`
- `SyncMessageToExternalAdapter`

Key invariants:

- Channel membership and workspace membership are separate checks.
- External adapter identity mapping does not bypass workspace membership or agent permissions.
- Delivery to external systems is an integration side effect and should be idempotent.

### 7. Knowledge

Owns documents, document folders, comments, versions, attachments, chunks, indexing, vector/full-text search, memory files, memory flushing, conversation summaries, and prompt memory retrieval.

Current code:

- `app/Models/Document.php`
- `app/Models/DocumentChunk.php`
- `app/Models/ConversationSummary.php`
- `app/Models/EmbeddingCache.php`
- `app/Services/Memory/*`
- `app/Services/AgentDocumentService.php`
- `app/Observers/DocumentObserver.php`
- document tools under `app/Agents/Tools/Docs`
- memory tools under `app/Agents/Tools/Memory`

Target use cases:

- `CreateDocument`
- `UpdateDocument`
- `DeleteDocument`
- `CreateAgentIdentityTree`
- `ReadAgentPromptDocuments`
- `IndexDocument`
- `SearchWorkspaceKnowledge`
- `SaveAgentMemory`
- `RecallAgentMemory`
- `CompactConversation`
- `FlushMemory`

Key invariants:

- System documents cannot be deleted by ordinary document workflows.
- Embedding index replacement is all-or-nothing per document.
- Search results are always workspace-scoped and, where relevant, agent-scoped.
- Memory tools are constrained by channel type and policy.
- Agent identity, memory, and user-authored knowledge can share storage tables, but they should not share application services blindly.

### 8. Work

Owns agent-executable tasks, task steps, task lifecycle, delegation trees, and the distinction between agent cases and kanban/list items.

Current code:

- `app/Models/Task.php`
- `app/Models/TaskStep.php`
- `app/Models/ListItem.php`
- `app/Http/Controllers/Api/TaskController.php`
- `app/Http/Controllers/Api/ListItemController.php`
- list tools and task tools

Target use cases:

- `CreateTask`
- `StartTask`
- `PauseTask`
- `ResumeTask`
- `CompleteTask`
- `FailTask`
- `CancelTaskTree`
- `AddTaskStep`
- `ConvertListItemToTask`
- `CreateListItem`
- `MoveListItem`

Key invariants:

- `Task` is execution history for agents and cases.
- `ListItem` is planning/kanban product surface.
- Cancelling a parent task only cascades to non-terminal subtasks.
- Task source determines provenance and should be preserved through retries and delegation.

### 9. Automations

Owns schedules, automation definitions, run history as tasks, prompt vs script execution, next-run calculation, failure counters, and dedicated automation channels.

Current code:

- `app/Models/Automation.php`
- `app/Http/Controllers/Api/AutomationController.php`
- `app/Jobs/RunAutomationJob.php`
- `app/Jobs/RunScriptAutomationJob.php`
- `app/Console/Commands/RunDueAutomations.php`

Target use cases:

- `CreateAutomation`
- `UpdateAutomationSchedule`
- `ActivateAutomation`
- `DeactivateAutomation`
- `RunAutomationNow`
- `RunDueAutomations`
- `RecordAutomationSuccess`
- `RecordAutomationFailure`

Key invariants:

- Cron expression and timezone are validated before persistence.
- Active automations compute `next_run_at`.
- Repeated failures disable an automation according to a single policy.
- Prompt execution and script execution are separate handlers behind one automation run contract.

### 10. Integrations

Owns workspace integration settings, account aliases, masked-secret presentation, credential resolution, connection testing, OAuth account linking, package catalog display, and app-local execution policy. It does not own package provider schemas or package tool behavior.

Current code:

- `app/Models/IntegrationSetting.php`
- `app/Http/Controllers/Api/IntegrationController.php`
- `app/Services/Integrations/*`
- `app/Services/IntegrationSettingCredentialResolver.php`
- `app/Agents/Tools/ToolRegistry.php`
- path packages in `../integrations/core`, `../integrations/packages/*`, `../integrations/catalog`

Target use cases:

- `ListIntegrations`
- `ShowIntegrationConfig`
- `UpdateIntegrationConfig`
- `DisconnectIntegrationAccount`
- `ResolveIntegrationAccount`
- `TestIntegrationConnection`
- `CallIntegrationTool`
- `RefreshProviderModels`

Key invariants:

- Raw secrets never leave infrastructure/model code.
- Shared credential groups come from package capability metadata.
- Workspace-level enablement is checked before agent-level permission.
- Account alias resolution is explicit and testable.
- Generic package behavior belongs in `../integrations`, not app-local service branches.

### 11. MCP and Tool Gateway

MCP can be a subcontext of Integrations or a separate context if it keeps growing. It owns MCP server registration, schema translation, permission evaluation, result normalization, and tool-provider exposure.

Current code:

- `app/Models/McpServer.php`
- `app/Http/Controllers/Api/McpServerController.php`
- `app/Services/Mcp/*`
- MCP tools under workspace tools

Target use cases:

- `RegisterMcpServer`
- `UpdateMcpServer`
- `DiscoverMcpTools`
- `CallMcpTool`
- `NormalizeMcpResult`
- `EvaluateMcpToolAccess`

Key invariants:

- MCP server slugs are workspace-scoped.
- MCP tool calls are authorized through the same runtime decision model as package tools.
- HTTP MCP and future stdio/command MCP should be separate infrastructure adapters behind one application contract.

### 12. WorkspaceResources

Owns files, disks, calendar, and tables as workspace resources that agents and humans can manipulate.

Current code:

- `app/Models/WorkspaceFile.php`
- `app/Models/WorkspaceDisk.php`
- `app/Models/CalendarEvent.php`
- `app/Models/DataTable.php`
- `app/Services/FileSystemService.php`
- calendar, file, and table controllers/tools

Target use cases:

- `CreateWorkspaceFile`
- `ReadWorkspaceFile`
- `WriteWorkspaceFile`
- `ConfigureWorkspaceDisk`
- `CreateCalendarEvent`
- `ImportCalendarFeed`
- `CreateDataTable`
- `UpdateTableRows`

Key invariants:

- File metadata authorization happens before reading/writing bytes.
- Physical disk details stay behind infrastructure adapters.
- Calendar imports and table mutations should preserve workspace scope and actor identity.

## Aggregate and Use-Case Map

Use aggregates where there is a real consistency boundary. Do not create aggregate wrappers for every table.

| Context | Aggregate/root | Persistence today | Main commands |
|---------|----------------|-------------------|---------------|
| Workspaces | `Workspace` | `workspaces`, `workspace_members`, `workspace_invitations` | create workspace, invite member, accept invitation, change role |
| Identity | `Identity` / `ExternalIdentity` | `users`, `user_external_identities` | link external identity, update profile, update presence |
| Agents | `AgentProfile` | `users` with `type=agent`, agent identity documents | create agent, update profile, delete agent, update identity file |
| AgentRuntime | `AgentRun` / `AgentTaskExecution` | `tasks`, `task_steps`, runtime events in task context | run turn, respond to message, execute assigned task, resume task |
| Authorization | `PermissionSet` / `ApprovalRequest` | `agent_permissions`, `approval_requests`, policy app settings | evaluate access, update permissions, request approval, approve/reject |
| Collaboration | `Channel` / `Conversation` | `channels`, `channel_members`, `messages`, `direct_messages` | send message, create channel, create DM, ingest external message |
| Knowledge | `DocumentTree` / `MemoryIndex` | `documents`, `document_chunks`, `embedding_cache`, `conversation_summaries` | create/update document, index document, search knowledge, save/recall memory |
| Work | `Task` / `ListProject` | `tasks`, `task_steps`, `list_items`, list related tables | start/complete task, cancel tree, create/move list item, convert list item |
| Automations | `AutomationSchedule` | `automations`, run history through `tasks` | create automation, update schedule, run now, record success/failure |
| Integrations | `IntegrationAccount` | `integration_settings` | update config, resolve account, test connection, call tool |
| MCP | `McpServerRegistration` | `mcp_servers` | register server, discover tools, call MCP tool |
| WorkspaceResources | `WorkspaceFileTree`, `WorkspaceDisk`, `Calendar`, `DataTable` | resource-specific workspace tables | read/write files, configure disks, manage events, manage table rows |

The most important aggregate boundaries are `Workspace`, `AgentProfile`, `PermissionSet`, `ApprovalRequest`, `AgentRun`, `Task`, `DocumentTree`, `IntegrationAccount`, and `AutomationSchedule`. These are where transaction boundaries, authorization checks, and events should be explicit.

## Package Ownership Boundaries

OpenCompany should remain the product/application boundary. Durable fixes should go to the owning package when behavior is generic.

```text
OpenCompany app owns:
  workspace membership
  agent profile and runtime orchestration
  OpenCompany-specific authorization and approval policy
  workspace integration settings and account selection
  UI/API presentation
  app-specific automation and task workflows

../integrations owns:
  provider package schemas
  tool definitions
  credential field metadata
  shared credential capability metadata
  generated catalog/provider metadata

tmp/prism-relay owns:
  provider transport selection
  relay/provider registry metadata
  model/provider driver behavior

tmp/prism-codex owns:
  Codex auth/runtime package behavior

tmp/chatogrator owns:
  reusable chat adapter abstractions
  platform message normalization where not OpenCompany-specific
```

App-local integration code should be anti-corruption/adaptation code, not a second implementation of package behavior.

Do not extract OpenCompany domain contexts into new packages as a roadmap goal. The default destination for OpenCompany product behavior is the app-local modular monolith. Move code out of the app only when ownership is already clear:

- Provider package schemas, tool definitions, credential fields, shared credential metadata, and generated catalog behavior belong in `../integrations`.
- Reusable chat adapter abstractions and platform message normalization that are not OpenCompany-specific belong in `tmp/chatogrator`.
- Provider transport selection, provider registry metadata, model routing metadata, and relay driver behavior belong in `tmp/prism-relay`.
- Everything else should stay in `app/Domain` until there is concrete evidence that another existing package owns it.

## Suggested Dependency Rules

Use these rules to keep the architecture maintainable:

```text
Presentation -> Application -> Domain
Application -> Infrastructure interfaces
Infrastructure -> Domain/Application contracts
Domain -> no Laravel facades, no HTTP, no queues
Context A -> Context B only through public application contracts or events
```

Allowed:

- `Agents\Application\CreateAgent` calls `Knowledge\Application\CreateAgentIdentityTree` through a contract.
- `AgentRuntime\Application\RunAgentTurn` calls `Authorization\Application\EvaluateToolAccess`.
- `Integrations\Application\CallIntegrationTool` calls package tools through `IntegrationToolRuntime`.
- `Collaboration\Application\IngestExternalMessage` dispatches `AgentRuntime\Application\RespondToChatMessage`.

Avoid:

- Controllers creating several unrelated models directly.
- Jobs containing full use cases.
- Tool classes duplicating controller logic.
- Domain code reading `request()`, `auth()`, session, or route parameters.
- App code hardcoding provider IDs, model support, integration schemas, or credential group lists.

## Events

Use domain events for important transitions, but keep them synchronous when consistency matters.

Recommended events:

```text
AgentCreated
AgentDeleted
AgentIdentityUpdated
AgentTurnStarted
AgentTurnCompleted
AgentTurnFailed
TaskStarted
TaskCompleted
TaskFailed
ApprovalRequested
ApprovalApproved
ApprovalRejected
IntegrationConfigured
IntegrationDisconnected
McpServerRegistered
DocumentUpdated
DocumentIndexed
AutomationRunStarted
AutomationRunFailed
ExternalMessageIngested
```

Rules:

- Events describe facts that happened, not commands to maybe do something.
- If the user-visible operation is incomplete without a side effect, keep that side effect in the application transaction or explicitly compensate on failure.
- Queue external, retryable, or slow side effects. Keep workspace/security checks synchronous.

## Read Models and API Resources

Do not force every UI query through aggregates. The frontend needs dense screens: agent detail, task timelines, integration cards, document trees, file trees, dashboard stats, workload views.

Use context-owned read models/query objects:

```text
Agents\Application\Queries\AgentDetailQuery
Work\Application\Queries\TaskTimelineQuery
Integrations\Application\Queries\IntegrationDirectoryQuery
Knowledge\Application\Queries\DocumentTreeQuery
Collaboration\Application\Queries\ChannelTimelineQuery
```

Controllers should call query objects and return resources. This is simpler than turning every read into an aggregate load.

## Testing Strategy

Restructure tests around domain behavior:

```text
tests/
  Feature/
    Domain/
      Agents/
      AgentRuntime/
      Authorization/
      Integrations/
      Knowledge/
      Collaboration/
      Automations/
      Work/
```

High-value test suites:

- Agent creation provisions identity docs, avatar, DM channel, and membership atomically.
- Runtime permission order cannot be bypassed by session grants.
- Integration workspace enablement blocks disabled integrations before tool approval.
- Agent response retries never deliver duplicate messages.
- System documents are protected from ordinary delete paths.
- Memory and document search cannot cross workspace or agent scopes.
- Automation failure policy disables after the configured threshold.
- MCP server/tool registration is workspace-scoped.

## Migration Plan

### Phase 0: Architecture guardrails

- Add this document to `docs/INDEX.md`.
- Add a short "new code goes into contexts" rule to `AGENTS.md` and `CLAUDE.md` after the first context lands.
- Keep `app/Models` during migration. Moving models is optional and should happen only when imports settle.

### Phase 1: Authorization first

Extract `AgentPermissionService` and runtime permission checks into:

```text
app/Domain/Authorization/
  Application/EvaluateToolAccess.php
  Application/UpdateAgentPermissions.php
  Domain/PermissionDecision.php
  Domain/Policies/*
  Infrastructure/EloquentAgentPermissionStore.php
```

Why first: this is security-sensitive, already policy-shaped, and currently touches agents, integrations, documents, files, channels, and approvals.

### Phase 2: Agent lifecycle

Extract agent create/update/delete and identity-file workflows:

```text
app/Domain/Agents/Application/CreateAgent.php
app/Domain/Agents/Application/DeleteAgent.php
app/Domain/Knowledge/Application/CreateAgentIdentityTree.php
app/Domain/Collaboration/Application/CreateDirectMessageThread.php
```

Keep existing DB tables. The first win is moving side-effect order and transaction boundaries out of `AgentController`.

### Phase 3: Runtime orchestration

Extract chat response and task execution workflows from jobs:

```text
app/Domain/AgentRuntime/Application/RespondToChatMessage.php
app/Domain/AgentRuntime/Application/ExecuteAssignedTask.php
app/Domain/AgentRuntime/Application/RunAgentTurn.php
```

Jobs become transport adapters only.

### Phase 4: Knowledge split

Split `AgentDocumentService` into smaller context services:

```text
Knowledge/Application/CreateAgentIdentityTree.php
Knowledge/Application/ReadAgentPromptDocuments.php
Knowledge/Application/UpdateAgentIdentityFile.php
Knowledge/Application/ManagePeerMemoryCards.php
Knowledge/Application/DeleteAgentIdentityTree.php
Knowledge/Application/SearchKnowledge.php
```

Keep `Document` as shared persistence, but stop routing all agent memory/document behavior through one large service.

### Phase 5: Integrations and MCP

Move app-owned integration orchestration into `Domain/Integrations`, and keep generic provider metadata in packages.

Controller before:

```php
IntegrationController::updateConfig()
  resolves account
  stores config
  conditionally fetches provider models
```

Application after:

```php
UpdateIntegrationConfig
  validates app-owned request
  resolves account alias
  persists masked-secret-safe config
  emits IntegrationConfigured

RefreshProviderModelsOnFirstConfig
  listens when provider supports model discovery
```

### Phase 6: Work and automations

Move `TaskController`, `AutomationController`, `RunAutomationJob`, and script/prompt run logic into use cases. Keep simple list CRUD as lower priority unless product rules increase.

### Phase 7: Package boundary cleanup only

Do not create new packages as part of the DDD migration. Keep contexts in `app/Domain` and clean up only code that clearly belongs to an existing sibling package:

- Move generic integration metadata/tool behavior to `../integrations`.
- Move reusable chat adapter behavior to `tmp/chatogrator`.
- Move provider transport or relay registry behavior to `tmp/prism-relay`.

Everything else stays app-local. In particular, do not extract workspace governance, product-specific approvals, OpenCompany-specific agent identity, agent lifecycle, or app runtime orchestration into generic packages unless a concrete existing package already owns that concern.

## Target End State

The end state is not a perfectly pure DDD codebase. It is a Laravel modular monolith where the highest-risk workflows are obvious:

- Agent creation is one application action.
- Agent runs are one application action.
- Tool authorization is one policy pipeline.
- Integration calls are one gateway with package-owned metadata.
- Memory/search is one knowledge context with explicit scope guards.
- Controllers and jobs are thin adapters.
- Eloquent remains useful but no longer acts as the only place where domain rules live.

That structure should scale better as OpenCompany grows toward managed pilots, self-hosting, more integrations, stronger governance, and more complex agent runtime behavior.
