# TickTick — Lua API Reference

## ticktick_list_projects

List all projects (task lists). No parameters. Call this first to discover project IDs.

```lua
local projects = ticktick_list_projects({})

for _, p in ipairs(projects) do
  log(p.name .. " (id: " .. p.id .. ")")
end
```

## ticktick_get_tasks

Get all tasks in a project.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `project_id` | string | yes | Project ID (from `ticktick_list_projects`) |

```lua
local tasks = ticktick_get_tasks({ project_id = "abc123" })
```

## ticktick_create_task

Create a new task.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `title` | string | yes | Task title |
| `project_id` | string | no | Project ID. Omit to add to Inbox |
| `content` | string | no | Description/notes |
| `start_date` | string | no | ISO 8601 (e.g. `"2026-03-30T09:00:00+0000"`) |
| `due_date` | string | no | ISO 8601 (e.g. `"2026-03-30T17:00:00+0000"`) |
| `priority` | integer | no | `0` = none, `1` = low, `3` = medium, `5` = high |
| `is_all_day` | boolean | no | `true` for all-day, `false` for specific times |
| `items` | string | no | JSON array of subtasks (see below) |

### Subtask format

```
[{"title": "Subtask 1", "status": 0}, {"title": "Subtask 2", "status": 0}]
```

Status: `0` = unchecked, `2` = checked.

## ticktick_complete_task

Mark a task as complete.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `project_id` | string | yes | Project ID the task belongs to |
| `task_id` | string | yes | Task ID to complete |

## ticktick_update_task

Update an existing task (same fields as create, plus `task_id` and `project_id`).

## ticktick_delete_task

Delete a task (requires `project_id` and `task_id`).

## Examples

### List projects, then create a task

```lua
-- Step 1: find the project
local projects = ticktick_list_projects({})
local project_id = nil
for _, p in ipairs(projects) do
  if p.name == "Work" then
    project_id = p.id
    break
  end
end

-- Step 2: create a high-priority task with a due date
ticktick_create_task({
  title = "Finish quarterly report",
  project_id = project_id,
  content = "Include revenue and churn metrics",
  due_date = "2026-04-01T17:00:00+0000",
  priority = 5,
  is_all_day = false
})
```

### Create a task with subtasks

```lua
ticktick_create_task({
  title = "Launch checklist",
  project_id = project_id,
  priority = 3,
  items = '[{"title": "Update changelog", "status": 0}, {"title": "Tag release", "status": 0}, {"title": "Notify team", "status": 0}]'
})
```

### Complete a task

```lua
-- Step 1: get tasks in the project
local tasks = ticktick_get_tasks({ project_id = "abc123" })

-- Step 2: complete the first one
ticktick_complete_task({
  project_id = "abc123",
  task_id = tasks[1].id
})
```

### Create a quick Inbox task

```lua
ticktick_create_task({
  title = "Buy groceries",
  priority = 1
})
```
