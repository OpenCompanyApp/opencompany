# ClickUp Integration

ClickUp project management integration for the Laravel AI SDK. Part of the **OpenCompany** integration ecosystem — an open platform where AI agents collaborate with humans to run organizations.

## Available Tools (17)

| Tool | Type | Description |
|------|------|-------------|
| `clickup_get_hierarchy` | read | Get workspace hierarchy: spaces, folders, lists |
| `clickup_search` | read | Search tasks across the workspace |
| `clickup_members` | read | List, find, or resolve workspace members |
| `clickup_get_tasks` | read | Get all tasks in a list |
| `clickup_get_task` | read | Get a task by ID with full details |
| `clickup_create_task` | write | Create a new task in a list |
| `clickup_update_task` | write | Update an existing task |
| `clickup_delete_task` | write | Delete a task |
| `clickup_manage_tags` | write | Add or remove tags on tasks |
| `clickup_attach_file` | write | Attach a file to a task |
| `clickup_manage_comments` | write | Read or add comments on tasks |
| `clickup_time_tracking` | write | Start, stop, log, or list time entries |
| `clickup_manage_list` | write | Create, get, or update lists |
| `clickup_manage_folder` | write | Create, get, or update folders |
| `clickup_chat` | write | List channels or send messages |
| `clickup_manage_document` | write | Create a ClickUp document |
| `clickup_manage_document_pages` | write | List, get, create, or update document pages |

## Installation

```bash
composer require opencompanyapp/integration-clickup
```

The service provider is auto-discovered by Laravel.

## Configuration

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `api_token` | secret | Yes | Personal API Token (starts with `pk_`). Generate at ClickUp → Settings → Apps. |
| `workspace_id` | text | No | Workspace ID from URL: `app.clickup.com/{id}/...`. Required for search, time tracking, members. |

## Quick Start

```php
use Laravel\Ai\Facades\Ai;

$response = Ai::tools(['clickup_get_hierarchy', 'clickup_create_task'])
    ->prompt('List all spaces, then create a task called "Review Q1 report" in the first list you find.');
```

## Dependencies

| Package | Version |
|---------|---------|
| PHP | ^8.2 |
| opencompanyapp/integration-core | ^2.0 |
| laravel/ai | ^0.1 |

## License

MIT
