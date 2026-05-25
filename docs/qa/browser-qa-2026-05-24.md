# Browser QA Report - 2026-05-24

## Scope

This QA pass used the local app at `http://opencompany.test` with the built-in browser/Playwright browser surface. The pass focused on the authenticated OpenCompany shell, workspace routing, AI chat UI, integrations, dark/light mode, responsive layout, and safe rollback handling.

Evidence artifacts are in `docs/qa/evidence/browser-2026-05-24/`. The database and `storage/app` were backed up before browser work to `/tmp/opencompany-qa-20260524T183920Z`.

## State Safety

- Database backup: `/tmp/opencompany-qa-20260524T183920Z/opencompany.before.dump`
- Storage backup: `/tmp/opencompany-qa-20260524T183920Z/storage-app.before.tgz`
- Restore target: PostgreSQL database `opencompany`, `storage/app`
- Browser-generated evidence was moved under `docs/qa/evidence/browser-2026-05-24/`
- A final database/storage restore is part of this report workflow so the local DB/storage ends at the pre-QA baseline.

## Coverage

| Area | Pages / feature surfaces checked | Evidence |
| --- | --- | --- |
| Dashboard | `/w/default`, `/w/test` | `qa-test-dashboard.yml` plus Playwright snapshots |
| Tasks | `/w/default/tasks`, `/w/default/tasks/{id}`, `/w/default/tasks/analytics`, `/w/test/tasks` | `qa-test-tasks.yml`, `qa-test-tasks-console.log` plus Playwright snapshots |
| Lists/docs/files/activity | `/w/default/lists`, `/w/default/docs`, `/w/default/files`, `/w/default/activity` | Playwright snapshots and console logs |
| Approvals | `/w/default/approvals`, sidebar badges in both workspaces | `qa-test-dashboard.yml`, prior approvals console log |
| Automation | `/w/default/automation`, `/w/default/automation/create`, `/w/default/automation/{id}/edit` | Playwright snapshots and console logs |
| Org/settings/workload | `/w/default/org`, `/w/default/settings`, `/w/default/workload` | Playwright snapshots and console logs |
| Integrations | `/w/default/integrations`, `/w/test/integrations` | `qa-test-integrations.yml`, `qa-test-integrations-console.log` |
| Developer tools | `/w/default/developer/tools`, `/w/default/developer/lua-console` | Playwright snapshots and console logs |
| Calendar/tables | `/w/default/calendar`, `/w/default/tables`, `/w/default/tables/{id}` | Playwright snapshots and console logs |
| Agent/profile | `/w/default/agent/a1`, `/w/default/profile/h1` | Playwright snapshots and console logs |
| Chat | `/w/default/chat?agent=a1`, `/w/test/chat?agent=a1`, new assistant chat, agent selector, status panel, tool timeline | `qa-default-chat-forced-light.png`, `qa-dark-chat-test.png`, `qa-chat-new-assistant-test.yml`, `qa-chat-agent-selector-test.yml` |
| Messages/DM redirects | `/w/default/messages`, `/w/default/messages/h1` | `qa-messages-redirect.yml`, `qa-messages-dm-self-redirect.yml`, `qa-messages-redirect-console.log` |
| Theme/responsive | Desktop light, desktop dark, mobile dark, mobile light | `qa-default-chat-forced-light.png`, `qa-dark-chat-test.png`, `qa-mobile-chat-dark.png`, `qa-mobile-status-light.png` |

## Findings

### P1 - Integrations page crashes its webhook API because the local DB is missing `integration_webhooks`

`/w/default/integrations` and `/w/test/integrations` render, but the browser console records a 500 from `GET /api/integration-webhooks`.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-test-integrations-console.log`
- Laravel log: `SQLSTATE[42P01]: Undefined table: relation "integration_webhooks" does not exist`
- `app/Http/Controllers/Api/IntegrationWebhookController.php` reads `IntegrationWebhook::forWorkspace()` in `index()`
- `database/migrations/2026_05_24_000002_create_integration_webhooks_table.php` exists, so the current local schema is behind the code

Suggested fix:

Run and verify the pending migration in local/dev environments, then add a feature test for `GET /api/integration-webhooks` returning `200` with an empty `data` array on a fresh workspace. The Integrations page should also surface an inline "webhooks unavailable" state instead of relying on a console-only failure.

### P1 - Auth/session expiry leaves an authenticated shell visible while API calls fail with 401/419

Multiple pages continued showing the app shell and composer after the browser session had expired. API calls then failed in the console, including `GET /api/approvals`, `GET /api/tasks?...`, and `PATCH /api/users/h1/presence`.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-default-chat-light-console.log`
- `docs/qa/evidence/browser-2026-05-24/qa-mobile-status-dark.png`
- `docs/qa/evidence/browser-2026-05-24/qa-mobile-status-light.png`
- Status panel showed `Unauthenticated.` while the chat shell and composer remained visible

Suggested fix:

Add a shared Axios/Wayfinder response interceptor for `401` and `419`. It should stop polling/presence updates, clear optimistic authenticated UI state, and redirect to login or show a single session-expired recovery panel. Presence updates should not log noisy stack traces when the session is gone.

### P1 - Test workspace chat can display default-workspace agents and conversations

Opening `/w/test/chat?agent=a1` shows the Test workspace shell but still presents Atlas, default-workspace conversations, and the default chat count. The Test workspace fixture only has the system automation agent, while `a1` belongs to the Default workspace.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-test-chat-invalid-agent.yml`
- `docs/qa/evidence/browser-2026-05-24/qa-chat-agent-selector-test.yml`
- `docs/qa/evidence/browser-2026-05-24/qa-chat-agent-selector-after-automation.yml`

Suggested fix:

Validate `agent`, `dm`, and channel query parameters against the current workspace before loading chat state. If the selected id is not visible in the workspace, remove the query parameter and show the new-chat empty state for the current workspace only. The conversation rail and agent selector should be sourced from workspace-scoped endpoints and should not reuse cached/default workspace data across workspace slugs.

### P1 - Sidebar approval badge is not aligned with approval state

Both Default and Test sidebars show `Approvals 3`, but the current `approval_requests` table has zero pending approvals and no workspace column. This makes the badge misleading and possibly global/stale.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-test-dashboard.yml`
- `docs/qa/evidence/browser-2026-05-24/qa-test-chat-invalid-agent.yml`
- Database inspection during QA showed `approval_requests.status = pending` count was `0`

Suggested fix:

Define the badge source explicitly. If it means pending approvals, compute it from `approval_requests.status = pending` and workspace scope through requester/channel/task relationships. If it means something else, rename the badge source and add a tooltip or page-level matching count. Add a regression test that the Test workspace does not inherit Default workspace approval counts.

### P2 - Agent selector updates visible state without updating the URL

In `/w/test/chat?agent=a1`, selecting `Automation` changed the composer placeholder to `Ask Automation...`, but the URL remained `?agent=a1`. The visible agent and URL state diverge.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-chat-agent-selector-after-automation.yml`

Suggested fix:

Make the selector write the selected agent id to the route query with Inertia/router state replacement, or clear the invalid query when the selected agent is local component state. Add a test that choosing an agent updates both the header/composer and URL query consistently.

### P2 - Mobile new-chat layout lets the sticky composer cover suggestion cards

On a 390px wide mobile viewport, the sticky composer covers the lower suggestion-card area. The first card is visible, but the lower cards are partly hidden behind the fixed composer before the user scrolls.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-mobile-chat-dark.png`

Suggested fix:

Increase the mobile bottom padding of the scroll container by the actual composer height plus safe-area inset. Treat the suggestion cards and composer as separate layout regions so the empty state can scroll fully above the composer.

### P2 - Self-DM route creates an odd empty DM surface

`/w/default/messages/h1` redirects to `/w/default/chat?dm=h1` and shows a `Message this DM...` composer for the current user. A self-DM is probably not a useful product surface and can confuse QA and users.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-messages-dm-self-redirect.yml`
- `docs/qa/evidence/browser-2026-05-24/qa-messages-redirect-console.log`

Suggested fix:

Block self-DM routes in the redirect/controller layer. Redirect `/messages/{currentUserId}` to the current user's profile or to `/chat` with a non-destructive notice. Add a feature test for self-DM prevention.

### P2 - Composer send state did not recover after auth degradation

During the safe mutation attempt, the DM composer accepted text, but the send button stayed disabled after the session had degraded. This prevented verifying a browser-created message without re-authenticating again.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-dm-composer-state.json`
- `docs/qa/evidence/browser-2026-05-24/qa-dm-composer-dispatch.json`

Suggested fix:

Tie composer availability to explicit auth/channel readiness state. If the session is invalid, disable the composer with a visible session-expired reason. If the session is valid and the channel is ready, reactive message text should enable send reliably after user input.

### P3 - Theme state has two localStorage keys that can disagree

The browser had `theme = light` while `color-mode = dark` and the document still had the `dark` class. The UI appears to use `color-mode`, but stale `theme` creates confusing state and made light-mode verification less deterministic.

Evidence:

- `docs/qa/evidence/browser-2026-05-24/qa-default-theme-state.json`
- `docs/qa/evidence/browser-2026-05-24/qa-localstorage-theme.json`

Suggested fix:

Consolidate theme persistence to one key, migrate any legacy key once on boot, and make the document class derive from that single source. Add a small unit test for `light`, `dark`, and absent-key startup.

## Positive Checks

- Core Default workspace pages generally rendered without browser console errors before session expiry.
- Chat tool run cards now appear chronologically in the message flow.
- Tool names are human readable in the visible timeline, for example `Update Task` and `Search Lua API Docs`.
- Light-mode tool cards and syntax container surfaces are legible in the checked viewport.
- Dark-mode chat, status panel, and new-chat cards render coherently on desktop.
- `/w/default/messages` redirects cleanly to `/w/default/chat`.

## Final Restore Verification

Final restore was performed from `/tmp/opencompany-qa-20260524T183920Z/opencompany.before.dump` after browser QA. Verification used direct PostgreSQL reads, not Laravel/Telescope-backed app requests.

- `storage/app` checksum diff: clean (`final_storage_sha_diff_status=0`)
- `users.h1.password` hash: matches the dump row exactly
- Non-Telescope table counts: restored to the baseline counts
- Telescope tables differ from the earlier `db-counts.before.json` by only the audit rows created during the baseline inspection itself: `telescope_entries` `410481 -> 410478`, `telescope_entries_tags` `240102 -> 240096`

The final DB state is the pre-QA dump state. The only count mismatch is Telescope self-auditing generated between the dump and the initial count file.
