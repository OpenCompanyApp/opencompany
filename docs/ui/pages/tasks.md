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
| | "Tasks"  [Workload] [Activity]  12 tasks / 3 active / 5 done  |
| +----------------------------------------------+-----------+     |
| | [All] [Pending] [Active] [Completed]                     |     |
| | [All agents v] [All sources v]           [+ New Task]    |     |
| +-----------------------------------------------------------+     |
+------------------------------------------------------------------+
| Table Header (sticky, bg-neutral-50)                             |
| +-----------------------------------------------------------+   |
| |   | Task              | Agent    | Source | Steps | Time   |   |
| +-----------------------------------------------------------+   |
| Task Rows (flex-1, overflow-auto, divide-y)                      |
| +-----------------------------------------------------------+   |
| | [v] * Task title  [tree 3]    Agent    [icon]  2/5  3m ago |   |
| |   > * Subtask 1               Agent    [icon]  1/2  2m ago |   |
| |   > * Subtask 2               Agent    [icon]  ---  1m ago |   |
| +-----------------------------------------------------------+   |
| | [v] * Another parent task     Req->Agt [icon]  ---  1h ago |   |
| +-----------------------------------------------------------+   |
| |                                                             |   |
| | Empty State: briefcase icon, "No tasks found", [Create]    |   |
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
| `ExecutionTrace` | Expandable step-by-step execution trace with tool icons, status indicators, duration badges, and collapsible argument/result panels |
| `Modal` (shared) | Reusable modal wrapper used for the "New Task" creation form |
| `AgentAvatar` (shared) | Displays agent/user avatar in task cards and detail page |
| `StatusBadge` (shared) | Agent status badge with dot, label, icon, and tooltip; supports all `AgentStatus` values including `sleeping`, `awaiting_approval`, `awaiting_delegation` |
| `SharedSkeleton` (shared) | Placeholder shimmer used during loading state on detail page |
| `Icon` (shared) | Iconify wrapper with Phosphor icons (`ph:` prefix) throughout |

---

## Features & Interactions

### Status Filtering
- Pill-style toggle group in header: All, Pending, Active, Completed
- "Active" filter includes both `active` and `paused` statuses
- "Completed" filter includes `completed`, `failed`, and `cancelled`
- Task counts shown in header: total, active (yellow), done (green)

### Source Filtering
- Dropdown select in header bar alongside status and agent filters
- Filters tasks by their origination source:
  - `manual` -- Created by a human user (icon: `ph:hand`)
  - `chat` -- Created from a chat conversation (icon: `ph:chat-circle`)
  - `automation` -- Created by an automation rule (icon: `ph:lightning`)
  - `agent_delegation` -- Delegated by another agent, async (icon: `ph:users-three`, indigo)
  - `agent_ask` -- Synchronous question from another agent (icon: `ph:question`, indigo)
  - `agent_notify` -- Fire-and-forget notification from another agent (icon: `ph:megaphone`, amber)
- Source icons appear in the "Source" column of the task list
- Delegation-source tasks (`agent_delegation`, `agent_ask`, `agent_notify`) show a requester-to-agent arrow flow in the Agent column: `[RequesterAvatar] -> [AgentAvatar] agent name`

### Agent Filtering
- Dropdown select populated from `fetchAgents()` API
- Filters tasks by assigned agent ID

### Task List
- Compact row-based table with columns: status dot, task title, agent, source icon, step count, and time-ago
- Hierarchical tree view: tasks with `parentTaskId` are nested under their parent with indentation and `ph:arrow-bend-down-right` nesting arrows
- Parent tasks display a collapse/expand toggle (caret icon) and a descendant count badge with `ph:tree-structure` icon
- Auto-collapse on first load: all parent roots except the most recent are collapsed by default
- Delegation-source tasks show requester-to-agent flow (`[RequesterAvatar] -> [AgentAvatar]`) in the Agent column
- Click on any row navigates to the task detail page via `router.visit(/tasks/{id})`

### Task Detail Page (`/tasks/{id}`)
- Full page view (route `Tasks/Show.vue`) with back button, replaces the previous drawer-based approach
- Header shows type badge, status badge, and source badge (with icon)
- Summary stats bar (4-column grid) with execution metrics:
  - **Duration** -- computed from `startedAt` to `completedAt` (or now if still active), formatted as `Xh Ym Zs`
  - **Input Tokens** -- `result.prompt_tokens` with cache sub-line showing `result.cache_read_tokens` when present
  - **Output Tokens** -- `result.completion_tokens`
  - **Tool Calls** -- `result.tool_calls_count` or count of tool steps
- Metadata grid: agent (linked to `/agent/{id}`), requester, priority, model (from context), created/started/completed dates, parent task link
- Input section: renders `task.description` in a bordered panel
- Execution Trace section (via `ExecutionTrace` component, see below)
- Subtasks section: lists child tasks with status dot, title, agent avatar, and status badge; each row navigates to that subtask
- Output section: renders `task.result.response` with Raw/Preview toggle (syntax-highlighted markdown source vs. rendered HTML)
- LLM Context panel (collapsible): system prompt (with Raw/Preview), conversation history, and available tools list
- Lifecycle actions in footer vary by current status:
  - Pending: Start
  - Active: Pause, Complete, Cancel
  - Paused: Resume, Complete, Cancel
- "View Chat" button when task has an associated channel

### Delegation Banner
- Shown on the task detail page when `task.source` is `agent_delegation`, `agent_ask`, or `agent_notify`
- Styled as a colored banner:
  - `agent_delegation` / `agent_ask`: indigo background, indigo border
  - `agent_notify`: amber background, amber border
- Displays:
  - Source icon (`ph:users-three`, `ph:question`, or `ph:megaphone`)
  - Label: "Delegated by", "Asked by", or "Notified by" depending on source
  - Requester avatar and name (from `task.requester`)
  - Link to parent task: "View parent task: {title}" with `ph:arrow-bend-up-left` icon, navigates to `/tasks/{parentTask.id}`

### Execution Trace
- Rendered by the `ExecutionTrace` component (`resources/js/Components/tasks/ExecutionTrace.vue`)
- Header: `ph:tree-structure` icon, "Execution Trace", step count badge
- Lists each `TaskStep` as an expandable row:
  - Status icon: green check (completed), spinning blue circle (in_progress), gray minus (skipped), gray clock (pending)
  - Tool-specific icon based on `step.metadata.tool` (e.g., `ph:paper-plane-tilt` for `send_channel_message`, `ph:users-three` for `contact_agent`, `ph:alarm` for `set_sleep_timer`)
  - Step description text
  - Duration badge (formatted as ms/s/m)
  - Expand chevron when step has arguments or result
- Expanded detail panel shows:
  - **Arguments**: syntax-highlighted JSON of `step.metadata.arguments`
  - **Result**: JSON results shown syntax-highlighted; text/markdown results shown with Raw/Preview toggle

### New Status Badges
The `StatusBadge` component (`resources/js/Components/shared/StatusBadge.vue`) supports the full `AgentStatus` type which now includes:
- `idle` -- Agent is available and waiting for tasks (gray dot)
- `working` -- Agent is actively processing a task (green dot, spinner icon)
- `offline` -- Agent is currently unavailable (light gray dot)
- `sleeping` -- Agent is sleeping and will wake at a scheduled time (indigo dot, moon icon `ph:moon`)
- `awaiting_approval` -- Agent is waiting for human approval to proceed (amber dot, shield icon `ph:shield-check`)
- `awaiting_delegation` -- Agent is waiting for delegated subtasks to complete (indigo dot, users icon `ph:users-three`, label: "Delegating")

Each status has full variant support: filled, soft, outline, ghost, dot-only, and minimal. Tooltips include status descriptions.

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
| **Mobile (<md)** | Header stacks vertically with gap-3; task counts hidden; "New" button (compact) shown in title row; filter pills horizontally scrollable; Agent and Source columns hidden; task detail page fills width |
| **Desktop (md+)** | Header is single row with `h-14`; full "New Task" button in filter bar; task counts visible; all table columns shown; task detail page is `max-w-4xl` centered |

---

## API Calls

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `fetchAgentTasks()` | `GET /api/agent-tasks` | Load all agent tasks (list page) |
| `fetchAgentTask(id)` | `GET /api/agent-tasks/:id` | Load single task with steps, subtasks, context, and result (detail page) |
| `fetchAgents()` | `GET /api/agents` | Load agents for assignment and filter dropdowns |
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
| `resources/js/Pages/Tasks.vue` | Task list page with tree view, filtering, and creation modal |
| `resources/js/Pages/Tasks/Show.vue` | Task detail page with stats, delegation banner, execution trace, output, and context |
| `resources/js/Components/tasks/ExecutionTrace.vue` | Expandable execution trace with tool icons, arguments, and results |
| `resources/js/Components/shared/StatusBadge.vue` | Agent status badge supporting all statuses including `sleeping`, `awaiting_approval`, `awaiting_delegation` |
| `resources/js/Components/shared/Modal.vue` | Modal wrapper used for task creation |
| `resources/js/Components/shared/AgentAvatar.vue` | Agent/user avatar display |
| `resources/js/Components/shared/Icon.vue` | Iconify icon wrapper |
| `resources/js/composables/useApi.ts` | API composable providing all fetch/mutation functions |
| `resources/js/composables/useMarkdown.ts` | Markdown rendering composable used in task detail |
| `resources/js/composables/useHighlight.ts` | Syntax highlighting composable used in execution trace and output |
| `resources/js/types/index.ts` | TypeScript types: `AgentTask`, `TaskStatus`, `TaskType`, `Priority`, `AgentStatus`, `User` |
