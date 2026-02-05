# Dashboard

> The authenticated landing page that provides a real-time overview of organization activity, agent status, pending approvals, and quick-action shortcuts.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/` (alias: `/dashboard`) |
| **Name** | `dashboard` |
| **Auth** | Required (`auth`, `verified`) |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
|  max-w-5xl mx-auto, p-4 md:p-6, overflow-y-auto                 |
|                                                                    |
|  +--------------------------------------------------------------+  |
|  | Header                                                        |  |
|  | h1 "Dashboard"                                                |  |
|  | subtitle "Welcome back. Here's what's happening."             |  |
|  +--------------------------------------------------------------+  |
|                                                                    |
|  +--------------------------------------------------------------+  |
|  | PendingApprovals  (conditional: only if approvals.length > 0) |  |
|  | "Action Required" banner with approve/reject buttons          |  |
|  +--------------------------------------------------------------+  |
|                                                                    |
|  +--------------------------------------------------------------+  |
|  | StatsOverview  (4-column grid on lg, 2-column on sm)          |  |
|  | [Agents Online] [Tasks Completed] [Messages] [Total Agents]   |  |
|  +--------------------------------------------------------------+  |
|                                                                    |
|  +----------------------------------------------+  +-----------+  |
|  | ActivityFeed (lg:col-span-2)                  |  | Sidebar   |  |
|  |                                               |  |           |  |
|  | Recent Activity list with avatars,            |  | Quick     |  |
|  | actor names, action text, timestamps.         |  | Actions   |  |
|  | Shows up to 8 items.                          |  |           |  |
|  | "View all" link to /activity                  |  | Working   |  |
|  |                                               |  | Agents    |  |
|  +----------------------------------------------+  +-----------+  |
|                                                                    |
+------------------------------------------------------------------+
  SpawnAgentModal (overlay, triggered by quick action)
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `PendingApprovals` | `Components/dashboard/PendingApprovals.vue` | Displays up to 3 pending approval requests with approve/reject actions. Links to `/approvals` when more exist. |
| `PendingApprovalItem` | `Components/dashboard/PendingApprovalItem.vue` | Individual approval row rendered inside PendingApprovals. |
| `StatsOverview` | `Components/dashboard/StatsOverview.vue` | 4-card stat grid showing agents online, tasks completed, total messages, and total agents. Uses `Stats` type. |
| `ActivityFeed` | `Components/dashboard/ActivityFeed.vue` | Scrollable list of recent activities (up to 8). Each row shows an `AgentAvatar`, actor name, action verb, target, and relative timestamp. |
| `QuickActions` | `Components/dashboard/QuickActions.vue` | Card with 4 action buttons: New channel, Spawn agent, Create task, New document. |
| `WorkingAgents` | `Components/dashboard/WorkingAgents.vue` | Card listing up to 5 agents currently in `working` status, each linking to `/agent/{id}`. |
| `SpawnAgentModal` | `Components/agents/SpawnAgentModal.vue` | Modal overlay for spawning a new agent. Controlled by `v-model:open`. |

---

## Data & API

| Composable Call | Returns | Usage |
|-----------------|---------|-------|
| `fetchStats()` | `Stats` | Feeds `StatsOverview` with agent/task/message counts |
| `fetchActivities(20)` | `Activity[]` | Feeds `ActivityFeed` with 20 most recent activities |
| `fetchAgents()` | `User[]` | Filtered client-side to `status === 'working'` for `WorkingAgents` |
| `fetchApprovals('pending')` | `ApprovalRequest[]` | Feeds `PendingApprovals` conditional banner |
| `respondToApproval(id, status, userId)` | void | Called on approve/reject; refreshes approvals, stats, and activities |

All API calls use the `useApi()` composable.

---

## Features & Interactions

### Quick Actions
- **New channel**: Navigates to `/chat` via `router.visit`
- **Spawn agent**: Opens `SpawnAgentModal`; on success refreshes agents, activities, and stats
- **Create task**: Navigates to `/tasks`
- **New document**: Navigates to `/docs`

### Pending Approvals
- Conditionally rendered only when there are pending approvals
- Approve/reject buttons call `respondToApproval` then refresh approvals, stats, and activities
- "View all" link at bottom when more than 3 approvals exist

### Activity Feed
- Shows 8 most recent activities with avatar, actor name, action verb, optional target, and relative timestamp
- "View all" link navigates to `/activity`

### Working Agents
- Displays up to 5 agents with `status === 'working'`
- Each agent row links to `/agent/{id}`
- Empty state shows moon icon with "All agents idle"

---

## States

| State | Description |
|-------|-------------|
| **Default** | Stats grid, activity feed, quick actions, and working agents all populated |
| **Empty activities** | ActivityFeed shows check-circle icon with "No recent activity" |
| **No working agents** | WorkingAgents shows moon-stars icon with "All agents idle" |
| **No pending approvals** | PendingApprovals component is hidden entirely (v-if) |
| **Loading** | Data defaults to zero/empty via computed fallbacks while API calls resolve |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< md` | Padding reduces from `p-6` to `p-4`. Stats grid goes from 4 columns to 2 columns. Main grid stacks to single column (sidebar below feed). |
| `md` | Standard padding. Stats remain 2 columns. |
| `lg+` | Stats grid expands to 4 columns. Main grid becomes 3-column layout (feed spans 2, sidebar takes 1). |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Dashboard.vue` | Page component |
| `resources/js/Components/dashboard/PendingApprovals.vue` | Approval banner |
| `resources/js/Components/dashboard/PendingApprovalItem.vue` | Single approval row |
| `resources/js/Components/dashboard/StatsOverview.vue` | 4-stat grid |
| `resources/js/Components/dashboard/ActivityFeed.vue` | Recent activity list |
| `resources/js/Components/dashboard/QuickActions.vue` | Quick action buttons |
| `resources/js/Components/dashboard/WorkingAgents.vue` | Active agents list |
| `resources/js/Components/agents/SpawnAgentModal.vue` | Spawn agent modal |
| `resources/js/composables/useApi.ts` | API composable |
