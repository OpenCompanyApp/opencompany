# Google Integration

Google Calendar, Gmail, Google Drive, Google Contacts, Google Sheets, Google Search Console, Google Tasks, Google Analytics, Google Docs, and Google Forms integration for the Laravel AI SDK. Part of the **OpenCompany** integration ecosystem — an open platform where AI agents collaborate with humans to run organizations.

## Integrations

This package registers **ten separate integrations**, each appearing independently on the integrations page:

### Google Calendar (3 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_calendar_list` | read | List calendars and search/list events |
| `google_calendar_event` | write | Create, update, delete, or quick-add calendar events |
| `google_calendar_freebusy` | read | Check free/busy status across calendars |

### Gmail (4 tools)

| Tool | Type | Description |
|------|------|-------------|
| `gmail_search` | read | Search and list email messages |
| `gmail_read` | read | Get full email content |
| `gmail_send` | write | Send emails or create/send drafts |
| `gmail_manage` | write | Labels, read/unread, trash, and archive |

### Google Drive (3 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_drive_search` | read | Search and retrieve files |
| `google_drive_manage` | write | Create, rename, move, copy, and delete files |
| `google_drive_share` | write | Share files and manage permissions |

### Google Contacts (2 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_contacts_search` | read | Search, list, and look up contacts |
| `google_contacts_manage` | write | Create, update, and delete contacts |

### Google Sheets (3 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_sheets_read` | read | Read spreadsheet data, metadata, and search |
| `google_sheets_write` | write | Create spreadsheets and write data |
| `google_sheets_manage` | write | Manage sheets, rows, columns, sorting, and filters |

### Google Search Console (2 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_search_console_query` | read | Search performance, URL inspection, and sitemaps |
| `google_search_console_manage` | write | Submit sitemaps and manage site properties |

### Google Tasks (2 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_tasks_read` | read | List task lists and tasks, get task details |
| `google_tasks_manage` | write | Create, update, complete, delete, and organize tasks |

### Google Analytics (8 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_analytics_list_properties` | read | Discover accessible GA4 accounts and properties |
| `google_analytics_metadata` | read | List available GA4 dimensions and metrics |
| `google_analytics_report` | read | Run a standard GA4 Data API report |
| `google_analytics_realtime` | read | Run a GA4 realtime report |
| `google_analytics_check_compatibility` | read | Check dimension and metric compatibility |
| `google_analytics_pivot_report` | read | Run an advanced pivot report |
| `google_analytics_batch_run_reports` | read | Run multiple standard reports in one request |
| `google_analytics_batch_run_pivot_reports` | read | Run multiple pivot reports in one request |

### Google Docs (2 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_docs_read` | read | Read document content, structure, and search text |
| `google_docs_write` | write | Create, edit, format, and manage documents |

### Google Forms (2 tools)

| Tool | Type | Description |
|------|------|-------------|
| `google_forms_read` | read | Read form structure and responses |
| `google_forms_write` | write | Create, edit, and manage forms |

## Installation

```bash
composer require opencompanyapp/integration-google
```

The service provider is auto-discovered by Laravel.

## Configuration

All integrations share the same Google Cloud OAuth credentials (Client ID and Secret only need to be entered once):

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `client_id` | text | Yes | OAuth 2.0 Client ID from Google Cloud Console |
| `client_secret` | secret | Yes | OAuth 2.0 Client Secret |
| `access_token` | oauth | Yes | Connected via OAuth flow |

### Setup

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/)
2. Enable the **Google Calendar API**, **Gmail API**, **Google Drive API**, **People API**, **Google Sheets API**, **Google Search Console API**, **Google Tasks API**, **Google Analytics Data API**, **Google Docs API**, and/or **Google Forms API**
3. Create OAuth 2.0 credentials (Web application type)
4. Add the redirect URI: `{your-domain}/api/integrations/google/oauth/callback`
5. Enter Client ID and Secret in Settings → Integrations
6. Click "Connect" to authorize via OAuth

## Quick Start

```php
use Laravel\Ai\Facades\Ai;

$response = Ai::tools(['google_calendar_list', 'google_calendar_event'])
    ->prompt('List my calendars, then create a meeting called "Team Standup" tomorrow at 10am.');
```

## Dependencies

| Package | Version |
|---------|---------|
| PHP | ^8.2 |
| opencompanyapp/integration-core | ^2.0 |
| laravel/ai | ^0.1 |

## License

MIT
