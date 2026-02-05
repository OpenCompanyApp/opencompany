# Workload

> A monitoring dashboard that visualizes agent performance, task distribution, workload scores, and efficiency metrics across all agents in the organization.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/workload` |
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
| | [Tasks link] "Workload"               [refresh button]        | |
| | "Monitor agent performance and task distribution"              | |
| +--------------------------------------------------------------+ |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Content (flex-1, overflow-y-auto, p-6)                        | |
| |                                                                | |
| | Summary Cards (4-column grid on lg, 2 on md, 1 on sm)        | |
| | +------------+ +------------+ +------------+ +------------+   | |
| | | Active     | | Tasks In   | | Completed  | | Avg        |   | |
| | | Agents     | | Progress   | | This Week  | | Efficiency |   | |
| | | 3/5        | | 12         | | 28         | | 85%        |   | |
| | +------------+ +------------+ +------------+ +------------+   | |
| |                                                                | |
| | Agent Cards (3-column grid on xl, 2 on lg, 1 on sm)          | |
| | +------------------+ +------------------+ +----------------+  | |
| | | Avatar  Name     | | Avatar  Name     | | Avatar  Name   |  | |
| | | AgentType Status | | AgentType Status | | AgentType Stat.|  | |
| | | Current task     | | Current task     | | Current task   |  | |
| | |                  | |                  | |                |  | |
| | | Workload  [==  ] | | Workload  [====] | | Workload [=  ] |  | |
| | | Efficiency[====] | | Efficiency[==  ] | | Efficiency[===]|  | |
| | |                  | |                  | |                |  | |
| | | In Prog | Pend | | In Prog | Pend | | In Prog | Pend |  | |
| | | Week    |      | | Week    |      | | Week    |      |  | |
| | |                  | |                  | |                |  | |
| | | activities $cost | | activities $cost | | activities     |  | |
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
| `Link` | `@inertiajs/vue3` | "Tasks" breadcrumb link and agent name links to `/agent/{id}` |

No child page-specific components -- the page renders everything inline.

---

## Data & API

Data is fetched directly via `fetch()` calls (not the `useApi()` composable):

| Endpoint | Purpose |
|----------|---------|
| `GET /api/users/agents` | Fetches all agent users |
| `GET /api/tasks` | Fetches all tasks to compute per-agent metrics |

Metrics are computed client-side by joining agents with their assigned tasks:

- **currentTasks**: Tasks with `status === 'in_progress'` assigned to agent
- **pendingTasks**: Tasks with `status === 'backlog'` assigned to agent
- **completedTasksWeek**: Tasks with `status === 'done'` assigned to agent
- **totalCostSpent**: Sum of `cost` field across agent's tasks
- **workloadScore**: `min(100, currentTasks * 30 + pendingTasks * 10)`
- **efficiencyScore**: `completedTasks / totalAssigned * 100`

---

## Features & Interactions

### Summary Cards
- Four stat cards at the top: Active Agents (ratio), Tasks In Progress, Completed This Week, Avg Efficiency
- Each card has a colored icon container and large numeric display

### Agent Cards
- Sorted by status (working agents first), then by workload score descending
- **Header**: Avatar with status dot, name (links to agent detail), agent type, current task description, status badge
- **Workload Bar**: Color-coded progress bar (green < 60%, yellow 60-80%, red >= 80%)
- **Efficiency Bar**: Green progress bar showing completion ratio
- **Metrics Grid**: 3-column mini-grid showing In Progress, Pending, and This Week counts
- **Footer**: Activity count and cost spent (if > 0)

### Refresh
- Manual refresh button in header triggers `fetchWorkload()`
- Auto-refresh every 30 seconds via `setInterval` (cleared on unmount)

---

## States

| State | Description |
|-------|-------------|
| **Default** | Summary cards populated, agent cards displayed in grid |
| **Empty** | Robot icon centered with "No agents found" when `agents` array is empty |
| **Loading** | No explicit loading skeleton; data appears on resolve |
| **Error** | Console error; `workloadData` set to null, which triggers empty state |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< md` | Summary cards stack to single column. Agent cards stack to single column. |
| `md` | Summary cards in 2-column grid. Agent cards remain single column. |
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
