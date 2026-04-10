# ClickUp Lua API Reference

## Priority Mapping

| Value | Level   |
|-------|---------|
| 1     | Urgent  |
| 2     | High    |
| 3     | Normal  |
| 4     | Low     |

```lua
-- Use numeric values for priority
clickup_create_task({ list_id = "901234", name = "Fix bug", priority = 1 })   -- urgent
clickup_update_task({ task_id = "abc123", priority = 3 })                      -- normal
```

## Date Handling

All dates use ISO 8601 format. The API converts them to Unix milliseconds internally.

```lua
-- Date only
clickup_create_task({ list_id = "901234", name = "Sprint review", due_date = "2026-04-01" })

-- Date with time
clickup_create_task({
  list_id = "901234",
  name = "Standup",
  start_date = "2026-04-01T09:00:00",
  due_date = "2026-04-01T09:30:00"
})

-- Clear a date by passing empty string
clickup_update_task({ task_id = "abc123", due_date = "" })

-- Filter tasks by due date range
clickup_get_tasks({
  list_id = "901234",
  due_date_gt = "2026-03-01",
  due_date_lt = "2026-03-31"
})
```

## Member Resolution

Always resolve names to numeric user IDs before assigning tasks.

```lua
-- Find a single member by name or email
local result = clickup_find_member({ query = "Sarah" })
-- Returns: { matches = { { id = "12345", username = "sarah", email = "sarah@co.com" } } }

-- Resolve multiple names at once
local result = clickup_resolve_members({ query = "Sarah, John, alex@co.com" })
-- Returns: { results = { { query = "Sarah", id = "12345", resolved = true }, ... } }

-- Use the IDs for assignment (comma-separated string)
clickup_create_task({ list_id = "901234", name = "Review PR", assignees = "12345,67890" })
```

## Common Workflows

### Create a task with assignees and tags

```lua
-- Step 1: Find the list ID
local hierarchy = clickup_get_hierarchy({})
-- Traverse: hierarchy.spaces[].folders[].lists[] or hierarchy.spaces[].lists[]

-- Step 2: Resolve member IDs
local members = clickup_resolve_members({ query = "Sarah, John" })
local ids = {}
for _, m in ipairs(members.results) do
  if m.resolved then table.insert(ids, m.id) end
end

-- Step 3: Create the task
clickup_create_task({
  list_id = "901234",
  name = "Implement auth flow",
  description = "Add OAuth2 login support",
  status = "open",
  priority = 2,
  assignees = table.concat(ids, ","),
  tags = "backend,auth",
  due_date = "2026-04-15"
})
```

### Search for tasks, then update them

```lua
-- Search across workspace
local results = clickup_search({
  query = "auth",
  statuses = "open,in progress",
  include_subtasks = true
})

-- Update each matching task
for _, task in ipairs(results.tasks) do
  clickup_update_task({
    task_id = task.id,
    priority = 1,
    status = "in progress"
  })
end

-- Custom task IDs (e.g., "DEV-42") work too
clickup_update_task({ task_id = "DEV-42", status = "closed" })
```

### Time tracking (start, stop, log)

```lua
-- Start a timer on a task
clickup_start_timer({
  task_id = "abc123",
  description = "Working on auth flow",
  billable = true
})

-- Check what's currently running
local current = clickup_current_time_entry({})
-- Returns task name, start time, description

-- Stop the running timer
clickup_stop_timer({})

-- Or log time manually (duration in milliseconds)
clickup_log_time({
  task_id = "abc123",
  start = "2026-03-29T09:00:00",
  duration = "3600000",           -- 1 hour = 3,600,000 ms
  description = "Code review",
  billable = true
})

-- View all time entries for a task
local entries = clickup_list_time_entries({ task_id = "abc123" })
-- entries.entries[].duration is already formatted ("60.0 min")
```

### Navigate hierarchy (find list and folder IDs)

```lua
-- Get full workspace tree
local tree = clickup_get_hierarchy({})

-- Filter to specific space(s)
local tree = clickup_get_hierarchy({ space_ids = "12345,67890" })

-- Get folder details including its lists
local folder = clickup_get_folder({ folder_id = "456" })
-- folder.lists = { { id = "789", name = "Backlog" }, ... }

-- Get list details
local list = clickup_get_list({ list_id = "789" })
-- list.task_count, list.space, list.folder

-- Get all tasks in a specific list
local tasks = clickup_get_tasks({
  list_id = "789",
  statuses = "open,in progress",
  include_closed = false
})
```

### Create a subtask

```lua
clickup_create_task({
  list_id = "901234",
  name = "Write unit tests",
  parent_task_id = "abc123",
  priority = 3
})
```

### Tags

```lua
-- Add a tag (must already exist in the space)
clickup_add_tag({ task_id = "abc123", tag_name = "urgent" })

-- Remove a tag
clickup_remove_tag({ task_id = "abc123", tag_name = "urgent" })

-- Set tags during task creation
clickup_create_task({
  list_id = "901234",
  name = "Deploy",
  tags = "devops,release"
})
```

## Tips

- **Time durations are in milliseconds**: 1 min = 60000, 1 hour = 3600000, 1 day = 86400000
- **Tags must pre-exist** in the ClickUp space before you can add them to tasks
- **workspace_id** is pulled from config automatically for search, time tracking, and member operations -- you rarely need to pass it explicitly
- **Custom task IDs** like `"DEV-42"` work anywhere a `task_id` is accepted
- **Pagination**: `clickup_search` and `clickup_get_tasks` support a `page` parameter (starts at 0)
- **Assignees** use comma-separated numeric user IDs, not names -- always resolve first
- **Statuses** are list-specific strings (e.g., `"open"`, `"in progress"`, `"closed"`) -- check the list for valid values
- **time_estimate** on `clickup_update_task` is in **minutes** (converted to ms internally)
