# Docs/Code Alignment Audit

Date: 2026-05-24
Status: In progress. This audit records code-backed documentation alignment work; it is not a claim that every Markdown file has been fully checked yet.

## Current Evidence Pass

The current pass checked the tracked and confidential Markdown inventory against high-risk code surfaces:

- Markdown inventory after this pass: 154 files across tracked docs plus `docs/confidential`.
- Route inventory: 348 Laravel routes from `php artisan route:list --json`.
- UI page inventory: 23 `docs/ui/pages/*.md` files covering routed page groups and legacy redirect surfaces.
- `AGENTS.md` and `CLAUDE.md` remain byte-identical.

## Updates Made In This Pass

- Added [files.md](../ui/pages/files.md) for `resources/js/Pages/Files.vue`, `useFileManager`, and file/disk API endpoints.
- Added [developer.md](../ui/pages/developer.md) for `Developer/Tools.vue`, `Developer/LuaConsole.vue`, `/api/tools/catalog`, and `/api/lua/execute`.
- Added [messages.md](../ui/pages/messages.md) to document that `/messages` routes now redirect into Chat, while legacy `Messages/*.vue` components remain present but unrouted.
- Added [workspace.md](../ui/pages/workspace.md) for setup, workspace creation, and invitation acceptance flows.
- Updated [chat.md](../ui/pages/chat.md) and [profile.md](../ui/pages/profile.md) so DM links and redirects match current routes.
- Updated [../discord.md](../discord.md) so the sidecar design is clearly marked historical and the current Chatogrator adapter/webhook path is not contradicted.
- Updated [../INDEX.md](../INDEX.md) so the new UI page docs are discoverable.

## Mechanical Checks

These checks passed after the updates:

```bash
git diff --check
```

```bash
# Local Markdown links across tracked Markdown plus docs/confidential
php <local-link-checker>
```

```bash
# docs/INDEX.md coverage for tracked docs
php <docs-index-coverage-checker>
```

```bash
# resources/js/Components/shared inventory against docs/ui/components.md
php <shared-component-inventory-checker>
```

```bash
# documented API route references against php artisan route:list --json
php <api-route-reference-checker>
```

```bash
# resources/js/Pages coverage against docs/ui/pages/*.md
php <ui-page-coverage-checker>
```

## Known Historical/Imported Exceptions

The stale-term scan still reports expected historical/imported references:

- `docs/architecture/laravel-ai-sdk.md` keeps old GLM and Prism removal details, but the file is marked historical and points current runtime readers to `ai-provider-runtime-architecture.md`.
- `docs/vendor-open-source-audit-2026-04-10.md` and `docs/vendor-open-source-critical-audit-2026-04-10-round-2.md` intentionally retain Prism findings as removal rationale.
- `docs/ecosystem/kosmokrator/**` and `docs/ecosystem/iris/**` are imported ecosystem context; Prism references there describe those repos or historical comparisons, not current OpenCompany runtime dependencies.
- `docs/confidential/strategy/fair-code-valuation-strategy.md` uses `Sustainable Use License` as an n8n comparator, not as an OpenCompany license claim.

## Remaining Work

Completion remains unproven until the broader Markdown set has been sampled or mechanically checked for stale claims beyond route/page/provider surfaces, especially older planning docs and confidential strategy docs.
