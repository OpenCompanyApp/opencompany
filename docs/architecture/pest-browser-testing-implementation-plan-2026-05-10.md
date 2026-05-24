# Pest Browser Testing Implementation Plan

Date: 2026-05-10
Status: Historical proposal. The current tree has an active Laravel Dusk browser-test suite under `tests/Browser`; the Pest/Playwright notes below are retained as a possible future migration path, not the current browser-testing implementation.

## Current State

- The app currently uses PHPUnit/Laravel test cases under `tests/Feature` and `tests/Unit`.
- `laravel/dusk` is installed and active through `tests/DuskTestCase.php` and `tests/Browser/*`.
- The frontend is Vue 3/Inertia/Tailwind v4, with the most urgent browser coverage needed around login, workspace routing, settings, and integration configuration flows.
- Local full-suite execution is slow enough to interrupt development feedback. Browser tests should be targeted locally and broader in CI.

## Tooling Direction

Use Pest v4 browser testing with Playwright rather than expanding Dusk. Pest browser tests run through Laravel's testing layer while driving a real browser, and support Playwright browser installs, parallel execution, device/browser selection, host configuration, screenshots, console checks, and JavaScript error checks.

Recommended install path:

```bash
composer require pestphp/pest:^4.7 pestphp/pest-plugin-laravel:^4.1 pestphp/pest-plugin-browser:^4.3 --dev
npm install --save-dev playwright@latest
npx playwright install
```

Pest 5 currently requires PHP 8.4, while this app is still declared as PHP 8.2+. Pin the Pest 4 line until the app itself moves to PHP 8.4.

Keep PHPUnit tests in place. Do not migrate existing tests wholesale just to use Pest syntax. Add Pest browser tests only where a browser catches behavior PHPUnit cannot.

## Repo Changes

1. Add Pest dependencies and generated bootstrap:
   - `composer.json`
   - `composer.lock`
   - `tests/Pest.php`
   - `tests/Browser/`

2. Add npm script shortcuts:
   - `test:browser`: `vendor/bin/pest tests/Browser`
   - `test:browser:debug`: `vendor/bin/pest tests/Browser --debug`
   - `test:browser:headed`: `vendor/bin/pest tests/Browser --headed`

3. Update `.gitignore`:
   - `tests/Browser/Screenshots`
   - any Pest/Playwright traces or videos if generated locally.

4. Configure browser defaults in `tests/Pest.php`:
   - host: `opencompany.test` or an explicit browser-test host once verified
   - timeout: long enough for Vite/Inertia transitions
   - default browser: Chromium locally; CI can matrix Firefox later

5. Add a browser-test user/workspace seeding helper:
   - create workspace
   - create admin user
   - bind expected workspace context
   - avoid relying on existing local database state

## First Coverage Slice

Start with smoke tests that catch the issues we just had:

1. Auth smoke:
   - login page renders
   - valid user can log in
   - dashboard/settings route loads after login

2. Settings/integrations smoke:
   - integrations tab renders without "Catalog unavailable"
   - catalog cards render with package-installed status
   - searching for `Aircall` shows a configurable installed package

3. Dynamic integration modal:
   - clicking a configurable catalog card opens the dynamic config modal
   - modal renders text, secret, URL, select, and string-list fields
   - switching integrations does not leak previous field values

4. Integration save path:
   - fill a fake API-key provider config
   - save
   - assert backend setting exists and card updates enabled/configured state

5. Dark-mode smoke:
   - same modal opens in dark mode without invisible text or broken contrast-critical controls.

## CI/CD Shape

- Local default: run the one browser spec relevant to the change, for example `vendor/bin/pest tests/Browser/IntegrationConfigTest.php`.
- Pull request CI: run `npm ci`, `npx playwright install --with-deps`, then browser tests in Chromium.
- Nightly or protected branch CI: run browser tests in parallel and add Firefox.
- Keep screenshots/traces as CI artifacts only on failure.

## Risks And Decisions

- Pest browser testing is newer than Dusk in this app, so introduce it as additive coverage first.
- Major dependency upgrades should not be bundled into the Pest setup. Laravel 13, Inertia 3, Vite 8, Echo 2, and VueUse 14 need their own migration passes.
- Browser tests should prefer stable `data-test` selectors. Add selectors to the Vue integration UI before writing brittle text-only tests.
- If `opencompany.test` is hard to drive in CI, use Pest's host configuration against a controlled local test host.

## Validation For The Implementation PR

Run only targeted local checks:

```bash
npm run build
vendor/bin/pest tests/Browser/AuthSmokeTest.php
vendor/bin/pest tests/Browser/IntegrationConfigTest.php
php artisan test tests/Feature/IntegrationCatalogControllerTest.php
```

Leave the full PHPUnit/Pest suite and browser matrix to CI/CD unless explicitly requested locally.
