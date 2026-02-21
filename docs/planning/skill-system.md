# Skill System

## Overview

Skills are **reusable prompt templates** that teach agents how to perform specific tasks. They are behavioral instructions — not executable code (that's what tools are for).

| Concept | What it is | Example |
|---------|-----------|---------|
| **Tool** | Executable PHP function the LLM calls | `send_channel_message`, `query_table` |
| **Skill** | Prompt template injected into agent context | `$code-review`, `$standup-report` |
| **Command** | Client-side chat utility | `/compact`, `/status` |

Skills augment an agent's behavior for a specific task by injecting structured instructions into the system prompt when invoked. The agent then follows those instructions using whatever tools it already has access to.

### Why skills?

- **Reusability** — write once, invoke from any channel or entry point
- **Specialization** — give agents expert-level instructions for specific tasks without bloating their identity files
- **User-created** — workspace admins and agents can create skills, not just developers
- **Composable** — skills leverage existing tools; they don't replace them

---

## Invocation: `$skill-name`

Skills use the `$` prefix, distinct from `/` (chat commands) and `@` (mentions):

```
$create-skill
$weekly-report
$translate This paragraph needs to be in Dutch
```

**`$` is always agent-side.** The message is sent to the agent with the skill's instructions injected into its system prompt. The agent processes the request and responds normally.

**`/` stays client-side.** Commands like `/compact` and `/status` are intercepted in the frontend and handled via API calls — no agent involvement.

### Invocation from any entry point

Since skill detection happens in `AgentRespondJob` (server-side, based on message content), `$skills` work identically from:
- Web chat
- Telegram
- DMs
- Agent delegation

No per-channel special handling needed.

---

## Data Model

### `skills` table

```
id              uuid, PK
workspace_id    uuid, FK → workspaces
slug            string          -- invocation handle: "code-review"
name            string          -- display name: "Code Review"
description     text            -- one-line for catalog/autocomplete
content         text            -- full prompt template (markdown)
icon            string          -- iconify icon, default 'ph:lightning'
category        string          -- general, development, writing, data, communication
is_builtin      boolean         -- shipped with the app (not deletable)
is_active       boolean         -- soft disable
integration_id  string|null     -- gate behind integration: 'telegram', 'clickup', etc.
arguments       json|null       -- parameter definitions (see Arguments section)
allowed_tools   json|null       -- tool slugs this skill should use (advisory hint)
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
        'content', 'icon', 'category', 'is_builtin', 'is_active',
        'integration_id', 'arguments', 'allowed_tools', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_builtin' => 'boolean',
            'is_active' => 'boolean',
            'arguments' => 'array',
            'allowed_tools' => 'array',
        ];
    }
}
```

**Design rationale:**
- DB-stored (not filesystem) for multi-workspace isolation and user editability
- `slug` is the unique invocation handle per workspace
- `content` supports `{{arg_name}}` placeholders for argument substitution
- `integration_id` gates the skill — only available when that integration is enabled
- `allowed_tools` is advisory — tells the agent which tools the skill expects, but doesn't enforce
- `is_builtin` prevents deletion but allows content editing

---

## Permission System

### New scope type: `skill`

Add `'skill'` to `AgentPermission.scope_type`. The `scope_key` is the skill slug.

```
scope_type = 'skill'
scope_key  = 'code-review'
permission = 'allow' | 'deny'
requires_approval = true | false
```

### Resolution logic

In `AgentPermissionService`, add `resolveSkillPermission()`:

```php
public function resolveSkillPermission(User $agent, string $skillSlug): array
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

    // Default: allowed, no approval (skills are behavioral, not destructive)
    return ['allowed' => true, 'requires_approval' => false];
}
```

### Integration gating

Skills with `integration_id` set are only available when:
1. The integration is enabled at workspace level (`IntegrationSetting.enabled`)
2. The integration is enabled for the agent (`AgentPermissionService::getEnabledIntegrations()`)

Both checks happen in `SkillRegistry::getSkillsForAgent()`.

### Permission layering summary

```
Workspace skill active?    ─── No  → hidden
Integration enabled?       ─── No  → hidden
Agent permission = deny?   ─── Yes → hidden
Agent permission = allow?  ─── Yes → available (may require approval)
No explicit permission?    ─── default: available
```

---

## Skill Registry

`App\Agents\Skills\SkillRegistry` — single point of truth for resolving skills.

```php
class SkillRegistry
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    /** Get all skills available to a specific agent (filtered). */
    public function getSkillsForAgent(User $agent): Collection;

    /** Resolve a single skill by slug for the current workspace. */
    public function resolveSkill(string $slug): ?Skill;

    /** Build compact skill catalog for the system prompt. */
    public function getSkillCatalog(User $agent): string;

    /** Render skill content with argument substitution. */
    public function renderSkillContent(Skill $skill, array $arguments = []): string;

    /** Get metadata for frontend (autocomplete, management UI). */
    public function getSkillsMeta(User $agent): array;
}
```

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

### Flow 1: User types `$skill-name args`

```
User → MessageInput.vue → Chat.vue → sendMessage API → AgentRespondJob
                                                              │
                                                    detect $ prefix
                                                    resolve skill
                                                    inject into prompt
                                                              │
                                                    OpenCompanyAgent
                                                    (skill in system prompt)
                                                              │
                                                    LLM response
                                                              │
                                                    normal delivery
```

**In `AgentRespondJob::handle()`**, before building the agent:

```php
$activeSkill = null;
if (str_starts_with($this->userMessage->content, '$')) {
    $parsed = SkillParser::parse($this->userMessage->content);
    $registry = app(SkillRegistry::class);
    $skill = $registry->resolveSkill($parsed['slug']);

    if ($skill) {
        $perm = $permissionService->resolveSkillPermission($this->agent, $skill->slug);
        if ($perm['allowed']) {
            $activeSkill = [
                'name' => $skill->name,
                'content' => $registry->renderSkillContent($skill, $parsed['arguments']),
            ];
        }
    }
}

// Pass to OpenCompanyAgent
$agent = OpenCompanyAgent::for($this->agent, $this->channelId, $task->id);
$agent->setActiveSkill($activeSkill); // new method
```

**In `OpenCompanyAgent::buildSections()`**, add skill section:

```php
if ($this->activeSkill) {
    $sections[] = [
        'label' => 'Active Skill',
        'content' => "## Active Skill: {$this->activeSkill['name']}\n\n"
                   . "{$this->activeSkill['content']}\n\n",
    ];
}
```

### Flow 2: Agent uses `use_skill` tool (model-initiated)

The agent sees available skills in the system prompt catalog and can decide to invoke one.

**`UseSkill` tool** registered in `ToolRegistry`:

```php
class UseSkill implements Tool
{
    public function description(): string
    {
        return 'Activate a skill to get specialized instructions for a task.';
    }

    public function handle(Request $request): string
    {
        $slug = $request['skill'];
        $args = $request['arguments'] ?? [];

        $skill = $this->registry->resolveSkill($slug);
        if (!$skill) {
            return "Skill not found. Available: "
                 . $this->registry->getSkillsForAgent($this->agent)->pluck('slug')->join(', ');
        }

        $perm = $this->permissionService->resolveSkillPermission($this->agent, $slug);
        if (!$perm['allowed']) {
            return "You don't have permission to use '{$slug}'.";
        }

        return $this->registry->renderSkillContent($skill, $args);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'skill' => $schema->string('Skill slug to activate')->required(),
            'arguments' => $schema->object('Named arguments for the skill'),
        ];
    }
}
```

The tool returns the skill's rendered content as text. The LLM incorporates it into its reasoning and follows the instructions. Simple, no magic.

### Flow 3: Agent manages skills via `manage_skill` tool

Agents can create, edit, list, and delete skills:

```php
class ManageSkill implements Tool
{
    // Actions: list, create, update, delete, get
    // Follows same pattern as ManageDocument, ManageAgent
    // Only non-builtin skills can be deleted
    // Creates skills scoped to the agent's workspace
}
```

Registered in `ToolRegistry` under the `'workspace'` app group.

---

## System Prompt Integration

### Skill catalog (always present, lightweight)

Added as a section in `OpenCompanyAgent::buildSections()`, after the Apps catalog:

```
## Skills

Invoke with $skill-name or use_skill tool. Use get_tool_info("skills") for details.

create-skill — Guide for creating new skills with proper structure and arguments
```

One line per skill. Costs ~10 tokens per skill. Capped at 30 skills in the catalog; beyond that, show "... and N more (use get_tool_info('skills') to list all)". Initially only `create-skill` appears; user/agent-created skills populate the catalog over time.

### Active skill (injected on invocation)

Only when a skill is invoked — full content appears as an "Active Skill" section. Token cost depends on the skill's content length (skill author's responsibility to keep it reasonable).

### Token budget

```
Catalog:  ~10 tokens/skill × 30 skills = ~300 tokens max
Active:   variable (skill content length)
Total:    negligible impact on context window
```

---

## Integration-Specific Skills

Skills can be gated behind an integration via the `integration_id` field. No integration-specific skills ship as builtins — they are all user/agent-created.

Example: a user could create `$tg-broadcast` with `integration_id = 'telegram'`. When Telegram is not enabled (workspace or agent level), the skill is invisible — it won't appear in the catalog, autocomplete, or `use_skill` results.

When creating a skill via the `$create-skill` builtin or `manage_skill` tool, the agent can ask whether to gate the skill behind an integration.

---

## Frontend

### `$` Autocomplete in MessageInput.vue

Parallel to the existing `/` command detection:

```typescript
// Skill detection
const showSkillsPopup = ref(false)
const skillQuery = ref('')
const availableSkills = ref<Skill[]>([])

const checkForSkill = () => {
  if (message.value.startsWith('$')) {
    skillQuery.value = message.value.slice(1).split(' ')[0]
    showSkillsPopup.value = true
  } else {
    showSkillsPopup.value = false
  }
}
```

Skills fetched from `GET /api/skills` on component mount. Popup shows matching skills with icon, `$slug`, and description. Same UI pattern as the existing commands popup.

### Skills Management Page

Route: `/w/{workspace_slug}/skills`

- List all workspace skills (name, slug, category, integration badge, builtin badge, active toggle)
- Create/Edit modal: name, slug, description, icon, category, integration gate, arguments, content editor with `{{placeholder}}` highlighting
- Builtin skills: editable but not deletable, with "Reset to default" action
- Delete confirmation for user-created skills

### Agent Skill Permissions

On the Agent detail page (capabilities tab), add a "Skills" section alongside the existing tool permissions:

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
            4. **Content** — Write the prompt template. Use `{{arg_name}}` for argument placeholders. The content should be clear instructions that any agent can follow.
            5. **Integration gate** — Ask if this skill should only be available when a specific integration is enabled.
            6. **Create** — Use the `manage_skill` tool with action "create" to save the skill.

            Tips for good skill content:
            - Be specific and actionable — the agent should know exactly what to do
            - Reference tools the agent should use (e.g. "use the query_table tool to fetch data")
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
     * Parse "$skill-name --flag value positional text" into structured data.
     *
     * @return array{slug: string, arguments: array<string, string>, remainder: string}
     */
    public static function parse(string $input): array;
}
```

Supports:
- `$skill-name` — slug only
- `$skill-name some free text` — assigned to first required argument or `remainder`
- `$skill-name --focus security` — named arguments
- `$skill-name --focus security Review this file` — mixed

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

---

## Implementation Phases

### Phase 1: Core

1. Migration: `create_skills_table`
2. `Skill` model with `BelongsToWorkspace`
3. `SkillRegistry` service
4. `SkillParser` utility
5. Add `resolveSkillPermission()` to `AgentPermissionService`
6. `SkillController` + API routes (CRUD)
7. `SkillSeeder` — seeds only `$create-skill` builtin per workspace
8. Skill catalog section in `OpenCompanyAgent::buildSections()`
9. `$` prefix detection + skill injection in `AgentRespondJob`
10. `UseSkill` tool in `ToolRegistry` (system app group)
11. `ManageSkill` tool in `ToolRegistry` (workspace app group)

### Phase 2: Frontend

12. `$` autocomplete popup in `MessageInput.vue`
13. `Skills/Index.vue` management page
14. Skill editor modal (name, content, arguments, integration gate)
15. Agent skill permissions on capabilities tab
16. Navigation: add Skills to sidebar
17. `useApi.ts`: skill endpoints

### Phase 3: Polish

18. Skill usage analytics (invocation count per skill)
19. "Reset to default" for builtin skills
20. Skill argument validation + error messages in chat
21. `get_tool_info("skills")` support in ToolRegistry
22. Skill categories filter on management page

---

## Key Files

| File | Change |
|------|--------|
| `app/Models/Skill.php` | New model |
| `app/Agents/Skills/SkillRegistry.php` | New service |
| `app/Agents/Skills/SkillParser.php` | New utility |
| `app/Agents/Tools/System/UseSkill.php` | New tool |
| `app/Agents/Tools/Workspace/ManageSkill.php` | New tool |
| `app/Http/Controllers/Api/SkillController.php` | New controller |
| `app/Services/AgentPermissionService.php` | Add `resolveSkillPermission()` |
| `app/Agents/Tools/ToolRegistry.php` | Register `use_skill`, `manage_skill`, add to APP_GROUPS |
| `app/Agents/OpenCompanyAgent.php` | Add skill catalog + active skill sections |
| `app/Jobs/AgentRespondJob.php` | Add `$` prefix detection + skill injection |
| `database/migrations/*_create_skills_table.php` | New migration |
| `database/seeders/SkillSeeder.php` | Builtin skills |
| `routes/api.php` | Skill CRUD routes |
| `resources/js/Components/chat/MessageInput.vue` | `$` autocomplete |
| `resources/js/Pages/Skills/Index.vue` | Management page |
| `resources/js/composables/useApi.ts` | Skill API methods |

---

## Reference: External Skill Systems

Researched Claude Code, OpenClaw, and OpenCode — all converge on the Agent Skills open standard (SKILL.md with YAML frontmatter). Key differences from our design:

| Aspect | Claude Code / OpenClaw / OpenCode | OpenCompany |
|--------|-----------------------------------|-------------|
| Storage | Filesystem (SKILL.md) | Database (multi-tenant) |
| Discovery | Directory scanning | DB query + permission filter |
| Invocation | `/slash-command` | `$dollar-prefix` |
| Management | Edit files | Web UI + agent tool |
| Permissions | File-level allow/deny | Per-agent allow/deny/approval via AgentPermission |
| Integration gating | Binary gating, env vars | `integration_id` field + workspace/agent enablement |
| Scope | Per-project / per-user | Per-workspace |

Our design takes the best ideas (prompt injection, meta-tool, catalog) and adapts them for a multi-tenant SaaS with workspace isolation, granular permissions, and web-based management.
