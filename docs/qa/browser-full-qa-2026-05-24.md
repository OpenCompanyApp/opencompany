# Browser QA Checkup - 2026-05-24

## Scope

Deep manual browser QA for the local OpenCompany app at `http://opencompany.test`, focused on the new AI chat UI but covering the main authenticated app surfaces:

- App shell, sidebar, workspace routing, dashboard
- AI chat: new-chat state, existing assistant thread, composer, status panel, run/tool boxes, task links
- Task detail: execution trace, Lua tool detail, tool argument/result rendering, context panel
- Integrations: catalog, installed services, MCP modal, webhook/API-key areas
- Tables and calendar: list/detail pages plus create modals opened and cancelled
- Docs, files, settings: basic navigation/search/read-only interaction
- Mobile viewport smoke check at 390px

I avoided save/send/destructive actions. Where I opened modals, I closed them without submitting. The database and `storage/app` were snapshotted before QA.

## Evidence

- DB backup: `/tmp/opencompany-qa-20260524T183920Z/opencompany.before.dump`
- Storage backup: `/tmp/opencompany-qa-20260524T183920Z/storage-app.before.tgz`
- Browser route evidence: `/tmp/opencompany-qa-20260524T183920Z/browser-route-results-stable.json`
- Isolated Playwright evidence: `/tmp/opencompany-qa-20260524T183920Z/playwright-route-results.json`
- Manual evidence files: `/tmp/opencompany-qa-20260524T183920Z/manual-*.json`
- Manual screenshots: `/tmp/opencompany-qa-20260524T183920Z/manual-*.png`

## Findings

### 1. Integrations page calls a pending table and returns 500

Severity: High

Manual result: `/w/default/integrations` visually renders, but the console records `Failed to load webhooks: AxiosError: Request failed with status code 500`. The page still says "No webhooks configured", which hides the backend failure from the user.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-integrations-initial.json`
- Backend: `SQLSTATE[42P01]: Undefined table: relation "integration_webhooks" does not exist`
- Code: `app/Http/Controllers/Api/IntegrationWebhookController.php:20` queries `IntegrationWebhook::forWorkspace()`
- Migration status: `database/migrations/2026_05_24_000002_create_integration_webhooks_table.php` is pending

Suggested fix:

Run or fold the pending webhook migration into the current local schema path, then add a regression test for `GET /api/integration-webhooks` in an authenticated workspace. In the UI, treat webhook load failure as a visible inline error instead of rendering the empty state.

### 2. New chat starter cards fail instead of pre-filling or sending

Severity: High

Manual result: from `New assistant chat`, clicking `Plan a task` leaves the composer empty/disabled and shows `Could not open an assistant chat for this message.`

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-new-chat-plan-card.json`
- Code: `resources/js/Components/chat/assistant/EmptyState.vue:21` emits the prompt
- Code: `resources/js/Pages/Chat.vue:534` tries to open an agent chat before sending
- Code: `resources/js/Pages/Chat.vue:540` sets the visible failure message when no channel is resolved

Suggested fix:

Do not immediately send starter prompts. Clicking a starter card should fill the composer draft and focus it. If the intended behavior is one-click send, resolve/create the DM first and surface a real error if `/api/dm/{agent}` fails. Add a feature test around `handleUnifiedSend` for the draft-new-assistant state.

### 3. `/w/test/chat?agent=a1` shows the wrong workspace agent

Severity: High

Manual result: the `test` workspace has no `a1` agent, but the chat UI still shows Atlas/`a1` in the new-chat selector state. This is confusing at minimum and may be a workspace scoping leak.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/feature-invalid-agent-test-chat.png`
- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-new-chat-plan-card.json`
- Code: `resources/js/Pages/Chat.vue:229` accepts `agent` query params directly
- Code: `resources/js/Pages/Chat.vue:251` falls back to the first assistant channel

Suggested fix:

Validate `agent` query params against the active workspace's agent list before setting `selectedAgentId`. If the agent is invalid for the workspace, clear the query or show an explicit "Agent not available in this workspace" state. Add a workspace-scoped browser or feature test for `/w/test/chat?agent=a1`.

### 4. Chat status panel is not dismissible by obvious user actions

Severity: Medium

Manual result: clicking `Status` opens the panel, but clicking `Status` again and pressing Escape both leave it open. On the new-chat state it also reports `No agent selected · model unknown · refreshing`, which is not useful after an agent is visibly selected.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-chat-status.json`
- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-chat-status-dismiss.json`
- Code: `resources/js/Pages/Chat.vue:551` only opens the status panel
- Code: `resources/js/Components/chat/assistant/AssistantStatusPanel.vue:26` has a dismiss button, but there is no Status toggle behavior

Suggested fix:

Make the `Status` button a toggle: if the panel is open, close it. Add Escape handling on the conversation surface while the panel is open, and ensure the panel receives a workspace-valid `selectedAgentId` in new-chat mode.

### 5. Shared modals do not close on Escape

Severity: Medium

Manual result: `Add MCP Server`, `New Table`, and `New Event` all open correctly, but Escape does not close any of them. Visible Cancel/Close buttons work.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-integrations-after-escape.json`
- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-tables-after-escape.json`
- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-calendar.json`
- Code: `resources/js/Components/shared/Modal.vue:18` does not emit `close` on Escape
- Code: many callers pass `:open="true" @close=...` rather than `v-model:open`

Suggested fix:

Make `Modal.vue` explicitly emit `close` when Reka tries to close, including Escape and outside interactions, or convert callers to controlled `v-model:open`. The current `DialogRoot v-model:open="isOpen"` updates internal state, but `:open="true"` parents are not notified.

### 6. Task detail trace uses raw tool IDs instead of human names

Severity: Medium

Manual result: chat run boxes show readable names like `Update Task` and `Execute Lua Code`, but task detail still shows `UpdateTask`, `CodeExec`, and `SetTaskStatus`.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-task-lua-detail.json`
- Code: `resources/js/Components/tasks/ExecutionTrace.vue:41` renders `step.description` directly
- Chat already has similar humanization in `resources/js/Components/chat/assistant/ThinkingPanel.vue`

Suggested fix:

Normalize task-step descriptions for tool steps in `ExecutionTrace.vue`, reusing the chat-side tool display logic or centralizing a shared `humanizeToolName` helper. Prefer explicit catalog display names where available, then fall back to splitting camel case.

### 7. Tool argument/result blocks are dark in light mode

Severity: Medium

Manual result: task output, tool arguments, and markdown raw views render with `bg-neutral-900`/GitHub-dark highlighting in light mode. This reproduces the "dark syntax highlighting on light mode" issue.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-task-update-tool-detail.json`
- Code: `resources/js/Components/tasks/ExecutionTrace.vue:86`
- Code: `resources/js/Components/tasks/ExecutionTrace.vue:93`
- Code: `resources/js/Pages/Tasks/Show.vue:402`

Suggested fix:

Introduce shared syntax block classes that use light backgrounds in light mode and dark backgrounds only under `dark:`. Also configure `useHighlight`/highlight.js with a light theme for light mode. Add a visual regression screenshot for a Lua task in light mode.

### 8. Generic tool arguments are JSON when the user-facing action is Lua-like

Severity: Medium

Manual result: `UpdateTask` expands to raw JSON under `ARGUMENTS`. The Lua execution step gets a dedicated `LUA CODE` rendering, but app-owned tools still expose developer-shaped JSON even when the user expects a friendlier command/action display.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-task-update-tool-detail.json`
- Code: `resources/js/Components/tasks/ExecutionTrace.vue:81` uses generic JSON rendering for all non-`CodeExec` tools

Suggested fix:

Add per-tool renderers for common OpenCompany tools. For task tools, render fields as a readable action summary (`Task`, `Title`, `Description`, `Status`) and optionally keep JSON behind a collapsed "Raw" toggle. For Lua bridge calls, keep the dedicated Lua renderer.

### 9. Task detail "View Chat" did not navigate during manual test

Severity: Medium

Manual result: from the Lua task detail page, pressing `View Chat` left the browser on the same task URL in the app-browser run.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-chat-task-links.json`

Suggested fix:

Check the `View Chat` handler in `resources/js/Pages/Tasks/Show.vue`. It should use Wayfinder/workspace route helpers and navigate to the backing chat channel/agent when available. If no channel exists, the button should be disabled with a tooltip explaining why.

### 10. Collapsed assistant sidebar has duplicate accessible names

Severity: Medium

Manual result: the collapsed/compact conversation rail exposes many separate `Atlas` buttons with identical labels. In automation this appeared as 15 `Atlas` targets; for keyboard and screen-reader users it is ambiguous.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-chat-atlas-thread.json`
- Code: `resources/js/Components/chat/assistant/ConversationSidebar.vue:56`
- Code: `resources/js/Components/chat/assistant/ConversationSidebar.vue:61` sets only `title=conversation.title`

Suggested fix:

Give each conversation button a unique accessible label, for example `aria-label="Open Atlas conversation: <latest preview or date>"`, and keep visible text unchanged. In collapsed mode, include a tooltip with the same unique context.

### 11. Mobile task detail clips a context tab label

Severity: Low

Manual result: at 390px width, chat has no horizontal overflow, but the task detail context tabs clip `Conversation History 52 messages · 7.1K tokens`.

Evidence:

- Browser: `/tmp/opencompany-qa-20260524T183920Z/manual-mobile-task.json`

Suggested fix:

Make the LLM context tabs horizontally scrollable on small screens or switch them to a select/accordion layout below `sm`. The tab buttons should have fixed min widths or allow wrapping.

### 12. Dev browser logs show Monaco worker cross-origin warnings

Severity: Low

Manual result: opening pages that include Monaco logs `Failed to construct 'Worker'` because the worker script is loaded from `http://[::1]:5174` while the app origin is `http://opencompany.test`.

Evidence:

- Browser logs captured in `/tmp/opencompany-qa-20260524T183920Z/playwright-feature-results.json`

Suggested fix:

Adjust Vite/Monaco worker configuration for the Valet host, or proxy worker URLs through the same origin. This is mostly a local dev performance issue, but it can cause UI freezes in Lua/editor-heavy screens.

## Passed Manual Checks

- Dashboard rendered and quick actions were visible.
- Chat composer accepted a draft and enabled `Send message`; the draft was cleared without sending.
- Chat run boxes at the bottom of the thread include `Open task details` links.
- Table list and table detail rendered without horizontal overflow on desktop.
- Calendar event modal opened without saving.
- Docs page rendered document/folder content and accepted search input.
- Files page rendered folders and accepted search input.
- Settings page rendered major sections without saving.
- Mobile chat at 390px did not show horizontal overflow.

## State Restoration

The local database and `storage/app` were restored from the pre-QA snapshot after browser testing.

Final verification:

- Restored DB dump: `/tmp/opencompany-qa-20260524T183920Z/opencompany.before.dump`
- Final DB count comparison: `/tmp/opencompany-qa-20260524T183920Z/db-counts.final.diff`
- Final storage checksum comparison: `/tmp/opencompany-qa-20260524T183920Z/storage-app.final.diff`
- Non-Telescope application tables: no count differences.
- `storage/app`: no checksum differences.
- Telescope tables differ by 20 `telescope_entries` and 12 `telescope_entries_tags`; those are dev observability rows and changed around the live snapshot/verification window. The application data tables matched after restore.
