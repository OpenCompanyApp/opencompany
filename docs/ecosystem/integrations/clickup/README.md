# ClickUp Integration

ClickUp project management integration for OpenCompany. Supports task workflows,
workspace hierarchy, members, comments, tags, attachments, time tracking, lists,
folders, chat, docs, and webhook triggers.

## Available Tools (34)

| Tool | Type | Description |
|------|------|-------------|
| `clickup_get_hierarchy` | read | Get workspace hierarchy: spaces, folders, and lists. |
| `clickup_search` | read | Search tasks across the configured workspace. |
| `clickup_list_members` | read | List workspace members visible to the token. |
| `clickup_find_member` | read | Find a member by name or email. |
| `clickup_resolve_members` | read | Resolve comma-separated names or emails to user IDs. |
| `clickup_get_tasks` | read | Get tasks in a list with filters. |
| `clickup_get_task` | read | Get one task by regular ID or custom task ID. |
| `clickup_create_task` | write | Create a task in a list. |
| `clickup_update_task` | write | Update task fields, dates, assignees, priority, or status. |
| `clickup_delete_task` | write | Delete a task. |
| `clickup_add_tag` | write | Add an existing tag to a task. |
| `clickup_remove_tag` | write | Remove a tag from a task. |
| `clickup_attach_file` | write | Upload a local file attachment to a task. |
| `clickup_read_comments` | read | Read task comments. |
| `clickup_add_comment` | write | Add a task comment. |
| `clickup_current_time_entry` | read | Get the currently running timer. |
| `clickup_list_time_entries` | read | List tracked time entries for a task. |
| `clickup_start_timer` | write | Start a timer on a task. |
| `clickup_stop_timer` | write | Stop the running timer. |
| `clickup_log_time` | write | Create a manual time entry. |
| `clickup_get_list` | read | Get list details. |
| `clickup_create_list` | write | Create a folderless list in a space. |
| `clickup_create_list_in_folder` | write | Create a list in a folder. |
| `clickup_update_list` | write | Update a list. |
| `clickup_get_folder` | read | Get folder details. |
| `clickup_create_folder` | write | Create a folder in a space. |
| `clickup_update_folder` | write | Update a folder. |
| `clickup_list_channels` | read | List ClickUp Chat channels. |
| `clickup_send_message` | write | Send a chat message to a channel. |
| `clickup_manage_document` | write | Create a ClickUp Doc. |
| `clickup_list_doc_pages` | read | List pages in a ClickUp Doc. |
| `clickup_get_doc_pages` | read | Fetch ClickUp Doc pages and content. |
| `clickup_create_doc_page` | write | Create a Doc page. |
| `clickup_update_doc_page` | write | Update a Doc page. |

## API Coverage Notes

The service layer includes mappings for additional official ClickUp v2/v3
resources such as custom fields, checklists, comments, time entries, spaces,
webhooks, docs, chat messages, reactions, replies, and attachments. Not every
official endpoint is exposed as a first-class agent tool yet; new tools should
continue to use one operation per tool and delegate HTTP work to `ClickUpService`.

Task attachments use ClickUp's official multipart upload endpoint. Public URL
passthrough is not supported by that endpoint, so `clickup_attach_file` accepts a
readable local `file_path`.

## Installation

```bash
composer require opencompanyapp/integration-clickup
```

The service provider is auto-discovered by Laravel.

## Configuration

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `api_token` | secret | Yes | Personal API token. Generate at ClickUp -> Settings -> Apps. |
| `workspace_id` | text | No | Workspace ID from `app.clickup.com/{id}/...`. Required for search, time tracking, chat, docs, and members. |

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

## License

MIT
