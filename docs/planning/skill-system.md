# Skill System

Status: Planning. Last code audit: 2026-05-25. The files listed in this document describe a proposed skill system and are not current tracked implementation unless they exist in the worktree.

Current code baseline:

- No `Skill` model, `skills` table, skill API routes, skill tools, or `$skill` parser exist in the current worktree.
- Chat response queueing starts in `app/Jobs/AgentRespondJob.php`, but that job is now only a transport adapter. Durable response orchestration lives in `app/Domain/AgentRuntime/Application/RespondToChatMessage.php`.
- Agent construction flows through `app/Agents/Runtime/AgentRunBuilder.php` and `app/Agents/Runtime/AgentRunOptions.php` before `app/Agents/OpenCompanyAgent.php` builds prompt sections.
- Prompt automations currently execute in `app/Domain/Automations/Application/ExecutePromptAutomation.php` and call `OpenCompanyAgent::for()` directly. Script automations execute in `ExecuteScriptAutomation` through `LuaSandboxService` and `LuaBridge`.
- Telegram and other external chat webhooks normalize provider messages into normal OpenCompany messages before dispatching agent responses, so server-side `$skill` parsing in the runtime path can cover Telegram without Telegram-specific skill syntax.
- VFS currently mounts `/agents`, `/docs`, `/files`, `/tasks`, `/lists`, `/channels`, `/tables`, `/tools`, `/automations`, `/approvals`, and `/workspace`. A skill implementation should add `/skills` as a first-class VFS mount, not hide skills only behind CRUD endpoints.
- Tool visibility is provider-based through `app/Agents/Tools/ToolRegistry.php` and built-in providers registered in `app/Providers/AppServiceProvider.php`; there is no longer an `APP_GROUPS` constant to append to.
- Agent permission decisions are layered through `app/Agents/Runtime/Permissions/OpenCompanyPermissionEvaluator.php`, with `app/Services/AgentPermissionService.php` acting as a compatibility facade over `app/Domain/Authorization/Application/AgentPermissionResolver.php`.

## Overview

Skills are **reusable workspace capabilities** that teach agents or automation runs how to perform specific tasks. A skill can be plain behavioral instructions, a Luau snippet/package with a description, or a hybrid package where prompt instructions explain when and how to run the included Lua.

| Concept | What it is | Example |
|---------|-----------|---------|
| **Tool** | Executable PHP function the LLM calls | `web_search`, `vfs_read`, `contact_agent` |
| **Skill** | Prompt and/or Luau package invoked by agents, automations, and channels | `$code-review`, `$standup-report`, `$daily-kpi-sync` |
| **Command/UI action** | Client-side chat utility or button action | compact, status |

Prompt skills augment an agent's behavior by injecting structured instructions into the system prompt when invoked. Lua skills run through the same Luau sandbox and `LuaBridge` used by script automations and `lua_exec`. Hybrid skills can do both: inject the "how to think" instructions and expose named snippets that the agent or automation can run when appropriate.

### Why skills?

- **Reusability** — write once, invoke from any channel or entry point
- **Specialization** — give agents expert-level instructions for specific tasks without bloating their identity files
- **User-created** — workspace admins and agents can create skills, not just developers
- **Composable** — skills leverage existing tools; they don't replace them
- **Channel-agnostic** — the same skill should be callable from web chat, Telegram/external chat, automations, agent delegation, and VFS-backed scripts
- **Scriptable** — repeatable operational recipes can live as Luau snippets with descriptions instead of being buried in chat history

---

## Invocation: `$skill-name`

Skills use the `$` prefix, distinct from `@` mentions and any future `/` chat commands:

```
$create-skill
$weekly-report
$translate This paragraph needs to be in Dutch
$research $weekly-report Summarize competitor changes since Monday
```

**`$` is always agent-side.** The message is sent to the agent with the skill's instructions injected into its system prompt. The agent processes the request and responds normally.

Multiple skills can be activated in the same request. Every surface that supports skills must accept the same ordered set of skills, whether the request comes from `$research $weekly-report`, an automation field, a tool call, Lua, or a VFS-backed script. Order matters because later skills may specialize or constrain earlier ones.

**Client-side actions stay client-side.** The current assistant composer exposes compact/status as buttons, not as a slash-command popup. If slash commands are reintroduced, they should remain frontend/API actions with no agent involvement.

### Invocation from any entry point

Planned server-side detection should happen in a shared skill invocation service, not directly in one channel adapter. `AgentRespondJob` only binds workspace context and delegates to `RespondToChatMessage`, and prompt automations currently bypass that path by calling `OpenCompanyAgent::for()` inside `ExecutePromptAutomation`. If parsing lives only in chat code, automations and Telegram will drift.

Since skill detection is server-side and shared, `$skills` should work identically from:
- Web chat composer
- Telegram and other external chat webhooks
- DMs and group-channel agent mentions
- Agent delegation
- Prompt automations
- Script automations and Lua via an `app.skills.*` namespace
- VFS paths such as `/skills/{slug}/SKILL.md` and `/skills/{slug}/snippets/*.lua`

No per-channel runtime special handling is needed beyond each surface passing its source metadata to the shared invocation service. Frontend autocomplete and Telegram command suggestions are optional convenience UI; correctness belongs on the server.

Skills may also reference other skills. A parent skill can declare dependencies/composed skills in metadata or frontmatter; the invocation service expands those references into the same active skill set, applies permission/surface checks to every referenced skill, and rejects cycles before building a prompt or executing Lua.

---

## Data Model

### `skills` table

```
id              uuid, PK
workspace_id    uuid, FK → workspaces
slug            string          -- invocation handle: "code-review"
name            string          -- display name: "Code Review"
description     text            -- one-line for catalog/autocomplete
content         text            -- prompt instructions / SKILL.md body (markdown)
execution_mode  string          -- prompt, lua, hybrid
lua_code        text|null       -- optional executable Luau entrypoint
lua_snippets    json|null       -- named reusable Luau snippets with descriptions
status          string          -- draft, published, deprecated, archived
published_version_id uuid|null  -- currently published immutable version
latest_version_id uuid|null     -- latest draft or published version
provenance_type string          -- builtin, human_created, agent_created, imported, marketplace, enterprise_managed
provenance      json|null       -- origin, author, import source, approval refs, package info
icon            string          -- iconify icon, default 'ph:lightning'
category        string          -- general, development, writing, data, communication
is_builtin      boolean         -- shipped with the app (not deletable)
is_active       boolean         -- soft disable
integration_id  string|null     -- gate behind integration: 'telegram', 'clickup', etc.
arguments       json|null       -- parameter definitions (see Arguments section)
allowed_tools   json|null       -- tool slugs this skill should use (advisory hint)
referenced_skills json|null     -- ordered skill slugs this skill composes/includes
surface_policy  json|null       -- optional allow/deny defaults per surface
automation_safe boolean         -- whether automations may invoke directly
enterprise_policy json|null     -- future org/workspace policy constraints
created_by      uuid|null       -- FK → users
created_at      timestamp
updated_at      timestamp

UNIQUE(workspace_id, slug)
```

### `Skill` model

```php
class Skill extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'workspace_id', 'slug', 'name', 'description',
        'content', 'execution_mode', 'lua_code', 'lua_snippets',
        'status', 'published_version_id', 'latest_version_id',
        'provenance_type', 'provenance', 'icon', 'category', 'is_builtin', 'is_active',
        'integration_id', 'arguments', 'allowed_tools',
        'referenced_skills', 'surface_policy',
        'automation_safe', 'enterprise_policy', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'is_active' => 'boolean',
            'automation_safe' => 'boolean',
            'arguments' => 'array',
            'allowed_tools' => 'array',
            'referenced_skills' => 'array',
            'lua_snippets' => 'array',
            'provenance' => 'array',
            'surface_policy' => 'array',
            'enterprise_policy' => 'array',
        ];
    }
}
```

**Design rationale:**
- DB-stored (not filesystem) for multi-workspace isolation and user editability
- `slug` is the unique invocation handle per workspace
- `content` supports `{{arg_name}}` placeholders for argument substitution
- `execution_mode` decides the default invocation path:
  - `prompt`: inject rendered `content` as instructions
  - `lua`: execute `lua_code` through `LuaSandboxService`/`LuaBridge`
  - `hybrid`: inject instructions and expose snippets/code to the agent or automation
- `lua_snippets` stores named reusable snippets, each with at least `{name, description, code}` and optional argument hints
- `status` controls lifecycle. Draft skills can be edited; published skills can be invoked; deprecated skills remain runnable for pinned automations but should not appear in normal creation/autocomplete; archived skills are hidden except in audit/history.
- `published_version_id` and `latest_version_id` keep mutable editing separate from immutable runtime behavior.
- `provenance_type` and `provenance` record where the skill came from and who/what is responsible for it.
- `integration_id` gates the skill — only available when that integration is enabled
- `allowed_tools` is advisory for prompt guidance and required preflight for Lua execution; it never grants tools by itself
- `referenced_skills` is an ordered list of skill slugs to include before this skill; each referenced skill is resolved with the same workspace, agent, surface, and permission rules
- `surface_policy` lets a skill opt out of risky surfaces such as external chat or unattended automations
- `automation_safe` is a product-level guardrail for unattended scheduled invocation; it does not bypass tool approvals or sandbox limits
- `enterprise_policy` is reserved for org/workspace constraints such as role gates, data classification, allowed networks/integrations, retention, export restrictions, and deny-by-default rollout controls
- `is_builtin` prevents deletion but allows content editing

The DB remains the source of truth for multi-workspace isolation and web editing. The VFS exposes the same records as projections and controlled patch/write targets; it should not become a second storage backend.

---

## Lifecycle, Versioning, and Provenance

Skills need a lifecycle from day one because automations, enterprise review, and audit trails make "edit the row in place" too risky.

### Lifecycle states

Use explicit states rather than overloading `is_active`:

- `draft`: editable working copy. Not visible to normal invocation unless the caller explicitly asks for draft preview/test mode.
- `published`: immutable version is callable from chat, Telegram, automations, Lua, tools, and VFS.
- `deprecated`: still callable by pinned automations and explicit references, but hidden from normal creation flows and autocomplete by default.
- `archived`: retained for audit and rollback history, not callable.

Suggested approach: keep `skills` as the stable package identity and add immutable `skill_versions` records for runtime content. Heavy edits through VFS should modify a draft/latest version, not mutate the published version currently used by automations.

```text
skill_versions
id                uuid, PK
skill_id          uuid, FK -> skills
version           integer or semver string
status            draft, published, deprecated, archived
content           text
lua_code          text|null
lua_snippets      json|null
arguments         json|null
referenced_skills json|null
surface_policy    json|null
automation_safe   boolean
checksum          string
change_summary    text|null
created_by        uuid|null
approved_by       uuid|null
published_at      timestamp|null
created_at        timestamp
```

### Runtime pinning

Automations and other unattended surfaces should pin skill versions:

- Prompt automations store ordered `skill_version_ids` or `{slug, version}` entries once published.
- Script automations using `app.skills.run_many(...)` can default to latest published for ad hoc runs, but scheduled automations should resolve and persist pinned versions at save/publish time.
- Chat/Telegram can use latest published by default because the human-visible conversation is the runtime surface, but audit logs must record the exact version/hash used.
- If a pinned version is deprecated, the automation may continue. If archived or deleted by policy, the automation should fail before execution with a migration/update prompt.

### Draft, publish, rollback

Publishing should be an explicit action:

- Validate frontmatter, arguments, referenced skills, Lua syntax, tests, policy, and budgets.
- Produce a diff from the previous published version.
- Require approval when workspace/enterprise policy says skill publication is controlled.
- Move the approved version to `published` and update `skills.published_version_id`.
- Keep rollback as "publish an older immutable version again" rather than mutating history.

VFS writes should expose this lifecycle clearly. Suggested paths:

```text
/skills/{slug}/draft/SKILL.md
/skills/{slug}/published/SKILL.md
/skills/{slug}/versions/{version}/SKILL.md
/skills/{slug}/changelog.md
```

### Provenance

Provenance is not just metadata polish. It affects trust, support, enterprise review, and future marketplace/import flows.

Suggested provenance types:

- `builtin`: shipped by OpenCompany.
- `human_created`: created by a workspace member.
- `agent_created`: created by an agent through `manage_skill` or VFS.
- `imported`: imported from a package file, repo, or another workspace.
- `marketplace`: installed from a curated/third-party catalog.
- `enterprise_managed`: controlled by org/workspace policy and usually publish-reviewed.

Record at least origin, creator, approving user, import package ID/version, source URL/repo if any, and whether the skill is locally modified from its source.

---

## Skill Composition

Skills can be composed explicitly by the caller or implicitly by skill metadata:

- Explicit composition: `$research $weekly-report`, automation `skill_slugs: ["research", "weekly-report"]`, `use_skill` with `skills: [...]`, or `app.skills.run_many(...)`.
- Declared composition: a skill lists `referenced_skills` in `skill.json` or `SKILL.md` frontmatter. Those referenced skills are included before the parent skill so foundational instructions and Lua helpers are available first.
- Hybrid composition: prompt skills may reference Lua/hybrid skills for executable helpers, and Lua/hybrid skills may reference prompt skills for operating guidance. Execution still happens only when the caller requests Lua execution or the agent chooses an allowed Lua tool path.

Composition rules:

- Preserve caller order first, then expand each skill's references depth-first in declared order.
- De-duplicate by slug after first inclusion; record later duplicate requests as aliases/reasons for audit logs rather than injecting the same skill twice.
- Reject cycles such as `a -> b -> a` with a clear error naming the cycle.
- Apply active, integration, agent permission, surface policy, automation safety, Lua sandbox, and underlying tool checks to every skill in the expanded set.
- Enforce total prompt/Lua budget for the expanded set. A single parent skill should not be able to smuggle an unbounded chain of referenced skills into an automation or external chat run.
- Resolve references against published versions at runtime. Dependency version pinning such as `child@1.2.0` is intentionally deferred; see Out of Scope.

---

## Permission System

Skills are a policy-sensitive runtime feature, not just prompt snippets. The first implementation should use the current agent permission system, but the contract must be ready for enterprise controls where skills may represent approved operating procedures, regulated data access, or privileged automation recipes.

### New scope type: `skill`

Add `'skill'` to `AgentPermission.scope_type`. The `scope_key` is the skill slug.

```
scope_type        = 'skill'
scope_key         = 'code-review'
permission        = 'allow' | 'deny'
requires_approval = true | false
```

### Resolution logic

Current authorization shape:

- `agent_permissions.scope_type` is a string column, so the database does not need an enum migration for `skill`.
- `App\Models\AgentPermission` docblocks/scopes currently list `tool`, `channel`, `folder`, `file_folder`, `integration`, and `agent`; update the model documentation and add a `scopeSkills()` helper if skill permissions are queried often.
- New resolver logic should live in `App\Domain\Authorization\Application\AgentPermissionResolver`, then be exposed through `App\Services\AgentPermissionService` for compatibility with existing runtime/tool code.
- Keep the resolver return shape extensible. It should be able to explain whether access was blocked by organization policy, workspace policy, role, agent permission, integration state, surface policy, referenced-skill expansion, automation safety, Lua capability, or an underlying tool.
- If skills can trigger external mutations through their expected tools, approval should respect the agent's behavior mode and the underlying tool permissions. A skill permission should never bypass tool permissions.
- Surface policy is evaluated in addition to agent permission. A skill can be available in chat but denied for unattended automation, or available internally but hidden from Telegram.
- Lua skill execution must require the normal `lua_exec`/Lua namespace permissions plus every underlying app tool permission reached through `LuaBridge`.
- Referenced skills do not inherit permission from the parent skill. Each referenced skill must independently pass active, integration, agent permission, surface policy, automation, and Lua/tool checks.

### Policy layers and deny precedence

Evaluate skill access from broadest to narrowest, and stop on the first hard deny:

1. **Organization policy**: enterprise-level feature flags, allow/deny lists, data classification restrictions, audit requirements, and default access mode.
2. **Workspace policy**: workspace plan, enabled integrations, workspace-level skill activation, admin-managed surface allowlists, and automation enablement.
3. **Role / membership policy**: human role and future agent role/team membership. Enterprise installs may require skills to be assigned to roles rather than individual agents only.
4. **Agent permission**: current `AgentPermission` allow/deny/approval rows for the acting agent.
5. **Skill policy**: `surface_policy`, `automation_safe`, referenced skills, execution mode, allowed tools, and Lua requirements.
6. **Underlying resource/tool policy**: file/folder/channel/table/integration/tool permissions reached by prompt instructions, Lua snippets, VFS reads/writes, or direct tool calls.

Precedence rules:

- Deny wins over allow at every layer.
- A parent skill cannot make a referenced skill available if the referenced skill is denied by any layer.
- A skill allow only permits activation/injection/execution of that skill. It never grants the tools, integrations, VFS paths, tables, channels, or files the skill talks about.
- Approval can be required at multiple layers. The final decision should carry every approval reason so the UI can show a useful summary instead of a generic "approval required".
- Enterprise deny-by-default should be supported even if the initial product default remains permissive for non-enterprise workspaces.

### Enterprise-ready policy shape

Do not hardcode enterprise behavior into the first resolver. Instead, define the decision shape and storage seams now:

```php
final class SkillPermissionDecision
{
    public bool $allowed;
    public bool $requiresApproval;
    public array $reasons;          // machine-readable reason codes
    public array $approvalReasons;  // human-readable approval summary rows
    public array $deniedSkills;     // slugs denied in an expanded skill set
    public array $policyLayers;     // org, workspace, role, agent, skill, resource
}
```

Future enterprise policy can live in dedicated org/workspace policy tables or a policy service. The skill system should only depend on an interface such as `SkillPolicyEvaluator`; it should not bake plan names, enterprise flags, role names, or compliance rules into `SkillRegistry`, `SkillInvocationService`, `LuaBridge`, tools, or VFS adapters.

In `AgentPermissionResolver`, add `resolveSkillPermission()` and expose it through `AgentPermissionService`:

```php
public function resolveSkillPermission(User $agent, string $skillSlug, string $surface = 'chat'): array
{
    $permission = AgentPermission::forAgent($agent->id)
        ->where('scope_type', 'skill')
        ->where('scope_key', $skillSlug)
        ->first();

    // Explicit deny
    if ($permission && $permission->permission === 'deny') {
        return ['allowed' => false, 'requires_approval' => false];
    }

    // Explicit allow (with optional approval)
    if ($permission) {
        return ['allowed' => true, 'requires_approval' => $permission->requires_approval];
    }

    // Product default for non-enterprise workspaces: allowed. Enterprise
    // policy may change the default to deny-by-default above this layer.
    // Surface policy and underlying tool calls still use normal approval rules.
    return ['allowed' => true, 'requires_approval' => false];
}
```

### Integration gating

Skills with `integration_id` set are only available when:
1. The integration is enabled at workspace level (`IntegrationSetting.enabled`)
2. The integration is enabled for the agent (`AgentPermissionService::getEnabledIntegrations()`)

`AgentPermissionResolver::getEnabledIntegrations()` already combines workspace-level `IntegrationSetting` enablement and per-agent deny rows for integration apps. `SkillRegistry::getSkillsForAgent()` should use that compatibility method rather than duplicating integration policy.

### Permission layering summary

```
Workspace skill active?       --- No  -> hidden
Enterprise/org policy deny?   --- Yes -> hidden/blocked
Workspace policy deny?        --- Yes -> hidden/blocked
Role policy deny?             --- Yes -> hidden/blocked
Surface allowed?              --- No  -> hidden/blocked
Integration enabled?          --- No  -> hidden
Agent skill permission deny?  --- Yes -> hidden
Agent skill permission allow? --- Yes -> available (approval only gates activation)
No explicit skill permission? --- default: available
Lua execution allowed?        --- required for lua/hybrid execution
Underlying tool permission?   --- always enforced by ToolRegistry/permission evaluator
```

---

## Skill Registry

`App\Domain\AgentRuntime\Application\Skills\SkillRegistry` — single point of truth for resolving skills.

```php
class SkillRegistry
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    /** Get all skills available to a specific agent (filtered). */
    public function getSkillsForAgent(User $agent): Collection;

    /** Resolve a single skill by slug for the current workspace and agent. */
    public function resolveSkill(User $agent, string $slug): ?Skill;

    /** Resolve an ordered set of skills and recursively expand declared references. */
    public function resolveSkillSet(User $agent, array $slugs, string $surface): SkillSet;

    /** Build compact skill catalog for the system prompt. */
    public function getSkillCatalog(User $agent): string;

    /** Render skill content with argument substitution. */
    public function renderSkillContent(Skill $skill, array $arguments = []): string;

    /** Render an ordered skill set for prompt injection. */
    public function renderSkillSetContent(SkillSet $skillSet, array $arguments = []): string;

    /** Render the executable Luau entrypoint or named snippet with arguments. */
    public function renderLua(Skill $skill, array $arguments = [], ?string $snippet = null): string;

    /** Get metadata for frontend (autocomplete, management UI). */
    public function getSkillsMeta(User $agent): array;
}
```

`SkillRegistry` should remain lookup/rendering only. It can resolve a single skill or an ordered `SkillSet`, including declared references, but invocation side effects belong in `SkillInvocationService` so chat, Telegram, prompt automation, script automation, tools, and VFS mutations all share one contract.

```php
class SkillInvocationService
{
    public function invoke(SkillInvocation $invocation): SkillInvocationResult;
}
```

`SkillInvocation` should include at least: workspace, agent, surface (`chat`, `telegram`, `automation_prompt`, `automation_script`, `tool`, `lua`, `vfs`), an ordered skill slug list, per-skill arguments, optional channel/task/automation IDs, and whether the caller wants prompt injection, Lua execution, or metadata only.

### Filtering pipeline in `getSkillsForAgent()`

```php
Skill::forWorkspace()
    ->where('is_active', true)
    ->get()
    ->filter(fn ($skill) =>
        // Integration gate
        (!$skill->integration_id || in_array($skill->integration_id, $enabledIntegrations))
        // Permission gate
        && $this->permissionService->resolveSkillPermission($agent, $skill->slug)['allowed']
    );
```

---

## Invocation Flows

### Shared invocation contract

All surfaces should call `SkillInvocationService`; none should parse and execute skills independently. The service owns:

- parsing `$skill-name` syntax and explicit `skillId`/`skillSlug` references;
- preserving ordered multi-skill activation and merging explicit skills with referenced skills;
- detecting duplicate skills, missing references, and cyclic references before prompt assembly or execution;
- resolving workspace, agent, surface, integration, and skill permissions;
- rendering prompt content and Lua snippets with arguments;
- executing Lua skills through `LuaSandboxService`/`LuaBridge` when requested;
- returning VFS paths and catalog metadata for discovery-only calls;
- logging usage, permission decisions, approvals, and failures with enough context to audit chat, automation, Telegram, and VFS-triggered runs.

For prompt-injection paths, the service returns an ordered active skills payload for the agent prompt. For Lua paths, it returns sandbox output, result value, bridge call log, and the VFS paths/snippets involved.

### Audit requirements

Every skill invocation should produce a structured audit event, even when the skill only injects prompt instructions:

- workspace, organization if available, acting agent, initiating user when present, and surface
- explicit skill slugs, expanded referenced skill slugs, final order, and per-skill version/hash
- permission decision per skill and aggregate decision for the skill set
- approval IDs and approval reasons when activation, Lua execution, VFS writes, or underlying tools require approval
- Lua snippet/entrypoint names, VFS paths touched, tool calls attempted, and denied references where applicable
- source object IDs such as message, channel, task, automation, webhook, or VFS operation

Enterprise customers will need to answer "which agent used which approved procedure, from which channel, against which data, under which policy." Keep the audit shape stable enough for export later.

### Flow 1: User types `$skill-name args`

```
User -> chat composer -> sendMessage API -> AgentRespondJob
                                            |
                                            v
                                     RespondToChatMessage
                                            |
                                   SkillInvocationService
                                            |
                                            v
                                      AgentRunBuilder
                                            |
                                  OpenCompanyAgent sections
                                  (skills in system prompt)
                                            |
                                      LLM response
                                            |
                                      normal delivery
```

**In `RespondToChatMessage::handle()`**, before building the agent run:

```php
$activeSkills = [];
if (str_starts_with($this->userMessage->content, '$')) {
    $result = app(SkillInvocationService::class)->invoke(
        SkillInvocation::fromMessage(
            message: $this->userMessage,
            agent: $this->agent,
            surface: 'chat',
            channelId: $this->channelId,
            taskId: $task->id,
        ),
    );

    $activeSkills = $result->activePromptSkills();
}

// Pass to AgentRunBuilder/OpenCompanyAgent through run-scoped options.
$agentRun = app(AgentRunBuilder::class)->build(
    $this->agent,
    $this->channelId,
    $task->id,
    new AgentRunOptions(
        resumeFromTask: $this->attempts() > 1,
        activeSkills: $activeSkills,
    ),
);
```

This requires adding an `activeSkills` field to `AgentRunOptions`, wiring it in `AgentRunBuilder`, and adding a method such as `OpenCompanyAgent::withActiveSkills()`. Keep it run-scoped; do not persist active skills on the agent record.

Telegram and other external chat adapters should not get a separate parser. They already become normal OpenCompany messages before `AgentRespondJob` runs, so their surface metadata should be passed to the shared service and the same `$skill` syntax should work.

**In `OpenCompanyAgent::buildSections()`**, add the active skills section after the task/current-context sections and before the app/tool catalog:

```php
if ($this->activeSkills !== []) {
    $sections[] = [
        'label' => 'Active Skills',
        'content' => $this->renderActiveSkillsSection($this->activeSkills),
    ];
}
```

The active skills section should preserve expansion order, mark whether each skill was explicit or referenced by another skill, and keep each skill's content bounded so a composed chain cannot silently consume the whole prompt budget.

### Flow 2: Agent uses `use_skill` tool (model-initiated)

The agent sees available skills in the system prompt catalog and can decide to invoke one.

**`UseSkill` tool** registered through a built-in tool provider:

```php
class UseSkill implements Tool
{
    public function description(): string
    {
        return 'Activate one or more skills to get specialized instructions for a task.';
    }

    public function handle(Request $request): string
    {
        $slugs = $request['skills'] ?? [$request['skill']];
        $args = $request['arguments'] ?? [];

        $skillSet = $this->registry->resolveSkillSet($this->agent, $slugs, 'tool');
        if ($skillSet->isEmpty()) {
            return "Skill not found. Available: "
                 . $this->registry->getSkillsForAgent($this->agent)->pluck('slug')->join(', ');
        }

        if ($skillSet->hasDeniedSkills()) {
            return "One or more requested skills are not available for this agent or surface.";
        }

        return $this->registry->renderSkillSetContent($skillSet, $args);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string('Single skill slug to activate'),
            'skills' => $schema->array('Ordered skill slugs to activate together')->items($schema->string()),
            'arguments' => $schema->object('Named arguments, optionally keyed by skill slug'),
        ];
    }
}
```

The tool returns the rendered skill set as text. The LLM incorporates it into its reasoning and follows the instructions. Simple, no magic. `skill` remains a convenience alias for a one-item `skills` array.

For Lua or hybrid skills, `use_skill` can return:

- rendered prompt instructions;
- a list of available snippets with descriptions and VFS paths;
- an explicit instruction that the agent should call `lua_exec` or `app.skills.run(...)` if execution is required and allowed.

Code alignment: create a `SkillsToolProvider` under `app/Agents/Tools/Providers/`, register it in `AppServiceProvider::registerBuiltInToolProviders()`, and add a `skills` direct tool group to `ToolRegistry::DIRECT_TOOL_GROUPS` if `use_skill` and `manage_skill` should be direct model-callable tools. Do not add to a non-existent `APP_GROUPS` constant.

### Flow 3: Agent manages skills via `manage_skill` tool

Agents can list, inspect, create, lightly update metadata, and delete skills:

```php
class ManageSkill implements Tool
{
    // Actions: list, get, create, update_metadata, delete
    // Heavy prompt/Lua edits should happen through /skills VFS paths.
    // Only non-builtin skills can be deleted
    // Creates skills scoped to the agent's workspace
}
```

Register this through the same `SkillsToolProvider`. It is a write-style workspace mutation and should be permissioned like other write tools. Keep `manage_skill` deliberately narrow: creation can scaffold a package, and metadata updates can change name, description, category, integration gate, surface policy, and active state, but substantial edits to `SKILL.md`, instructions, Lua entrypoints, or snippets should be done through VFS (`vfs_patch`/`vfs_write` against `/skills/...`) so edits get path semantics, validation, version checks, diffs, and approval summaries. If the product wants skill management to be code-first rather than direct, expose it through Lua docs and mark the direct tool metadata `luaOnly`; otherwise include the `skills` group in `DIRECT_TOOL_GROUPS`.

### Flow 4: Automations call skills

Automations need first-class skill support. Do not rely only on chat syntax.

Current automation code has two execution modes:

- `prompt`: `ExecutePromptAutomation` creates a task and calls `OpenCompanyAgent::for()` directly.
- `script`: `ExecuteScriptAutomation` runs `automation->script` through `LuaSandboxService` with a `LuaBridge` and `ctx`.

Planned support:

- Prompt automations may store ordered `skill_ids`/`skill_slugs` plus per-skill arguments, or their prompt may start with one or more `$skill-name` tokens. Both paths should call `SkillInvocationService` before the agent prompt is built.
- Script automations may call `app.skills.run("slug", args)` or `app.skills.run_many({"research", "weekly-report"}, args)` for executable Lua skills, `app.skills.snippet("slug", "name")` to fetch a described snippet, or read the same packages through VFS under `/skills`.
- Automation runs use the configured automation agent for permission checks. The creator/admin status does not grant the skill new tool access at run time.
- Skills with `automation_safe = false` or a surface policy denying `automation_prompt`/`automation_script` must fail before task execution.

Prompt automation alignment: either route `ExecutePromptAutomation` through `AgentRunBuilder` like chat or explicitly pass the active skills payload into `OpenCompanyAgent`. Avoid a second prompt assembly path that omits skill catalog, VFS paths, Lua snippet metadata, or referenced-skill expansion.

Script automation alignment: execute Lua skills through the existing `LuaSandboxService`/`LuaBridge` path so `ctx`, call logs, CPU limits, and app tool permissions behave the same as normal script automations.

### Flow 5: Lua and VFS call skills

Lua should expose a small `app.skills` namespace:

```lua
local meta = app.skills.get("daily-kpi-sync")
local list = app.skills.list({ category = "reporting" })
local code = app.skills.snippet("daily-kpi-sync", "fetch-metrics")
local result = app.skills.run("daily-kpi-sync", { date = ctx.today })
local combined = app.skills.run_many({ "research", "weekly-report" }, {
  research = { topic = "competitors" },
  ["weekly-report"] = { date = ctx.today },
})
```

`app.skills.list` and `app.skills.get` are read-only metadata helpers. `app.skills.snippet` returns a named snippet plus description for composition. `app.skills.run` executes a single skill and expands its declared references. `app.skills.run_many` executes an ordered skill set and expands declared references for every member. Both run helpers execute only skills whose `execution_mode` is `lua` or `hybrid`, and they must apply the same skill permission, surface policy, Lua sandbox, cycle detection, prompt/Lua budget, and underlying tool permission checks as direct invocation.

Lua should not grow broad skill mutation helpers. At most, later add tiny admin helpers such as `app.skills.create_draft` or `app.skills.set_active` if they are clearly useful. Heavy editing belongs to VFS:

```lua
local skill = app.vfs.read("/skills/daily-kpi-sync/SKILL.md")
local stat = app.vfs.stat("/skills/daily-kpi-sync/SKILL.md")
app.vfs.patch("/skills/daily-kpi-sync/SKILL.md", patch, { version = stat.version })
app.vfs.write("/skills/daily-kpi-sync/snippets/fetch-metrics.lua", code, {
  mode = "overwrite",
  version = snippet_version,
})
```

This keeps Lua good at composing and running skills while VFS owns precise edits, stale-write checks, and reviewable diffs.

VFS should expose the same assets:

```text
/skills
/skills/catalog.json
/skills/{slug}/SKILL.md
/skills/{slug}/skill.json
/skills/{slug}/instructions.md
/skills/{slug}/lua/main.lua
/skills/{slug}/snippets/{name}.lua
/skills/{slug}/README.md
```

The VFS mount is a projection over the database source of truth. Reads support discovery, grep, and script composition. Writes/patches are the preferred path for serious skill editing and must go through skill validation and approval gates like other workspace write tools.

---

## System Prompt Integration

### Skill catalog (always present, lightweight)

Added as a section in `OpenCompanyAgent::buildSections()`, near the Apps catalog:

```
## Skills

Invoke with one or more $skill-name tokens or use_skill.

create-skill — Guide for creating new skills with proper structure and arguments
```

One line per skill. Include mode and VFS path when useful:

```
daily-kpi-sync [hybrid] — Pull KPI inputs and summarize changes. VFS: /skills/daily-kpi-sync/SKILL.md
```

Costs ~10-20 tokens per skill. Capped at 30 skills in the catalog; beyond that, show "... and N more". Initially only `create-skill` appears; user/agent-created skills populate the catalog over time.

### Active skills (injected on invocation)

Only when one or more prompt or hybrid skills are invoked — full rendered instructions appear as an "Active Skills" section in deterministic expansion order. Token cost depends on the combined skill content length, so enforce a per-skill and total active-skill budget. Lua snippets should not be blindly injected into the prompt; list their names/descriptions and VFS paths unless the agent explicitly needs the code.

### Token budget

```
Catalog:      ~10-20 tokens/skill × 30 skills = ~300-600 tokens max
Active prompts: variable (combined skill content length, budgeted per skill set)
Lua snippets:  metadata only by default; code loaded via VFS/Lua when needed
```

---

## Integration-Specific Skills

Skills can be gated behind an integration via the `integration_id` field. No integration-specific skills ship as builtins — they are all user/agent-created.

Example: a user could create `$tg-broadcast` with `integration_id = 'telegram'`. When Telegram is not enabled (workspace or agent level), the skill is invisible — it won't appear in the catalog, autocomplete, or `use_skill` results.

When creating a skill via the `$create-skill` builtin or `manage_skill` tool, the agent can ask whether to gate the skill behind an integration.

---

## VFS Integration

Skills should be first-class VFS resources. This makes them inspectable and reusable from agents, automations, Lua scripts, future Dream jobs, and human debugging without creating a second storage system.

Planned mount:

| Path | Shape | Operations |
|------|-------|------------|
| `/skills` | Directory of active skills plus discovery files | browse, read, search |
| `/skills/catalog.json` | Permission-filtered skill catalog for the acting agent | read, search |
| `/skills/{slug}/SKILL.md` | Agent Skills-style frontmatter plus rendered instructions and snippet list | read, patch |
| `/skills/{slug}/skill.json` | Full structured metadata, arguments, referenced skills, surface policy, mode, VFS links | read, patch |
| `/skills/{slug}/instructions.md` | Prompt-only instructions | read, patch |
| `/skills/{slug}/lua/main.lua` | Executable Lua entrypoint when present | read, patch/write with validation |
| `/skills/{slug}/snippets/{name}.lua` | Named reusable Lua snippets | read, patch/write with validation |
| `/skills/{slug}/draft/SKILL.md` | Draft editable package projection | read, patch/write with validation |
| `/skills/{slug}/published/SKILL.md` | Current published immutable package projection | read |
| `/skills/{slug}/versions/{version}/SKILL.md` | Historical immutable package projection | read |
| `/skills/{slug}/changelog.md` | Version history and publish notes | read |
| `/skills/{slug}/README.md` | Human-readable usage notes and examples | read, patch |

Implementation notes:

- Add `MountsVfsSkills` to `OpenCompanyVfs` and include `skills` in root entries.
- The DB remains source of truth. VFS patch/write operations call a `SkillRepository`/`ManageSkills` application service; they do not write files to disk.
- `stat` should expose capabilities based on skill permission, surface policy, and whether the path is metadata, prompt text, executable Lua, or a snippet.
- `rg`/`find` over `/skills` should search instructions, snippet descriptions, and Lua text while still filtering by the acting agent's skill permissions.
- VFS writes should update draft versions by default. Publishing, deprecating, archiving, and rollback are explicit lifecycle actions with validation and approval behavior.
- VFS writes must validate frontmatter, arguments, referenced skill slugs, reference cycles, Luau syntax/`--!strict` expectations where applicable, and dangerous surface-policy changes before saving.
- `/tools/vfs/*` discovery should mention `/skills`, and `lua_read_doc("skills")` should document `app.skills.*`.

Example VFS projection:

```markdown
---
slug: daily-kpi-sync
name: Daily KPI Sync
mode: hybrid
status: published
version: 3
provenance:
  type: human_created
  creator: rutger
referenced_skills:
  - metric-normalizer
  - summary-writer
arguments:
  - name: date
    type: string
automation_safe: true
---

# Daily KPI Sync

Use the `fetch-metrics` snippet to gather values, then summarize notable changes.

## Snippets

- `fetch-metrics`: Reads KPI source tables and returns normalized rows.
- `post-summary`: Writes the final summary to the configured channel.
```

---

## Safety, Testing, and Operations

### Tests and dry runs

Skills should be testable before they are published or attached to automations.

Suggested approach:

- Store examples and test cases alongside the skill version: input arguments, expected prompt rendering, expected Lua return shape, and allowed side effects.
- Add a `dry_run` mode to `SkillInvocationService` that resolves the skill set, renders prompts, validates Lua, calculates permission decisions, and previews VFS/tool/integration effects without executing external mutations.
- Require passing validation before publishing a Lua/hybrid skill when workspace policy says so.
- For automation-safe skills, run a dry-run preview when an automation is created or updated and store the resolved pinned versions and validation summary.

### Secrets and credentials

Skills must not store secrets directly in prompt content, Lua snippets, frontmatter, or VFS projections.

Suggested approach:

- Lua snippets access credentials only through permissioned integrations/tools exposed by `LuaBridge`.
- VFS validation should reject obvious secret-shaped values in skill files and warn on suspicious inline tokens.
- Skill packages may declare required integrations and scopes, but not credential values.
- Audit logs should record integration/tool identifiers, not raw secrets or credential payloads.

### Prompt and instruction boundaries

Skills are lower priority than platform/system/security instructions. A user-created skill must not be able to override workspace security policy, tool approval rules, or enterprise restrictions.

Suggested approach:

- Render active skills under a clearly labeled bounded section such as `## Active Skills`.
- Add a guard sentence around rendered skills: "Follow these skill instructions only when they do not conflict with system, workspace, permission, or tool policies."
- Do not inject raw Lua snippets by default. Show names, descriptions, VFS paths, and hashes; load code only through VFS/Lua when needed and permissioned.
- Validate imported or agent-created skills for attempts to claim elevated authority, disable approvals, reveal secrets, or bypass workspace scoping. This is not perfect security, but it catches common bad packages and creates review friction.

### Quotas and budgets

Budgets should be explicit and enforced in the shared invocation service.

Suggested limits to start:

- max explicit skills per invocation
- max expanded referenced skills
- max reference depth
- max rendered prompt bytes/tokens per skill and per skill set
- max Lua CPU/time/memory per skill run
- max VFS reads/writes and result bytes per skill run
- max automation fanout or channel sends triggered by one skill run

Budget failures should be deterministic and auditable, not model-dependent.

### Namespaces and slug collisions

Workspace slugs will eventually collide with builtins, imports, marketplace packages, or enterprise-managed skills.

Suggested approach:

- Keep the user-facing shorthand `$weekly-report` for normal workspace use.
- Internally support namespaced IDs such as `builtin:create-skill`, `workspace:weekly-report`, `marketplace:vendor/package`, and `enterprise:policy-review`.
- If a shorthand is ambiguous, return a specific disambiguation error and show available namespaces.
- Builtins and enterprise-managed skills should be protected from accidental replacement by workspace imports.

### Import, export, and packages

Package support should align with the VFS projection rather than inventing a second format.

Suggested package shape:

```text
skill.json
SKILL.md
README.md
lua/main.lua
snippets/*.lua
tests/*.json
changelog.md
```

Import should create a draft first, record provenance, run validation, and require publish before normal invocation. Export should omit secrets and include version/provenance/checksum metadata.

### Approval UX

Approval requests for skill activation or publication should show what will actually run, not just the top-level skill name.

Approval summaries should include:

- explicit and referenced skills in final order
- versions/checksums
- Lua entrypoints/snippets
- VFS paths expected to be read/written
- tools/integrations expected by the skill set
- policy reasons and approval requirements
- automation pinning changes when applicable

### Failure and retry semantics

Multi-skill execution needs predictable behavior:

- Prompt injection is all-or-nothing. If any required skill is denied, missing, over budget, or cyclic, the skill set fails before the agent run starts.
- Lua execution is ordered-stop-on-error by default. Later skills do not run after an earlier skill fails unless the caller explicitly opts into best-effort behavior.
- Automations should fail before creating external side effects when validation, permission, version resolution, or dry-run checks fail.
- Retried automation runs should use the same pinned versions as the original run unless the automation definition was updated.

### Observability

Track per-skill and per-skill-set usage:

- invocations by surface, agent, workspace, and version
- failures, denials, approval requests, and approval outcomes
- token cost, Lua runtime cost, VFS bytes, downstream tool calls, and latency
- deprecated/pinned version usage so admins know what still depends on old behavior

### Migration path

Existing prompt and script automations should keep working.

Suggested approach:

- Add nullable skill-set fields to automations without changing existing `prompt`/`script` behavior.
- Let admins attach skills to existing automations, preview dry-run output, then save pinned versions.
- For script automations, introduce `app.skills.*` as additive Lua helpers and avoid rewriting existing scripts automatically.
- Keep `$skill` syntax in prompts as a convenience, but prefer structured `skill_slugs`/pinned versions for scheduled automations.

---

## Out of Scope For First Pass

- Dependency version constraints such as `child@1.2.0`, semver ranges, lockfiles, and automatic dependency update workflows. For now, referenced skills resolve to the latest published version at invocation/save time, and automations pin the expanded result.
- Marketplace trust scoring, package signing, and remote update channels. Preserve provenance/checksum fields now so those can be added later.
- Full role/team policy UI for enterprise. The backend decision shape should support it, but the first UI can expose agent-level skill permissions plus workspace policy basics.
- General-purpose package manager behavior. Skill import/export should stay simple until lifecycle, validation, and audit behavior are stable.

---

## Frontend

### `$` Autocomplete In Chat Composers

Current code has at least two chat composer surfaces:

- `resources/js/Components/chat/MessageInput.vue` for the channel-style chat UI.
- `resources/js/Components/chat/assistant/PromptComposer.vue` for the assistant shell.

If `$` autocomplete is added, wire both surfaces or deliberately scope the first pass to server-side invocation only. There is no current `/` command popup in `PromptComposer.vue`; it uses compact/status buttons rather than slash-command interception.

```typescript
// Skill detection
const showSkillsPopup = ref(false)
const skillQuery = ref('')
const availableSkills = ref<Skill[]>([])

const checkForSkill = () => {
  const token = tokenNearCursor(message.value, cursorPosition.value)

  if (token?.startsWith('$')) {
    skillQuery.value = token.slice(1)
    showSkillsPopup.value = true
  } else {
    showSkillsPopup.value = false
  }
}
```

Planned endpoint: skills would be fetched from `GET /api/skills` on component mount. This route is not present in the current Laravel route table; add the API before wiring the popup. Popup shows matching skills with icon, `$slug`, and description.

Autocomplete should support multiple skill tokens in one message. It should complete the skill token nearest the cursor, not only the first token in the message, and it should avoid inserting duplicate explicit skills when a selected skill is already active or referenced by another selected skill.

Routing/Wayfinder note: frontend route/action generation is owned by Laravel Wayfinder. After adding `SkillController` routes, run `npm run wayfinder:generate` and use generated imports or existing `useApi.ts` wrapper patterns rather than string-built URLs.

### Skills Management Page

Route: `/w/{workspace_slug}/skills`

- List all workspace skills (name, slug, category, integration badge, builtin badge, lifecycle state, published version, provenance)
- Create/Edit modal: name, slug, description, icon, category, lifecycle state, current version, provenance, mode, referenced skills, integration gate, arguments, prompt instructions, Lua entrypoint, Lua snippets, tests, automation safety, and surface policy
- Lua editor should reuse the same Monaco/syntax infrastructure as automation scripts and require explicit validation before save
- Publish flow should show diff, validation results, referenced skills, approval requirements, and version notes before changing the callable version
- Builtin skills: editable but not deletable, with "Reset to default" action
- Delete confirmation for user-created skills

### Agent Skill Permissions

On the Agent detail page (capabilities tab), add a "Skills" section alongside the existing tool permissions. Current data for that page comes from `AgentDetailQuery`, then flows through `resources/js/Pages/Agent/Show.vue` into `resources/js/Components/agents/AgentCapabilities.vue`.

- Lists all active workspace skills
- Per-skill toggle: allow / deny
- Same UI pattern as the integration permission toggles

---

## Builtin Skills

Only **one** builtin skill ships with the app: `$create-skill` — a meta-skill that teaches the agent how to create well-structured skills using the `manage_skill` tool. All other skills are user/agent-created.

```php
class SkillSeeder extends Seeder
{
    private const BUILTINS = [
        [
            'slug' => 'create-skill',
            'name' => 'Create Skill',
            'description' => 'Guide for creating new skills with proper structure and arguments',
            'icon' => 'ph:plus-circle',
            'category' => 'general',
            'content' => <<<'MD'
            You are helping the user create a new skill. A skill is a reusable prompt template that teaches agents how to perform a specific task.

            Walk through these steps:
            1. **Purpose** — Ask what the skill should do. Get a clear, specific description.
            2. **Slug** — Suggest a short kebab-case slug (e.g. `weekly-report`, `translate`, `code-review`).
            3. **Arguments** — Identify any variable inputs the skill needs. Define them with name, type, description, required flag, and default value.
            4. **Mode** — Decide whether this is prompt-only, Lua-only, or hybrid.
            5. **Content** — Write the prompt template. Use `{{arg_name}}` for argument placeholders. The content should be clear instructions that any agent can follow.
            6. **Lua** — If the skill includes code, write described Luau snippets or an executable entrypoint. Use lua_read_doc before referencing app APIs.
            7. **Referenced skills** — Decide whether this skill should compose existing skills and list them in the order they should run or be injected.
            8. **Surfaces** — Decide whether chat, Telegram, automations, Lua, and VFS can invoke it.
            9. **Integration gate** — Ask if this skill should only be available when a specific integration is enabled.
            10. **Create** — Use the `manage_skill` tool with action "create" to save the skill.

            Tips for good skill content:
            - Be specific and actionable — the agent should know exactly what to do
            - Reference tools the agent should use (e.g. "use web_search for web research" or "read the Lua docs before using lua_exec for workspace data")
            - For Lua snippets, include a one-line description and expected input/output shape
            - Reference other skills instead of duplicating large instruction blocks, but avoid circular references
            - Mention the VFS path where future agents can inspect or reuse the skill
            - Keep it focused — one skill, one task
            - Use markdown formatting for structure
            MD,
        ],
    ];

    public function run(): void
    {
        foreach (Workspace::all() as $workspace) {
            foreach (self::BUILTINS as $data) {
                Skill::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'slug' => $data['slug']],
                    [...$data, 'id' => Str::uuid(), 'workspace_id' => $workspace->id, 'is_builtin' => true]
                );
            }
        }
    }
}
```

Also called during workspace creation to seed new workspaces. Beyond this single builtin, all skills are created by users or agents via the management UI or `manage_skill` tool.

---

## Arguments

### Definition

Arguments are stored as JSON on the skill:

```json
[
    {
        "name": "focus",
        "type": "string",
        "description": "Focus area for review",
        "required": false,
        "default": "all"
    },
    {
        "name": "language",
        "type": "string",
        "description": "Target language",
        "required": true
    }
]
```

### Parsing: `SkillParser`

```php
class SkillParser
{
    /**
     * Parse one or more leading "$skill-name --flag value" tokens into an
     * ordered skill request and leave the remaining user prompt intact.
     *
     * @return SkillParseResult
     */
    public static function parse(string $input): SkillParseResult;
}
```

Supports:
- `$skill-name` — slug only
- `$skill-name some free text` — assigned to first required argument or `remainder`
- `$skill-name --focus security` — named arguments
- `$skill-name --focus security Review this file` — mixed
- `$research $weekly-report --date today summarize churn` — ordered skill set with shared remainder

For multi-skill requests, named arguments can be keyed by skill slug in structured surfaces such as automations and tools. Chat shorthand should stay conservative: parse leading skill tokens and keep ambiguous free text as the shared remainder rather than guessing which skill owns it.

### Rendering

Replace `{{placeholder}}` tokens in skill content:

```php
public function renderSkillContent(Skill $skill, array $arguments = []): string
{
    $content = $skill->content;

    foreach ($skill->arguments ?? [] as $def) {
        $value = $arguments[$def['name']] ?? $def['default'] ?? '';
        $content = str_replace("{{{$def['name']}}}", $value, $content);
    }

    // Clean up any unreplaced placeholders
    return trim(preg_replace('/\{\{[^}]+\}\}/', '', $content));
}
```

### Lua snippets

Lua snippets are stored as structured JSON so they can be rendered into VFS files and catalog rows:

```json
[
    {
        "name": "fetch-metrics",
        "description": "Read KPI inputs for a date and return normalized rows.",
        "arguments": [
            {"name": "date", "type": "string", "required": true}
        ],
        "code": "--!strict\nlocal date = args.date\nreturn app.vfs.read('/tables/kpis/rows.ndjson')"
    }
]
```

Rules:

- Snippets are not automatically injected into the system prompt.
- `app.skills.list` and `app.skills.get` return permission-filtered metadata.
- `app.skills.snippet(slug, name)` returns code plus description for composition.
- `app.skills.run(slug, args)` executes one skill plus its declared references through the same sandbox path as `lua_exec` and script automations.
- `app.skills.run_many(slugs, args)` executes an ordered skill set and should behave the same as explicit multi-skill invocation from chat, tools, and automations.
- Lua helpers are for discovery, composition, and execution. Do not add broad `app.skills.update`/`app.skills.write` APIs; use `app.vfs.patch` or `app.vfs.write` against `/skills/...` for heavy editing.
- Snippets should prefer `app.vfs.*` helpers and generated Lua docs over hardcoded app internals.

---

## Implementation Phases

### Phase 1: Core

1. Migration: `create_skills_table`
2. Migration: `create_skill_versions_table`
3. `Skill` and `SkillVersion` models with `BelongsToWorkspace`
4. `SkillRegistry` service in the AgentRuntime domain/application area
5. `SkillParser` utility with ordered multi-skill parsing
6. `SkillInvocationService` + DTO/result objects shared by chat, Telegram, automations, tools, Lua, and VFS, including referenced-skill expansion and cycle detection
7. `SkillLifecycleService` for draft/publish/deprecate/archive/rollback and immutable version creation
8. Add `SkillPolicyEvaluator`/`SkillPermissionDecision`, backed initially by `AgentPermissionResolver`, then expose `resolveSkillPermission()` on `AgentPermissionService`
9. `SkillValidationService` for frontmatter, arguments, Lua syntax, references, budgets, secret warnings, and dry-run checks
10. `SkillController` + API routes (CRUD plus lifecycle actions)
11. `SkillSeeder` — seeds only `$create-skill` builtin per workspace
12. Skill catalog section in `OpenCompanyAgent::buildSections()`
13. `$` prefix detection in `RespondToChatMessage`, with run-scoped active skills passed through `AgentRunOptions`/`AgentRunBuilder`
14. Prompt automation support in `ExecutePromptAutomation` through the same invocation service
15. Script automation/Lua support through read/run-focused `app.skills.*` over `LuaSandboxService`/`LuaBridge`
16. `UseSkill` tool via a built-in skills tool provider
17. Narrow `ManageSkill` tool via the same provider for list/get/scaffold/metadata/delete/lifecycle; heavy edits go through `/skills` VFS
18. `MountsVfsSkills` and `/skills` VFS projections with draft/published/version paths

### Phase 2: Frontend

19. Multi-token `$` autocomplete popup in `MessageInput.vue`
20. Multi-token `$` autocomplete popup in `PromptComposer.vue`
21. `Skills/Index.vue` management page
22. Skill editor modal (metadata, lifecycle, provenance, versions, prompt content, Lua snippets, tests, arguments, integration gate, surface policy)
23. Publish/review modal with diff, validation, approval, and rollback actions
24. Agent skill permissions on capabilities tab
25. Navigation: add Skills to sidebar
26. Automation create/edit support for selecting ordered skills, per-skill arguments, and pinned versions
27. `useApi.ts` or direct generated Wayfinder action imports for skill endpoints
28. `npm run wayfinder:generate` after adding Laravel routes/controller actions

### Phase 3: Polish

29. Skill usage/audit analytics (invocation count per skill, surface, mode, version, policy decision, and approval status)
30. "Reset to default" for builtin skills
31. Skill argument, reference, cycle, secret, lifecycle, version, and budget validation + error messages in chat, Telegram, automations, Lua, and VFS
32. Skill categories filter on management page
33. Telegram-friendly skill suggestions or help cards
34. Import/export package support
35. VFS discovery docs under `/tools/skills` or `/tools/catalog.md`

---

## Key Files

| File | Change |
|------|--------|
| `app/Models/Skill.php` | New model |
| `app/Models/SkillVersion.php` | Immutable version model for draft/published/deprecated/archived skill content |
| `app/Domain/AgentRuntime/Application/Skills/SkillRegistry.php` | New service |
| `app/Domain/AgentRuntime/Application/Skills/SkillParser.php` | New utility |
| `app/Domain/AgentRuntime/Application/Skills/SkillInvocationService.php` | Shared invocation service for chat, Telegram, automations, Lua, tools, and VFS |
| `app/Domain/AgentRuntime/Application/Skills/SkillLifecycleService.php` | Draft, publish, deprecate, archive, rollback, pinning, and provenance updates |
| `app/Domain/AgentRuntime/Application/Skills/SkillValidationService.php` | Frontmatter, Lua, references, secrets, budgets, tests, and dry-run validation |
| `app/Domain/AgentRuntime/Application/Skills/SkillSet.php` | Ordered expanded skill set with explicit/reference provenance and cycle errors |
| `app/Domain/AgentRuntime/Application/Skills/SkillInvocation.php` | Invocation DTO with surface, agent, ordered skill slugs, per-skill arguments, and context IDs |
| `app/Domain/AgentRuntime/Application/Skills/SkillInvocationResult.php` | Result DTO for prompt skill-set payloads, Lua output, metadata, and errors |
| `app/Domain/AgentRuntime/Application/Skills/SkillPolicyEvaluator.php` | New policy seam for agent, workspace, role, enterprise, surface, automation, and Lua checks |
| `app/Domain/AgentRuntime/Application/Skills/SkillPermissionDecision.php` | Structured effective decision with reasons, approval requirements, and denied skill refs |
| `app/Domain/AgentRuntime/Application/Skills/SkillInvocationAudit.php` | Structured audit event writer for invocation, denial, approval, and execution records |
| `app/Agents/Tools/Providers/SkillsToolProvider.php` | New built-in tool provider |
| `app/Agents/Tools/Skills/UseSkill.php` | New tool |
| `app/Agents/Tools/Skills/ManageSkill.php` | New tool |
| `app/Domain/Vfs/OpenCompany/Concerns/MountsVfsSkills.php` | New VFS mount for `/skills` |
| `app/Services/LuaBridge.php` | Add read/run-focused `app.skills.*` local namespace dispatch |
| `resources/lua-docs/skills.md` | Lua skill API docs |
| `app/Http/Controllers/Api/SkillController.php` | New controller |
| `app/Domain/Authorization/Application/AgentPermissionResolver.php` | Add `resolveSkillPermission()` |
| `app/Services/AgentPermissionService.php` | Expose `resolveSkillPermission()` compatibility method |
| `app/Agents/Tools/ToolRegistry.php` | Add `skills` to `DIRECT_TOOL_GROUPS` only if skill tools are direct model-callable |
| `app/Providers/AppServiceProvider.php` | Register `SkillsToolProvider` |
| `app/Agents/OpenCompanyAgent.php` | Add skill catalog + active skills section |
| `app/Agents/Runtime/AgentRunOptions.php` | Add run-scoped active skills option |
| `app/Agents/Runtime/AgentRunBuilder.php` | Pass active skills into `OpenCompanyAgent` |
| `app/Domain/AgentRuntime/Application/RespondToChatMessage.php` | Add multi-skill `$` prefix detection + skill-set injection |
| `app/Domain/Automations/Application/ExecutePromptAutomation.php` | Resolve prompt automation skill sets before building the prompt |
| `app/Domain/Automations/Application/ExecuteScriptAutomation.php` | Allow script automations to discover/run skills through `app.skills.*` |
| `database/migrations/*_create_skills_table.php` | New migration |
| `database/migrations/*_create_skill_versions_table.php` | Immutable version records and publish metadata |
| `database/migrations/*_add_skill_set_columns_to_automations.php` | Optional pinned skill version refs for prompt/script automations |
| `database/seeders/SkillSeeder.php` | Builtin skills |
| `routes/api.php` | Skill CRUD, validation, dry-run, publish, deprecate, archive, rollback, import/export routes |
| `resources/js/Components/chat/MessageInput.vue` | `$` autocomplete |
| `resources/js/Components/chat/assistant/PromptComposer.vue` | `$` autocomplete if assistant shell should support suggestions |
| `resources/js/Pages/Skills/Index.vue` | Management page |
| `resources/js/Pages/Automation/Create.vue` and `resources/js/Pages/Automation/Edit.vue` | Optional skill selector/argument editor for automations |
| `resources/js/composables/useApi.ts` | Skill API methods |
| `resources/js/actions/**` and `resources/js/routes/**` | Generated Wayfinder sources after route/action changes |

---

## Reference: External Skill Systems

Researched Claude Code, OpenClaw, and OpenCode — all converge on the Agent Skills open standard (SKILL.md with YAML frontmatter). Key differences from our design:

| Aspect | Claude Code / OpenClaw / OpenCode | OpenCompany |
|--------|-----------------------------------|-------------|
| Storage | Filesystem (SKILL.md) | Database source of truth + VFS projection |
| Discovery | Directory scanning | DB query, VFS `/skills`, Lua docs + permission filter |
| Invocation | `/slash-command` | one or more `$dollar-prefix` skills, automation skill sets, `app.skills.*`, `use_skill` |
| Management | Edit files | Web UI + agent tool + VFS patch/write |
| Permissions | File-level allow/deny | Per-agent allow/deny/approval via AgentPermission |
| Integration gating | Binary gating, env vars | `integration_id` field + workspace/agent enablement |
| Scope | Per-project / per-user | Per-workspace |

Our design takes the best ideas (prompt injection, meta-tool, catalog) and adapts them for a multi-tenant SaaS with workspace isolation, granular permissions, web-based management, script automations, Lua snippets, and VFS-discoverable operational assets.
