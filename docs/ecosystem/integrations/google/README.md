# Google Integration

Google Calendar, Gmail, Google Drive, Google Contacts, Google Sheets, Google Search Console, Google Tasks, Google Analytics, Google Docs, and Google Forms integration tools. Part of the **OpenCompany** integration ecosystem — an open platform where AI agents collaborate with humans to run organizations.

## Integrations

This package registers **ten separate integrations** in the current OpenCompany registry, each appearing independently on the integrations page. Tool counts below are checked against `ToolProviderRegistry`.

### Google Calendar (8 tools)

Current slugs: `google_calendar_create_event`, `google_calendar_delete_event`, `google_calendar_freebusy`, `google_calendar_get_event`, `google_calendar_list_calendars`, `google_calendar_list_events`, `google_calendar_quick_add`, `google_calendar_update_event`.

### Gmail (16 tools)

Current slugs include search/read/send/reply, draft send/create, labels, archive/trash/untrash, read-state changes, sender counts, label listing, and attachment saving.

### Google Drive (15 tools)

Current slugs cover file/folder creation, search, get, copy, rename, move, delete/trash/untrash, star/unstar, share/unshare, and permission listing.

### Google Contacts (7 tools)

Current slugs cover contact create/get/list/search/update/delete and contact-group listing.

### Google Sheets (20 tools)

Current slugs cover spreadsheet create, range read/write/append/clear, batch read/write, metadata, find, sheet add/rename/duplicate/delete, row/column insert/delete, sorting, and filters.

### Google Search Console (9 tools)

Current slugs cover site listing/add/delete, search performance, URL inspection, sitemap list/get/submit/delete.

### Google Tasks (11 tools)

Current slugs cover task-list list/create/delete, task list/get/create/update/complete/delete/move, and clearing completed tasks.

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

### Google Docs (14 tools)

Current slugs cover document create/get/structure/search, text insert/delete/replace, formatting, headings, bullets, page breaks, tables, and images.

### Google Forms (13 tools)

Current slugs cover form create/get, response list/get, publishing, info/settings update, question/section/text-item add/update/delete/move.

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

## License

MIT
