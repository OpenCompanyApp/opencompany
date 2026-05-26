# Standalone Artifact Service Investigation

Date: 2026-05-25

Question: should OpenCompany split artifact generation/runtime into a standalone, no-build, VFS-backed service that can store bundles in S3-compatible object storage and use very cheap current models such as DeepSeek for generation and maintenance?

## Verdict

Yes, this is a good idea if the product is scoped as a governed artifact runtime for small operational software: reports, dashboards, forms, calculators, data exports, customer portals, microsites, and internal tools.

It is a weaker idea if positioned as a generic Lovable/Base44 clone. That market is already crowded, and full arbitrary SaaS generation pulls the product into auth, billing, database migrations, staging/prod, package management, and deployment support. The better wedge is:

```text
No-build artifact bundles + VFS editing + Lua endpoints + cheap model maintenance + object-storage hosting.
```

The strategic claim should be: agents can create durable, inspectable, low-cost workspace software without a repo, package install, Vite build, server provisioning, or fragile cloud IDE.

## Existing OpenCompany Baseline

The relevant current planning document is `docs/planning/automation-endpoints-artifacts.md`. It already proposes most of the right primitives:

- first-class `Artifact` domain rather than a narrow `Dashboard` model
- folder-first artifact bundles under `/artifacts/{slug}`
- `artifact.json` manifest
- Liquid pages/layouts/partials
- no-build SPA runtime
- manifest-declared browser libraries
- Lua backend endpoint scripts
- shared automation runtime for schedules/manual runs/endpoints
- sandboxed iframe viewer
- versioned bundle publishing
- run logs
- future VFS exposure at `/artifacts`

The surrounding docs add important constraints:

- `docs/planning/unified-vfs-overview.md` says VFS should be a permission-aware projection layer over domain services, not a replacement storage engine.
- `docs/planning/lua-scripting.md` says Lua script automations already exist partially through `LuaSandboxService`, `LuaBridge`, `RunScriptAutomationJob`, and `ExecuteScriptAutomation`.
- `WorkspaceDisk`, `WorkspaceFile`, and `FileSystemService` already support workspace-scoped storage and configurable `local`, `s3`, and `sftp` disks.

Current implementation gaps:

- no `Artifact` model yet
- no artifact routes/viewer/catalog
- no endpoint trigger type or artifact endpoint runner
- no `app/Domain/Vfs`
- no artifact-specific file/version table
- automation validation still assumes cron-style scheduling

## Standalone Service Shape

The service can be a narrow runtime, not a whole app platform:

```text
Artifact Service
  Control API
    create/update/publish artifact
    generate/repair artifact with model
    list versions/runs
    issue signed/public/internal links

  Runtime API
    render page
    serve asset
    execute Lua endpoint
    run scheduled/manual job

  Storage
    manifest + files + versions in S3-compatible object storage
    metadata and run logs in Postgres/SQLite/D1/etc.
    optional generated cache in R2/S3/CDN

  Agent Interface
    VFS paths over artifact bundles
    typed patch/write/read operations
    model repair loop
    deterministic Lua tests
```

The artifact bundle remains the canonical unit:

```text
/artifacts/customer-health/
  artifact.json
  pages/index.liquid
  layouts/app.liquid
  assets/styles.css
  assets/app.js
  backend/summary.lua
  tests/summary.request.json
```

Storage can be content-addressed:

```text
s3://artifact-bucket/workspaces/{workspace_id}/artifacts/{artifact_id}/draft/...
s3://artifact-bucket/workspaces/{workspace_id}/artifacts/{artifact_id}/versions/{version}/...
s3://artifact-bucket/workspaces/{workspace_id}/artifacts/{artifact_id}/runs/{run_id}/...
```

This keeps published versions immutable, makes rollback cheap, and allows static assets to sit behind CDN/object storage. Only Liquid render and Lua endpoint calls need compute.

## Why No-Build Matters

No-build is the key cost and reliability lever.

Build-system app generators inherit problems from modern frontend stacks: dependency drift, npm install time, package vulnerabilities, framework upgrades, generated lockfiles, CI failures, slow cold starts, and build minutes. A no-build artifact runtime avoids most of that:

- Liquid is rendered server-side with a controlled dialect.
- CSS/JS are plain files.
- Approved browser libraries are pre-bundled or manifest-declared.
- Lua scripts provide backend logic without package installs.
- Tests can run by executing endpoint fixtures and checking rendered HTML.
- Agents patch files directly through VFS paths instead of regenerating whole projects.

This will not replace full-stack product engineering. It can replace many "I need a small app/report/form/dashboard by tomorrow" workflows.

## Budget Economics

Object storage is a good fit because artifacts are mostly small text files and static assets. Cloudflare R2 currently advertises S3-compatible storage, zero egress fees, and published pricing of $0.015/GB-month for Standard storage plus request pricing; AWS S3 Standard examples still show cheap storage but separate request and egress economics; Bunny Edge Storage documents $0.01/GB single-region standard storage with free storage-to-CDN traffic.

DeepSeek changes generation economics materially. Its official pricing page currently lists `deepseek-v4-flash` at 1M context, JSON output and tool calls, cache-hit input at $0.0028/M tokens, cache-miss input at $0.14/M, and output at $0.28/M. That is cheap enough for iterative artifact repair loops, linting, fixture generation, and "patch this bundle" flows, especially if prompts are cache-friendly.

Important caveat: use DeepSeek as one low-cost route, not the product's identity. Keep provider routing abstract so sensitive work can use OpenAI/Anthropic/Gemini, regional providers, or customer-owned keys. Cheap model economics can change, and DeepSeek jurisdiction/data-policy questions may matter for EU/customer data.

## Pricing Model Candidate

A strong consumer/prosumer pricing shape is:

```text
$5/month base subscription
Pay while creating or repairing artifacts
Published artifacts stay hosted while the account remains active
Dormant users must sign in once per year to keep free long-tail hosting alive
```

This fits the no-build/object-storage model. Most artifacts should be tiny bundles with low read traffic, so long-tail hosting can be close to free when static assets are CDN/object-storage backed and dynamic Lua endpoint calls are bounded. The expensive moment is generation, repair, endpoint execution with model calls, and high-traffic dynamic usage. Charge there instead of charging heavily for dormant artifacts.

Suggested commercial framing:

- `$5/month` includes account, artifact dashboard, public/internal links, modest static hosting, small storage allowance, and a few repair/generation credits.
- Creation uses metered credits based on model/provider, token usage, validations, screenshot checks, and repair loops.
- Static published artifact hosting is included up to fair-use storage/bandwidth/request limits.
- Dynamic endpoints have separate monthly included quotas, then usage billing or throttling.
- AI-powered artifact calls are either user-paid credits, BYO key, or viewer-authenticated usage; do not silently let one popular artifact drain the creator's account.
- Annual sign-in is an inactivity and abuse-control mechanism, not a literal permanent-hosting promise.

Avoid the literal phrase "host forever" in terms or pricing copy. Better wording:

```text
Keep published artifacts online for as long as your account is active.
For dormant free/low-cost accounts, sign in once per year to renew hosting.
```

That preserves the emotional promise while keeping operational control. It also gives a clean cleanup path for abandoned phishing pages, malware, spam, stale customer data, and forgotten public artifacts.

Hard guardrails for this model:

- static hosting included, dynamic execution metered
- public artifacts must have abuse scanning and takedown controls
- annual renewal emails before suspension
- export available before suspension/deletion
- no custom domains on the cheapest plan unless abuse controls are ready
- published artifact storage caps, attachment caps, request caps, and endpoint CPU/tool-call caps
- separate pricing for team/workspace governance, private artifacts, audit logs, and integrations

This pricing is plausible because it turns artifact hosting into a retention feature instead of the main cost center. The product makes money when users create, repair, automate, and use dynamic capabilities, while the long tail of simple static artifacts remains cheap enough to keep online.

## Competitor Gap

Claude Artifacts are the closest conceptual competitor. They now support published/shared artifacts, embedding, AI-powered artifacts, MCP integration, and persistent storage. But Claude's model is still tied to Claude accounts/subscriptions and Claude-hosted artifact constraints; persistent storage is documented as text-only with a 20 MB limit per artifact. It is excellent for shareable interactive artifacts, not an open artifact runtime that companies can mount as VFS-backed workspace software.

Lovable/Base44/Bolt/v0/Replit/Firebase Studio are broader prompt-to-app builders. Their center of gravity is generated apps, repos, Supabase/Vercel/Netlify/Firebase/Replit hosting, or cloud IDEs. They win when the user wants a real app project. They are heavier when the user just needs a governed report/form/tool that can live as a tiny bundle.

Retool/Softr/Glide/Appsmith/Superblocks win internal-tool governance, but they are visual-builder platforms. Their artifacts are not simple VFS folders that agents can grep, patch, version, run, test, and host as object-storage bundles.

ChatGPT Canvas/Codex/Cursor/Windsurf win editing real code. They do not provide a low-cost, no-build artifact hosting/runtime layer by themselves.

## Lua Differentiators

Lua is a real differentiator if it is sold as the artifact backend language, not just "we have scripts."

Competitors usually generate JavaScript/TypeScript, React, SQL, or platform-specific workflow blocks. Lua gives OpenCompany a different runtime contract:

- small sandbox with low startup and memory overhead
- deterministic backend endpoints for generated artifacts
- no npm/package install
- easy-to-read code for agents
- explicit bridge calls into workspace tools/integrations
- bounded CPU/memory/tool-call budgets
- cheap scheduled execution without an LLM call
- portable enough to run in PHP, a sidecar, or a future edge/wasm-style runtime

The strongest version is:

```text
Liquid for views.
Lua for artifact backend functions.
VFS for source inspection and patching.
Manifest permissions for runtime capabilities.
Model router for generation and repair.
```

That combination is uncommon. The VFS matters because it makes artifacts agent-operable: `rg`, `stat`, `patch`, version tokens, tests, run logs, and exact bundle diffs. Lua matters because it makes generated backend logic cheap and sandboxable.

## Product Differentiators

Strong differentiators:

- **VFS-native artifacts**: every generated object is a folder an agent can inspect, grep, patch, test, and version.
- **No build step**: no npm install, no lockfile, no framework upgrade treadmill for small tools.
- **Object-storage hosting**: published bundles are cheap static objects plus tiny dynamic endpoints.
- **Lua endpoints**: generated backend logic runs under explicit workspace/tool permissions.
- **Governed runtime**: permissions are the intersection of actor, agent, artifact, manifest, workspace policy, and tool scopes.
- **Operational lifecycle**: run logs, versions, rollback, fixtures, endpoint tests, publish summaries, failure repair loops.
- **Workspace-native data**: artifacts can read OpenCompany tables/docs/files/tasks/integrations through approved tools instead of inventing a parallel database.
- **Model-cost routing**: cheap models generate/repair routine artifacts; premium models handle sensitive or high-complexity work.
- **Exportability**: artifact bundles are plain files and can be exported as a zip, static site, or manifest bundle.

Weak differentiators by themselves:

- "AI builds apps" because everyone says this.
- "Cheap hosting" because static hosting is already cheap.
- "Uses S3" because that is infrastructure, not a product promise.
- "Uses Lua" unless Lua is tied to sandboxing, VFS, permissions, and deterministic automation.

## Architecture Recommendation

Start inside OpenCompany, but design the artifact runtime as extractable from day one.

Suggested boundaries:

```text
OpenCompany owns:
  workspace identity
  users/agents
  approvals
  integration credentials
  domain data and tools
  billing/customer account

Artifact Runtime owns:
  artifact manifest schema
  artifact file tree
  version snapshots
  Liquid rendering
  asset serving
  Lua endpoint execution
  run logs
  bundle import/export

Adapter boundary:
  StorageAdapter: local, s3, r2, b2
  AuthAdapter: OpenCompany session, signed link, API token
  ToolBridgeAdapter: OpenCompany tools, MCP tools, external host tools
  ModelRouterAdapter: DeepSeek/OpenAI/Anthropic/etc.
```

Do not split the service physically before the domain boundary is proven. Premature service extraction would slow iteration. Build as `app/Domain/Artifacts` with clean interfaces, then extract when a second host app or standalone product needs it.

## MVP

The smallest credible MVP:

1. `Artifact` model with workspace scope, slug, type, status, manifest cache, root storage pointer, and latest published version.
2. Artifact file storage backed by existing workspace disks first; hide from Files UI and expose through `ArtifactService`.
3. Manifest validator for pages, assets, endpoints, permissions, and library presets.
4. Liquid renderer with `asset_url`, `page_url`, `endpoint_url`, partials, layouts, escaping, and friendly errors.
5. Sandboxed iframe viewer for draft and published artifacts.
6. Lua endpoint runner that injects `ctx.request` and `ctx.artifact`, calls tools through `LuaBridge`, and logs bounded summaries.
7. Version publish/rollback model.
8. VFS paths for `/artifacts/{slug}` with read/stat/patch/write over artifact files.
9. Model-powered "generate artifact" and "repair failing artifact" flows with provider routing.
10. A single example artifact: table-backed form + dashboard + scheduled report.

Do not build public arbitrary hosting, custom domains, payments, or a marketplace first. Prove that an agent can create, inspect, run, fix, and publish one useful internal artifact cheaply.

## Main Risks

- Security: artifact JS must not call arbitrary OpenCompany APIs; use iframe sandbox and endpoint proxy.
- Data leakage: manifest capabilities must reduce scope, never grant scope.
- Runtime escape: Lua needs CPU, memory, output, response, and bridge-call budgets.
- Support burden: users may expect Lovable-level arbitrary app building; product copy must constrain the promise.
- Storage consistency: object-storage writes need versioning/locking semantics for draft edits.
- Debugging: no-build does not mean no debugging; good fixture tests and run logs are mandatory.
- Model quality: cheap models are excellent for routine generation but will still need validators and repair loops.

## Recommendation

Proceed, but phrase it as:

```text
Artifact Runtime: governed, no-build mini-apps for agent workspaces.
```

The first product should not be "build any startup idea." It should be "turn workspace data and automations into durable tools/reports/forms that agents can keep maintaining." That is closer to OpenCompany's strengths and leaves Lovable/Base44/Bolt/v0 fighting over the generic app-builder lane.

## Sources

- OpenCompany docs: `docs/planning/automation-endpoints-artifacts.md`, `docs/planning/unified-vfs-overview.md`, `docs/planning/lua-scripting.md`
- OpenCompany code: `app/Models/Automation.php`, `app/Services/LuaSandboxService.php`, `app/Services/LuaBridge.php`, `app/Services/FileSystemService.php`, `app/Models/WorkspaceDisk.php`, `app/Http/Controllers/Api/WorkspaceDiskController.php`
- DeepSeek: [Models & Pricing](https://api-docs.deepseek.com/quick_start/pricing/)
- Cloudflare: [R2 product/pricing](https://www.cloudflare.com/en-gb/developer-platform/products/r2/), [Workers Static Assets](https://developers.cloudflare.com/workers/static-assets/), [Workers pricing](https://developers.cloudflare.com/workers/platform/pricing/)
- Bunny: [Storage pricing](https://docs.bunny.net/storage/pricing)
- AWS: [S3 pricing](https://aws.amazon.com/s3/pricing/)
- Claude: [What are artifacts?](https://support.claude.com/en/articles/9487310-what-are-artifacts-and-how-do-i-use-them), [Publishing and sharing artifacts](https://support.claude.com/en/articles/9547008-publishing-and-sharing-artifacts), [AI-powered artifacts announcement](https://claude.com/blog/build-artifacts)
- Competitor baseline: [Lovable Supabase docs](https://docs.lovable.dev/integrations/supabase), [v0 full-stack apps](https://v0.dev/docs/full-stack-apps)
