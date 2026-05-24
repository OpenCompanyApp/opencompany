# Workload

> A monitoring dashboard that visualizes agent performance, task distribution, workload scores, and efficiency metrics across all agents in the organization.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/w/{workspace}/workload` |
| **Name** | `workload` |
| **Auth** | Required (`auth`, `verified`) |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| h-full flex flex-col                                              |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Header (shrink-0, border-b)                                   | |
| | [Tasks] "Workload" [Activity] [Analytics]   [refresh button]  | |
| +--------------------------------------------------------------+ |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Content (flex-1, overflow-y-auto, p-6)                        | |
| |                                                                | |
| | Summary Cards (4-column grid on lg, 2 on sm)                 | |
| | +------------+ +------------+ +------------+ +------------+   | |
| | | Active     | | Active     | | Completed  | | Failed     |   | |
| | | Agents     | | Tasks      | | This Week  | | This Week  |   | |
| | | 3/5        | | 12         | | 28         | | 1          |   | |
| | +------------+ +------------+ +------------+ +------------+   | |
| |                                                                | |
| | Agent Cards (3-column grid on xl, 2 on lg, 1 on sm)          | |
| | +------------------+ +------------------+ +----------------+  | |
| | | Avatar  Name     | | Avatar  Name     | | Avatar  Name   |  | |
| | | AgentType Status | | AgentType Status | | AgentType Stat.|  | |
| | | Current task     | | Current task     | | Current task   |  | |
| | |                  | |                  | |                |  | |
| | | Active | Pending | Today | Week                               | |
| | | Avg duration                         failed count if any       | |
| | +------------------+ +------------------+ +----------------+  | |
| |                                                                | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `Icon` | `Components/shared/Icon.vue` | Phosphor icons throughout |
| `AgentAvatar` | `Components/shared/AgentAvatar.vue` | Agent avatar with status indicator in each card header |
| `StatusBadge` | `Components/shared/StatusBadge.vue` | Status pill (working, idle, etc.) in each card header |
| `Link` | `@inertiajs/vue3` | Header navigation links and agent name links through `workspacePath('/agent/{id}')` |

No child page-specific components -- the page renders everything inline.

---

## Data & API

Data is fetched through `useApi().fetchWorkload()`.

| Endpoint | Purpose |
|----------|---------|
| `GET /api/workload` | Returns workspace-scoped agent workload rows and summary counts |

Metrics are computed server-side in `WorkloadController` to avoid client-side joins and N+1 task fetching:

- **currentTasks**: Active tasks assigned to the agent
- **pendingTasks**: Pending tasks assigned to the agent
- **completedToday**: Completed tasks since today started
- **completedThisWeek**: Completed tasks since the current week started
- **failedThisWeek**: Failed tasks updated this week
- **avgDurationSeconds**: Average completed-task duration for the current week when start/end timestamps exist
- **currentTaskTitle**: Most recent active task title for the agent

---

## Features & Interactions

### Summary Cards
- Four stat cards at the top: Active Agents (ratio), Active Tasks, Completed This Week, Failed This Week
- Each card has a colored icon container and large numeric display

### Agent Cards
- Returned by the workload API sorted by status (working agents first, then idle, then other statuses)
- **Header**: Avatar with status dot, name (links to agent detail), agent type, current task description, status badge
- **Metrics Grid**: 4-column mini-grid showing Active, Pending, Today, and Week counts
- **Footer**: Average task duration when available, plus failed-this-week count when greater than zero

### Refresh
- Manual refresh button in header triggers `fetchWorkload()`
- Auto-refresh every 30 seconds via `setInterval` (cleared on unmount)

---

## States

| State | Description |
|-------|-------------|
| **Default** | Summary cards populated, agent cards displayed in grid |
| **Empty** | Robot icon centered with "No agents found" when `agents` array is empty |
| **Loading** | Centered spinner while `fetchWorkload()` is loading |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< lg` | Summary cards render in 2 columns. Agent cards stack to single column. |
| `lg` | Summary cards in 4-column grid. Agent cards in 2-column grid. |
| `xl+` | Agent cards expand to 3-column grid. |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Workload.vue` | Page component (self-contained) |
| `resources/js/Components/shared/Icon.vue` | Icon component |
| `resources/js/Components/shared/AgentAvatar.vue` | Agent avatar with status |
| `resources/js/Components/shared/StatusBadge.vue` | Status badge pill |
