# TickTick — JavaScript API Reference

## list_projects

List all projects (task lists). No parameters. Call this first to discover project IDs.

```js
var projects = app.integrations.ticktick.list_projects({})

for (const p of (projects)) {
  console.log(p.name + " (id: " + p.id + ")")
}
```
## get_tasks

Get all tasks in a project.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `project_id` | string | yes | Project ID (from `list_projects`) |

```js
var tasks = app.integrations.ticktick.get_tasks({ project_id: "abc123" })
```
## create_task

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

## complete_task

Mark a task as complete.

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `project_id` | string | yes | Project ID the task belongs to |
| `task_id` | string | yes | Task ID to complete |

## update_task

Update an existing task (same fields as create, plus `task_id` and `project_id`).

## delete_task

Delete a task (requires `project_id` and `task_id`).

## Examples

### List projects, then create a task

```js
// Step 1: find the project
var projects = app.integrations.ticktick.list_projects({})
var project_id = null
for (const p of (projects)) {
  if (p.name === "Work") {
    project_id = p.id
    break
  }
}

// Step 2: create a high-priority task with a due date
app.integrations.ticktick.create_task({
  title: "Finish quarterly report",
  project_id: project_id,
  content: "Include revenue && churn metrics",
  due_date: "2026-04-01T17:00:00+0000",
  priority: 5,
  is_all_day: false,
})
```
### Create a task with subtasks

```js
app.integrations.ticktick.create_task({
  title: "Launch checklist",
  project_id: project_id,
  priority: 3,
  items: '[{"title": "Update changelog", "status": 0}, {"title": "Tag release", "status": 0}, {"title": "Notify team", "status": 0}]',
})
```
### Complete a task

```js
// Step 1: get tasks in the project
var tasks = app.integrations.ticktick.get_tasks({ project_id: "abc123" })

// Step 2: complete the first one
app.integrations.ticktick.complete_task({
  project_id: "abc123",
  task_id: tasks[0].id,
})
```
### Create a quick Inbox task

```js
app.integrations.ticktick.create_task({
  title: "Buy groceries",
  priority: 1,
})
```
---

## Multi-Account Usage

If you have multiple ticktick accounts configured, use account-specific namespaces:

```js
// Default account (always works)
app.integrations.ticktick.function_name({ /* parameters */ })

// Explicit default (portable across setups)
app.integrations.ticktick.default.function_name({ /* parameters */ })

// Named accounts
app.integrations.ticktick.work.function_name({ /* parameters */ })
app.integrations.ticktick.personal.function_name({ /* parameters */ })
```
All functions are identical across accounts — only the credentials differ.
