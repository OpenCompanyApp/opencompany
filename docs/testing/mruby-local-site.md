# Isolated macOS mruby site and worker qualification

Verified on 2026-09-08. The user selected a separate test site/database instead
of replacing the running VFS/run-control checkout. This is real local Laravel,
PHP-FPM, SQLite, and database-queue evidence, not production deployment evidence.

## Isolation and installation

- Site: `http://opencompany-mruby.test`, resolved by the existing Valet parked
  `/Users/rutger/Sites` directory. No shared Nginx/PHP configuration was changed.
- Checkout: `opencompany-mruby`, branch `feat/mruby`.
- Database: ignored `.runtime/mruby-site/database.sqlite`, migrated from empty.
  No real application database or legacy user source was copied or migrated.
- Workspace: synthetic `mruby-qualification`, initialized using the real setup
  form. No broad seeders or provider credentials were installed.
- Session cookie: `opencompany_mruby_qualification_session`, distinct from the
  primary application's cookie. The ignored `.env` and `.auth/state.json` are
  permission-restricted; authentication values are not published in this guide.
- Queue: `database`, with the unique name `mruby-qualification`. No scheduler,
  continuous worker, service restart, or primary-worker drain was performed.
- Broadcasting and mail use local log drivers. No integrations are configured.
- Engine: released `v0.1.0-rc.1` macOS ARM64 binary, SHA-256
  `120c90bb38ac52c03b4c91518ebb43fcfa8f25cab8c3e9d3c684d9754ff40688`.
  The same ignored configuration is consumed by PHP-FPM and CLI workers.

## Real browser execution

The Playwright CLI named session completed the normal first-time setup flow
with synthetic QA data, then opened the workspace's Developer Code Console.
For `puts "mruby web smoke"; 6 * 7`:

1. Validate returned HTTP 200, `validatedOnly: true`, no error, and empty output.
2. Run returned HTTP 200, console profile, log `mruby web smoke`, and integer `42`.
3. The built Vue output displayed both the log and `Return: 42` in dark mode.

This exposed and fixed a real client bug: `typeof null === 'object'` caused
successful responses to display a JavaScript error while inspecting diagnostic
locations. The null contract is now explicit in both console components, with
a [reusable mocked-browser regression](mruby-console-browser.md) for CI.

## Real database queue execution

The browser created one script automation, `mruby isolated worker smoke`, owned
by the workspace's system Automation agent. Source:

```ruby
puts "mruby worker smoke"; 6 * 7
```

Its admission stored `opencompany-code-v1` and the exact installed engine digest.
The annual UTC schedule was chosen to avoid an imminent scheduled run. Clicking
Run now queued one `RunAutomationJob` on `mruby-qualification`; its script branch
invoked `RunScriptAutomationJob` synchronously under the outer 60-second policy.
To reproduce with an explicit worker memory ceiling:

```sh
php artisan queue:work database --once --queue=mruby-qualification \
  --tries=1 --timeout=60 --sleep=0 --memory=1024
```

Observed persisted evidence: one completed automation task, `return_value: 42`,
log `mruby worker smoke`, run count 1, consecutive failures 0, and zero bridge
callbacks/tool calls. This source invokes no external capability or LLM.

Three follow-up jobs were inspected before draining: MessageSent, SyncToChat,
and TaskUpdated. Broadcasting was local-log only, and the message channel was an
internal DM without an external provider/ID, so SyncToChat returned before an
adapter call. Bounded workers drained those jobs; jobs and failed_jobs both
ended empty. The browser disabled the smoke automation. No worker remains
running for this site.

## Retained local evidence and limits

Ignored `.runtime/mruby-site/` retains console/automation screenshots and a
consistent `qualified-backup.sqlite` snapshot. The directory is private; the
database/backup and saved authentication state are not Git artifacts. The backup
contains synthetic test data and authentication state, so treat it as private.

The primary `opencompany.test` site, database and workers remain separate.
There was no real provider request, model-quality benchmark, production cutover,
or real-user source migration. Parser-backed static validation, matched-model AX,
deeper native security qualification, and coordinated dependency releases remain
tracked in the [migration evidence](../planning/mruby-implementation-progress.md).
