# OpenCompany Repo Guide

This file is intentionally mirrored in both `AGENTS.md` and `CLAUDE.md`. Keep them identical.

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
- Generated frontend route/action sources live in `resources/js/routes`, `resources/js/actions`, and `resources/js/wayfinder`.
- Run `npm run wayfinder:generate` after changing Laravel routes or controller actions that are called from Vue.
- Prefer generated route/action imports and helpers from `resources/js/composables/useWorkspace.ts` over string-built URLs.
- Docker builds generate Wayfinder sources in a PHP stage, then run Vite with `WAYFINDER_SKIP_GENERATE=true` in the Node stage.

## Runtime Ownership

- OpenCompany owns its AI runtime in `app/Domain/Ai`, `app/Ai`, `config/ai.php`, and the provider/model catalogs.
- Do not reintroduce Prism, Prism Relay, Prism Server, or Prism Codex as runtime dependencies.
- Provider transport behavior, model metadata, prompt caching, cost accounting, Codex OAuth, embeddings, and the OpenAI-compatible gateway are app-owned unless they clearly belong in `../integrations`.
- Inspect sibling package source before patching integration-runtime behavior. In this workspace, integration package code may be path-based or symlinked into `vendor/`.
- Common package sources:
  - `../integrations/core`
  - `../integrations/packages/*`
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
