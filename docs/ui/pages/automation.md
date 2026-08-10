# Automations

> Manage scheduled prompt and runtime-pinned QuickJS automations, including run history, bulk actions, and editor-based create/edit flows.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Index route** | `/w/{workspace}/automation` |
| **Create route** | `/w/{workspace}/automation/create` |
| **Edit route** | `/w/{workspace}/automation/{id}/edit` |
| **Names** | `automation`, `automation.create`, `automation.edit` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| Header                                                           |
| "Automations" [status tabs or bulk toolbar] [Search] [New]       |
+------------------------------------------------------------------+
| Content                                                          |
|                                                                  |
| Loading: skeleton list                                           |
| Empty: lightning icon, "No automations yet", create button       |
| List: selectable automation rows                                 |
|   [checkbox] [status icon] name [Prompt/Script] [schedule]       |
|   agent + prompt/script preview                                  |
|   next run / run count / last run / failure snippet              |
|   [toggle] [actions menu: run, edit, delete]                     |
+------------------------------------------------------------------+
| Confirm dialogs: bulk delete, bulk run                           |
+------------------------------------------------------------------+
```

Create and edit screens use a full-height editor layout:

```
+------------------------------------------------------------------+
| Toolbar: back, name input, Prompt/Script toggle, Save/Run        |
+------------------------------------------------------------------+
| Monaco editor                                      | Sidebar     |
| Markdown prompt or JavaScript                      | Agent       |
|                                                    | Schedule    |
|                                                    | Timezone    |
|                                                    | History     |
|                                                    | Recent runs |
+------------------------------------------------------------------+
| Status bar: language, cursor position                            |
+------------------------------------------------------------------+
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `Button` (shared) | Header, row, editor, and dialog actions |
| `Checkbox` (shared) | Per-row and select-all bulk selection |
| `ConfirmDialog` (shared) | Bulk delete and bulk run confirmations |
| `Modal` (shared) | Run-detail modal on edit screen |
| `SearchInput` (shared) | Index search |
| `CronBuilder` | Schedule builder in create/edit sidebars |
| `MonacoEditor` | Prompt Markdown and JavaScript editor |
| `Icon` (shared) | Phosphor icons throughout |

---

## Features & Interactions

### Index

- Status tabs filter all, active, inactive, and failing automations.
- Search matches automation name, agent name, prompt text, or script text.
- Selecting rows replaces the status tabs with a bulk toolbar for Run, Delete, and Clear.
- Rows show execution type (`Prompt` or `Script`), human-readable cron schedule, assigned agent, preview text, next run, run count, last run, and failure snippet.
- Toggle switch updates `isActive`.
- Row action menu supports Run, Edit, and Delete.
- "New" navigates through the generated `automation.create` route helper.
- Edit navigates through the generated `automation.edit` route helper.

### Create/Edit

- The name is edited inline in the toolbar.
- Prompt/Script segmented control switches the Monaco editor between Markdown and JavaScript modes.
- Script mode shows a QuickJS badge and an API Reference link through the generated developer tools route helper.
- New or rewritten scripts compile capability-empty before persistence and are pinned to `quickjs-v1`.
- Pre-cutover scripts remain visible but disabled until a user rewrites and validates them; they are never guessed or translated automatically.
- Run details include execution ID, wall/CPU time, memory, capability calls, and the effect ledger.
- Sidebar fields select agent, cron schedule, timezone, and conversation history retention.
- Create screen saves through `createAutomation()`.
- Edit screen loads the automation and recent runs, saves through `updateAutomation()`, and can trigger an immediate run.
- Edit screen opens recent run details in a modal.

---

## States

| State | Description |
|-------|-------------|
| **Loading** | Skeleton rows while `fetchAutomations()` resolves |
| **Empty** | Centered lightning icon, "No automations yet", explanatory text, and create button |
| **No results** | Search/filter empty state with magnifying-glass icon |
| **Failing** | Rows with `consecutiveFailures > 0` use red status styling and show the latest error snippet |

---

## API Calls

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `fetchAutomations()` | `GET /api/automations` | Load automation list |
| `fetchAutomation(id)` | `GET /api/automations/:id` | Load one automation for edit |
| `createAutomation()` | `POST /api/automations` | Create prompt or script automation |
| `updateAutomation()` | `PATCH /api/automations/:id` | Update fields, enabled state, schedule, prompt, or script |
| `deleteAutomation()` | `DELETE /api/automations/:id` | Delete one automation |
| `triggerAutomation()` | `POST /api/automations/:id/run` | Run one automation immediately |
| `bulkDeleteAutomations()` | `POST /api/automations/bulk-delete` | Delete selected automations |
| `bulkTriggerAutomations()` | `POST /api/automations/bulk-run` | Run selected automations |
| `fetchAutomationRuns()` | `GET /api/automations/:id/runs` | Load recent runs for edit screen |
| `previewSchedule()` | `GET /api/automations/preview-schedule` | Preview next cron run times |
| `fetchAgents()` | `GET /api/agents` | Populate agent selector |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Automation.vue` | Automation index with filters, bulk actions, row actions, and status summaries |
| `resources/js/Pages/Automation/Create.vue` | Editor-based automation creation page |
| `resources/js/Pages/Automation/Edit.vue` | Editor-based automation edit page with run history and immediate run |
| `resources/js/Components/automation/CronBuilder.vue` | Cron schedule control |
| `resources/js/Components/developer/MonacoEditor.vue` | Shared Monaco editor surface |
| `resources/js/composables/useApi.ts` | Automation API functions |
| `resources/js/types/index.ts` | `Automation` and related TypeScript types |
