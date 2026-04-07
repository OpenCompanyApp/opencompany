# Feature Test Map — Full Project Audit

Detailed test cases for all changes in the current git tree. Check off each item as you verify.

---

## 1. Integration Ecosystem Refactor

**Commits:** `4cffd75`, `24e2cc8`, `df74cb3`

### Composer & Autoloading

- [ ] `../integrations/` directory exists as a sibling to the project root
- [ ] `../integrations/core/` exists (integration-core package)
- [ ] `../integrations/packages/` exists with all integration subdirectories
- [ ] `composer install` completes without errors
- [ ] `composer show | grep opencompanyapp` lists all integration packages + integration-core + prism-relay
- [ ] `php artisan tinker` boots without class-not-found errors
- [ ] `ToolRegistry` resolves as singleton
- [ ] `ToolProviderRegistry` resolves

### BuiltInToolProviders (15 providers)

- [ ] **AgentsToolProvider** — `list_agents`, `contact_agent` work
- [ ] **AutomationsToolProvider** — list, get, create, update, delete, run
- [ ] **CalendarToolProvider** — calendar tools available
- [ ] **ChatToolProvider** — `send_channel_message`, `get_channel`, `list_channels`
- [ ] **DocsToolProvider** — `query_documents`, `index_document`
- [ ] **FilesToolProvider** — `list_files`, `read_file`, `write_file`, `search_files`
- [ ] **ListsToolProvider** — list CRUD tools
- [ ] **LuaToolProvider** — `lua_exec`, `lua_read_doc`, `lua_search_docs`
- [ ] **MemoryToolProvider** — `save_memory`, `recall_memories`
- [ ] **SvgToolProvider** — SVG generation
- [ ] **SystemToolProvider** — system info
- [ ] **TablesToolProvider** — table query/management
- [ ] **TasksToolProvider** — task tools
- [ ] **WorkspaceToolProvider** — workspace management

### Direct vs Lua-only Tools

Only `DIRECT_TOOL_GROUPS` (tasks, system, agents, memory, lua) are direct AI tools. Everything else via `lua_exec`.

- [ ] Agent uses `tasks` tools directly
- [ ] Agent uses `memory` tools directly
- [ ] Agent accesses `chat` tools via `lua_exec`
- [ ] Agent accesses `files` tools via `lua_exec`
- [ ] Agent accesses `tables` tools via `lua_exec`
- [ ] MCP tools still register and work via Lua

### Integration Packages

**No-auth (test first):**
- [ ] **CoinGecko** — `app.integrations.coingecko.*` returns data
- [ ] **ExchangeRate** — `app.integrations.exchangerate.*` returns data
- [ ] **Celestial** — `app.integrations.celestial.*` returns data
- [ ] **WorldBank** — `app.integrations.worldbank.*` returns data

**Auth-required:**
- [ ] **ClickUp** — configure API key → returns data
- [ ] **Google** — configure OAuth → returns data
- [ ] **Plausible** — configure API key → returns data
- [ ] **TickTick** — configure credentials → returns data

**ConfigurableIntegration:**
- [ ] Config UI renders on Integrations settings page
- [ ] "Test Connection" button returns success/failure
- [ ] Invalid credentials caught and shown as error

### Lua Doc Generation

- [ ] `lua_read_doc("overview")` — full namespace index
- [ ] `lua_read_doc("chat")` — detailed docs with parameter tables
- [ ] `lua_read_doc("integrations.clickup")` — includes supplementary Lua docs
- [ ] `lua_search_docs("send message")` — scored results
- [ ] `lua_read_doc("nonexistent")` — helpful error with available namespaces
- [ ] Namespaces sorted: internal → integrations → mcp

### LuaBridge Call Routing

- [ ] Table args: `app.chat.send_channel_message({channel_id = "x", content = "hello"})` works
- [ ] Positional args: `app.chat.send_channel_message("x", "hello")` maps to params
- [ ] Invalid function: `app.chat.nonexistent()` returns error with suggestions
- [ ] New-style integration tool executes via `Tool::execute()`
- [ ] Legacy tool executes via `handle(Request)` with snake_case→camelCase
- [ ] JSON auto-decoding: legacy JSON string → Lua table
- [ ] Call log records path, duration, status, icon, name, group
- [ ] Failed calls in call log with error message

### PrismRelay (Custom LLM Providers)

- [ ] GLM — agent generates response
- [ ] GLM Coding — agent generates response
- [ ] Kimi — agent generates response
- [ ] Kimi Coding — agent generates response
- [ ] MiniMax — agent generates response
- [ ] MiniMax CN — agent generates response
- [ ] No stored models → falls back to `ProviderMeta::defaultModel()`
- [ ] No custom URL → falls back to `ProviderMeta::url()`
- [ ] Unknown provider key → `InvalidArgumentException`

### Monorepo Path & CI

- [ ] `composer.json` path repo points to `../integrations/*`
- [ ] `.github/workflows/ci.yml` clones to `../integrations`
- [ ] `.claude/commands/create-integration.md` references `../integrations/`
- [ ] CI monorepo clone + `composer install` succeeds
- [ ] `php artisan test` passes

---

## 2. File Management System

### Migrations & Models

- [ ] `workspace_files` and `workspace_disks` tables exist
- [ ] Default "Local" disk auto-seeded per workspace
- [ ] `WorkspaceDisk.config` is encrypted at rest

### File Browser UI (`/files`)

- [ ] Page loads with grid view and file/folder icons
- [ ] Switch between grid and list view
- [ ] Create new folder (Cmd+Shift+N or toolbar)
- [ ] Upload file via drag-and-drop
- [ ] Upload file via toolbar button
- [ ] Inline rename (click name or press Enter)
- [ ] Move file to different folder
- [ ] Copy file
- [ ] Delete file (Delete key or context menu)
- [ ] Preview file (Space or click) — slideover opens
- [ ] Search files — results update as you type
- [ ] Select all (Cmd+A)
- [ ] Breadcrumb navigation
- [ ] Sidebar: disk list and folder tree

### Storage Disks

- [ ] Settings → Storage section visible
- [ ] Add new disk (S3 or SFTP) — config modal
- [ ] Test connection button returns success/failure
- [ ] Switch default disk
- [ ] Secrets masked in API responses (contain `****`)

### Agent File Tools (10 tools)

- [ ] `list_disks` — returns available disks
- [ ] `list_files` — files in folder
- [ ] `read_file` — file contents (text) or metadata+download URL (binary)
- [ ] `write_file` — creates/overwrites, auto-creates parent folders
- [ ] `create_folder` — creates with auto-intermediate
- [ ] `move_file` — moves between folders
- [ ] `copy_file` — copies file (not folders)
- [ ] `delete_file` — deletes file/folder (recursive for non-empty)
- [ ] `search_files` — by name/description, MIME filter
- [ ] `get_file_info` — metadata

### Agent Permissions

- [ ] Agent with no file_folder permissions can only access `/agents/{slug}/` home folder
- [ ] Grant folder access → agent can read/write that folder
- [ ] Revoke folder access → agent denied
- [ ] Wildcard `*` grants unrestricted access
- [ ] Behavior modes: `autonomous` (no approval), `supervised` (write needs approval), `strict` (all needs approval)
- [ ] Agent capabilities page shows file folder access section

### Edge Cases

- [ ] Overwrite: `write_file` to same path silently overwrites
- [ ] Copy folder: returns error (only files can be copied)
- [ ] Recursive delete without `recursive=true` on non-empty folder → error
- [ ] Agent name change: old slug folder still exists but doesn't match new home folder path
- [ ] Disk update with masked `****` values → preserves original secrets

---

## 3. Automation / Script System

### Create Automation

- [ ] Navigate to Automation → Create
- [ ] Toggle Prompt/Script mode — UI switches (agent selector ↔ Monaco editor)
- [ ] Script mode shows "Luau" badge and API Reference link
- [ ] Create prompt automation — agent, schedule, enable
- [ ] Create script automation with `--!strict` header — schedule, enable
- [ ] Script without `--!strict` header → validation error

### Run Automation

- [ ] Prompt automation runs at scheduled time → task created, agent responds
- [ ] Script automation runs at scheduled time → task created, Luau executes, output posted to channel
- [ ] `ctx` table populated: `ctx.automation_id`, `ctx.run_number`, `ctx.last_run_at`, `ctx.schedule`
- [ ] Script calls `app.*` API: workspace tools work
- [ ] Script with syntax error → task shows failure status, error captured
- [ ] Auto-disable: trigger 5 consecutive failures → automation sets `is_active = false`

### Run History & Edit

- [ ] Edit page shows run history with status dots
- [ ] "Run" button triggers manual execution
- [ ] Run detail modal: status, duration, output
- [ ] Link to task from run details

### Task Source Labels

- [ ] Chat message → "Chat" label
- [ ] Manual run → "Manual" label
- [ ] Prompt automation → "Automation" label
- [ ] Script automation → "Automation" + "Luau Script" badge
- [ ] Delegated task → "Delegated" label

### Edge Cases

- [ ] `keep_history = false`: channel messages cleared before each run (destructive — verify intended)
- [ ] Script runs synchronously within `RunAutomationJob` — verify timeout handling
- [ ] Retry guard: on `attempts() > 1`, skips if recent message exists in last 30 min
- [ ] Channel auto-creation: no `channel_id` → creates DM with creator + agent

---

## 4. Chat UI

### Channel List

- [ ] Filter chips work: All, Unread, DMs, Channels, External
- [ ] Search bar expands/collapses
- [ ] Pinned channels section
- [ ] Compose dropdown: New Message, New Channel, Connect External

### Messages

- [ ] Telegram-style bubbles with grouped border-radius
- [ ] Consecutive same-sender messages visually grouped (5-min window)
- [ ] Inline timestamps on hover
- [ ] Code blocks with syntax highlighting
- [ ] Image messages inline with lightbox (zoom, pan, drag)
- [ ] Approval cards render correctly
- [ ] Reactions display and can be toggled
- [ ] Thread previews expandable
- [ ] Hover actions: emoji, reply, thread, more

### Message Input

- [ ] Drag-and-drop file upload
- [ ] @mentions popup with fuzzy search (keyboard nav: up/down/enter/escape)
- [ ] /commands popup
- [ ] Formatting toolbar: bold, italic, strikethrough, code, codeblock, quote, link
- [ ] Emoji picker opens and inserts
- [ ] Attachment preview grid with progress
- [ ] Character count for long messages

### Channel Info

- [ ] Collapsible sections: About, Pinned, Shared Media, Shared Files, Members
- [ ] Members filter: All / Humans / Agents / Online
- [ ] Notification settings (all/mentions/none)

---

## 5. Telegram / File Forwarding

- [ ] Agent sends inline workspace file URL → forwarded as Telegram document
- [ ] Agent sends PDF attachment → forwarded as Telegram document (not dropped)
- [ ] Agent sends image attachment → forwarded as Telegram photo
- [ ] File URLs detected in: markdown link, bare URL, image embed formats
- [ ] No echo loop: external messages not re-forwarded to external
- [ ] Content stripping: image URLs removed from text after sending as attachment

---

## 6. LLM Providers & Token Metrics

### Token Metrics

- [ ] Chat task → token counts recorded (prompt, completion, cache read/write)
- [ ] Automation task → same metrics
- [ ] Tool call count recorded
- [ ] Generation time and tokens/second computed

### Analytics Dashboard

- [ ] Charts load on analytics page
- [ ] Breakdown by agent — correct
- [ ] Breakdown by model — correct
- [ ] Breakdown by source — correct
- [ ] Icons render (regression check)

### Workspace Context

- [ ] Task in Workspace A → belongs to Workspace A
- [ ] Task in Workspace B → belongs to Workspace B
- [ ] No context leak between queued jobs

---

## 7. Security

### Workspace Scoping

- [ ] Agent in Workspace A cannot access Workspace B data
- [ ] API returns 403 for cross-workspace resource access
- [ ] File API workspace-scoped

### IDOR Prevention

- [ ] User cannot update another user's profile
- [ ] User cannot update another user's presence

### XSS Prevention

- [ ] Message with `<script>alert('xss')</script>` renders as text, doesn't execute
- [ ] Agent name with HTML entities renders safely
- [ ] File names with special characters render safely
- [ ] Markdown output sanitized via DOMPurify (verify `<iframe>`, `<script>` stripped)

### Agent Permissions

- [ ] System Automation agent hidden from inter-agent communication
- [ ] Agent cannot use tools it lacks permission for
- [ ] Approval flow: `strict` mode → all tools create approval request
- [ ] Approval execution binds workspace context correctly

### Job Status Resolution

- [ ] Agent sleeping + awaiting approval → sleeping wins
- [ ] Agent awaiting approval + awaiting delegation → approval wins
- [ ] Agent idle → status is `idle`
- [ ] `finally` block in jobs resolves status correctly on both success and failure

---

## 8. Uncommitted Changes

### Multi-Account Integration Settings

- [ ] Migration `2026_04_05` runs successfully
- [ ] Integration settings support multiple accounts per integration
- [ ] Existing integration settings migrated correctly

### Provider & MCP Updates

- [ ] `DynamicProviderResolver` resolves all providers correctly
- [ ] `GlmPrismGateway` generates responses
- [ ] MCP server registration works (`McpServerRegistrar`)
- [ ] MCP tool provider lists tools correctly
- [ ] `AgentChatService` sends messages
- [ ] `IntegrationController` CRUD works
- [ ] `AgentController` endpoints work
- [ ] `IntegrationSettingCredentialResolver` resolves credentials

---

## Cross-Cutting Integration Tests

- [ ] **Automation + Files:** Script automation writes file via `app.files.write_file(...)` → file appears in browser
- [ ] **Automation + Chat:** Script calls `app.chat.send_channel_message(...)` → message appears + forwards to Telegram
- [ ] **Integration + Lua docs:** `lua_read_doc("integrations.clickup")` → docs with supplementary content
- [ ] **Files + Telegram:** Agent shares workspace file in Telegram channel → forwarded as document
- [ ] **Provider + Lua:** Switch to Kimi provider, execute Lua script calling integration → full pipeline works
- [ ] **Permissions + Automation:** Agent with restricted file access runs automation → permission enforced
- [ ] **Security + Chat:** Send XSS in chat → sanitized, forwarded to Telegram safely

---

## Post-Deploy Verification

- [ ] `../integrations/` cloned and up to date on server
- [ ] `php artisan migrate --force` succeeds
- [ ] `composer install --no-dev --optimize-autoloader` succeeds
- [ ] `php artisan config:cache` succeeds
- [ ] `php artisan route:cache` succeeds
- [ ] Queue workers restart: `php artisan queue:restart`
- [ ] `storage/logs/laravel.log` — no class-not-found or provider-not-found errors
- [ ] Trigger agent task — completes with tools working
- [ ] At least one integration returns live data
- [ ] Scheduled automations visible: `php artisan schedule:list`
