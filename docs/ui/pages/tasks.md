# Tasks

> Displays agent-managed work items (cases) in a filterable list with detail drawer, task lifecycle controls, and creation modal.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/tasks` |
| **Name** | `tasks` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| Header (border-b, shrink-0)                                      |
| +----------------------------------------------+-----------+     |
| | "Tasks"  [Workload]  12 tasks / 3 active / 5 done        |     |
| +----------------------------------------------+-----------+     |
| | [All] [Pending] [Active] [Completed]     [+ New Task]    |     |
| +-----------------------------------------------------------+     |
+------------------------------------------------------------------+
| Task List (flex-1, overflow-auto, p-4 md:p-6)                   |
| +-----------------------------------------------------------+   |
| | +-------------------------------------------------------+ |   |
| | | [Type] [Status]                    [Priority]          | |   |
| | | Task title                         Created date        | |   |
| | | Description (line-clamp-2)                             | |   |
| | | AgentAvatar  agent name       2/5 steps  [chat icon]  | |   |
| | +-------------------------------------------------------+ |   |
| |                                                           |   |
| | +-------------------------------------------------------+ |   |
| | | (next task card...)                                    | |   |
| | +-------------------------------------------------------+ |   |
| |                                                           |   |
| | Empty State: briefcase icon, "No tasks found", [Create]  |   |
| +-----------------------------------------------------------+   |
+------------------------------------------------------------------+
| TaskDetailDrawer (right side-panel, 480px, z-50)                 |
| +-----------------------------------------------------------+   |
| | Header: [Type] [Status]                         [X close] |   |
| | Title & Description                                        |   |
| | Metadata grid: Priority, Agent, Requester, Dates           |   |
| | Steps Timeline (progress indicators)                       |   |
| | Result / Error section                                     |   |
| | Footer: [View Chat]  [Start/Pause/Resume] [Complete] [X]  |   |
| +-----------------------------------------------------------+   |
+------------------------------------------------------------------+
| Modal: "New Task" (overlay)                                      |
| +-----------------------------------------------------------+   |
| | Title, Description, Type + Priority (2-col),              |   |
| | Assign to Agent                                            |   |
| | [Cancel] [Create Task]                                     |   |
| +-----------------------------------------------------------+   |
+------------------------------------------------------------------+
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `TaskDetailDrawer` | Right-side sliding drawer displaying full task detail, step progress timeline, result/error blocks, and lifecycle action buttons |
| `Modal` (shared) | Reusable modal wrapper used for the "New Task" creation form |
| `AgentAvatar` (shared) | Displays agent/user avatar in task cards and detail drawer |
| `Icon` (shared) | Iconify wrapper with Phosphor icons (`ph:` prefix) throughout |

---

## Features & Interactions

### Status Filtering
- Pill-style toggle group in header: All, Pending, Active, Completed
- "Active" filter includes both `active` and `paused` statuses
- "Completed" filter includes `completed`, `failed`, and `cancelled`
- Task counts shown in header: total, active (yellow), done (green)

### Task List
- Vertical card list with type badge, status badge, priority badge, and creation date
- Each card shows assigned agent with avatar or "Unassigned" placeholder
- Step progress shown as `completed/total steps` when steps exist
- Chat icon button navigates to associated channel via `router.visit(/chat?channel=...)`
- Click on card opens TaskDetailDrawer

### Task Detail Drawer
- Slides in from right with backdrop overlay (200ms ease-out transition)
- Width: full on mobile, 480px on desktop
- Displays metadata grid: priority, agent, requester, created/started/completed dates
- Step timeline with animated status indicators (spinning icon for in-progress)
- Result section (green) or Error section (red) for completed/failed tasks
- Lifecycle actions in footer vary by current status:
  - Pending: Start
  - Active: Pause, Complete, Cancel
  - Paused: Resume, Complete, Cancel
- "View Chat" button when task has an associated channel

### Task Creation
- Modal with form fields: title, description, type (select), priority (select), agent assignment
- Type options: Custom, Ticket, Request, Analysis, Content, Research
- Priority options: Low, Normal, High, Urgent
- Agent dropdown populated from `fetchAgents()` API
- Submit disabled until title is non-empty
- On creation: calls `createAgentTask()`, closes modal, resets form, refreshes list

### Task Lifecycle Management
- Start: `startAgentTask(taskId)` -- transitions pending to active
- Pause: `pauseAgentTask(taskId)` -- pauses active task
- Resume: `resumeAgentTask(taskId)` -- resumes paused task
- Complete: `completeAgentTask(taskId, result?)` -- marks as completed
- Cancel: `cancelAgentTask(taskId)` -- cancels task
- All actions refresh the task list and update the selected task in the drawer

### Header Navigation
- "Workload" link in header navigates to `/workload` page

---

## States

| State | Description |
|-------|-------------|
| **Empty** | Briefcase icon centered, "No tasks found" message, contextual subtitle (differs based on active filter), "Create Task" button |
| **Loading** | Data loaded via `useApi()` composable `fetchAgentTasks()` -- no explicit loading skeleton in list |
| **Error** | Errors handled at API composable level |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Mobile (<md)** | Header stacks vertically with gap-3; task counts hidden; "New" button (compact) shown in title row; filter pills horizontally scrollable; TaskDetailDrawer goes full-width |
| **Desktop (md+)** | Header is single row with `h-14`; full "New Task" button in filter bar; task counts visible; TaskDetailDrawer is 480px wide |

---

## API Calls

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `fetchAgentTasks()` | `GET /api/agent-tasks` | Load all agent tasks |
| `fetchAgents()` | `GET /api/agents` | Load agents for assignment dropdown |
| `createAgentTask()` | `POST /api/agent-tasks` | Create new task |
| `startAgentTask()` | `POST /api/agent-tasks/:id/start` | Start a pending task |
| `pauseAgentTask()` | `POST /api/agent-tasks/:id/pause` | Pause an active task |
| `resumeAgentTask()` | `POST /api/agent-tasks/:id/resume` | Resume a paused task |
| `completeAgentTask()` | `POST /api/agent-tasks/:id/complete` | Complete a task |
| `cancelAgentTask()` | `POST /api/agent-tasks/:id/cancel` | Cancel a task |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Tasks.vue` | Main page component |
| `resources/js/Components/tasks/TaskDetailDrawer.vue` | Side-panel drawer for task detail and lifecycle actions |
| `resources/js/Components/shared/Modal.vue` | Modal wrapper used for task creation |
| `resources/js/Components/shared/AgentAvatar.vue` | Agent/user avatar display |
| `resources/js/Components/shared/Icon.vue` | Iconify icon wrapper |
| `resources/js/composables/useApi.ts` | API composable providing all fetch/mutation functions |
| `resources/js/types/index.ts` | TypeScript types: `AgentTask`, `TaskStatus`, `TaskType`, `Priority`, `User` |
