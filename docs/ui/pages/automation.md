# Automation

> Manage task templates and automation rules that power workflow automations, including template-based task creation and event-driven rule execution.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/automation` |
| **Name** | `automation` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| Header (shrink-0, border-b, px-6 py-4)                          |
| +--------------------------------------------------------------+ |
| | "Automation"                                                  | |
| | Create task templates and automation rules for your workflows | |
| +--------------------------------------------------------------+ |
| | [Task Templates]  [Automation Rules]       (tab buttons)      | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| Content (flex-1, overflow-y-auto, p-6)                           |
|                                                                  |
| Tab: Task Templates                                              |
| +--------------------------------------------------------------+ |
| | "Task Templates"                     [+ Create Template]      | |
| +--------------------------------------------------------------+ |
| | +----------------------------------------------------------+ | |
| | | Template Name  [Active/Inactive]                         | | |
| | | Description                                              | | |
| | | [title icon] Default Title  [flag] Priority  [user] Asn  | | |
| | | [tag] [tag] [tag]                  [play] [edit] [delete] | | |
| | +----------------------------------------------------------+ | |
| | +----------------------------------------------------------+ | |
| | | (next template...)                                       | | |
| | +----------------------------------------------------------+ | |
| | Empty: file-dashed icon, "No task templates yet"             | |
| +--------------------------------------------------------------+ |
|                                                                  |
| Tab: Automation Rules                                            |
| +--------------------------------------------------------------+ |
| | "Automation Rules"                       [+ Create Rule]      | |
| +--------------------------------------------------------------+ |
| | +----------------------------------------------------------+ | |
| | | Rule Name  [Active/Inactive]                             | | |
| | | Description                                              | | |
| | | [lightning] Trigger Type  -->  [gear] Action Type        | | |
| | | Using template: Template Name                            | | |
| | | Triggered 5 times - Last: Jan 15, 3:30 PM               | | |
| | |                          [toggle] [edit] [delete]        | | |
| | +----------------------------------------------------------+ | |
| | Empty: robot icon, "No automation rules yet"                 | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| Create Template Modal                                            |
| +--------------------------------------------------------------+ |
| | Template Name, Description, Default Task Title,              | |
| | Default Description, Default Priority + Estimated Cost,      | |
| | Default Assignee, Tags (comma separated)                     | |
| | [Cancel]  [Create/Update Template]                           | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| Create Rule Modal                                                |
| +--------------------------------------------------------------+ |
| | Rule Name, Description, Trigger + Action (2-col selects),   | |
| | Task Template (conditional, shown when action=create_task)   | |
| | [Cancel]  [Create/Update Rule]                               | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `Modal` (shared) | Reusable modal wrapper for both template and rule create/edit forms |
| `Icon` (shared) | Iconify wrapper with Phosphor icons throughout the page |

---

## Features & Interactions

### Tab Navigation
- Two tabs styled as pill buttons: "Task Templates" (icon: `ph:file-text`) and "Automation Rules" (icon: `ph:robot`)
- Active tab uses dark filled style; inactive tabs use muted text with hover state
- Default tab is "templates"

### Task Templates
- Grid list of template cards with rounded-xl borders
- Each template card displays:
  - Name with Active/Inactive status badge (green or neutral)
  - Optional description
  - Metadata row: default title, default priority, default assignee, estimated cost
  - Tags as small rounded-full chips
- Actions per template:
  - **Play** (run): Creates a task from the template via `createTaskFromTemplate()`, then navigates to `/tasks`
  - **Edit**: Opens the create modal pre-filled with template data
  - **Delete**: Browser confirm dialog, then `deleteTaskTemplate()`
- "Create Template" button opens modal

### Template Form (Modal)
- Fields: name, description, default task title, default description, default priority (select), estimated cost (number), default assignee (select from users), tags (comma-separated text input)
- Tags are split on commas, trimmed, and filtered for empty strings
- Modal title changes to "Update Template" when editing
- Form resets when modal closes (via watcher)

### Automation Rules
- Grid list of rule cards with trigger-action flow visualization
- Each rule card displays:
  - Name with Active/Inactive status badge
  - Optional description
  - Visual flow: `[lightning icon] Trigger Type --> [gear icon] Action Type`
  - Associated template name (when applicable)
  - Trigger count and last triggered timestamp
- Actions per rule:
  - **Toggle**: Switches `isActive` state via `updateAutomationRule()`
  - **Edit**: Opens the create modal pre-filled with rule data
  - **Delete**: Browser confirm dialog, then `deleteAutomationRule()`
- "Create Rule" button opens modal

### Rule Form (Modal)
- Fields: name, description, trigger type (select), action type (select), task template (conditional select, only shown when action is `create_task`)
- Trigger types: Task Created, Task Completed, Task Assigned, Approval Granted, Approval Rejected
- Action types: Create Task, Assign Task, Send Notification, Update Task, Spawn Agent
- Modal title changes to "Update Rule" when editing
- Form resets when modal closes (via watcher)

---

## States

| State | Description |
|-------|-------------|
| **Empty (Templates)** | Dashed-file icon at 50% opacity, "No task templates yet", "Create a template to get started" |
| **Empty (Rules)** | Robot icon at 50% opacity, "No automation rules yet", "Create a rule to automate your workflows" |
| **Loading** | Data loaded via `useApi()` composable; no explicit loading indicators in the UI |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **All sizes** | Single-column layout; no distinct mobile breakpoint handling beyond standard padding and text sizing. The page uses `px-6 py-4` header and `p-6` content padding throughout. |

---

## API Calls

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `fetchUsers()` | `GET /api/users` | Load users for assignee dropdown |
| `fetchTaskTemplates(false)` | `GET /api/task-templates` | Load all templates (lazy) |
| `fetchAutomationRules(false)` | `GET /api/automation-rules` | Load all rules (lazy) |
| `createTaskTemplate()` | `POST /api/task-templates` | Create new template |
| `updateTaskTemplate()` | `PATCH /api/task-templates/:id` | Update existing template |
| `deleteTaskTemplate()` | `DELETE /api/task-templates/:id` | Delete template |
| `createTaskFromTemplate()` | `POST /api/task-templates/:id/run` | Create task from template |
| `createAutomationRule()` | `POST /api/automation-rules` | Create new rule |
| `updateAutomationRule()` | `PATCH /api/automation-rules/:id` | Update rule (including toggle active) |
| `deleteAutomationRule()` | `DELETE /api/automation-rules/:id` | Delete rule |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Automation.vue` | Main page component with tab switching, template/rule lists, and create/edit modals |
| `resources/js/Components/shared/Modal.vue` | Modal wrapper for template and rule forms |
| `resources/js/Components/shared/Icon.vue` | Iconify icon wrapper |
| `resources/js/composables/useApi.ts` | API composable providing template and rule CRUD functions |
| `resources/js/types/index.ts` | TypeScript types: `User` (used for assignee selection) |
