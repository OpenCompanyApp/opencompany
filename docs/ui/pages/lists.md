# Lists

> Kanban-style project board for managing user-created list items across Backlog, In Progress, and Done columns, with project hierarchy, multiple view modes, and rich filtering.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/lists` |
| **Name** | `lists` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+------------------------------------------------------------------+
| +----------+---------------------------------------------------+ |
| | Project  | Header (border-b, shrink-0)                       | |
| | Sidebar  | +-----------------------------------------------+ | |
| | (w-64)   | | "Lists"  24 items / 8 done                    | | |
| |          | +-----------------------------------------------+ | |
| | Projects | | [Filters: status, priority, assignee, view]   | | |
| |  [search]| | [sort] [board|list|timeline] [+ New Item]     | | |
| |          | +-----------------------------------------------+ | |
| | [All]    |                                                   | |
| | --------+---------------------------------------------------+ | |
| | folder1  | Board View (flex-1)                               | |
| |   sub1   | +---------------+---------------+---------------+ | |
| |   sub2   | | Backlog       | In Progress   | Done          | | |
| | folder2  | | +-----------+ | +-----------+ | +-----------+ | | |
| |          | | | TaskCard  | | | TaskCard  | | | TaskCard  | | | |
| |          | | +-----------+ | +-----------+ | +-----------+ | | |
| |          | | | TaskCard  | | | TaskCard  | |               | | |
| |          | | +-----------+ | +-----------+ |               | | |
| |          | | [+ Add task]  | [+ Add task]  | [+ Add task]  | | |
| |          | +---------------+---------------+---------------+ | |
| +----------+---------------------------------------------------+ |
+------------------------------------------------------------------+
| List View (alternative to Board):                                |
| +--------------------------------------------------------------+ |
| | Item (col-5) | Status (col-2) | Priority (2) | Assignee (2) | |
| +--------------------------------------------------------------+ |
| | Task title    | [Backlog]      | [medium]     | Avatar Name  | |
| | Description   |                |              |              | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| Timeline View: Placeholder ("Coming soon")                       |
+------------------------------------------------------------------+
| ItemDetail Slideover (right, lg)                                 |
| +--------------------------------------------------------------+ |
| | Header: [priority] [status]  title (editable)  [edit] [X]   | |
| | Description, Assignee, Status/Priority, Cost, Collaborators  | |
| | Timestamps, Comments with replies                            | |
| | Footer: [Mark Complete / Reopen] [Delete]                    | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| ItemCreateModal (overlay)                                        |
| +--------------------------------------------------------------+ |
| | Title*, Description, Status + Priority (2-col), Assignee*,  | |
| | Estimated Cost, Channel                                      | |
| | [Cancel] [Create Task]                                       | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
| CreateProjectModal (overlay)                                     |
| +--------------------------------------------------------------+ |
| | Project Name                                                 | |
| | [Cancel] [Create Project]                                    | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `ProjectList` | Left sidebar with project tree, search, "All Tasks" option, and create/rename/delete project actions |
| `ProjectTreeItem` | Recursive tree node rendering nested project folders with expand/collapse, task counts, and hover actions |
| `TaskFilters` | Toolbar with search input, status/priority/assignee filter dropdowns, sort select, view toggle (board/list/timeline), and "New Task" button |
| `TaskBoard` | Kanban board container managing three status columns, drag-and-drop, scroll shadows, keyboard shortcuts, and empty/loading states |
| `TaskColumn` | Individual kanban column with header, card list, drag/drop zones, collapsible state, WIP limit indicators, quick-add input, and empty states |
| `TaskCard` | Draggable card showing priority bar, title, description, labels, progress, subtasks, assignee avatars, due date, comments/attachments counts, and cost badge |
| `TaskDetail` | Right-side Slideover for viewing/editing a list item: description, assignee, status, priority, cost, collaborators, timestamps, and threaded comments |
| `TaskCreateModal` | Modal form for creating new list items with title, description, status, priority, assignee (agents/humans), estimated cost, and channel |
| `Modal` (shared) | Reusable modal wrapper for project creation |
| `AgentAvatar` (shared) | Avatar component used in cards, list view, and detail drawer |
| `CostBadge` (shared) | Displays estimated or actual cost with `actual`/`estimated` variant |
| `Icon` (shared) | Iconify wrapper with Phosphor icons |
| `Button` (shared) | Shared button component used in detail footer and create modal |
| `Slideover` (shared) | Side-panel container used by TaskDetail |
| `Select` (shared) | Dropdown select used in TaskFilters |
| `Tooltip` (shared) | Tooltip wrapper for view toggle buttons |
| `DropdownMenu` (shared) | Dropdown menu for card actions and column actions |

---

## Features & Interactions

### Project Sidebar
- Left panel (w-64, hidden on mobile) listing folder-type list items as projects
- Search field filters projects by name
- "All Tasks" option at top shows all non-folder items regardless of project
- Selecting a project filters the board/list to items with that `parentId`
- Recursive tree via `ProjectTreeItem` supports nested project folders
- Hover actions on each project: rename (browser prompt), delete (browser confirm)
- Create project button opens a simple modal with name field

### View Modes
- **Board**: Default kanban view with three columns (Backlog, In Progress, Done)
- **List**: Table view on desktop (12-col grid), card view on mobile
- **Timeline**: Placeholder with "Coming soon" message

### Kanban Board
- Three columns: Backlog (`backlog`), In Progress (`in_progress`), Done (`done`)
- Drag-and-drop between columns via HTML5 drag events
- Drop indicators appear between cards showing exact insertion point
- Columns are collapsible -- collapsed state shows task count and priority breakdown
- Scroll shadows appear on horizontal overflow
- Keyboard shortcut: `N` creates new task in Backlog
- Loading state shows animated skeleton cards
- Empty state shows kanban icon with "Create task" button
- Each column has an "Add task" button at the bottom

### TaskCard Features
- Priority indicator bar at top of card
- Priority badge, type badge in header
- Drag-and-drop with cursor feedback (grab/grabbing)
- Hover actions: edit button, dropdown menu (view, edit, duplicate, move, archive, delete)
- Labels with color chips (max 3 shown, overflow count)
- Progress bar when `progress` field exists
- Subtask count with mini progress bar
- Footer: assignee avatars (with collaborators), due date, comments count, attachments count, cost badge
- Completion indicator when status is `done`
- AI working indicator overlay

### List View
- Desktop: table with columns for Item, Status, Priority, Assignee, Cost
- Mobile: vertical cards with status badge, priority badge, assignee, and cost
- Click on row/card opens item detail

### Item Detail Slideover
- Opens as right-side `Slideover` component (lg size)
- View mode: displays all fields as read-only
- Edit mode: inline editing of title, description, status, priority, estimated cost
- Assignee section with avatar, name, and link to agent/profile page
- Collaborators list with avatars
- Timestamps: created, completed
- Threaded comments section with reply support and delete actions
- Footer actions: "Mark Complete" / "Reopen Task", delete button

### Item Creation
- Modal with fields: title (required), description, status, priority, assignee (required, grouped by agents/humans), estimated cost, channel
- Initial status can be pre-set when triggered from a specific column's "Add task" button
- Parent ID set to currently selected project
- On creation: calls `createListItem()`, refreshes list

### Filtering
- Status filter: All tasks, Agent tasks, Human tasks (filters by `assignee.type`)
- Priority filter: Any, Urgent, High, Medium, Low
- Assignee filter: Anyone, Assigned to me, Unassigned
- Sort: Most recent, Priority, Due date, Alphabetical
- Active filter chips shown with clear button; "Clear all" when multiple active

---

## States

| State | Description |
|-------|-------------|
| **Empty (Board)** | Kanban icon centered, "No tasks yet" heading, "Create your first task..." description, "Create task" button |
| **Empty (List)** | List-checks icon, "No items found" message |
| **Empty (Projects)** | Folder-dashed icon, "No projects yet", "Create a project to organize your tasks", "New Project" button |
| **Loading** | Board shows skeleton cards per column (3 per column with animated pulse) |
| **Timeline** | Placeholder with chart-line icon and "Coming soon" text |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Mobile (<md)** | Project sidebar hidden; header stacks vertically; item counts hidden; "New" button (compact) in title row; List view renders as vertical cards instead of table; Board columns scroll horizontally |
| **Desktop (md+)** | Project sidebar visible (w-64); header is single row; full "New Item" button; List view renders as data table |

---

## API Calls

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `fetchListItems()` | `GET /api/list-items` | Load all list items |
| `fetchUsers()` | `GET /api/users` | Load users for assignee dropdown |
| `fetchChannels()` | `GET /api/channels` | Load channels for channel dropdown |
| `createListItem()` | `POST /api/list-items` | Create new item or project folder |
| `updateListItem()` | `PATCH /api/list-items/:id` | Update item fields (rename, status change) |
| `deleteListItem()` | `DELETE /api/list-items/:id` | Delete item or project |
| `reorderListItems()` | `POST /api/list-items/reorder` | Batch reorder items after drag-and-drop |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Lists.vue` | Main page component orchestrating sidebar, views, and modals |
| `resources/js/Components/lists/ProjectList.vue` | Project sidebar with tree navigation, search, and project CRUD |
| `resources/js/Components/lists/ProjectTreeItem.vue` | Recursive tree node for nested project folders |
| `resources/js/Components/lists/TaskFilters.vue` | Filter toolbar with search, dropdowns, view toggle, and actions |
| `resources/js/Components/lists/TaskBoard.vue` | Kanban board container with columns, drag-and-drop, and keyboard shortcuts |
| `resources/js/Components/lists/TaskColumn.vue` | Individual status column with cards, drag zones, collapse, and quick-add |
| `resources/js/Components/lists/TaskCard.vue` | Draggable task card with priority, labels, progress, and metadata |
| `resources/js/Components/lists/TaskDetail.vue` | Right-side Slideover for item detail, editing, and comments |
| `resources/js/Components/lists/TaskCreateModal.vue` | Modal for creating new list items |
| `resources/js/Components/shared/Modal.vue` | Modal wrapper for project creation |
| `resources/js/Components/shared/Slideover.vue` | Side-panel wrapper used by TaskDetail |
| `resources/js/Components/shared/AgentAvatar.vue` | Avatar display |
| `resources/js/Components/shared/CostBadge.vue` | Cost badge with actual/estimated variants |
| `resources/js/Components/shared/Button.vue` | Shared button component |
| `resources/js/Components/shared/Select.vue` | Dropdown select component |
| `resources/js/Components/shared/Tooltip.vue` | Tooltip wrapper |
| `resources/js/Components/shared/DropdownMenu.vue` | Dropdown menu for actions |
| `resources/js/Components/shared/Icon.vue` | Iconify icon wrapper |
| `resources/js/composables/useApi.ts` | API composable with list item CRUD functions |
| `resources/js/types/index.ts` | TypeScript types: `ListItem`, `ListItemStatus`, `Priority`, `User` |
