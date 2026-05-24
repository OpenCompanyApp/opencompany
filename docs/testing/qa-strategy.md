# QA Testing Strategy — Full Project Audit

Status: Historical QA plan. The commit range and "uncommitted changes" notes below describe the March/April audit window, not the current working tree. Use it as regression-test coverage guidance, not as a live git-state report.

**Scope:** All changes in current git tree (commits ce35785 through df74cb3, plus uncommitted work)

---

## What Changed

### 1. Integration Ecosystem Refactor (Mar 30 – Apr 5)

Three commits that restructured how integrations are organized, shared, and loaded.

- **Commit `4cffd75`** — ToolRegistry decomposed into 15 `BuiltInToolProvider` classes. New `integration-core` package with shared contracts. 13 integration packages consolidated into monorepo. Lua docs for 6 integrations.
- **Commit `24e2cc8`** - Lua doc generation and LuaBridge moved to shared `integration-core`. This older note predates the app-owned AI runtime that replaced Prism-era provider registration.
- **Commit `df74cb3`** — Monorepo moved from `tmp/integrations/` to `../integrations/`.

### 2. File Management System (Mar 1–2)

Complete virtual filesystem with multi-disk storage (local, S3, SFTP), agent file tools, permission-based access, and Finder-style UI.

- `WorkspaceFile` and `WorkspaceDisk` models with encrypted disk configs
- `FileSystemService` — upload, write, read, createFolder, move, copy, delete, search, agent home folders
- `AgentPermissionService` — tool/channel/folder/integration permissions with deny→exempt→explicit→default cascade
- 10 agent file tools (list_disks, list_files, read_file, write_file, create_folder, move_file, copy_file, delete_file, search_files, get_file_info)
- FileController + WorkspaceDiskController APIs
- Finder-style Vue UI with grid/list views, drag-drop upload, inline rename, keyboard shortcuts

### 3. Automation / Script System (Feb 28 – Mar 1)

Prompt and Luau script automations with Monaco editor, cron scheduling, run history.

- Two execution modes: **prompt** (agent-driven, costs tokens) and **script** (Luau sandbox, zero cost)
- Auto-disable after 5 consecutive failures
- 6 agent tools: list, get, create, update, delete, run
- `RunScriptAutomationJob` with `ctx` table (automation_id, run_number, last_run_at, schedule)
- Monaco editor with Prompt/Script toggle, cron builder, run history
- Task source labels (Chat, Manual, Automation, Delegated, Agent Ask)

### 4. Chat UI Polish (Feb 28)

Full chat interface overhaul.

- Channel list with filter chips (All/Unread/DMs/Channels/External), search, compose dropdown
- Telegram-style message bubbles with grouping, inline timestamps, lightbox
- Rich input with drag-drop upload, @mentions, /commands, formatting toolbar, emoji picker
- Channel info sidebar with collapsible sections, member filters, notification settings

### 5. Telegram / File Forwarding (Mar 2)

Fixed file forwarding to external platforms.

- Workspace file URLs detected in any format (markdown, bare URL, image embed)
- Files forwarded as proper Telegram documents (not silently dropped)
- PDFs and non-image files sent with `forceDocument`

### 6. LLM Provider & Token Metrics (Feb 28 – Mar 4)

- `TokenMetrics` helper — centralized token/cost calculation across all job types
- `SetsWorkspaceContext` trait — workspace binding for queue jobs
- Multiple custom providers (GLM, Kimi, MiniMax, Codex)
- Analytics dashboard with cost estimation, breakdowns by agent/model/source

### 7. Security Hardening (Feb 28)

37-file audit commit.

- IDOR fixes: auth checks on user update, workspace scoping on 14 controllers
- XSS prevention: DOMPurify sanitization via `sanitize.ts`, applied in `useMarkdown.ts`
- Job status resolution: proper priority ordering (sleeping > awaiting_approval > awaiting_delegation > idle)
- ApprovalExecutionService: workspace context binding for approved tools
- Memory leak fixes: event listener cleanup in `usePresence.ts`, `useKeyboardShortcuts.ts`

### 8. Historical Uncommitted Changes From Audit Window

Multi-account integration settings and additional refinements.

- `IntegrationSetting` model: multi-account support migration
- `DynamicProviderResolver`, `OpenCompanyAiProviderFactory`, `PromptCachePolicy`: provider resolution and runtime updates
- `ToolRegistry`: further refinements
- `McpServerRegistrar`, `McpToolProvider`: MCP tool registration updates
- `AgentChatService`, `IntegrationController`, `AgentController`: service updates
- `IntegrationSettingCredentialResolver`: credential resolution updates
- New `docs/ecosystem/` directory

---

## Risk Assessment

| Area | Risk | Why |
|------|------|-----|
| Integration refactor | **High** | All integrations reorganized. Missing `../integrations/` breaks everything. |
| File management | **High** | New subsystem with storage ops, permissions, agent tools. Data loss potential. |
| Automation / Scripts | **High** | Luau sandbox execution, auto-disable, destructive keep_history=false. |
| Security hardening | **High** | 14 controllers rescoped — any regression is an auth bypass or false denial. |
| Telegram forwarding | **Medium** | Single-try, echo prevention, file handling. |
| Chat UI | **Medium** | XSS surface (mitigated), UX edge cases. |
| Token metrics | **Low** | Utility class, but billing accuracy matters. |
| Uncommitted changes | **Medium** | Multi-account integration, provider changes — needs verification. |

---

## Test Environment Setup

1. Ensure `../integrations/` sibling directory exists and is populated
2. `composer install` — verify no path resolution errors
3. `php artisan migrate` — run all pending migrations (including `2026_04_05` multi-account)
4. `php artisan config:clear && php artisan cache:clear`
5. Build frontend: `npm run build`
6. Create a test workspace with at least 2 agents
7. Configure at least one external channel (Telegram) for forwarding tests
8. Have test credentials for at least one integration (ClickUp, Google, etc.)
9. Have at least one custom LLM provider configured (GLM, Kimi, or MiniMax)

---

## Regression Smoke Test

Before detailed testing, confirm nothing is fundamentally broken:

- [ ] App loads without errors (check `storage/logs/laravel.log`)
- [ ] `php artisan tinker` — no autoloading errors
- [ ] Login works
- [ ] Workspace switcher works (sidebar agents/channels refresh)
- [ ] Agent responds to a basic chat message
- [ ] Agent can use built-in tools (tasks, memory, lists, tables)
- [ ] `php artisan test` — test suite passes
