# Integrations Catalog Branch Audit - 2026-05-10

> Status: Historical branch audit. This file preserves the May 10 findings that
> shaped the integration-catalog work, but the "current tree" and findings below
> are not the live repository state. A May 24 code check found the local `dev`
> branch clean against `origin/dev`, with account-aware Google/TickTick OAuth,
> workspace/admin middleware on package OAuth routes, packaged catalog lookup,
> paged catalog loading, catalog-unavailable UI state, global integration-card
> dedupe, and focused feature/browser coverage now present. Use
> `docs/ui/pages/integrations.md`, `docs/planning/integrations.md`, and
> `docs/architecture/docs-code-alignment-audit-2026-05-24.md` for the current
> integration surface.

## Scope

This original audit covered the May 10 `dev` branch against `origin/main`, plus
the then-uncommitted integration-catalog and multi-account integration work in
the OpenCompany and sibling `integrations` working trees.

No commit was created during this audit.

## Original Audit Tree State

OpenCompany:

- Branch: `dev`
- Remote state: `dev...origin/dev [ahead 1]`
- Baseline branch diff against `origin/main...HEAD`: 138 files changed, 20,706 insertions, 7,885 deletions
- Uncommitted OpenCompany areas:
  - `AGENTS.md` and `CLAUDE.md` repo instructions
  - integration config API and model multi-account behavior
  - integration catalog API and catalog service
  - integrations UI cards/config modal/catalog merge
  - memory tool tests and integration catalog tests

Sibling `integrations` repo:

- Branch: `main`
- Remote state: clean relative to `origin/main`
- Uncommitted files:
  - `packages/google/src/GoogleClient.php`
  - `packages/google/src/GoogleOAuthController.php`
  - `packages/google/src/GoogleServiceProvider.php`
  - `packages/ticktick/src/TickTickOAuthController.php`
  - `packages/ticktick/src/TickTickServiceProvider.php`
  - `catalog/` package and generated catalog resource
  - `build-catalog.php`

## Inventory Snapshot

Catalog file: `/Users/rutger/Sites/integrations/catalog/resources/integrations-catalog.json`

- Catalog integrations: 591
- Catalog tools: 41,493
- Catalog categories:
  - analytics: 34
  - data: 213
  - productivity: 337
  - rendering: 7
- Installed integration packages: 14
- Runtime providers registered in OpenCompany: 26

Runtime provider list:

`celestial`, `clickup`, `coingecko`, `exchangerate`, `google-calendar`, `gmail`, `google-drive`, `google-contacts`, `google-sheets`, `google-search-console`, `google-tasks`, `google-analytics`, `google-docs`, `google-forms`, `mermaid`, `plantuml`, `plausible`, `ticktick`, `trustmrr`, `typst`, `vegalite`, `worldbank`, `mcp_exa_search`, `mcp_deepwiki`, `mcp_context7`, `mcp_cloudflare_docs`.

The important product distinction is that the catalog has 591 possible integrations, while the local runtime can only execute the installed package/provider subset. The UI must keep "catalog available" separate from "package installed/runnable".

## May 24 Refresh

The following May 10 findings were rechecked against current code on May 24:

- Multi-account config and OAuth alias propagation now exist in
  `DynamicConfigModal.vue`, `IntegrationConfigResolver`, Google OAuth, TickTick
  OAuth, Google token refresh persistence, and `IntegrationOAuthAccountTest`.
- Google and TickTick OAuth authorize/callback routes now carry
  `ResolveWorkspace` and `EnsureWorkspaceAdmin` middleware in the Laravel route
  table.
- Catalog lookup now uses the packaged catalog path with availability metadata;
  `IntegrationCatalogControllerTest` covers the unavailable-file response.
- `Integrations.vue` now loads catalog entries in pages (`perPage = 50`), shows
  load-more/unavailable/error states, and uses global card-key dedupe when
  merging runtime, catalog, and MCP entries.
- Remaining risk is no longer the P0/P1 branch blockers below. Current risks are
  the usual product-hardening items: deeper provider-specific setup UX, broader
  browser coverage for catalog interactions, and continued separation of
  installed/runnable/catalog-only states.

## Original Findings

### P0 - Multi-account OAuth is not wired end to end

The UI saves the selected account before redirecting to OAuth, but the redirect does not carry the account alias:

- `resources/js/Components/integrations/DynamicConfigModal.vue:394-408`

Google OAuth stores workspace and service in session, but not the selected account alias:

- `/Users/rutger/Sites/integrations/packages/google/src/GoogleOAuthController.php:31-56`

Google callback writes tokens to the first/default setting for the service, not the selected account:

- `/Users/rutger/Sites/integrations/packages/google/src/GoogleOAuthController.php:75-116`

TickTick has the same shape. It reads the first TickTick setting in authorize/callback and never preserves an account alias:

- `/Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:17-35`
- `/Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:52-80`

Google token refresh persistence also updates the first setting for the integration ID, not the account that produced the token:

- `/Users/rutger/Sites/integrations/packages/google/src/GoogleClient.php:196-203`

Impact:

- Configuring default accounts can work.
- Configuring a named account can save credentials to one row, then store OAuth tokens on another row.
- Refreshing tokens can silently mutate the wrong account.
- Any UI that appears to support multiple OAuth accounts is currently unreliable for Google and TickTick.

Required fix:

- Append `account` to OAuth authorize URLs from the config modal.
- Store the account alias in OAuth session state.
- Resolve credentials through `IntegrationSetting::forAccount($accountAlias)` during authorize/callback.
- Persist OAuth tokens to the same `integration_id` + `account_alias` row.
- Pass account context into Google client construction and token refresh persistence.
- Add tests for named OAuth accounts and default OAuth accounts.

### P1 - OAuth routes are weaker than config routes for workspace scoping

The package OAuth routes are registered with only `web` and `auth`:

- `/Users/rutger/Sites/integrations/packages/google/src/GoogleServiceProvider.php:184-191`
- `/Users/rutger/Sites/integrations/packages/ticktick/src/TickTickServiceProvider.php:34-41`

They resolve workspace through session state and helper methods, but the route itself does not enforce the same workspace/admin middleware path as the config API. That is less strict than the settings endpoints that users operate from.

Impact:

- Normal UI navigation probably works because `ResolveWorkspace` has already set session state elsewhere.
- Direct OAuth route hits can depend on stale or missing session workspace state.
- Admin/config authorization is not obviously equivalent to the config save/test/disconnect flow.

Required fix:

- Route OAuth authorize/callback through the app's workspace resolution and authorization middleware where possible.
- If package-level route middleware cannot depend on app aliases, move route registration or middleware wiring into OpenCompany.
- Include workspace/account/service in signed state or session state and verify the callback still matches a workspace the user can administer.

### P1 - Catalog path is local-only and deploy fragile

Original audit state: `IntegrationCatalog` hardcoded the sibling repo path:

- `app/Services/Integrations/IntegrationCatalog.php:144-147`

If the deployed app did not include `/../integrations/integrations-catalog.json`, the endpoint returned an empty catalog.

Impact:

- Local development works.
- Production or staging can silently lose all 591 catalog entries.
- The UI has no strong indication that the catalog is unavailable versus genuinely empty.

Implemented fix:

- Add `opencompanyapp/integration-catalog` as a Composer package owned by the integrations repo.
- Store the generated catalog at `catalog/resources/integrations-catalog.json`.
- Resolve the default catalog path through `OpenCompany\IntegrationCatalog\CatalogLocator`.
- Keep `INTEGRATIONS_CATALOG_PATH` as an override.
- Surface catalog availability in the API response and UI.

### P1 - Hardcoded UI cards still include non-runtime and duplicate integrations

The static UI still defines cards that are not backed by the dynamic integration registry/config endpoint:

- `resources/js/Pages/Integrations.vue:951-976`

Backend static support check:

- `slack`, `discord`, `teams`, `google_chat`, `github_chat`, `linear_chat`: present in config
- `email`, `rest-api`, `webhooks`, `github`: not present in config as dynamic integrations

The catalog also has a real `github` entry under `productivity`, while the static UI has `github` under `developer`, creating duplicate cards because current merge logic only deduplicates within the target category.

Impact:

- Users can see fake install/config state for cards that do not map to runtime providers.
- GitHub can appear twice with different metadata.
- "Installed" can mean hardcoded UI default, runtime-enabled setting, package installed, or built-in feature depending on the card.

Required fix:

- Remove or clearly separate static non-runtime cards.
- Treat `webhooks` as a built-in feature surface, not a dynamic integration card, unless it has a backend provider.
- Merge catalog/runtime cards globally by ID, not only within category.
- Prefer runtime/catalog metadata over static placeholders.

### P2 - Catalog UI loads all entries eagerly without real paging or loading state

The UI requests `perPage=1000` and merges every catalog entry on mount:

- `resources/js/Pages/Integrations.vue:750-768`

The API supports paging/search/category, but the UI currently bypasses that by loading the whole catalog into the page.

Impact:

- It works for 591 entries locally.
- It does not scale well if the catalog grows.
- Search/filtering remains client-heavy and the user gets no explicit loading or unavailable state.

Required fix:

- Add server-backed search/category paging to the UI.
- Add loading, error, and "catalog unavailable" states.
- Keep installed/runtime integrations visible first and lazy-load catalog-only suggestions.

### P2 - Integration card merge only deduplicates inside one category

`mergeIntegrationCard` looks for an existing card only in the incoming card's category:

- `resources/js/Pages/Integrations.vue:732-748`

Impact:

- Same integration ID in another static/catalog category produces duplicate cards.
- This currently affects `github` and can affect any catalog item with a category that differs from the static seed.

Required fix:

- Search all categories for an existing ID before appending.
- If moving categories, remove from the old category or keep the runtime category as canonical.

### P2 - Coverage gaps remain around the newest integration behavior

Current tests cover the catalog endpoint and broad runtime/memory surfaces, but do not yet cover the risky new wiring:

- multi-account `show`, `save`, `test`, and `disconnect` behavior per alias
- OAuth account alias preservation for Google and TickTick
- Google token refresh persistence per account alias
- OAuth route workspace/admin authorization behavior
- catalog missing-file/deploy behavior
- frontend duplicate prevention and catalog loading/error states

Impact:

- The focused suite passes, but the highest-risk new integration behavior can still regress.

Required fix:

- Add package tests in the integrations repo for Google/TickTick account-aware OAuth.
- Add OpenCompany feature tests for multi-account API behavior.
- Add focused frontend tests or component-level checks for global card dedupe if the project has a frontend test runner.

### P3 - Full project validation is currently noisy

Fresh validation that passed:

- `php -l` on changed PHP files
- `git diff --check`
- `php artisan test --testsuite=Feature --filter='Integration|DynamicProvider|ToolRegistry|CodeApiDocGenerator|LuaSandbox|Memory'`
  - 197 passed, 7 skipped

Validation that failed due to broader existing drift:

- `npm run typecheck`
  - Fails across unrelated Vue/TypeScript files, not obviously in the integration files.
- `php artisan test`
  - First isolated failure: `Tests\Feature\AgentCommunicationServiceTest::test_get_or_create_dm_channel_creates_new_dm`
  - Error: `Call to undefined method App\Services\AgentCommunicationService::resetDepth()`
  - Full run then cascades into SQLite transaction failures.

Impact:

- The targeted integration/runtime suite is green.
- The repo does not currently have a clean full PHP or TypeScript gate, so branch readiness cannot be judged from full-suite green status.

Required fix:

- Fix or quarantine the stale `AgentCommunicationServiceTest` setup failure.
- Clean the TypeScript baseline enough that integration UI changes can be checked reliably.

## Recommended Fix Plan

1. Fix OAuth account propagation first.
   - This is the highest-risk functional break because the UI implies multi-account OAuth support.
   - Update `DynamicConfigModal.vue`, Google OAuth, TickTick OAuth, and Google token refresh persistence together.

2. Align OAuth route scoping with integration config routes.
   - Add workspace/account/service verification to OAuth state.
   - Ensure the authenticated user can administer the workspace before starting and completing OAuth.

3. Make the catalog deployable.
   - Add config/env catalog path.
   - Add a sync/copy command or build step.
   - Add unavailable-state API/UI behavior.

4. Clean up integration cards.
   - Remove fake dynamic cards or move them to a separate built-in section.
   - Globally dedupe cards by integration ID.
   - Clarify installed/runnable/catalog-only states in the card model.

5. Add focused tests for the new behavior.
   - OpenCompany API tests for aliases.
   - Integrations package tests for OAuth alias persistence.
   - UI/component coverage for duplicate prevention where practical.

6. Restore broad validation gates.
   - Fix the `AgentCommunicationService::resetDepth()` test drift.
   - Reduce TypeScript baseline errors enough that `npm run typecheck` is meaningful for UI wiring.

## Bottom Line

The branch has a useful catalog foundation and the focused runtime suite passes, but it is not ready as a full integration UX yet. The main blocker is multi-account OAuth: named-account setup can write credentials and tokens to different rows. The second blocker is product clarity: the UI mixes runtime integrations, catalog-only entries, hardcoded built-ins, and fake/static cards without enough distinction.
