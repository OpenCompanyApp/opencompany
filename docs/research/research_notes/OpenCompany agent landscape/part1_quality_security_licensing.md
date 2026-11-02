# OpenCompany local code audit: quality, tests, security, licensing, momentum

Scope and method: read-only audit of `/home/ruttydm/Projects/opencompany` on branch `feat/vfs` (HEAD `4c62c17`), measured 2026-11-02. All counts come from the **working tree**, which includes a large uncommitted change set (see Momentum). "Verified in code" means I read the file or line myself. "Claimed in docs" means a repo document says it and I did not independently confirm it. Sources are cited as `path:line` or as commit hashes. No tests were run.

## 1. Code quality: size, layering, static analysis, hotspots, adherence to repo rules

### Takeaway
This is a large, single-author Laravel/Vue monolith: about 88k LOC of PHP in `app/` and about 67k LOC of Vue/TS. Layering into `app/Domain/*/Application` is only partial, and much logic still lives in `app/Services` and `app/Agents`. Complexity is concentrated in a few very large files. The biggest is the 5.7k-line `TelegramWebhookPipeline`. On the checked-out branch, the only quality gate is Larastan at level 6. There is no Pint config, no ESLint, and TypeScript runs with `strict: false`. Stronger CI exists only on unmerged side branches.

### Cited Findings
- **Size by area** (PHP/TS/Vue/JS files; generated Wayfinder sources excluded):
  - `app/Domain`: 182 files / 33,556 LOC
  - `app/Agents`: 212 / 20,080
  - `app/Services`: 66 / 12,403
  - `app/Http`: 77 / 10,701
  - `app/Models`: 58 / 5,474
  - `app/Console`: 19 / 2,296
  - `app/Jobs`: 13 / 1,245
  - `app/Ai`: 9 / 615
  - `app/Policies`: **1 file / 49 LOC**
  - `config`: 25 / 3,067
  - `database`: 140 / 8,553
  - `routes`: 5 / 849
  - `tests`: 126 / 31,639
  - `resources/js`: 227 / 66,695
  - Source: `find`/`wc` over the working tree.
- **Totals:** 658 PHP files / 88,022 LOC in `app/`. Frontend has 195 `.vue` files (60,732 LOC) and 32 hand-written `.ts` files (5,963 LOC), plus 140 generated Wayfinder `.ts` files in `resources/js/{routes,actions,wayfinder}` (source: `find`/`wc`).
- **Domain layer is lopsided.** Two domains hold 23k of the 33.5k LOC: `app/Domain/Vfs` (83 files / 12,654 LOC) and `app/Domain/Chat` (10 files / 10,512 LOC). Other domains are thin:
  - AgentRuntime: 7 files / 1,475 LOC
  - Automations: 6 / 842
  - Knowledge: 4 / 109
  - Collaboration: 2 / 128
  - Work: 1 / 436
  - Calendar: 1 / 422

  Only 9 of 13 domains have an `Application/` subfolder. Other subfolder names (Core, Gateway, Codex, Safety, Providers, Registry, and so on) show that sub-layering is not uniform (source: `find app/Domain -maxdepth 2`).
- **Largest PHP files:**
  1. `app/Domain/Chat/Telegram/Application/TelegramWebhookPipeline.php`: 5,716 LOC
  2. `app/Http/Controllers/Api/TelegramMiniAppController.php`: 1,695
  3. `app/Domain/Chat/Telegram/Application/TelegramCardRenderer.php`: 1,460
  4. `app/Services/TelegramService.php`: 1,010
  5. `app/Services/AgentDocumentService.php`: 984
  6. `app/Domain/Chat/Telegram/Application/TelegramNotificationRouter.php`: 896
  7. `app/Agents/Tools/ToolRegistry.php`: 856
  8. `app/Domain/AgentRuntime/Application/RespondToChatMessage.php`: 772
  9. `app/Http/Controllers/Api/IntegrationController.php`: 707

  Telegram alone accounts for more than 10k LOC across the top files.
- **Largest frontend files:** `resources/js/Pages/Integrations.vue` (1,970), `Components/chat/ChannelInfo.vue` (1,157), `Components/chat/MessageInput.vue` (1,149), `Pages/Tasks/Show.vue` (1,071), `composables/useApi.ts` (785).
- **Static analysis on HEAD/working tree:**
  - `phpstan.neon` includes Larastan, covers `app/` at **level 6**, and has no baseline (`phpstan.neon:1-7`). No `pint.json`, ESLint or Prettier config exists. Pint is installed only as a dev dependency (`composer.json` require-dev).
  - `tsconfig.json:7,9` sets `"strict": false` and `"strictNullChecks": false`. `package.json` has a `typecheck` script (`vue-tsc --noEmit`) but CI does not run it.
- **Branch `feat/quickjs`** adds a PHPStan baseline that is "a regression gate" (`git show feat/quickjs:phpstan.neon`). That baseline lists 652 baselined errors (sum of `count:` across 527 entries in `feat/quickjs:phpstan-baseline.neon`). So level 6 holds only because existing debt is allowlisted on that branch.
- **Workspace scoping rule (`forWorkspace()`):** 389 `forWorkspace(` call sites in `app/`. 35 model files define a scope or trait for it, and 37 model files mention `'workspace_id'` (source: `rg`). Adherence is broad but not total. See the Security section for verified misses (`AutomationRuleController`).
- **Wayfinder routing rule:**
  - 46 files import from `@/routes`, `@/actions` or `@/wayfinder`.
  - Only 7 hard-coded `'/api/...'` string occurrences remain in 4 files. Two of them are Wayfinder keyed lookups (`ProviderConfigModal.vue:279`, `CodexConfigModal.vue:274`).
  - The genuine string-built URLs are in `resources/js/Pages/Telegram/MiniApp.vue:643,686,701`.
  - Recent commits `226223b`, `6d7fa01` and `05bc21a` ("Convert ... to route helpers") show an active migration. Adherence is good.
- **Commenting-style rule:** about 351 of 655 PHP class/trait/interface/enum files have a docblock directly above the declaration, roughly 54% (heuristic `grep -B1` count). Newer code (VFS, approvals, Telegram, `ApprovalExecutionService::resolve`) carries the extensive intent comments that CLAUDE.md asks for (e.g. diff of `4c62c17` in `app/Services/ApprovalExecutionService.php`).
- **Little marker debt:** 10 `TODO|FIXME|HACK|XXX` markers across `app/` and `resources/js`. Debt is instead tracked in `docs/ROADMAP.md` ("Maintainability": split `RespondToChatMessage`, move message side effects into chat-domain use cases, "Continue replacing hidden `workspace()` dependencies with explicit workspace/actor inputs").
- **Contradiction with the runtime-ownership rule:** CLAUDE.md says "Do not reintroduce Prism ..." and `669d99a` "Remove Prism runtime dependencies" (2026-05-24) removed it. Yet `.github/workflows/ci.yml` still clones `prism-codex` into `tmp/prism-codex`. `docs/ROADMAP.md` (P1 Testing and CI) acknowledges this: "Remove the stale `prism-codex` clone from CI".
- **Hygiene:** the repo root holds untracked or ignored artifacts: `app-session-O7kcZj7R.js`, `assistant-chat-*.png`, `inspiration/` (third-party repos such as opencode and vercel-chat) and `tmp/` (prism-codex, prism-relay clones). `inspiration/` and `tmp/` are gitignored (`.gitignore:39,42`; `git ls-files inspiration tmp` returns 0 files).
- **Supply-chain coupling:** `composer.json` pins 19 `opencompanyapp/*` integration packages at `@dev` or `dev-main` (path/sibling repos), plus `opencompany/chatogrator`. CI must `git clone` the sibling repos before `composer install` (`.github/workflows/ci.yml`).

### Inferences
- Maintainability risk is concentrated in Telegram. A 5.7k-line application service plus a 1.7k-line controller suggests the "domain/application" folder is being used as a namespace rather than as a decomposition boundary.
- With `strict:false` TS and no lint config, frontend quality depends on the author's discipline. This matters for a codebase with 60k LOC of Vue.
- `@dev`/`dev-main` dependencies on sibling repos make reproducible builds and outside contributions hard. A third party cannot build HEAD without access to the matching state of the sibling repos.

### Gaps
- I did not run PHPStan, so the current error count on `feat/vfs` without a baseline is unknown. The only figure available is the 652 baselined errors on `feat/quickjs`.
- No cyclomatic-complexity or duplication tool was run. Duplication claims are limited to what `docs/ROADMAP.md` and `docs/architecture/maintainability-refactor-audit-2026-05-19.md` list (not read in full).
- Dead-code analysis was not performed.

## 2. Test coverage: layout, counts, subsystem coverage, CI

### Takeaway
There is a meaningful PHPUnit-style suite of about 931 test methods in 105 Feature/Unit files, plus 14 Pest browser tests. Coverage is concentrated on tools, memory, MCP, permissions, webhooks and VFS. However, on `main` and on the checked-out `feat/vfs`, **CI does not run any tests**: it runs only PHPStan. The flagship subsystems are thinly tested relative to their size, notably the 10k+ LOC Telegram runtime, automations, and subagents.

### Cited Findings
- **Framework:**
  - Pest 4 with PHPUnit 12 is installed (`composer.json` require-dev: `pestphp/pest ^4`, `pest-plugin-browser`, `phpunit/phpunit ^12`, `laravel/dusk`).
  - Tests are overwhelmingly class-based: 107 files `extends TestCase`, and only 3 files use Pest `it()`/`test()` (4 calls).
  - `tests/Pest.php` binds `TestCase` to Feature and configures `pest()->browser()->inChrome()` for `tests/Browser`.
  - `phpunit.xml` runs `Unit` and `Feature` suites on in-memory SQLite (`DB_CONNECTION=sqlite`, `:memory:`). Production depends on pgvector (`composer.json`: `pgvector/pgvector`), so vector-search paths are presumably not exercised against Postgres.
- **Counts:** 931 `public function test*` methods. Test files by directory:
  - `tests/Feature`: 37 top-level files
  - `tests/Feature/Tools`: 22
  - `tests/Browser`: 14
  - `tests/Feature/Services/Memory`: 9
  - `tests/Unit/Domain/Web`: 8
  - `tests/Unit`: 7
  - `tests/Feature/Auth`: 6
  - `tests/Feature/Domain/Web`: 4
  - `tests/Feature/Vfs`: 3
  - Everything else: 1 file each
- **Per-subsystem test methods** (file: methods):
  - **Approvals:** `ApprovalResumeTest` 12, `ApprovalWrappedToolTest` 7, `WaitForApprovalTest` 5, `AgentPermissionServiceTest` 33, `AgentPermissionControllerTest` 9. Reasonably covered.
  - **VFS:** `VfsFeatureTest` grows from 29 methods at HEAD to 45 in the working tree (+1,057 lines uncommitted). Add `VfsPaginationTest` 13 and `VfsCapabilityTruthTest` 4, both untracked.
  - **Agent runtime:** `AgentRunControlPlaneTest` 18 (untracked), `AgentRespondJobTest` 10, `OpenCompanyAgentTest` 8, `RuntimeServiceLayerTest` 9, `ExecuteAgentTaskJobTest` 4, `AgentCommunicationServiceTest` 16.
  - **Memory:** 9 files in `tests/Feature/Services/Memory` (6–12 methods each), plus `RecallMemoryTest` 22 and `SaveMemoryTest` 16. Well covered.
  - **MCP:** `McpServerControllerTest` 17, `McpSchemaTranslatorTest` 12, `McpProxyToolTest` 8, `McpToolProviderTest` 8.
  - **Webhooks/chat:** `ChatWebhookControllerTest` 119 (the largest file), `IntegrationWebhookControllerTest` 3.
  - **Telegram:** only `TelegramWorkspaceToolsTest` (4 methods) is named for it. Six test files mention "Telegram" at all, against more than 10k LOC of Telegram code (see Code quality).
  - **Automations:** `Domain/Automations/ManageAutomationsTest` has 3 methods. Lua coverage: `LuaBridgeTest` 9, `LuaSandboxServiceSecurityTest` 3, `Tools/LuaExecTest` 7, `LuaApiDocGeneratorTest` 21.
  - **Web/SSRF:** `Unit/Domain/Web/WebRequestGuardTest` 13, plus other web-provider unit tests.
  - **Workspace:** `WorkspaceCreationTest` 4 (added in `4c62c17`), `WorkspaceStatusTest` 1.
  - **AI gateway:** `AiGatewayTest` 4.
  - **Billing/Stripe:** a single file mentions it. No billing domain exists in `app/`.
  - **Subagents:** 3 files mention "Subagent"; none is dedicated to it.
- **Frontend tests:**
  - No vitest or jest config and no `*.spec.ts`/`*.test.ts` files.
  - `playwright` is a devDependency, but browser coverage is Pest browser tests in `tests/Browser/*.php` (14 files, e.g. `ApprovalsTest.php`, `ChatTest.php`), with `phpunit.dusk.xml` and `.env.dusk.local` present.
  - QA evidence is in `docs/qa/browser-full-qa-2026-05-24.md`, `docs/qa/browser-qa-2026-05-24.md` and `docs/testing/qa-strategy.md` (claimed in docs).
- **CI on main and feat/vfs:** `.github/workflows/ci.yml` triggers on push and PR to `main` and has a single job, `phpstan` (clone siblings, `composer install`, `vendor/bin/phpstan analyse`). It runs no `php artisan test`, no `npm run typecheck` and no build.
- **CI on unmerged `feat/quickjs`** adds a "Backend tests" job (`php artisan test` with a pinned QuickJS extension), "Frontend typecheck and build" (`npm ci`, `npm run typecheck`, `npm run build`), PHPStan with the baseline, and "Production image" (`git show feat/quickjs:.github/workflows/ci.yml`). `feat/mruby` commit `7cf8235` "Make mruby CI environment explicit and fail on PHP warnings" shows similar work. Neither branch is merged into `feat/vfs` or `origin/main`.
- **Known local test breakage (claimed in docs):** `docs/ROADMAP.md` P1 items 4 and 7 say "Restore/verify the local `Lua\Sandbox` runtime so Lua/VFS bridge tests can run instead of failing at class load" and "Reconcile the stale `VfsFeatureTest` assertion". ROADMAP "Testing and CI" says "Add focused PHP tests to CI, then frontend build/type checks and browser smoke coverage".

### Inferences
- The suite is credible in breadth, but without CI execution it gives no regression protection on the default line. The working tree's own docs admit some tests currently fail locally.
- The sqlite test database versus Postgres/pgvector in production is a fidelity gap for hybrid search and any Postgres-specific SQL.
- Test investment does not track code mass. Telegram, the largest subsystem, has about 4 dedicated test methods, though `ChatWebhookControllerTest` (119 methods) may cover part of the webhook path.

### Gaps
- I did not run `php artisan test --list-tests` because it boots the app and could need extensions such as `Lua\Sandbox`. Counts are static greps and may miss data-provider expansions.
- No coverage percentage is available; no coverage report is configured (CI uses `coverage: none`).
- I did not check whether `ChatWebhookControllerTest` substantially covers Telegram pipeline branches.

## 3. Security posture

### Takeaway
The security foundations are better than average for an early project:
- Encrypted casts for credentials, and hashed AI-gateway keys.
- Constant-time HMAC checks for webhooks and the Telegram Mini App.
- An SSRF guard on web tools.
- A Lua sandbox with CPU and memory limits.
- An atomic approval claim, added in `4c62c17`.

Authorization is still thin. There is one Policy class, no human RBAC beyond the admin/member split, no channel-membership or document-permission enforcement, and no rate limiting on the API, webhooks or AI gateway. At least one confirmed cross-workspace data-access flaw exists (list automation rules). The project's own `docs/ROADMAP.md` lists most of these as P0.

### Cited Findings
**Recent fixes (commit `4c62c17`, 2026-08-09, "Secure approvals and workspace provisioning", 13 files, +354/−206):**
- `ApprovalController::store` previously accepted a browser-supplied `toolExecutionContext` and persisted it as `tool_execution_context`. It now uses `'toolExecutionContext' => ['prohibited']` and always stores `null`. The code comment says accepting it "would let a member fabricate an approval and execute allowed tools as another agent." This was a real privilege-escalation path before the fix.
- `ApprovalController::update` now validates `status in:approved,rejected` and delegates to the new `ApprovalExecutionService::resolve()`. That method does a conditional `UPDATE ... WHERE status='pending'` as the single claim point, preventing double execution from concurrent clicks across transports. The `execute*` methods became `private`.
- `POST /api/workspaces` gained `->middleware(['auth','verified'])` (`routes/api.php`). `User` now `implements MustVerifyEmail`. Workspace creation moved to `app/Domain/Collaboration/Application/ProvisionWorkspace.php`. Covered by the new `tests/Feature/WorkspaceCreationTest.php` (116 lines).
- Separately, `origin/main` received `51ddd0c` "Patch PHP 8.4.27, Laravel 12.70 and frontend advisories" (2026-10-22, PR #9 from `fix/security-2026-10`). That fix is **not** in `feat/vfs` (`fix/security-2026-10` is 1 commit ahead of and 46 behind HEAD).

**Workspace isolation (verified):**
- `app/Http/Middleware/ResolveWorkspace.php` resolves the workspace from the URL slug, the `X-Workspace-Id` header, the session, or the user's first workspace. It verifies membership (`$user->workspaces()->where('workspaces.id', ...)->exists()`) before binding `currentWorkspace`.
- Agent users bypass resolution entirely (`if ($user->isAgent()) return $next($request);`), so `workspace()` would be unbound for them.
- **Confirmed cross-tenant flaw:** `app/Http/Controllers/Api/AutomationRuleController.php`:
  - `index()` returns `ListAutomationRule::with(...)->get()` with no workspace filter.
  - `update()` and `destroy()` use `ListAutomationRule::findOrFail($id)`.
  - `app/Models/ListAutomationRule.php` has no `workspace` or `forWorkspace` reference at all.
  - The routes sit in the general member group (`routes/api.php:167-170`), not behind `workspace.admin`.
  - Result: any authenticated member of any workspace can list all workspaces' rules and modify or delete any rule by UUID.
  - The project acknowledges this as `docs/ROADMAP.md` P0 #4, "Scope list automation rules through workspace-owned records".
- `DmController::show` calls `$this->directMessages->getOrCreate($currentUserId, $userId)` and `User::find($userId)` with an arbitrary user ID (`app/Http/Controllers/Api/DmController.php:28-34`). ROADMAP P0 #5 says to "Route all direct-message creation through the workspace-validating resolver and reject self-DMs". The untracked migration `database/migrations/2026_08_09_000001_create_agent_runs_and_scope_direct_messages.php` adds `workspace_id` to `direct_messages` and backfills it.
- **Messages are workspace-scoped but not membership-scoped.** `MessageController` uses `Channel::forWorkspace()->findOrFail(...)` and `whereHas('channel', workspace_id)` (lines 50, 65, 106, 193, 269–322), but I saw no channel-membership check. ROADMAP P0 #2 is "Enforce channel membership for channel/message reads and writes" and P0 #3 is "Enforce document permissions across CRUD and search".
- **Authorization model:**
  - One policy, `app/Policies/AgentPolicy.php` (49 LOC). Effectively one `Gate::define` (`viewTelescope`), and about one `authorize`/`can` usage across `app/`.
  - Roles: `EnsureWorkspaceAdmin` guards the settings, integration-config, MCP-server, Telegram-ops and webhook-admin routes (`routes/api.php:367+`). Everything else is open to any member.
  - `ApprovalController::update` has no role check: any workspace member can approve. ROADMAP P0 #6 says "no self-approval for high-risk actions".
  - The README diff now admits "full human RBAC remains planned" (`git diff -- README.md`).
- **Telescope gate:** `app/Providers/TelescopeServiceProvider.php:78-80` allows any user with `type === 'human'`. `config/telescope.php:8` defaults to disabled, but `.env.example` sets `TELESCOPE_ENABLED=true`. Any registered human could see all workspaces' requests and queries if a deployment copies the example env (inference about deployment practice; the gate itself is verified).

**Secrets (verified encrypted casts):**
- `IntegrationSetting.config` → `encrypted:array` (`app/Models/IntegrationSetting.php:51`)
- `WorkspaceDisk.config` → `encrypted:array` (`app/Models/WorkspaceDisk.php:31`)
- `McpServer.url` → `encrypted`; `auth_config` → `encrypted:array` (`app/Models/McpServer.php:64-65`)
- `IntegrationWebhook.secret` and `last_payload` → encrypted (`app/Models/IntegrationWebhook.php:50,53`)
- Codex OAuth `access_token`, `refresh_token`, `token_data` → encrypted (`app/Domain/Ai/Codex/CodexTokenStore.php:30-33`)
- `AgentToolExecution.result_payload` → encrypted (untracked `app/Models/AgentToolExecution.php:50`)
- VFS pagination cursors are encrypted with `Crypt` (`app/Domain/Vfs/Core/VfsPageCursor.php:31,51`)
- AI gateway keys are stored as SHA-256 hashes (`app/Models/AiGatewayApiKey.php:63-65`, `findByPlainText`) with expiry checks (`app/Http/Middleware/AuthenticateAiGateway.php:20-27`). There is no scoping and no rate limiting (ROADMAP P0 #7).

**Webhook verification:**
- Telegram webhook: `TelegramWebhookPipeline.php:182-191` matches the `X-Telegram-Bot-Api-Secret-Token` header with `hash_equals`. The generic `ChatWebhookController::resolveFromTelegramSecret` (`app/Http/Controllers/Api/ChatWebhookController.php:73-83`) compares with `===`, which is not constant-time (minor). Slack/Teams paths require a signature or secret header with `hash_equals` (lines 90–201, 247).
- Telegram Mini App: `initData` is verified with the standard HMAC-SHA256 `WebAppData` scheme, a 24h max age and `hash_equals` (`app/Domain/Chat/Telegram/Application/TelegramMiniAppVerifier.php:19-40`).
- Incoming integration webhooks: a per-webhook secret via `hash_equals`, accepted from the `X-Webhook-Secret` header **or the `?secret=` query string** (`app/Http/Controllers/Api/IncomingIntegrationWebhookController.php:37-41`). Query-string secrets leak into access logs.
- Telegram replay protection: a payload hash is checked with `hash_equals` (`TelegramWebhookPipeline.php:139`).

**Rate limiting:** `throttle` appears only on email-verification routes (`routes/web.php:33`, `routes/auth.php:47,51`) and in Breeze `LoginRequest` (5 attempts). None applies to `/api/*`, `/api/webhooks/*`, `/api/telegram/mini-app/*` or `/api/ai-gateway/v1/*` (`routes/api.php`; `rg throttle`). `bootstrap/app.php:17` sets `trustProxies(at: '*')`, so IP-keyed throttles trust any `X-Forwarded-For`.

**SSRF:**
- `app/Domain/Web/Safety/WebRequestGuard.php` blocks localhost and `*.localhost`, cloud-metadata IPs (169.254.169.254, 169.254.170.2, 169.254.169.253), and private/reserved ranges after DNS resolution of A and AAAA records. Its comment says redirects are re-checked (line 13). Workspace-configurable private-host allowlists are supported (lines 158–168).
- The guard is used by the direct-fetch provider and the Tavily, Parallel, Jina, Firecrawl, Exa and ZaiReader providers. It has 13 unit tests (`tests/Unit/Domain/Web/WebRequestGuardTest.php`).
- `app/Services/Mcp/McpClient.php:108` calls `Http::withHeaders(...)` against admin-configured MCP URLs and does not use `WebRequestGuard`. Admin-only routes limit the exposure, but an admin can point the server at internal hosts.

**Code-execution sandboxing:**
- `ExecuteScriptAutomation` (`app/Domain/Automations/Application/ExecuteScriptAutomation.php:99-100`) runs scripts through `LuaSandboxService`. That service uses the PHP `Lua\Sandbox` extension with default limits of 32 MB memory and 30 s CPU (`app/Services/LuaSandboxService.php:25-30`) and exposes only selected bridge globals (line 11).
- The only `Symfony\Process` uses are fixed-argv `rsvg` calls (`AgentAvatarService.php:109`, `Charts/RenderSvg.php:70`).
- VFS `exec` is an emulated shell with a syntax guard (`app/Domain/Vfs/Core/Concerns/GuardsVfsShellSyntax.php:15` blocks `find -exec`), not a real shell.
- Alternative sandbox runtimes (QuickJS on `feat/quickjs`, mruby on `feat/mruby`, e.g. `35afc1e` "Install attested mruby RC and enforce task-owned write receipts") are unmerged.

**Approvals for dangerous tools:**
- Per-agent tool permission resolution returns `{allowed, requires_approval}` (`app/Services/AgentPermissionService.php`, delegating to `AgentPermissionResolver`). Default-deny is `default => false` (`AgentPermissionResolver`, around line 317).
- Approval wrapping lives in `ToolRegistry`/`ApprovalWrappedTool` (referenced in `app/Domain/Vfs/OpenCompany/Approvals/VfsApprovalRequestFactory.php:12`).
- VFS mutations have their own `VfsMutationPolicy` with a `requires_approval` decision (`app/Domain/Vfs/OpenCompany/Policy/VfsMutationPolicy.php:59-99`, untracked).
- Uncommitted runtime additions `IdempotentToolExecutor`, `AmbiguousAgentToolExecution` and `RunGuardedTool` indicate idempotency and ambiguity handling for tool side effects.

**Prompt injection:** a search for "prompt injection", "untrusted" or "external content" in `app/` returned no hits. I found no explicit isolation or labelling of untrusted tool, web or MCP output in the agent prompt. Mitigation relies on the permission and approval layer.

**Docs audits:** `docs/vendor-open-source-audit-2026-04-10.md` and `docs/vendor-open-source-critical-audit-2026-04-10-round-2.md` are crash and robustness findings in `prism-php/prism` v0.99.22: 10 "Critical" `TypeError` crashes on malformed tool-call args. Both are now marked "Historical ... Prism is no longer an OpenCompany dependency". They are not security vulnerabilities in OpenCompany itself.

### Inferences
- Before `4c62c17`, any workspace member could create an approval carrying arbitrary executable tool context and approve it themselves. That was a high-severity issue, now fixed on `feat/vfs` but not on `origin/main`, whose head (`faf7a75`) predates `4c62c17`.
- The tenant model is "workspace equals security boundary, all members equal". That is acceptable for small trusted teams but not for the "enterprise governance" positioning. The ROADMAP explicitly says P0 authorization items "block credible enterprise positioning".
- The lack of throttling on unauthenticated webhook, mini-app and AI-gateway endpoints is a DoS and brute-force surface, and it amplifies LLM cost.
- DNS-resolve-then-fetch SSRF guards are usually vulnerable to DNS-rebinding TOCTOU unless the HTTP client is pinned to the checked IP. I did not verify pinning (inference).

### Gaps
- I did not audit every controller for scoping. Sampled hits were `AutomationRuleController` (confirmed), `DmController` and `MessageController::*` `User::find`/`Channel::find` (partial). A full sweep is needed.
- I did not inspect how `ApprovalWrappedTool` decides which tools require approval by default, i.e. which tools are "dangerous" out of the box.
- MCP server trust model (tool-name collisions, schema sanitisation) beyond `McpPermissionEvaluator`'s existence was not reviewed.
- I did not verify whether `.env` or `.env.dusk.local` are tracked. They exist on disk; `.gitignore` normally covers `.env`.

## 4. Licensing and commercial model

### Takeaway
The license has changed three times in ten months:
1. "OpenCompany Sustainable Use License" (fair-code). This is still the license on public `origin/main`.
2. Business Source License 1.1 with an Additional Use Grant and a 4-year change to GPL-3.0+, committed on dev lines in May 2026, plus `LICENSE_EE.md` for license-key-gated "premium" features.
3. A working-tree, **uncommitted** switch to **AGPL-3.0-only** for the complete self-hosted app. It comes with an Apache-2.0 carve-out for SDKs and kits, a proprietary carve-out for Cloud operations only, DCO with inbound equals outbound, no CLA, and a new trademark policy.

An FSL-1.1-ALv2 step was prepared but, per the docs and git, never committed. The AGPL move is explicitly "prospective" and gated on counsel review (ROADMAP P0 #11).

### Cited Findings
- **Public `origin/main`** `LICENSE.md` is "OpenCompany Sustainable Use License, Version 1.1, February 2025": internal business or non-commercial use only, and distribution "only if ... free of charge for non-commercial purposes" (`git show origin/main:LICENSE.md`). `5424f59` (2026-02-15) "Update license to permit add-on distribution" touched it.
- **HEAD (`feat/vfs`)** `LICENSE.md` is BSL 1.1:
  - Licensor: Ginger Media BV.
  - Additional Use Grant: production use for own internal operations, affiliates, and "a single customer's internal business operations" by a service provider.
  - Change Date: 4th anniversary per version. Change License: GPL-3.0-or-later.
  - Introduced via `f0c52e1` "Checkpoint current dev tree" (2026-05-10).
  - Source: `git show HEAD:LICENSE.md`.
- **Deleted `LICENSE_EE.md`** (37 lines at HEAD) said some premium capabilities require "a separate commercial agreement", "a valid license key" and "plan-based entitlement checks at runtime". Examples: SSO/SAML/SCIM, advanced audit, enterprise governance, white-labeling, premium analytics. Hosted resale, white-label and OEM required a commercial agreement. Contact: `enterprise@opencompany.app`.
- **HEAD `LICENSING.md`** FAQ: internal self-hosting yes. Multi-customer hosting, white-label and OEM embedding were "No, not under the Additional Use Grant".
- **Working tree (uncommitted):**
  - `LICENSE.md` is the verbatim GNU AGPL v3 text (661 lines).
  - `composer.json` and `package.json` declare `"license": "AGPL-3.0-only"`. At HEAD, composer said "SEE LICENSE IN LICENSE.md".
  - `LICENSE_EE.md` is deleted (`git status`: ` D LICENSE_EE.md`).
- **New `LICENSING.md`** ("Status: Prospective ... Last verified: 2026-09-01"):
  - AGPL is OSI-approved. There are no user, workspace, agent or Run limits, and no Cloud account is required.
  - Competing hosting is allowed, with AGPL §13 Corresponding Source obligations for modified network versions.
  - White-label and OEM are not prohibited by copyright, but are trademark-restricted.
  - "OpenCompany will make reusable protocols, schemas, SDKs, integration/provider kits, examples, and templates available under Apache-2.0 where their path notices explicitly say so", "for the shared OpenCompany/Bowerbird integration ecosystem".
  - "OpenCompany Cloud operations, billing, metering, abuse prevention, hosted-fleet automation, and company-internal systems may remain proprietary when they are separate works". The private Cloud "cannot silently patch or copy the AGPL application into a private fork or use a feature flag, license key ... to remove rights".
  - "not a crippled community edition".
  - Historical BSL and FSL versions keep their terms. "The current branch's earlier FSL preparation was not itself proof that an FSL release was published."
  - Contributions use DCO 1.1 with Signed-off-by, inbound equals outbound, and "not using a broad copyright-assignment CLA".
- **New `COMMERCIAL_USE.md`:** commercial use is allowed, including paid hosting and competing services. Official monetisation is listed as "OpenCompany Cloud and managed infrastructure; deployment, migration, operations, security response, and support; service levels, compliance assistance, training, and certification; managed AI inference, storage, automation, and Bowerbird execution; marketplace, partner, and professional-service offerings." It also says: "Customers pay for an excellent official service ... not for permission".
- **New untracked `TRADEMARKS.md`:** "Interim policy ... legal review is required before publication". Ginger Media BV reserves the marks. "Based on/compatible with OpenCompany" statements are allowed.
- **New untracked `CONTRIBUTING.md`:** DCO sign-off is required, and "The planned automation should enforce these checks" (line 43), so DCO enforcement is not yet automated.
- **README diff:** the license section changes from BSL to "prepared for a future open-source release under AGPL-3.0-only". Product claims are toned down: "Persistent AI Agents ... 24/7" becomes "Durable AI Agents", "Multi-Agent Orchestration — Dynamic spawning" becomes "Multi-Agent Delegation ... sequential result aggregation", and "Isolated workspaces with RBAC" becomes "full human RBAC remains planned". It links the new `docs/CURRENT_STATE.md` and `docs/ROADMAP.md` (`git diff -- README.md`).
- **FSL never committed:** "FSL-1.1" appears in git only in `refs/t3/checkpoints/*` (T3 Code agent checkpoint refs, e.g. `0839dff`, `2de3b57`), not in any branch commit (`git log --all -S "FSL-1.1"`; `git log --branches --remotes -- LICENSE.md` shows only `f0c52e1`, `5424f59`, `5bb313d`).
- **Apache-2.0 boundary not yet implemented:** no `SPDX-License-Identifier: Apache-2.0` or Apache path notice exists in tracked app paths (`rg` found Apache/other LICENSE files only in ignored `tmp/` and `inspiration/`).
- **Publication gate:** ROADMAP P0 #11 says "Before publishing the AGPL transition, verify copyright and contributor authority, review the final documents with counsel, record the exact cutover commit/release date, preserve historical BSL/FSL terms, and activate the DCO contribution workflow".
- **Confidential docs** (`docs/confidential/README.md`: "Legacy Local-Only Private Notes ... must never be added to Git"; the canonical home is the sibling `oc-docs` Obsidian vault). Titles only:
  - `cloud-offering-strategy.md`, `corporate-structure.md` ("Corporate Structure & Tax Strategy"), `domains.md`, `gcp-cloud-infrastructure.md`
  - `license-gating-strategy.md`, now marked "Historical feature-entitlement proposal, superseded by the prospective 2026-08-31 AGPL-3.0-only licensing decision ... optional `app/Enterprise/` namespace ... not current implementation"
  - `sso-strategy.md`, `tenancy-and-byok-economics.md`, `usa-dubai.md` ("Corporate Structure: Delaware LLC + Dubai (DMCC)")
  - `strategy/{ai-tool-strategy, business-strategy, emergent, enterprise-security, fair-code-valuation-strategy, masterplan}.md`
  - `research/{competitive-landscape, openai-frontier-analysis}.md`
- **`docs/market/ai-app-builder-market-research-2026-05-25.md`** (single file) covers prompt-to-app platforms, no-code/low-code, design-to-code, internal-tool builders and AI coding agents, plus an "OpenCompany Implications" section.

### Inferences
- The AGPL decision (dated 2026-08-31 in the confidential doc) reverses the earlier open-core plan of license keys and `app/Enterprise/` gating. The business model shifts to "Cloud + services + managed inference". This is a Plausible/GitLab-style service model rather than open core.
- Single-author copyright (all branch commits are by Rutger De Maeyer aliases) makes relicensing legally simple today. The DCO and no-CLA choice will make future relicensing away from AGPL hard once outside contributions arrive. That is a deliberate commitment.
- The public repo still shows a non-OSI "Sustainable Use License" on `main`. External observers see a different license than both HEAD (BSL) and the working tree (AGPL), which is a messaging and credibility risk until a release is cut.

### Gaps
- I could not confirm whether any BSL version was ever published as a release or tag. `git tag` was not checked, and no release artifacts were inspected.
- The full `TRADEMARKS.md` and `CONTRIBUTING.md` were only skimmed.
- I did not read the confidential strategy docs beyond titles and status lines, per scope.

## 5. Git history and momentum

### Takeaway
A solo-developer project (one human author), first commit 2026-01-20, with 310 commits across branches. Activity was a massive burst in February 2026 (213 commits), then bursty and low: 3 commits in October 2026 and 1 in November (checkpoint refs only). Much recent work sits in a large uncommitted working tree on `feat/vfs` (183 changed paths, about +8.8k/−1.8k lines in tracked files plus 52 untracked paths) and in two competing unmerged sandbox branches. AI coding agents (Codex, T3 Code) are heavily used.

### Cited Findings
- **Commits:** 290 on HEAD, 310 across branches and remotes, 378 including 68 `refs/t3/*` checkpoint refs authored by "T3 Code <t3code@users.noreply.github.com>".
- **First commit:** `be6702a` 2026-01-20 "x". Then `5f545bf` 2026-01-21 "Migrate from Nuxt.js to Laravel 12 + Vue 3 + Inertia.js". Renamed from "Olympus" to OpenCompany in `5bb313d` (2026-01-31).
- **Authors (branches/remotes):** `ruttydm <rutger.demaeyer@gmail.com>` 307, plus 3 commits under other aliases of the same person (Rutger De Maeyer, gingermedia.biz address, Rutty). Effectively a bus factor of 1.
- **Commits per month (branches and remotes):**

  | Month | Commits |
  | --- | --- |
  | 2026-01 | 11 |
  | 2026-02 | 213 |
  | 2026-03 | 17 |
  | 2026-04 | 9 |
  | 2026-05 | 39 |
  | 2026-06 | 0 |
  | 2026-07 | 0 |
  | 2026-08 | 8 |
  | 2026-09 | 10 |
  | 2026-10 | 3 |

  Including `refs/t3` checkpoints: 2026-08 59, 2026-09 22, 2026-10 7, 2026-11 1.
- **Branches and dates:**

  | Branch | Last commit |
  | --- | --- |
  | `origin/main` | 2026-10-28 (`faf7a75` "Add ginger-dev local dev config") |
  | `fix/security-2026-10` | 2026-10-22 |
  | `feat/mruby` | 2026-09-08 |
  | `feat/quickjs` | 2026-08-10 |
  | `feat/vfs` (HEAD) | 2026-08-09 |
  | `dev` | 2026-05-25 |
  | `codex/runtime-memory-alignment` | 2026-04-10 |
  | local `main` | 2026-04-07 |
  | `feat/integration-refactor` | 2026-04-07 |
  | `codex/share-lua-core-opencompany` | 2026-04-01 |
  | `feat/queues` | 2026-02-28 |
  | `feat/chatogrator` | 2026-02-27 |

- **Divergence:** HEAD is 46 commits ahead of `origin/main` and 3 behind. `feat/quickjs` is 7 ahead and 2 behind HEAD; `feat/mruby` is 10 ahead and 2 behind. All three are mutually unmerged (`git rev-list --left-right --count`). The `origin/main` head therefore lacks the May 2026 runtime/Prism-removal/web work, the Telegram runtime, and `4c62c17`.
- **Recent themes (last 50 commits on HEAD):**
  - May 24, 2026 burst: about 30 commits in one day (`669d99a` remove Prism, web providers `02c31d3`…`553efff`, docs alignment, Wayfinder conversions).
  - `58aee3c` "Move OpenCompany LLM runtime to Laravel AI" (2026-05-11).
  - `93895a1` "Implement Telegram chat runtime" (2026-05-25).
  - `693a5eb` "Add VFS baseline and planning docs" (2026-05-26).
  - `4c62c17` security (2026-08-09).
  - Side branches: QuickJS `fd13181` "replace Code Mode runtime with QuickJS" (2026-08-10); mruby `b45a8ac` "Introduce mruby Code Mode and source-bound automation admission" (2026-09-08).
  - Several commits are bulk checkpoints (`498ef99` "Checkpoint current OpenCompany tree", `f0c52e1` "Checkpoint current dev tree").
- **Uncommitted working tree:**
  - `git status --short`: 183 entries (130 modified, 1 deleted, 52 untracked).
  - `git diff --stat`: 131 files, +8,814/−1,816.
  - Untracked additions include the agent-run control plane (`ActiveAgentRun`, `AgentRunCancelled`, `AgentRunPaused`, `ReconcileAgentRuns`, `IdempotentToolExecutor`, `AgentRunRecord`, `AgentToolExecution`, `config/agent_runtime.php`), 3 migrations dated 2026_08_09, the VFS approvals/policy/pagination code, `docs/CURRENT_STATE.md`, `docs/ROADMAP.md`, `CONTRIBUTING.md` and `TRADEMARKS.md`.
  - The newest untracked file mtime is 2026-10-09 (`docs/planning/linear-project-backlog-coverage-2026-10-09.md`).
- ROADMAP P1 #1: "Finish and commit the agent-run queue control plane as one scoped change after focused review."

### Inferences
- Momentum is real but fragmented. The author works in large uncommitted batches and parallel experimental branches (VFS, QuickJS, mruby), so the shipped `main` lags by months. Losing the local working tree would lose roughly 2–3 months of work.
- Activity since June 2026 is about 21 real commits in five months. This looks like a part-time or side project, or one where work happens through AI-agent sessions checkpointed outside branches.

### Gaps
- I did not check GitHub stars, issues, PRs or external contributors, since the scope was local only. The `origin` remote shows at least PR #9, so the repo is on GitHub (`OpenCompanyApp`).
- No release tags were inspected.

## 6. Biggest gaps and risks

### Takeaway
The dominant risks are bus factor 1, authorization immaturity (verified IDOR, no RBAC, no rate limits), a non-gating CI on the default branch, and large uncommitted or unmerged work. Behind those sit a licensing story that is unpublished and inconsistent across branches, and complexity hotspots in Telegram.

### Cited Findings
- **Bus factor:** 1 human author across 310 branch commits (`git shortlog -sne --branches --remotes`). Development leans on AI agents (`.codex/`, `.claude/`, `.kosmokrator/`, `refs/t3/*` checkpoints, `codex/*` branches).
- **Authorization and tenancy:**
  - Verified cross-workspace access in `AutomationRuleController` (index/update/destroy). DM creation with arbitrary user IDs is under remediation.
  - No channel-membership or document-permission enforcement (ROADMAP P0 #2–3).
  - Single policy class; any member can approve; no self-approval prevention (P0 #6).
  - AI Gateway keys are not scoped or rate-limited (P0 #7).
  - "full human RBAC remains planned" (README diff).
- **No API, webhook or gateway throttling**, combined with `trustProxies('*')` (`bootstrap/app.php:17`; `routes/api.php`).
- **CI does not run tests on main or feat/vfs** (`.github/workflows/ci.yml`: PHPStan only). Better CI exists only on unmerged `feat/quickjs`, where PHPStan passes against a 652-error baseline.
- **Unshipped fixes:** `4c62c17` (approval privilege fix) is not on `origin/main`. `51ddd0c` (PHP/Laravel/frontend advisories) is not on `feat/vfs`.
- **Working-tree risk:** 183 uncommitted paths, about 10.6k changed lines in tracked files plus 52 new paths, including migrations and core runtime classes. The ROADMAP notes a pending local migration and failing Lua/VFS tests (P1 #3, #4, #7).
- **Complexity hotspots:** `TelegramWebhookPipeline.php` (5,716 LOC) and `TelegramMiniAppController.php` (1,695 LOC) have few dedicated tests (`TelegramWorkspaceToolsTest`, 4 methods).
- **Frontend quality gates:** `strict:false` TS, no linting, no unit tests for 60k LOC of Vue.
- **Prompt injection:** no explicit untrusted-content handling found in `app/`. Defence relies on tool permissions and approvals.
- **Licensing and product truth:**
  - Public `main` is "Sustainable Use License", HEAD is BSL, and the working tree is AGPL (uncommitted, counsel review pending, P0 #11).
  - The Apache-2.0 SDK boundary has no path notices yet.
  - Prior README claims ("24/7", "Dynamic spawning", "RBAC") were overstated, as acknowledged by the README diff and the new `docs/CURRENT_STATE.md`.
- **Build reproducibility:** 19 `@dev`/`dev-main` sibling packages and CI cloning external repos (`composer.json`; `.github/workflows/ci.yml`).
- **Sandbox runtime churn:** Lua (current, `LuaSandboxService`) versus QuickJS (`feat/quickjs`) versus mruby (`feat/mruby`). Three competing "Code Mode" execution substrates, with no decision merged.

### Inferences
- For any enterprise or MSP pitch, the P0 authorization list must land first; the project's own ROADMAP says so.
- The healthiest near-term move is consolidation: commit or merge the control plane and `4c62c17`, merge the security-advisory branch, pick one sandbox runtime, adopt `feat/quickjs`'s CI, and cut a tagged AGPL release so public, HEAD and working-tree licensing converge.

### Gaps
- No dynamic verification: tests, PHPStan and exploits were not run.
- No load or performance data. Queue and reconciliation behaviour under production-sized load is listed as untested in ROADMAP P1 #5.
- Billing and metering code does not exist in this repo. Per `LICENSING.md`, it belongs to the proprietary Cloud, so it cannot be assessed here.
