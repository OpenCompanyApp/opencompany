# OpenCompany Repo Guide

This file is intentionally mirrored in both `AGENTS.md` and `CLAUDE.md`. Keep them identical.

## Product Direction

- `docs/PRODUCT_DIRECTION.md` is the locked product direction (final, accepted 2026-11-02) and the top documentation authority for what OpenCompany is becoming: "Grok Bot for companies", a messenger-first app with named AI teammates, optional per-agent computers via a `ComputerProvider` contract, the VFS as the governed path to company data, governance as the moat, built-in Tables/Issues/Docs, connections/events/routines (pillar 9, see `docs/planning/connections-events-routines.md`), AGPL self-host plus hosted Cloud, and mruby as the only Code Mode runtime. `docs/ROADMAP.md` must follow it; `docs/CURRENT_STATE.md` still owns implementation truth.
- The product must work fully without an agent computer; never make a computer a hard requirement. A computer never bypasses the VFS for company data.
- API-first now: Inertia is frozen. Do not add new Inertia pages or page props; new UI, starting with the messenger, reads and writes through the versioned API plus the realtime stream.
- UI structure is locked (`docs/planning/ui-structure-and-agent-editing.md`, accepted): the new client is a Vue 3 + Reka UI single-page app with a token design system, one left rail, Home as the attention page (not "Inbox"), three panes, and flat Spaces. Old Inertia pages are deleted as the new client reaches parity. Core primitives are fixed (Threads, Issues, Tables/Records, Docs, Files, Artifacts, Routines, Agents, People); every Space has six tools. Artifacts (Pages and interactive HTML on the MCP Apps bridge, served from a separate cookie-less user-content origin, with mruby `endpoint` logic) are the only code-bearing primitive and never own storage (`docs/planning/artifacts-and-forms.md`). Forms are a view on any Table and submit only through the one governed pipeline. Add-on apps are manifests built only from the primitives and never own storage. Agents edit structured data through change sets (review, apply, revert this run; human edits win).
- Governance is one policy engine with Personal, Team and Company presets (pillar 5). Presets set defaults only and are never paywalled. Gate actions by reversibility, not by "write": reversible change sets auto-apply, delete goes to a 30-day trash, and purge, external sends, spend and exposure follow the preset table. Always-on invariants (hard-deny floor, on-behalf-of access intersection, an agent never approves, triggers are never consent, audit log) are never relaxed by a preset. Access is Space-level only (no per-doc ACLs); agents join Spaces like teammates. The autonomy slider only writes scoped grants; behaviour modes (autonomous/supervised/strict) are deleted, so do not build on them.
- The Rust `oc` CLI (client and connected-host daemon) lives in the separate Apache-2.0 `opencompany-cli` repository (`docs/planning/oc-cli-and-hosts.md`). It uses only the public versioned API and the realtime stream, so never add CLI-only or private endpoints; every capability it needs must be in the OpenAPI spec. Connected hosts connect outbound only and are governed like any other computer: host approval is `exposure_widening`, capabilities are granted per agent, and host tokens are scoped and rotated.
- Data sovereignty (pillar 11, `docs/planning/data-sovereignty.md`) is a product invariant. Every new primitive or app record must be exportable through object downloads and the round-trippable `.ocarchive`, which has a CI round-trip test. Self-hosted installs send no telemetry and have no remotely flippable flags or egress switches. Never add phone-home code. Agents can never change backup or mirror destinations or export credentials.
- Do not add per-app integration packages, a visual flow builder, or new Automations; use connections, routines, triggers and the workspace event log.

## Local

- Local URL: `http://opencompany.test`
- Ngrok URL: `https://your-subdomain.ngrok-free.dev` for Telegram webhooks and external integrations; set up your own with `ngrok http 80`
- Use `http://opencompany.test` for local navigation and testing.
- Stack: Laravel 12, Vue 3, Inertia.js, Tailwind CSS v4, Reka UI

## Workspace

- OpenCompany is multi-workspace. Scope queries correctly.
- `ResolveWorkspace` binds the active workspace as `currentWorkspace`.
- `workspace()` returns the current `Workspace`.
- Models with `workspace_id` should use `forWorkspace()`.
- Related models should be scoped through the relation, typically with `whereHas(...)`.
- Humans belong to workspaces through `workspace_members`. Agents have a direct `workspace_id`.

## Runtime

- Main runtime agent class: `app/Agents/OpenCompanyAgent.php`
- Identity/system-prompt content is assembled from identity files and agent config, not from a static hardcoded prompt.

## Commenting Style

- Prefer extensive comments that explain intent, contracts, invariants, ownership, and side effects; avoid comments that merely restate syntax.
- Add class-level docblocks for services, jobs, agents, tools, controllers, policies, runtime components, and other important models. State what the code owns, what it does not own, and any workspace/security/package boundary.
- Add method docblocks for non-trivial public methods and complex private methods. Cover input shape, return meaning, failure behavior, network/LLM/tool side effects, workspace scope, and secret-handling expectations where relevant.
- Place short invariant comments before rules that must not be reordered or bypassed, especially permission order, workspace scoping, approvals, provider/model resolution, MCP normalization, queue failure handling, and external mutations.
- Leave agent-oriented breadcrumbs when code is intentionally app-local instead of package-owned, or when a future coding agent might otherwise “simplify” away an important boundary.
- In tests, comment the scenario/regression being protected rather than each assertion.

## UI

- Shared UI components live in `resources/js/Components/shared/`.
- Prefer wrapper components over native elements when equivalents already exist.
- Dark mode exists and should not be broken.

## Routing

- Frontend route generation is owned by Laravel Wayfinder, not Ziggy.
- Inertia pages are frozen (API-first). Wayfinder remains the routing stack for the existing pages; whether the API clients use Wayfinder or a generated OpenAPI client is still open in `docs/PRODUCT_DIRECTION.md`.
- Generated frontend route/action sources live in `resources/js/routes`, `resources/js/actions`, and `resources/js/wayfinder`.
- Run `npm run wayfinder:generate` after changing Laravel routes or controller actions that are called from Vue.
- Prefer generated route/action imports and helpers from `resources/js/composables/useWorkspace.ts` over string-built URLs.
- Docker builds generate Wayfinder sources in a PHP stage, then run Vite with `WAYFINDER_SKIP_GENERATE=true` in the Node stage.

## Runtime Ownership

- OpenCompany owns its AI runtime in `app/Domain/Ai`, `app/Ai`, `config/ai.php`, and the provider/model catalogs.
- Do not reintroduce Prism, Prism Relay, Prism Server, or Prism Codex as runtime dependencies.
- Provider transport behavior, model metadata, prompt caching, cost accounting, Codex OAuth, embeddings, and the OpenAI-compatible gateway are app-owned.
- The integration package system is being deleted and the `../integrations` repository is archived read-only (Phase 0 nuclear cleanup). Do not add code to it, depend on new packages from it, or patch it. Old package source may be read only to mine code for native connectors.
- Deep connectors (storage mounts such as Google Drive and OneDrive, mailboxes, calendars) are app-owned under `app/Domain/Connections/`. Generic reach goes through the rebuilt MCP client on `laravel/mcp`, API manifests, SKILL.md skills and inbound webhooks. Leave a breadcrumb comment in connector code stating that it is intentionally app-owned and not a package, so future agents do not "extract" it back into a package.
- Bowerbird boundary (`docs/planning/bowerbird-synergy.md`): OpenCompany owns business automation (routines, events, approvals, agent work); Bowerbird owns software delivery and heavy durable pipelines. Bowerbird is reached only through the `ComputerProvider` driver, the Bowerbird deep connection, the shared mruby engine (Bowerbird owns the guest, OpenCompany owns host bindings and permissions) and shared CLI crates. Self-hosted OpenCompany must never require a Bowerbird account. Aya is discontinued.
- Do not patch `vendor/` for durable fixes.
- Avoid hardcoding provider IDs, model IDs, API formats, URLs, capabilities, auth modes, or runtime support lists outside the app-owned provider/model catalogs or config.

## Working Notes

- Prefer `rg` and `rg --files` for search.
- Keep edits targeted. Do not revert unrelated user changes.
- Never commit automatically. Only create commits when the user explicitly asks for a commit.
- Run only the tests that directly matter for the change during local work; CI/CD is responsible for full suite coverage unless the user explicitly asks for a full local run.
- Put audits and investigations into `docs/`.
- MCP CLI: `~/.local/bin/mcp-cli`
- MCP config: `~/.config/mcp/mcp_servers.json`
- Common MCP usage: `mcp-cli`, `mcp-cli info <server>`, `mcp-cli call <server> <tool> '<json>'`

## Docs

- Repo rules and local setup: `CLAUDE.md`
- Docs index: `docs/INDEX.md`
- Runtime audit: `docs/architecture/runtime-alignment-implementation-audit.md`
- Plane tool is available via `mcp-cli`

## Licensing

- The current working tree is prepared prospectively for `AGPL-3.0-only` on the complete
  self-hosted application. Historical releases retain the BSL or FSL terms actually shipped.
- Public protocols, schemas, SDKs, integration/provider kits, examples, and templates should use
  Apache-2.0 only when their path notices explicitly establish that boundary.
- Separate Cloud operations may remain proprietary, but they must not privately fork, patch, copy,
  or replace functionality required by the complete AGPL self-hosted application.
- Contributions default to inbound equals outbound with DCO certification. Do not infer copyright
  assignment or proprietary relicensing authority from a pull request.
- Keep `AGENTS.md` and `CLAUDE.md` byte-for-byte identical.

## Browser Automation

Use `playwright-cli` from the shell for all browser work; never use the Playwright MCP server. The CLI is more token-efficient and keeps concurrent agents isolated, while MCP's shared browser profile can cause lock conflicts and lost login state.

Always use the named session `-s=browser`. Open it persistently with `playwright-cli -s=browser open <url> --persistent`; add `--headed` only when the user must see or interact with the browser.

Load `.auth/state.json` with `playwright-cli -s=browser state-load .auth/state.json` when that file is available. If login is required or the saved state has expired, open the session headed, ask the user to log in manually, then save the refreshed state with `playwright-cli -s=browser state-save .auth/state.json`. Treat this file as a secret and ensure it is gitignored.

Use `playwright-cli -s=browser snapshot` and its element refs for interactions. Reuse refs until navigation or a major page update makes them stale; then take a fresh snapshot. Use CSS or role selectors only when refs are unavailable.

Inspect failures with `playwright-cli -s=browser console`, `requests`, `screenshot`, or `run-code`. Close only your session with `playwright-cli -s=browser close`; never use `kill-all` or `close-all` unless the user confirms that no other agents are running.

If `playwright-cli` is unavailable, try `npx --no-install playwright-cli` first. If that is unavailable too, install it globally with `npm install -g @playwright/cli@latest`.

Follow any project-specific target URL, sign-in, and live-verification requirements documented elsewhere in this file.
