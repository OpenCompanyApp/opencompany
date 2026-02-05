# Activity

> A filterable, real-time timeline of all organization activities with type, user, and date range filters.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/activity` |
| **Name** | `activity` |
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
| | "Activity Feed"                            [refresh button]   | |
| | "Track all activities across your organization"               | |
| |                                                                | |
| | Filters:                                                       | |
| | [All|Messages|Tasks|Approvals|Agents|Errors]                  | |
| | [All users v]  [This Week v]  [Clear filters]                 | |
| +--------------------------------------------------------------+ |
|                                                                    |
| +--------------------------------------------------------------+ |
| | Content (flex-1, overflow-y-auto)                             | |
| | max-w-4xl mx-auto                                             | |
| |                                                                | |
| | Timeline                                                       | |
| | |                                                              | |
| | O--- [Actor] sent a message               2m ago              | |
| |      "Description text..."                                    | |
| |      [#channel-name badge]                                    | |
| | |                                                              | |
| | O--- [Actor] completed a task              1h ago              | |
| |      "Description text..."                                    | |
| |      [task-title badge]                                       | |
| | |                                                              | |
| | ...                                                            | |
| |                                                                | |
| |            [Load more]                                         | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `Icon` | `Components/shared/Icon.vue` | Timeline dot icons and metadata badges |
| `Link` | `@inertiajs/vue3` | Actor name links to `/agent/{id}` or `/profile/{id}` based on type |

The page renders everything inline without child components.

---

## Data & API

| Composable Call | Purpose |
|-----------------|---------|
| `fetchActivities(limit)` | Load activities (starts at 50, increments by 50 on "Load more") |
| `fetchUsers()` | Populate the user filter dropdown |

---

## Real-time Events

| Event | Handler |
|-------|---------|
| `activity:new` | Calls `refreshActivities()` to prepend new items |

Subscribed via `useRealtime()` in `onMounted`, cleaned up in `onUnmounted`.

---

## Features & Interactions

### Filters

| Filter | Type | Options |
|--------|------|---------|
| **Activity type** | Toggle button group | All, Messages, Tasks, Approvals, Agents, Errors |
| **User** | Select dropdown | All users + list from `fetchUsers()` |
| **Date range** | Select dropdown | Today, This Week (default), This Month, All Time |
| **Clear filters** | Text button | Resets all filters; only shown when filters are active |

All filtering is performed client-side on the loaded activities array.

### Timeline
- Vertical timeline line (absolute, w-0.5, left-aligned)
- Each activity has a colored circle icon (40px) representing the type
- Activity card shows actor name (linked), verb, description, optional metadata badges (task title, amount, channel name)
- Relative timestamps: "just now", "Xm ago", "Xh ago", "Xd ago", or date

### Activity Type Icons and Colors

| Type | Icon | Background |
|------|------|------------|
| `message` | `ph:chat-circle-fill` | `bg-blue-500` |
| `task_completed` | `ph:check-circle-fill` | `bg-green-500` |
| `task_started` | `ph:play-circle-fill` | `bg-yellow-500` |
| `agent_spawned` | `ph:robot-fill` | `bg-purple-500` |
| `approval_needed` | `ph:seal-question-fill` | `bg-orange-500` |
| `approval_granted` | `ph:seal-check-fill` | `bg-green-500` |
| `error` | `ph:warning-circle-fill` | `bg-red-500` |

### Pagination
- "Load more" button at bottom (increments limit by 50)
- Spinner icon shown while loading more
- Button hidden when activities count is less than limit

---

## States

| State | Description |
|-------|-------------|
| **Default** | Timeline with activity cards |
| **Empty (no data)** | Activity icon centered with "No activities found" and "Activities will appear here" |
| **Empty (filtered)** | Activity icon with "No activities found" and "Try adjusting your filters" |
| **Loading more** | Spinner on "Load more" button, button text changes to "Loading..." |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< md` | Filters may wrap to multiple lines. Content padding adapts. |
| `md+` | Filters displayed in a single row. Content centered at max-w-4xl. |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Activity.vue` | Page component (self-contained) |
| `resources/js/Components/shared/Icon.vue` | Icon component |
| `resources/js/composables/useApi.ts` | API composable |
| `resources/js/composables/useRealtime.ts` | Real-time event subscriptions |
