# Automation Endpoints & Agent-Built Artifacts

Status: Planning, reconciled with the current checkout on 2026-08-09. This
document describes a future endpoint/artifact surface. It supersedes the older
"dashboards" framing: dashboards are one artifact type, not the whole product
surface. The VFS foundation now exists; endpoint automations and the Artifact
domain do not.

Related: `docs/planning/lua-scripting.md`, `docs/planning/unified-vfs-overview.md`, `docs/architecture/cloudflare-os-opencompany-investigation-2026-08-09.md`, `docs/ui/pages/automation.md`, `docs/ui/pages/files.md`, `docs/ui/pages/tables.md`

## Current Code Snapshot

The pieces below exist today and should be reused instead of rebuilt:

- Automations already have `trigger_type`, `execution_type`, nullable `prompt`, nullable `script`, schedule fields, run counts, failure counts, and `last_result` on `app/Models/Automation.php`.
- `execution_type = script` already runs through `RunScriptAutomationJob`, `ExecuteScriptAutomation`, `LuaSandboxService`, and `LuaBridge`.
- Current automation creation requires a cron expression through `AutomationController`, `ManageAutomations`, and the create/edit Vue screens.
- Automation tools already exist in `app/Agents/Tools/Workspace/*` and are registered by `AutomationsToolProvider`, but they create scheduled prompt/script automations only.
- API routes expose automation CRUD, manual runs, file CRUD, document CRUD, table CRUD, Lua console execution, and tool catalog data under the current workspace context.
- Workspace files are backed by `WorkspaceFile`, `WorkspaceDisk`, and `FileSystemService`; agents can already create text files through `write_file`, and server-side importers can write binary files. This is useful infrastructure, but it should not be the canonical product home for artifacts.
- The SVG tool already returns generated PNG artifacts as a conversion primitive, but there is no first-class `Artifact` model, artifact route, artifact viewer, or artifact catalog.
- `app/Domain/Vfs` now implements a permission-aware VFS baseline over current
  workspace resources, including command discovery, pagination, budgets,
  approvals, and mutation events. There is still no `/artifacts` mount or
  first-class Artifact domain.

Implication: build Artifacts as a first-class domain over scheduled script
automations, tables, documents, files, and the current VFS foundation. File
storage can be reused internally where helpful, but humans and agents should
think in `/artifacts`, not `/files/generated`.

## Vision

Turn automations into callable API endpoints and let agents build durable workspace artifacts: small internal tools, reports, generated pages, forms, exports, charts, and dashboard-like views that read and write through endpoint automations and the VFS.

The platform evolves from "agents that chat" into "agents that build inspectable workspace objects."

## System Overview

OpenCompany Lab is the place where agents and humans build, run, publish, share, and operate small workspace apps.

```text
⚗️ Lab
  Automations
  Artifacts
  Runs / Logs
```

The core artifact stack:

```text
Liquid templates = frontend pages, layouts, and partials
Assets = shared CSS/JS plus optional no-build browser libraries
Lua = backend endpoint scripts
Tables/docs/files/tasks/channels = workspace data and side effects
Lab = operations, publishing, versions, logs, schedules, approvals
VFS = implemented foundation; `/artifacts` projection remains future work
```

The differentiator:

```text
OpenCompany agents do not just answer.
They ship durable workspace software.
```

Artifacts bridge conversational agents and real operational mini-apps.

## Vocabulary

| Term | Meaning |
|---|---|
| Endpoint automation | A script automation with `trigger_type = endpoint`, URL/method metadata, request context, and a JSON response contract. |
| Artifact | A durable workspace object produced by an agent or automation. The canonical unit is a folder bundle with a manifest, pages, assets, backend scripts, data, and tests. |
| Artifact bundle | The source tree for an artifact. It behaves like a small workspace app/repo under `/artifacts/{artifact-slug}`. |
| Dashboard artifact | A renderable artifact whose primary UI is Liquid-rendered HTML, shared CSS/JS, and endpoint-backed data from automations, tables, files, or VFS reads. |
| Artifact file | A file inside an artifact bundle: Liquid, HTML, CSS, JS, Lua, JSON, Markdown, CSV, image, or other asset. It may be backed by a dedicated artifact file table or hidden workspace-file storage. |
| Liquid template | A safe, restricted template file used for pages, layouts, and partials. OpenCompany controls the allowed objects, tags, filters, and URL helpers. |
| Backend script | A Lua source file inside `backend/` that is exposed as an artifact-scoped endpoint by the artifact runtime. |
| No-build SPA | A multi-file artifact that feels like a single-page app through OpenCompany runtime navigation and shared assets, without `node_modules` or a compile step. |
| Binding requirement | A portable, named declaration of a resource an artifact needs, the operations it requests, and whether it is required. It contains no credentials or concrete cross-workspace identifiers. |
| Artifact binding | A workspace-scoped resolution of a binding requirement to one concrete table, document, file scope, channel, integration resource, or later another supported resource. |
| Artifact template | A reusable, versioned snapshot of artifact source, tests, assets, and binding requirements. This is distinct from a Liquid rendering template. |
| Artifact change set | A base-versioned group of proposed file, manifest, and binding edits that can be reviewed and applied to one draft as a unit. |
| Endpoint catalog | A generated, permission-filtered description of callable artifact endpoints and their typed contracts. It is a discovery surface, not a new execution runtime or permission grant. |
| VFS path | The stable agent-facing path that later exposes artifacts, files, documents, tables, automations, and endpoint definitions through one filesystem model. |

## Product Shape

Three pillars should work together:

1. **Artifact backend endpoints** serve as workspace-scoped serverless functions.
2. **Artifacts** provide durable human-visible output and lightweight internal tools.
3. **VFS primitives** give agents and scripts one way to inspect, write, patch, and verify the artifacts and backing data.

Dashboard pages remain valid, but the model should be folder-first and broader:

- `app`: one or more Liquid-rendered pages, shared assets, optional no-build frontend JS, and Lua backend scripts.
- `report`: Markdown/HTML/PDF source plus generated data files.
- `dashboard`: dashboard-like app with charts, tables, refresh policy, and read endpoints.
- `form`: Liquid form pages plus write endpoints and validation scripts.
- `data_export`: CSV/JSON/NDJSON files generated by script automation.
- `image_bundle`: generated or converted image outputs plus source SVG/prompts/metadata.

## Canonical Folder Layout

An artifact should look like a small repo. The root folder is canonical for agents and humans:

```text
/artifacts/feedback-tracker/
  artifact.json
  README.md

  layouts/
    app.liquid

  partials/
    nav.liquid
    toolbar.liquid
    empty-state.liquid

  pages/
    index.liquid
    admin.liquid
    settings.liquid

  assets/
    styles.css
    app.js
    components.js
    logo.png

  backend/
    list-feedback.lua
    create-feedback.lua
    update-status.lua

  data/
    seed.json
    sample-response.json

  tests/
    list-feedback.request.json
    create-feedback.request.json
```

`artifact.json` is the runtime contract:

```json
{
  "name": "Feedback Tracker",
  "slug": "feedback-tracker",
  "type": "app",
  "entry": "pages/index.liquid",
  "template": {
    "engine": "liquid",
    "mode": "restricted"
  },
  "navigation": {
    "mode": "spa"
  },
  "pages": [
    { "slug": "home", "path": "pages/index.liquid", "title": "Feedback" },
    { "slug": "admin", "path": "pages/admin.liquid", "title": "Admin" },
    { "slug": "settings", "path": "pages/settings.liquid", "title": "Settings" }
  ],
  "libraries": [
    {
      "preset": "vue3",
      "purpose": "Small reactive admin view"
    },
    {
      "preset": "chartjs",
      "purpose": "Dashboard charts"
    }
  ],
  "endpoints": [
    {
      "slug": "feedback.list",
      "description": "List feedback visible to the current workspace member",
      "method": "GET",
      "script": "backend/list-feedback.lua",
      "auth_mode": "session",
      "side_effect": "read",
      "bindings": ["FEEDBACK_TABLE"],
      "response_schema": {
        "type": "array",
        "items": { "type": "object" }
      }
    },
    {
      "slug": "feedback.create",
      "description": "Create one feedback item",
      "method": "POST",
      "script": "backend/create-feedback.lua",
      "auth_mode": "session",
      "side_effect": "write",
      "bindings": ["FEEDBACK_TABLE"],
      "request_schema": {
        "type": "object",
        "required": ["title"],
        "properties": { "title": { "type": "string" } }
      },
      "response_schema": { "type": "object" }
    }
  ],
  "bindings": {
    "FEEDBACK_TABLE": {
      "kind": "workspace_resource",
      "resource_type": "table",
      "operations": ["read", "create"],
      "required": true,
      "description": "Table that stores feedback submissions",
      "suggested": { "slug": "feedback_tracker" }
    },
    "NOTIFICATIONS": {
      "kind": "integration_resource",
      "provider": "slack",
      "resource_type": "channel",
      "operations": ["send"],
      "required": false,
      "description": "Optional channel for new-feedback notifications"
    }
  },
  "permissions": {
    "tables": ["read", "create"],
    "channels": ["send"],
    "files": ["self"],
    "network": []
  }
}
```

Keep the folder convention stable. Agents should be able to infer where frontend pages, shared assets, backend scripts, fixtures, and tests belong without asking.

## Template Rendering

Use a restricted Liquid dialect for artifact pages, layouts, and partials. Liquid is well-known, agent-friendly, designed for customer-editable templates, and safer than letting agent-authored templates execute PHP.

Recommended PHP implementation approach:

```text
Evaluate `keepsuit/liquid` first for modern Shopify Liquid compatibility.
Keep `liquid/liquid` as the older, widely installed fallback candidate.
Wrap the chosen library behind App\Domain\Artifacts\Template\ArtifactTemplateRenderer.
```

Do not expose third-party template-engine APIs directly to artifact runtime code. OpenCompany should own:

- allowed objects
- allowed tags
- allowed filters
- partial lookup
- layout rendering
- URL generation
- escaping behavior
- error formatting
- caching

Template files:

```text
layouts/*.liquid
partials/*.liquid
pages/*.liquid
```

Example `layouts/app.liquid`:

```liquid
<!doctype html>
<html>
  <head>
    <title>{{ page.title }} - {{ artifact.name }}</title>
    <link rel="stylesheet" href="{{ 'styles.css' | asset_url }}">
  </head>
  <body>
    {% render "nav" %}
    <main id="artifact-page">
      {{ content }}
    </main>
    <script type="module" src="{{ 'app.js' | asset_url }}"></script>
  </body>
</html>
```

Example `pages/admin.liquid`:

```liquid
---
layout: app
title: Admin
---

{% render "toolbar", title: "Feedback Admin" %}

<section data-page="admin" data-init="adminPage">
  <div id="feedback-table"></div>
</section>
```

Example `partials/nav.liquid`:

```liquid
<nav>
  <a href="{{ 'home' | page_url }}" data-artifact-link>Home</a>
  <a href="{{ 'admin' | page_url }}" data-artifact-link>Admin</a>
  <a href="{{ 'settings' | page_url }}" data-artifact-link>Settings</a>
</nav>
```

OpenCompany-provided filters and tags:

```liquid
{{ 'styles.css' | asset_url }}
{{ 'admin' | page_url }}
{{ 'feedback.list' | endpoint_url }}
{% render "nav" %}
```

Keep the dialect small at first. Add loops, conditionals, filters, and partial render support, but avoid arbitrary server-side logic. Data that needs logic should come from Lua endpoints or approved context objects, not template execution.

## No-Build SPA Runtime

Artifacts should feel like apps, but they should not require Node, npm packages, Vite, or a build step. The default model is:

```text
multi-file artifact structure
single-page-app feel
no compile step
```

OpenCompany should provide a small artifact browser runtime loaded from the app, for example:

```html
<script type="module" src="/artifact-runtime/v1.js"></script>
```

Runtime responsibilities:

- intercept internal links marked with `data-artifact-link`
- fetch and render the next page fragment
- preserve shared layout/assets where possible
- update browser history
- expose safe endpoint helpers
- expose safe page/asset URL helpers
- provide loading and error hooks
- provide small helpers for forms, toasts, dialogs, and tables later

Page scripts should live in shared assets, not be duplicated in every page:

```js
artifact.page("adminPage", async ({ endpoint, table }) => {
  const rows = await endpoint("feedback.list")
  table("#feedback-table", rows)
})
```

Agents can still use plain JavaScript, Alpine, Vue, HTMX, Chart.js, or similar browser libraries if they do not require compiling. External libraries must be manifest-declared:

```json
{
  "libraries": [
    {
      "preset": "vue3",
      "purpose": "Small reactive admin view"
    },
    {
      "type": "script",
      "src": "https://cdn.jsdelivr.net/npm/chart.js",
      "integrity": "sha384-...",
      "purpose": "Charts"
    }
  ]
}
```

Preferred policy:

- v1 uses approved library presets where possible.
- Custom external URLs require manifest declaration and approval.
- CSP is generated from the manifest.
- No `node_modules`.
- No package install.
- No artifact-specific build command.
- No server-side JS execution.

This gives artifacts a modern app feel while keeping them inspectable, patchable, and safe for agents to generate.

## Artifact Backend Endpoints

Artifact backend endpoints should primarily be manifest entries that point at Lua files inside the artifact bundle. That keeps the source of a mini-app together: frontend in `pages/` and `assets/`, backend in `backend/`, fixtures in `data/`, tests in `tests/`.

Backend execution is automation-adjacent. Treat artifact endpoints and scheduled automations as two runtime bindings over script source, not two unrelated products:

```text
Artifact bundle owns source:
  /artifacts/feedback-tracker/backend/list-feedback.lua

Artifact endpoint binding owns request/response execution:
  GET /api/artifacts/feedback-tracker/endpoints/feedback.list

Automation binding owns scheduled/manual/background execution:
  every weekday 09:00 -> run backend/list-feedback.lua -> write data/report/output
```

This keeps editable source in the artifact bundle while letting the existing automation runtime own scheduling, run history, failure handling, and manual runs.

Suggested route shapes:

```text
GET  /w/{workspace_slug}/artifacts/{artifact_slug}
GET  /w/{workspace_slug}/artifacts/{artifact_slug}/pages/{page_slug}
GET  /api/artifacts/{artifact_slug}/assets/{path}
GET|POST /api/artifacts/{artifact_slug}/endpoints/{endpoint_slug}
```

Scheduled/manual automation records should be optional runtime bindings, not the canonical place where artifact source code lives. If an artifact endpoint needs scheduled refresh or manual background execution, create an `Automation` that points back to the artifact source:

```text
artifact_id
artifact_endpoint_slug
script_artifact_file_id
```

Endpoint execution should:

1. Resolve the workspace before finding the artifact.
2. Find an active `Artifact::forWorkspace()` record by slug.
3. Read and validate `artifact.json`.
4. Resolve the endpoint entry to a Lua file under the same artifact root.
5. Resolve every named binding against the workspace-scoped binding registry and fail closed when a required binding is missing, revoked, or outside its allowed operations.
6. Check the endpoint method allowlist, auth mode, side-effect class, artifact status, artifact permissions, and binding operations.
7. Build a request context and pass it to `LuaSandboxService` as `ctx.request`, `ctx.artifact`, and opaque `ctx.bindings` handles.
8. Use `LuaBridge` so endpoint scripts call the same agent-visible tools and permission checks as normal Lua.
9. Return JSON with a bounded body, status code, headers allowlist, execution metadata, and clear errors.
10. Record success/failure without assuming endpoint calls should always create long-lived task conversation output.

Request context should be explicit:

```lua
local request = ctx.request
local artifact = ctx.artifact

return {
  status = 200,
  body = {
    method = request.method,
    query = request.query,
    user = request.user,
    artifact = artifact.slug,
  },
}
```

Endpoint manifest fields:

| Field | Purpose |
|---|---|
| `slug` | Human-friendly endpoint name, unique inside the artifact. |
| `description` | Short purpose statement used in Lab, generated docs, and the agent endpoint catalog. |
| `method` / `methods` | Allowed methods, usually `GET`, `POST`, or both. |
| `script` | Relative path to a Lua file under `backend/`. |
| `auth_mode` | `session`, `token`, `signed`, or later `public`. |
| `side_effect` | Required declaration: `read`, `write`, or `external`; checked against script capabilities and used in approval/catalog UX. |
| `bindings` | Names of manifest binding requirements this endpoint may use. |
| `request_schema` | Optional JSON schema used for validation and agent docs. |
| `response_schema` | Optional JSON schema used by artifact builders and tests. |
| `rate_limit` | Per-user/workspace throttle for endpoint calls. |
| `cache_ttl_seconds` | Optional read endpoint cache for dashboards and reports. |

### Agent-Callable Endpoint Catalog

Automations and HTTP endpoints are already the planned execution surfaces. The catalog adds the missing discovery and typing surface; it tells an agent what can be called before the agent chooses an invocation path.

| Surface | Responsibility |
|---|---|
| Artifact endpoint runner | The one canonical workspace-scoped Lua execution path. |
| HTTP route | Invokes the runner from an authenticated, signed, or later public request. |
| Automation binding | Invokes the same source through a schedule, manual run, or background workflow. |
| Agent endpoint catalog | Lists and describes the endpoints the current principal may discover and call. It does not execute code or grant authority. |

The catalog should be generated from the validated, versioned manifest rather than maintained as another source file. A catalog projection can expose:

```json
{
  "artifact": "feedback-tracker",
  "version": 12,
  "endpoints": [
    {
      "slug": "feedback.create",
      "description": "Create one feedback item",
      "methods": ["POST"],
      "side_effect": "write",
      "request_schema": { "type": "object" },
      "response_schema": { "type": "object" },
      "required_bindings": ["FEEDBACK_TABLE"],
      "invocation_modes": ["http", "automation", "agent"]
    }
  ]
}
```

Discovery rules:

- Filter the projection at read time by workspace, artifact visibility, version, actor/agent permission, endpoint auth mode, and binding availability. A cached manifest descriptor must not become an authorization decision.
- Expose it as a read-only virtual VFS projection such as `/artifacts/{slug}/catalog/endpoints.json` and through bounded `list_artifact_endpoints` / `describe_artifact_endpoint` agent operations.
- Keep `run_artifact_endpoint` as the invocation operation. It must call the canonical endpoint runner and recheck all permissions, bindings, budgets, schemas, and approvals at execution time.
- Do not register every artifact endpoint as a permanent top-level tool. Catalog discovery plus one typed call operation avoids tool-list explosion and stale tool definitions.
- Include concise schemas and summaries only. Large examples and fixtures stay in the artifact bundle and can be read on demand.

If endpoint automations are still added as first-class automation triggers, keep `cron_expression` nullable or conditionally required once `trigger_type = endpoint` exists. Current controller and UI validation require cron for all automations, so this remains a code change for automation-bound endpoints.

## Artifact Model

Do not create a narrow `Dashboard` model. Create an artifact concept that owns a bundle folder and can represent dashboards, forms, reports, exports, and other generated tools.

Suggested first table:

| Field | Purpose |
|---|---|
| `id` | UUID. |
| `workspace_id` | Required workspace boundary. |
| `created_by_id` | Human or agent owner. |
| `updated_by_id` | Last editor. |
| `name` | Display name. |
| `slug` | Stable URL slug per workspace. |
| `type` | `app`, `dashboard`, `form`, `report`, `data_export`, `image_bundle`, etc. |
| `description` | Human/agent summary. |
| `status` | `draft`, `published`, `archived`, `failed`. |
| `root_folder_id` | Root folder for the artifact file tree. This can point to `artifact_files` or an internal `WorkspaceFile` folder. |
| `manifest_path` | Usually `artifact.json`; stored as a file so agents can inspect and patch it. |
| `manifest_cache` | Optional parsed manifest cache for listing/query speed. |
| `source_automation_id` | Automation that generated or refreshes it, if any. |
| `last_rendered_at` | Render freshness marker. |
| `last_error` | Latest render or refresh failure. |

Storage rule:

```text
Artifact metadata belongs in an Artifact model.
Artifact source/content belongs in an artifact-owned folder tree.
Domain data should stay in tables/documents/tasks/channels/etc.
WorkspaceFile can be an internal storage implementation, but it is not the canonical product or VFS location.
The future VFS exposes artifacts at /artifacts.
```

Recommended storage approach:

1. Prefer a dedicated `artifact_files` tree if implementation starts from scratch. It gives artifact-specific paths, versions, checksums, MIME types, and ownership without leaking into the Files product.
2. If speed matters, reuse `WorkspaceFile` internally by pointing `artifacts.root_folder_id` at a hidden folder. Hide that folder from the normal Files UI and expose it through `ArtifactService`.
3. Do not store generated HTML, CSS, JS, or Lua directly in `artifacts` columns. Keep source files patchable and inspectable as files.
4. Do not make `/files/generated/artifacts` the agent-facing path. That makes Artifacts feel like file clutter instead of a first-class workspace object.

## Binding Requirements And Resolutions

Artifact source should depend on named bindings instead of embedded workspace IDs, provider connection IDs, or credentials. The manifest declaration is portable; the database resolution is workspace-local.

```text
artifact.json requirement
  FEEDBACK_TABLE: table, read + create, required
          |
          v install/configure inside one workspace
artifact_bindings resolution
  FEEDBACK_TABLE -> Table 7d1..., allowed: read + create
          |
          v runtime permission intersection
opaque ctx.bindings.FEEDBACK_TABLE handle
```

Suggested runtime table:

```text
artifact_bindings
  id
  workspace_id
  artifact_id
  binding_name
  kind: workspace_resource|integration_resource
  resource_type
  resource_id nullable
  integration_connection_id nullable
  operations
  status: unresolved|active|revoked|invalid
  configured_by_id nullable
  configured_at nullable
  revoked_at nullable
  metadata
  created_at
  updated_at
```

Binding rules:

- A requirement declares purpose, resource type, requested operations, and whether it is required. `suggested` metadata may help create or locate a resource, but it is never authority.
- A resolution belongs to the same workspace as the artifact and points to one concrete resource through an app-owned relation. Never store a provider credential, token, webhook secret, or raw connection configuration in `artifact.json`, version snapshots, templates, or run logs.
- Installation offers three explicit choices per requirement: select an existing permitted resource, create the suggested workspace resource through its normal approval path, or leave an optional binding unresolved.
- Required unresolved, revoked, deleted, or type-mismatched bindings block publishing and fail closed at runtime. Optional unresolved bindings must have an intentional code path and an observable disabled state.
- Requested operations are a ceiling, not a grant. They must be a subset of manifest capabilities and are intersected with actor, agent, artifact, workspace, integration, and tool permissions on every call.
- Rebinding changes configuration without rewriting source. Binding additions, removals, target changes, or operation expansion are audited and enter the approval flow when they increase authority.
- Lua receives an opaque typed handle, for example `app.tables.insert(ctx.bindings.FEEDBACK_TABLE, row)`. The bridge resolves and authorizes the handle; the script never receives credentials and should not need a portable resource to have the same database ID in every workspace.
- V1 should support the resources Artifacts already need: tables, documents, artifact-owned files, channels, and named integration resources. Add further kinds only with a typed resolver, authorization policy, redaction policy, and revocation behavior.

This adds concrete-resource least privilege to the existing manifest capability ceiling. The manifest says what classes of operations the Artifact may attempt; bindings say exactly which resource each portable name resolves to.

## Artifact Viewer

Render modes:

| Type | Viewer |
|---|---|
| `app` / `dashboard` / `form` | Sandboxed iframe using the manifest entry page. |
| `report` | Existing document/Markdown viewer or a simple read-only renderer. |
| `data_export` | Download plus preview for CSV/JSON/NDJSON. |
| `image_bundle` | Image preview using an artifact asset URL. |
| any bundle | Manifest-driven file tree with a selected entry file. |

Artifact frontend security requirements:

- Use a sandboxed iframe.
- Start with no same-origin access unless endpoint calls require same-origin session auth.
- Prefer endpoint calls through a narrow artifact proxy that enforces workspace, artifact, endpoint, method, and auth rules.
- Use a CSP that disallows arbitrary network access by default.
- Prefer OpenCompany-approved library presets and local runtime assets; custom CDN URLs require manifest declaration, integrity metadata where supported, CSP inclusion, and explicit approval.
- Never let artifact JS call arbitrary app APIs directly. It should call artifact-bound endpoints or read-only artifact assets.

## Versioning

Use artifact-level versions, not just file versions. An artifact is a bundle, so a version must snapshot the manifest plus the relevant file tree.

Suggested table:

```text
artifact_versions
  id
  artifact_id
  version_number
  manifest_snapshot
  file_tree_snapshot
  created_by_id
  source: agent|human|automation
  change_summary
  created_at
```

Versioning rules:

- Draft edits are mutable.
- Published versions are immutable.
- Endpoint execution uses a specific published version unless explicitly running a draft preview.
- Rollback creates a new draft from an older published version; do not mutate historical versions.
- Diffs compare `artifact.json` plus file contents by path.
- Publish should record a short change summary generated from the file diff and manifest diff.
- Run logs must include `artifact_version_id` so failures can be reproduced against the exact source that ran.

This keeps artifact behavior stable for viewers while still allowing agents to iterate in a draft.

## Artifact Templates And Installation

Artifact templates should turn a proven Artifact into an installable starting point without copying its authority or runtime history. This use of "template" means an Artifact bundle template; it is distinct from the Liquid template engine used to render pages.

Suggested records:

```text
artifact_templates
  id
  workspace_id nullable
  name
  slug
  description
  visibility: built_in|workspace
  latest_version_id
  created_by_id nullable
  status: draft|published|archived
  metadata
  created_at
  updated_at

artifact_template_versions
  id
  artifact_template_id
  version_number
  source_artifact_version_id nullable
  manifest_snapshot
  file_tree_snapshot
  binding_requirements_snapshot
  change_summary
  created_by_id nullable
  created_at
```

A template version includes:

- Manifest, pages, layouts, assets, backend scripts, tests, and safe example data required to understand the bundle.
- Binding requirements, requested operations, descriptions, and optional resource-creation suggestions.
- Presentation metadata such as category, preview image, supported Artifact types, and expected setup time.

A template version excludes:

- Concrete binding resolutions, workspace IDs, integration connection IDs, credentials, secrets, signed/share tokens, and custom-domain state.
- Production rows, private documents/files, customer data, run logs, schedules, approvals, conversations, agent memory, or source Artifact permissions.
- Generated seed data unless it is explicitly marked safe, reviewed, bounded, and part of the template source.

Installation flow:

1. Choose an immutable template version.
2. Create a new workspace-owned Artifact draft and copy the versioned bundle into it.
3. Review every binding requirement; select an existing resource, explicitly create a suggested resource through its owning domain, or leave an optional requirement unresolved.
4. Apply the source and binding resolutions through an Artifact change set so a failed install cannot leave a half-written draft.
5. Validate the manifest, compile scripts, run fixtures, render a preview, and show requested capabilities and unresolved optional features.
6. Publish and enable schedules/shares separately. Installation never carries over a prior workspace's runtime authority.

Templates should initially be built-in or workspace-private. A cross-workspace marketplace adds provenance, malware review, publisher trust, update policy, licensing, and data-safety concerns and should come only after the install and binding model is proven.

## Runs And Logs

Endpoint runs, scheduled runs, manual runs, previews, and tests should use one run-log model with a trigger field.

Suggested table:

```text
artifact_runs
  id
  artifact_id
  artifact_version_id
  endpoint_slug nullable
  automation_id nullable
  trigger: http|schedule|manual|preview|test
  actor_id nullable
  status: running|success|failed|cancelled
  request_summary
  response_summary
  stdout
  error
  duration_ms
  memory_bytes
  tool_calls_count
  started_at
  finished_at
```

Log policy:

- Store summaries by default, not unbounded raw request/response bodies.
- Redact secrets before persistence.
- Store larger payloads in private storage only when needed, with pointers from the run log.
- Keep stdout/stderr/output bounded.
- Record Lua bridge call count and compact tool-call summaries.
- Show artifact version, endpoint slug, trigger, actor, status, duration, and time in the Lab UI.

Lab run list:

```text
Lab / Artifacts / Feedback Tracker / Runs
  feedback.list    http      success  42ms   Rutger  v12  2m ago
  feedback.create  http      failed   81ms   Nova    v12  4m ago
  refresh-report   schedule  success  1.2s   System  v11  1h ago
```

## Permissions And Capabilities

Use two permission layers.

Artifact-level permissions:

- who can view
- who can edit
- who can publish
- who can run endpoints
- who can schedule backend scripts
- who can manage signed, password-gated, or public access later

Manifest capabilities:

```json
{
  "permissions": {
    "tables": ["read", "create"],
    "channels": ["send"],
    "files": ["self"],
    "network": [],
    "tasks": ["create"]
  }
}
```

Runtime enforcement must use the intersection of:

```text
actor permissions
agent permissions
artifact permissions
manifest capabilities
resolved binding operations and concrete resource scope
workspace policy
tool permissions
```

The manifest and its resolved bindings can only reduce scope. They must never grant more access than the actor, assigned agent, workspace policy, integration connection, or underlying tool permission already allows.

## Sandboxing And Endpoint Security

Frontend sandbox:

- Render artifact frontend output in a sandboxed iframe.
- Start without same-origin access unless endpoint auth requires it.
- Prefer an artifact API proxy over direct app API access.
- Apply a strict CSP.
- Allow artifact pages to call only endpoints declared in `artifact.json`.
- Do not let artifact JavaScript call arbitrary OpenCompany APIs.

Lua sandbox:

- No OS access.
- No host filesystem access.
- No raw network by default.
- Artifact file/VFS access only through explicit artifact or VFS APIs.
- Tool calls only through `LuaBridge`.
- CPU, memory, execution-time, response-size, output-size, and tool-call budgets.

Endpoint request flow:

```text
/api/artifacts/{artifact}/endpoints/{endpoint}
  -> resolve workspace
  -> resolve Artifact::forWorkspace()
  -> resolve published or draft version
  -> parse artifact.json
  -> validate endpoint exists
  -> validate method/auth/request schema
  -> enforce rate limit and capability scope
  -> execute the versioned Lua script
  -> persist run log
  -> return bounded JSON
```

## CSP And Assets

Start with a strict default:

```text
default-src 'none';
script-src 'self';
style-src 'self' 'unsafe-inline';
img-src 'self' data:;
connect-src 'self';
font-src 'self';
frame-ancestors 'self';
```

Avoid arbitrary CDN dependencies in v1. If Tailwind, Chart.js, or similar libraries are needed, serve approved local copies from the artifact runtime. Later, allow manifest-declared external origins only through review and approval.

## Secrets

Artifacts should not directly read secrets. Backend scripts should call integration tools through existing credential resolvers and permission checks.

Manifest example:

```json
{
  "integrations": {
    "github": ["issues:read"],
    "stripe": []
  }
}
```

Secret handling rules:

- Never expose raw integration credentials to artifact frontend code or Lua.
- Redact tokens, API keys, cookies, OAuth values, authorization headers, and known secret-shaped strings from logs.
- Do not include request headers in run logs unless explicitly allowlisted and redacted.
- Keep integration access scoped to named capabilities, not broad credential access.

## Sharing And Password Gates

Artifacts can be workspace-only, signed-share, password-gated, or eventually public. Start with workspace-only as the default.

Suggested table:

```text
artifact_shares
  id
  artifact_id
  artifact_version_id nullable
  token_hash
  access_mode: signed|password|public
  password_hash nullable
  expires_at nullable
  revoked_at nullable
  created_by_id
  last_used_at nullable
  metadata
  created_at
```

Access modes:

| Mode | Behavior |
|---|---|
| `workspace` | No share row; viewer must be authenticated and authorized in the workspace. |
| `signed` | Unguessable share token grants access until expiry/revocation. |
| `password` | Unguessable share token plus password unlocks access until session/expiry/revocation. |
| `public` | No password; visible to anyone with the URL. Delay until the rest of the security model is proven. |

Share routes:

```text
/share/artifacts/{share_token}
/share/artifacts/{share_token}/pages/{page_slug}
/share/artifacts/{share_token}/endpoints/{endpoint_slug}
```

Sharing rules:

- Store share tokens hashed, not in plaintext.
- Store passwords with the app password hasher, not reversible encryption.
- Allow expiry and manual revocation.
- Prefer version-bound shares by default, so a shared URL does not silently change when the draft is republished.
- Allow "track latest published version" only as an explicit option.
- Log share creation, password changes, access, failed password attempts, revocation, and expiry.
- Rate-limit password attempts by token and IP.
- Shared viewers should not inherit workspace-member privileges.
- Shared artifacts should expose only endpoints explicitly marked share-safe in `artifact.json`.
- Write endpoints should be disabled for shared viewers in v1 unless explicitly approved.
- Shared access should not expose Lab editing, source files, logs, versions, or settings.

Manifest endpoint sharing flag:

```json
{
  "endpoints": [
    {
      "slug": "feedback.list",
      "method": "GET",
      "script": "backend/list-feedback.lua",
      "share": {
        "enabled": true,
        "methods": ["GET"]
      }
    }
  ]
}
```

Password-gated UX:

```text
Open share link
  -> if signed and valid: render artifact
  -> if password-gated and locked: show password form
  -> if password correct: set short-lived share session and render artifact
  -> if expired/revoked: show unavailable state
```

## Custom Domains

Custom domains are a paid future feature and are explicitly outside v1. The first scope should prove workspace-auth, signed shares, password gates, versions, logs, and sandboxing before adding tenant-controlled hostnames.

Future model:

```text
artifact_domains
  id
  workspace_id
  artifact_id nullable
  hostname
  status: pending|verified|active|failed|disabled
  verification_token_hash
  tls_status
  access_mode: artifact|workspace_portal
  created_by_id
  verified_at nullable
  last_checked_at nullable
  metadata
  created_at
```

Use cases:

- A customer-facing report at `reports.customer.com`.
- A lightweight portal at `onboarding.vendor.com`.
- A public/internal status page at `status.team.com`.
- A password-gated client dashboard at `client-name.example.com`.

Required capabilities before shipping:

- DNS ownership verification.
- Automated TLS provisioning and renewal.
- Hostname-to-workspace/artifact routing.
- Per-domain access policy: public, password-gated, signed, or workspace-auth redirect.
- Domain-level CSP and frame policy.
- Domain-level rate limiting and abuse controls.
- Clear billing/plan checks.
- Audit events for domain add, verify, activate, disable, and delete.

Security notes:

- Custom domains must not expose Lab editing, source files, logs, versions, or settings.
- Domain routing must resolve to a published artifact version or an explicit latest-published policy.
- Backend endpoints on custom domains must still pass artifact manifest, share/public access, capability, rate-limit, and sandbox checks.
- Cookies should be scoped carefully. Do not reuse the main app session cookie on arbitrary custom domains.
- Password-gated custom domains should use share sessions scoped to that hostname and artifact.

## Git Integration

Git integration should be a strong artifact capability because artifacts are folder bundles with source files. It is not required for v1, but the model should leave room for it from the start.

Use cases:

- Export an artifact bundle to a GitHub repository for external review.
- Import an existing small HTML/Lua tool into Lab as an artifact.
- Sync `/artifacts/{slug}` to a branch so developers can edit with normal Git workflows.
- Map published artifact versions to commits or tags.
- Open pull requests for high-risk artifact changes before publishing.
- Let advanced teams keep artifact source in their own repository while OpenCompany handles runtime, logs, sharing, and permissions.

Suggested table:

```text
artifact_git_links
  id
  artifact_id
  provider: github|gitlab|generic
  repository
  branch
  path_prefix
  sync_mode: export_only|import_only|two_way
  last_commit_sha nullable
  last_synced_at nullable
  status: active|paused|failed
  created_by_id
  metadata
  created_at
```

Recommended modes:

| Mode | Behavior |
|---|---|
| `export_only` | OpenCompany is source of truth; pushes artifact bundle snapshots to Git. |
| `import_only` | Git is source of truth; OpenCompany imports into a draft and validates before publish. |
| `two_way` | Future advanced mode with conflict detection, version checks, and explicit sync operations. |

Start with `export_only` or manual import/export. Two-way sync is powerful but easy to get wrong because artifact versions, approvals, and runtime safety must stay authoritative in OpenCompany.

Git mapping:

```text
artifact version v12
  -> git commit abc123
  -> optional tag artifact/feedback-tracker/v12
  -> optional PR link for review
```

Security and workflow rules:

- Git credentials must go through integration credential storage; never expose tokens to artifact Lua or HTML.
- Importing from Git creates or updates a draft, never a published version directly.
- Publish gates still run after Git import.
- Capability expansions from Git diffs still require approval.
- Sync logs should be stored as artifact events.
- Conflict handling must compare artifact version, file checksums, and last synced commit.
- Git content must not bypass manifest validation, CSP rules, endpoint sandboxing, or secret scanning.
- Pull request publishing should show the same readable capability/file diff as normal artifact approvals.

## Stripe And Agentic Payments

Stripe payments and agentic commerce are future scope. They are powerful because an artifact can become a paid portal, generated micro-SaaS tool, client dashboard, paid report, or agent-readable storefront. This should not be part of the first artifact release, but the architecture should leave room for it.

Example commerce artifact:

```text
/artifacts/client-portal/
  artifact.json

  pages/
    index.liquid
    pricing.liquid
    success.liquid
    account.liquid

  assets/
    styles.css
    app.js

  backend/
    list-products.lua
    create-checkout-session.lua
    handle-stripe-webhook.lua
    get-entitlement.lua
    cancel-subscription.lua
    accept-agent-order.lua

  data/
    products.json
    sample-checkout-request.json

  tests/
    create-checkout.request.json
    webhook-checkout-completed.json
```

Practical future flow using normal Stripe Checkout/Billing:

```text
User opens artifact pricing page
  -> POST /api/artifacts/client-portal/endpoints/checkout.create
  -> backend/create-checkout-session.lua calls app.stripe.checkout.create_session(...)
  -> Stripe Checkout handles payment
  -> Stripe webhook reaches OpenCompany
  -> app-owned webhook verifier invokes artifact-safe handler or updates entitlement tables
  -> artifact account page reads entitlement state
```

Future agentic-commerce flow:

```text
Buyer agent requests catalog/quote
  -> backend/list-products.lua or backend/quote.lua returns structured products, prices, policies

Buyer approves purchase through its AI platform/payment rail
  -> platform sends scoped payment token/order context
  -> backend/accept-agent-order.lua validates and accepts order through app.stripe.agentic.*
  -> artifact records order, entitlement, fulfillment task, and receipt
```

Manifest capability example:

```json
{
  "type": "app",
  "entry": "pages/index.liquid",
  "template": {
    "engine": "liquid",
    "mode": "restricted"
  },
  "navigation": {
    "mode": "spa"
  },
  "endpoints": [
    {
      "slug": "checkout.create",
      "method": "POST",
      "script": "backend/create-checkout-session.lua",
      "permissions": {
        "stripe": ["checkout.sessions.create"],
        "tables": ["artifact_entitlements:write"]
      }
    }
  ]
}
```

Lua handler pattern:

```text
validate input
call scoped OpenCompany Stripe integration wrapper
write local state to tables
emit optional channel/task side effects
return bounded JSON
```

Example `backend/list-products.lua`:

```lua
--!strict

local products = app.tables.rows("artifact_products", {
  where = { active = true },
  order_by = "sort_order"
})

return {
  status = 200,
  body = {
    products = products
  }
}
```

Example `backend/create-checkout-session.lua`:

```lua
--!strict

local request = ctx.request
local artifact = ctx.artifact
local input = request.body

if not input.price_id then
  return {
    status = 422,
    body = { error = "price_id is required" }
  }
end

local idempotency_key = "artifact:" .. artifact.id .. ":checkout:" .. request.id

local session = app.stripe.checkout.create_session({
  mode = input.mode or "subscription",
  price_id = input.price_id,
  quantity = input.quantity or 1,
  customer_email = input.email,
  success_url = artifact.urls.page("success") .. "?session_id={CHECKOUT_SESSION_ID}",
  cancel_url = artifact.urls.page("pricing"),
  metadata = {
    artifact_id = artifact.id,
    artifact_slug = artifact.slug,
    workspace_id = ctx.workspace.id,
    actor_id = request.user and request.user.id or nil
  },
  idempotency_key = idempotency_key
})

app.tables.insert("artifact_payment_events", {
  artifact_id = artifact.id,
  provider = "stripe",
  event_type = "checkout_session_created",
  external_id = session.id,
  status = "created",
  metadata = {
    price_id = input.price_id,
    mode = input.mode or "subscription"
  }
})

return {
  status = 200,
  body = {
    checkout_url = session.url,
    session_id = session.id
  }
}
```

Example `backend/handle-stripe-webhook.lua`:

```lua
--!strict

local event = ctx.request.body

if event.type == "checkout.session.completed" then
  local session = event.data.object

  app.tables.upsert("artifact_entitlements", {
    external_customer_id = session.customer,
    artifact_id = session.metadata.artifact_id
  }, {
    workspace_id = session.metadata.workspace_id,
    artifact_id = session.metadata.artifact_id,
    actor_id = session.metadata.actor_id,
    external_customer_id = session.customer,
    external_subscription_id = session.subscription,
    status = "active",
    source = "stripe",
    activated_at = app.time.now()
  })

  app.channels.send("billing", "New artifact subscription: " .. session.id)
end

if event.type == "customer.subscription.deleted" then
  local subscription = event.data.object

  app.tables.update_where("artifact_entitlements", {
    external_subscription_id = subscription.id
  }, {
    status = "cancelled",
    cancelled_at = app.time.now()
  })
end

return {
  status = 200,
  body = { received = true }
}
```

Example `backend/get-entitlement.lua`:

```lua
--!strict

local user = ctx.request.user

if not user then
  return {
    status = 401,
    body = { error = "Authentication required" }
  }
end

local entitlement = app.tables.first("artifact_entitlements", {
  where = {
    artifact_id = ctx.artifact.id,
    actor_id = user.id,
    status = "active"
  }
})

return {
  status = 200,
  body = {
    active = entitlement ~= nil,
    entitlement = entitlement
  }
}
```

Future agentic payment handler shape:

```lua
--!strict

local input = ctx.request.body

if not input.shared_payment_token then
  return {
    status = 422,
    body = { error = "shared_payment_token is required" }
  }
end

local order = app.stripe.agentic.accept_order({
  shared_payment_token = input.shared_payment_token,
  cart = input.cart,
  buyer_agent = input.buyer_agent,
  idempotency_key = "agent-order:" .. ctx.request.id
})

app.tables.insert("artifact_orders", {
  artifact_id = ctx.artifact.id,
  order_id = order.id,
  status = order.status,
  total = order.total,
  currency = order.currency,
  buyer_agent = input.buyer_agent
})

return {
  status = 200,
  body = {
    order_id = order.id,
    status = order.status
  }
}
```

Payment security rules:

- Artifact Lua must never receive Stripe secret keys.
- Artifact frontend code must never receive Stripe secret keys.
- Lua calls an OpenCompany-owned Stripe integration wrapper such as `app.stripe.*`; exact API shape is future work.
- Stripe webhooks must be verified by app-owned code before any artifact handler sees normalized event data.
- Use idempotency keys for checkout, order acceptance, refunds, and fulfillment actions.
- Store payment events, entitlements, and orders in workspace-scoped tables or first-class billing records.
- Payment endpoints require explicit capability grants and approval.
- Write/payment endpoints should not be share-accessible in v1.
- Logs must redact payment tokens, checkout session secrets, customer PII beyond approved summaries, and webhook signatures.
- Payment runs must include artifact version, endpoint slug, actor/share context, Stripe object IDs, and idempotency key.
- Refunds, cancellations, and subscription mutations should require stricter approval or admin-only artifact permissions.

## Atomic Proposed Changes

Agent-authored Artifact edits should be staged as a coherent proposal before they mutate a draft. This gives the human one reviewable diff and prevents a multi-file change from leaving `artifact.json`, frontend files, backend scripts, tests, and bindings out of sync.

Suggested records:

```text
artifact_change_sets
  id
  workspace_id
  artifact_id
  agent_run_id nullable
  chat_id nullable
  base_artifact_version_id nullable
  base_draft_revision
  status: open|ready|accepted|rejected|conflicted|applied|failed
  summary
  proposed_by_id
  resolved_by_id nullable
  resolved_at nullable
  applied_at nullable
  failure_summary nullable
  created_at
  updated_at

artifact_change_operations
  id
  artifact_change_set_id
  sequence
  kind: file_create|file_replace|file_patch|file_delete|manifest_patch|binding_set|binding_remove
  target
  base_checksum nullable
  payload
  preview_summary
  status: pending|validated|applied|failed
  created_at
```

Change-set rules:

- Scope one change set to one Artifact draft. It may include bundle files, manifest edits, and Artifact binding resolutions, but not arbitrary cross-domain data changes or external side effects.
- Record the draft revision and per-target checksum observed by the proposer. Acceptance rechecks both; drift produces `conflicted` and requires a fresh proposal or an explicit rebase, never a silent overwrite.
- Validate paths, manifest shape, binding types, requested operations, scripts, schemas, permissions, and all operation payloads before writing any live draft state.
- Apply database-backed source and metadata in one transaction. If source bytes use object storage, upload immutable candidate blobs first, atomically switch their database pointers with the other draft records, and clean up unreferenced candidates afterward. The user-visible draft must never expose a partially applied set.
- Do not create tables, mutate integration resources, send messages, call external APIs, publish, enable schedules, or change shares while building or applying a proposal. Those actions stay in their owning workflows with their own permission and approval checks.
- Acceptance authorizes only the exact operations shown and only against the mutable draft. It is not publish approval, capability-expansion approval, or permission to execute the resulting endpoints.
- Keep the accepted proposal, rendered diff, validation result, actor/agent attribution, and apply outcome in the audit trail. Redact secrets and avoid storing full private data in operation previews.

The initial UI can be compact:

```text
Proposed Artifact change
  Summary: Add feedback submission flow
  Base: draft revision 14
  Files: +2 ~3 -0
  Manifest: +feedback.create endpoint
  Bindings: FEEDBACK_TABLE -> feedback_tracker
  Capabilities: table create added

  [Preview] [Reject] [Apply to draft]
```

This is intentionally narrower than a general-purpose workspace transaction system. Table rows, documents, tasks, channels, and integration mutations remain normal runtime/domain operations; the change set protects the deployable Artifact definition.

## Publishing Gates

Separate draft from published. Before publishing:

1. Validate `artifact.json`.
2. Validate all referenced files exist.
3. Validate endpoint scripts compile.
4. Run request fixtures/tests from `tests/`.
5. Resolve every required binding and validate its type, workspace, current status, and operation ceiling.
6. Check permissions and manifest capabilities.
7. Generate the permission-filtered endpoint catalog and validate request/response schemas and side-effect declarations.
8. Scan rendered pages, templates, and frontend assets for disallowed APIs and external origins.
9. Generate an immutable version snapshot.
10. Require approval when capabilities are high risk.

High-risk capability changes:

- Write access to tables.
- Sending messages to channels.
- Deleting or overwriting files.
- External integration mutation.
- Public or signed sharing.
- Password-gated sharing.
- Custom domain activation.
- Git import or sync that expands capabilities.
- Payment capability grants or payment-flow changes.
- Scheduled writes.
- Broad read access across channels, files, docs, or tables.

## Approvals

Use approval gates for dangerous changes, not for every edit.

Low-risk changes can publish without approval:

- Template/CSS-only changes.
- Read-only endpoint changes with the same capability scope.
- Copy/text changes in reports.

Approval-required changes:

- New write endpoint.
- New schedule.
- Capability expansion.
- Required binding addition, rebinding to a more sensitive resource, or binding operation expansion.
- Public/signed share.
- Password-gated share.
- Custom domain activation.
- Git import or sync that expands capabilities.
- Payment capability grants, checkout endpoint changes, refunds, cancellations, or subscription mutations.
- Integration mutation.
- Channel send.
- File delete/overwrite outside the artifact bundle.

Applying an accepted Artifact change set updates only the draft. Publishing gates and any capability, binding, integration, sharing, schedule, or payment approvals still run independently against the exact resulting draft.

Approval prompt should show a readable diff:

```text
Artifact: Feedback Tracker
Change: added POST feedback.create

New capabilities:
  tables.feedback_tracker.write
  channels.general.send

Files changed:
  artifact.json
  backend/create-feedback.lua
```

## Audit Trail

Add append-only events for artifact lifecycle and runtime operations.

Suggested table:

```text
artifact_events
  id
  artifact_id
  actor_id nullable
  event_type
  version_id nullable
  run_id nullable
  metadata
  created_at
```

Event types:

```text
created
file_changed
endpoint_added
endpoint_changed
capability_changed
published
rolled_back
endpoint_run
schedule_created
schedule_changed
approval_requested
approval_granted
approval_denied
archived
deleted
```

Events are for investigation and accountability. They should not replace immutable published versions or detailed run logs.

## Rate Limits And Budgets

Enforce budgets at artifact, endpoint, actor, and workspace levels.

Suggested v1 defaults:

```text
10 endpoint requests/minute per user
100 endpoint requests/minute per workspace
30 seconds max Lua execution
fixed max tool calls per run
fixed max response bytes
fixed max log bytes
one scheduled run per artifact endpoint at a time
disable or pause after repeated consecutive failures
```

Scheduled runs need overlap behavior:

- `skip`: if a prior run is active, skip this tick and log the skip.
- `queue`: enqueue the next run after the active one.
- `parallel`: only for explicitly safe read-only endpoints.

Default to `skip` for v1.

## Recommended V1 Security Policy

Start conservative:

- Workspace-auth only.
- No public artifacts by default.
- Signed or password-gated sharing only after explicit publish/share approval.
- Custom domains are paid future scope, not v1.
- Git import/export is future scope unless limited to manual export of artifact source.
- Stripe payments and agentic commerce are future scope, not v1.
- No external network from artifact frontend.
- No arbitrary CDN assets; only approved presets or explicitly approved manifest-declared external URLs.
- No direct OpenCompany API calls from artifact frontend.
- Lua only through the existing sandbox and tool bridge.
- Read endpoints before write endpoints.
- Write endpoints require approval.
- Published versions are immutable.
- Full run logs with redaction and bounded payloads.
- Lab owns versions, logs, approvals, schedules, and publishing.

## Product, Automation, And VFS Home

The unified VFS document says agents should see one filesystem while OpenCompany keeps each domain in its proper model and service. This feature should fit that boundary.

Artifacts should not be a totally separate primary nav island at first. Their management surface belongs with automation runtime controls because backend scripts, endpoint runs, schedules, logs, and failures are the same operational world. The clean product shape is a single **Lab** area.

Recommended sidenav label:

```text
⚗️ Lab
```

The alembic icon fits the product intent: a place where agents and humans experiment, build, run, inspect, and publish internal tools. It is short enough for the sidebar while still broad enough to hold automations, artifacts, endpoint scripts, and runs.

Lab sections:

```text
Lab
  Automations
  Artifacts
  Runs / Logs
```

Evolve the existing Automation page into the Lab operational hub. Artifacts still need direct open/view URLs because a published mini-app/report should be linkable without making the user navigate through Lab management.

Canonical management routes:

```text
/w/{workspace_slug}/lab
/w/{workspace_slug}/lab/automations
/w/{workspace_slug}/lab/artifacts
/w/{workspace_slug}/lab/artifacts/{artifact_slug}/edit
/w/{workspace_slug}/lab/artifacts/{artifact_slug}/logs
```

Keep `/w/{workspace_slug}/automation` as a compatibility alias during migration.

Canonical open/view routes:

```text
/w/{workspace_slug}/artifacts/{artifact_slug}
/w/{workspace_slug}/artifacts/{artifact_slug}/pages/{page_slug}
/share/artifacts/{share_token}
/share/artifacts/{share_token}/pages/{page_slug}
```

Canonical VFS paths:

```text
/artifacts/{artifact-slug}/artifact.json
/artifacts/{artifact-slug}/README.md
/artifacts/{artifact-slug}/pages/index.liquid
/artifacts/{artifact-slug}/pages/admin.liquid
/artifacts/{artifact-slug}/assets/styles.css
/artifacts/{artifact-slug}/assets/app.js
/artifacts/{artifact-slug}/backend/list-feedback.lua
/artifacts/{artifact-slug}/backend/create-feedback.lua
/artifacts/{artifact-slug}/data/sample-response.json
/artifacts/{artifact-slug}/tests/create-feedback.request.json
/artifacts/{artifact-slug}/catalog/endpoints.json   # generated, read-only
```

Files app behavior:

- The Files page is not the canonical home for artifacts.
- If `WorkspaceFile` backs artifact files internally, hide those implementation folders from normal Files browsing or show them only as linked/generated system files.
- Lab owns preview, edit, endpoint bindings, schedules, logs, publish state, permissions, duplicate, archive, and delete.

Agent and Lua behavior should converge on the future VFS primitives:

```lua
local rows = app.tables.get_rows("feedback_tracker", { limit = 100 })

app.vfs.write("/artifacts/feedback-tracker/data/feedback.json", json.encode(rows), {
  mode = "overwrite",
})
```

`app.vfs.*` does not exist yet, so current implementation work should add an `ArtifactService` and artifact tools first, or use hidden file storage behind artifact-specific operations. Avoid teaching agents to use `/files/generated/artifacts` as the durable path.

## Human And Agent AX

Human AX should feel like one operational builder area, not two unrelated pages. Lab can look like this:

```text
⚗️ Lab
  [Automations] [Artifacts] [Runs / Logs]

Automations
  Daily standup summary       schedule   active     next run 09:00
  Weekly KPI report           schedule   active     next run Monday

Artifacts
  Feedback Tracker            app        Published  endpoints 2  schedules 0
  Weekly Ops Report           report     Draft      endpoints 1  schedules 1
  Renewal Dashboard           dashboard  Published  endpoints 3  schedules 1
```

Artifact detail should be organized around how people operate the bundle:

```text
Feedback Tracker

Tabs:
  Preview
  Files
  Endpoints
  Automations
  Runs / Logs
  Settings
```

The `Endpoints` tab owns request/response bindings for `backend/*.lua` scripts. The `Automations` tab owns schedules/manual background jobs that run the same scripts. This makes the backend feel integrated with automations without moving the source out of the artifact.

Opening a published artifact is a separate consumption route:

```text
/w/default/artifacts/feedback-tracker
```

Shared artifacts use share routes rather than workspace routes:

```text
/share/artifacts/oc_shr_...
```

Editing or inspecting its backend is in the automation builder area:

```text
/w/default/lab/artifacts/feedback-tracker/edit
```

Agent AX should feel like working in a small repo:

```text
create_artifact(name: "Feedback Tracker", slug: "feedback-tracker", type: "app")
change_set = begin_artifact_change_set("feedback-tracker", summary: "Build feedback submission flow")
stage_artifact_file(change_set, "artifact.json", manifest)
stage_artifact_file(change_set, "pages/index.liquid", liquid)
stage_artifact_file(change_set, "assets/styles.css", css)
stage_artifact_file(change_set, "backend/list-feedback.lua", lua)
stage_artifact_binding(change_set, "FEEDBACK_TABLE", table: "feedback_tracker")
preview_artifact_change_set(change_set)
apply_artifact_change_set(change_set)
describe_artifact_endpoint("feedback-tracker", "feedback.list")
run_artifact_endpoint("feedback-tracker", "feedback.list", { method: "GET" })
publish_artifact("feedback-tracker")
```

Once the Artifact VFS mount exists, inspection and explicitly authorized low-level edits can also use:

```text
vfs_exec("tree /artifacts/feedback-tracker")
vfs_exec("cat /artifacts/feedback-tracker/artifact.json")
vfs_patch("/artifacts/feedback-tracker/pages/index.liquid", ...)
vfs_write("/artifacts/feedback-tracker/backend/create-feedback.lua", ...)
```

The important AX rule: an artifact is a named workspace object with a folder tree. Agents should never have to remember an implementation storage path to find or modify it.

## Agent Workflow

User: "Build me a feedback tracker with a submission form and a table of all submissions."

The agent should:

1. Create a table called `feedback_tracker` with columns for title, category, status, submitter, and timestamps.
2. Create an Artifact draft at `/artifacts/feedback-tracker` and declare the portable `FEEDBACK_TABLE` binding requirement.
3. Resolve `FEEDBACK_TABLE` to the newly created table through a workspace-scoped Artifact binding; do not put its database ID or credentials in source.
4. Open one Artifact change set against the current draft revision.
5. Stage `artifact.json`, `pages/index.liquid`, `assets/styles.css`, shared JS, `backend/list-feedback.lua`, `backend/create-feedback.lua`, tests, and the binding resolution in that change set.
6. Present one manifest/file/binding/capability diff and apply the accepted operations atomically to the draft.
7. Read the generated endpoint catalog, then test the typed endpoint contracts and Artifact rendering through the canonical runner.
8. Publish the Artifact only when the preview, endpoints, bindings, schemas, tests, manifest, and any required capability approvals validate.

## Implementation Backlog

| Component | Effort | Details |
|---|---:|---|
| Optional automation trigger bindings | Small | If automation records are used for endpoint-like or scheduled artifact scripts, make `cron_expression` nullable or conditionally required and adjust model next-run behavior. |
| Artifact model + policy | Medium | Workspace-scoped metadata, type/status, owner, root folder, manifest path/cache, source automation, publish state. |
| Artifact file tree | Medium | Prefer `artifact_files`; alternatively hide internal `WorkspaceFile` folders behind `ArtifactService`. |
| Artifact versions | Medium | Immutable published snapshots, draft workflow, rollback, manifest/file diffs, and version-bound endpoint execution. |
| Artifact binding registry | Medium | Parse portable requirements, resolve workspace-local typed resources, expose opaque runtime handles, support revocation/rebinding, and audit every authority change without persisting credentials in source. |
| Artifact templates + installer | Medium | Version source/tests/assets and binding requirements, create a fresh Artifact draft, guide binding resolution, validate the install, and keep credentials/runtime data out of snapshots. |
| Artifact change sets | Medium | Base-revision/checksum conflict checks, multi-file/manifest/binding preview, full pre-validation, logically atomic draft apply, redacted audit records, and no proposal-time side effects. |
| Artifact runs | Medium | Unified run log for http, schedule, manual, preview, and test triggers with redaction and bounded payloads. |
| Artifact events | Small | Append-only audit trail for lifecycle, file, endpoint, capability, publish, rollback, approval, and schedule changes. |
| Artifact manifest parser | Medium | Validate `artifact.json`, entry page, pages, assets, endpoint scripts, side-effect declarations, schemas, binding requirements, permissions, and test fixtures. |
| Liquid template renderer | Medium | Add a restricted Liquid renderer wrapper with approved tags, filters, layouts, partials, URL helpers, escaping rules, caching, and actionable render errors. |
| Artifact SPA runtime | Medium | Ship a no-build frontend runtime for link interception, page swaps, history, endpoint helpers, loading/error states, and page initializers. |
| Library preset registry | Small | Maintain approved no-build libraries with local/CDN source, integrity, allowed origins, CSP policy, purpose, and approval requirements for custom URLs. |
| Artifact endpoint router | Medium | Resolve Artifact + manifest endpoint + Lua script + typed bindings, then execute every HTTP, automation, test, and agent call through one request/artifact context. |
| Agent endpoint catalog | Small | Generate a permission-filtered, schema-backed virtual descriptor from the validated manifest; expose VFS list/describe reads without registering one permanent tool per endpoint. |
| Security gate evaluator | Medium | Intersect actor, agent, Artifact, manifest, concrete binding, integration, workspace, and tool permissions before reads/writes. |
| Publishing gates | Medium | Validate manifest/files/scripts/tests/bindings/catalog/capabilities, generate an immutable version, and require approval for high-risk changes. |
| Approval integration | Medium | Reuse approval flow for capability or binding expansion, write endpoints, scheduled writes, public sharing, and integration mutations; keep change-set acceptance distinct from publish approval. |
| Optional automation bindings | Medium | Let scheduled/manual automations point to artifact backend scripts without moving source out of the artifact bundle. |
| Lab page + route aliases | Medium | Evolve `/automation` into `/lab` with Automations, Artifacts, and Runs/Logs tabs; keep `/automation` as an alias during migration. |
| Artifact open routes | Small | Add standalone `/artifacts/{slug}` viewer routes for published/previewable artifacts. |
| Artifact share links | Medium | Signed/password-gated share routes, access policies, expiry/revocation, share audit events, and no workspace session requirement for allowed viewers. |
| Custom artifact domains | Large | Paid future feature outside v1: DNS verification, TLS, hostname routing, domain access policy, billing checks, and domain audit events. |
| Git integration | Large | Future artifact source sync: export/import bundles, commit/tag published versions, optional PR flow, conflict detection, and approval-aware capability diffs. |
| Stripe/payment artifacts | Large | Future commerce capability: Checkout/Billing endpoints, verified webhooks, entitlement tables, agentic payment handlers, idempotency, and payment-specific approvals/logging. |
| Request context in Lua | Small | Add `ctx.request.body`, `query`, `method`, `headers`, `user`, `workspace`, and endpoint metadata. |
| Artifact viewer routes/UI | Medium | List, show, preview, archive, refresh, edit source files where permitted. |
| Artifact sandbox/proxy | Medium | Iframe CSP, endpoint allowlist, signed asset/proxy URLs, no broad app API access. |
| Asset runtime/CSP | Medium | Serve approved local runtime assets, apply strict iframe CSP, and block arbitrary external origins in v1. |
| Rate limits and budgets | Small | Per-user/workspace rates, Lua execution limits, tool-call budgets, response/log byte caps, and overlap behavior. |
| Agent tool: `create_artifact` | Medium | Creates artifact record, root folder, manifest, and initial file tree. |
| Agent operations: Artifact change sets | Medium | Begin, stage file/manifest/binding operations, preview, accept/reject, rebase after conflict, and apply to one draft. The default agent edit path should use these operations. |
| Agent tool: `write_artifact_file` | Small | Low-level create/update inside an Artifact draft with path and expected-revision validation; use change sets for multi-file agent work. |
| Agent tools: endpoint discovery | Small | List/describe the current principal's callable Artifact endpoints from the generated catalog. |
| Agent tool: `run_artifact_endpoint` | Small | Smoke-test a backend endpoint with request fixtures. |
| Agent tool: `publish_artifact` | Small | Validate manifest, pages, endpoints, and status transition. |
| Lua docs | Small | Extend `lua_read_doc()` docs for endpoint request context and artifact/VFS patterns. |
| VFS integration | Medium | Extend the implemented `app/Domain/Vfs` with top-level `/artifacts`, bundle file mutation events, change-set-aware revision checks, and a read-only generated endpoint catalog. |
| Tests | Medium | Feature tests for endpoint auth/method/schema/catalog, binding isolation/revocation, template sanitization/install, change-set conflict/atomicity, Artifact viewer, workspace isolation, and iframe/proxy security. |

## Design Principles

- **Artifact-first**: dashboard pages are one renderable artifact type.
- **Folder-first**: an artifact is a bundle folder with manifest, pages, assets, backend scripts, data, and tests.
- **Lab UX**: Artifact management lives in Lab with automations because endpoints, schedules, runs, and failures are shared runtime concerns.
- **Direct open URLs**: Published artifacts get clean `/artifacts/{slug}` routes for viewing/sharing inside the workspace.
- **First-class VFS home**: Artifacts live under VFS `/artifacts`, not under Files.
- **Reuse current domains**: Lua executes scripts, tables store rows, documents store docs, file storage may store bytes, VFS projects them.
- **No runtime fork**: endpoint scripts use the same Lua sandbox and tool bridge as scheduled script automations.
- **One runner, several entry points**: HTTP, automations, tests, previews, and agent calls invoke one endpoint runner; the generated catalog only describes it.
- **Workspace-safe**: every lookup uses `forWorkspace()`, workspace-bound routes, or the future VFS authorizer.
- **Bind resources, never credentials**: portable source names required resources while workspace-local typed bindings resolve them and can only reduce authority.
- **Portable reuse**: Artifact templates copy versioned source and binding requirements, never another workspace's data, runtime state, or authority.
- **Atomic draft proposals**: multi-file agent changes are reviewed and conflict-checked as one change set before the mutable draft changes.
- **Inspectable**: artifacts should have source files, manifests, endpoint bindings, and run logs agents can inspect.
- **Patchable later**: structure artifacts so future `vfs_patch` and `vfs_write` can update them safely with version checks.
- **Bounded output**: endpoint and artifact generation must enforce response size, execution time, rate limits, and cache policy.
- **Least privilege**: an artifact can call only the endpoints explicitly bound in its manifest.

## Open Questions

- Should endpoint calls create a `Task` record every time, only on failure, or only when invoked manually from the UI?
- Which concrete binding kinds belong in v1 beyond tables, documents, Artifact-owned files, channels, and named integration resources?
- Should every agent-authored Artifact mutation require a change set, or may low-risk single-file edits use expected-revision writes under workspace policy?
- Should the generated endpoint catalog describe draft endpoints to editors, published endpoints to runners, or both through separate version-qualified views?
- Which safe seed-data formats may ship in a template, and must suggested resource creation always be a separate accepted proposal?
- Should `/automation` remain a permanent alias after Lab ships, or only exist during a route migration window?
- Which libraries should ship as approved no-build presets in v1, and which custom external URLs require approval?
- Should v1 use dedicated `artifact_files`, or hidden `WorkspaceFile` folders behind `ArtifactService`?
- Should implementation-backed files appear in Files as read-only linked system files, or be completely hidden from Files?
- Should public/shareable artifacts use signed links first, with fully public artifacts delayed?
- Should password-gated shares require a separate password per share link, per artifact, or per viewer identity?
- Should custom domains attach to a single artifact, a workspace artifact portal, or both?
- Should Git integration start as export-only, import-only, or manual import/export before any two-way sync?
- Should each artifact map to its own repository, or should a workspace have one artifacts repository with per-artifact folders?
- Should payment-capable artifacts use generic workspace tables for entitlements/orders first, or first-class OpenCompany billing/order models?
- Should Stripe webhooks route directly to artifact handlers after app verification, or should app-owned billing services update state and let artifacts read that state?
- Should generated reports be artifact bundles that optionally publish a `Document`, or direct `Document` records with linked artifact source?
- Should endpoint automations support streaming responses, or keep v1 strictly JSON?

## Future Considerations

- Visual artifact editor for non-code users.
- WebSocket or event-stream refresh for live dashboard artifacts.
- Public/shareable artifacts with explicit publish controls.
- Paid custom domains for published artifacts after v1 security hardening.
- Git import/export and PR-backed artifact review workflows.
- Stripe Checkout/Billing and agentic-commerce artifacts.
- Artifact version history and rollback.
- Cross-workspace Artifact marketplace with provenance, security review, publisher trust, licensing, and update policy. Built-in and workspace-private templates come first.
- QuickJS only if Luau plus Liquid/HTML artifacts are insufficient; do not add a second script runtime casually.
