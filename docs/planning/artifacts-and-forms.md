# Artifacts and Forms

Status: **Accepted** (owner, 2026-11-02). Nothing here is implemented. Binding summary: [Product Direction](../PRODUCT_DIRECTION.md) pillar 7 and the phase table.
Verified: existing plans and code read on 2026-11-02; external landscape researched on the web on
2026-11-02 (sources inline; secondary sources marked **[2nd]**).
Direction: [Product Direction](../PRODUCT_DIRECTION.md). This document proposes amending pillars 4 and 7
and the phase table. Artifacts would move from "after Phase 4" to a core primitive delivered from
Phase 1.
Builds on and supersedes the runtime parts of
[Automation endpoints and artifacts](automation-endpoints-artifacts.md) and the accepted Artifact
decisions in the
[Cloudflare OS investigation](../architecture/cloudflare-os-opencompany-investigation-2026-08-09.md).

## Why artifacts matter for OpenCompany

Every lab now ships "agent builds a thing":

- Claude artifacts and Claude Code artifacts;
- ChatGPT Work and Sites;
- Gemini Canvas and Sheets canvas;
- Airtable Canvas;
- Notion interactive HTML blocks;
- Microsoft 365 App Builder.

What none of them combine is what OpenCompany already has:

- live, governed company data in core primitives;
- connections;
- mruby Code Mode for safe server logic;
- routines and events;
- agents with their own computers;
- approvals and audit;
- self-hosting under AGPL.

Artifacts are where those pieces become visible output. The strongest products bind artifacts to the
system of record (Airtable Canvas, Sheets canvas) instead of creating copies.

Sources:
- [Airtable apps on bases](https://www.airtable.com/articles/apps-dashboards-and-decks), 2026-08-12
- [Sheets canvas](https://blog.google/products-and-platforms/products/workspace/sheets-canvas-for-google-sheets-spreadsheets/), 2026-08-13
- [Claude artifacts](https://support.claude.com/en/articles/9487310-what-are-artifacts-and-how-do-i-use-them), accessed 2026-11-02

## Current state (code)

- **No Artifact domain exists.** There is no model, route, viewer or `/artifacts` VFS mount.
- **Renderers are package-owned and unscoped.** Mermaid, PlantUML, Typst and Vega-Lite render
  through integration packages that are slated for deletion. `RenderSvg` writes PNGs to the public
  disk with no workspace scope. Cleanup is incomplete: only `svg/` and an unused `charts/` folder are
  pruned. Attachments break after 7 days.
- **mruby cannot serve HTTP.** There is no `ctx.request`, no synchronous endpoint profile and no
  viewer principal. The Code Mode bridge (`CodeBridge`, `CodeApiDocGenerator`) depends on
  integration-core, which is being deleted, so it must be re-homed in the app.
- **No public sharing.** No share tokens exist beyond the ICS calendar feed. The Telegram Mini App's
  typed panels are a useful precedent for rendering typed views.
- **No forms.** There is no form view, public submission path or "ask a human for input" tool.

## Decision summary (proposed)

1. **Artifact becomes the ninth core primitive.** It is the only code-bearing primitive, and every
   Space gets a sixth tool, **Artifacts**.
2. **Two artifact kinds, one model:**
   - **Pages:** declarative reports and dashboards built from blocks.
   - **Interactive artifacts:** custom HTML UI plus optional mruby endpoints.
3. **Artifacts never own storage.** Data lives in Tables and other primitives through bindings. An
   artifact that needs state gets an artifact-owned Table, which is visible and governed.
4. **One manifest family.** An app manifest is a package of record types, views, routines, an agent
   pack and artifacts. Interactive artifacts are how apps get custom views, so an artifact can
   graduate into an app.
5. **Forms: both, through one pipeline.**
   - **Form** is a first-class view type over any Table, Tally-style.
   - Artifacts may render custom form UIs, but must submit through the same form submission
     endpoint.
   - Conversational intake is a render mode of the same form.
6. **Runtime:**
   - Interactive UI runs in a sandboxed iframe on a separate user-content origin and speaks the
     **MCP Apps bridge** (`ui/*` over postMessage).
   - Server logic is **mruby endpoints**.
   - Agents with a computer may build with real frameworks and publish the **static build output**.
   - No generated JavaScript ever runs on the server.
7. **Private by default.** Publishing outside the workspace is `exposure_widening`, so it always
   needs approval. Public publishing is off per workspace until an admin enables it.

## Artifact model

### Kinds

| Kind | What it is | Code | Typical examples |
|---|---|---|---|
| **Page** (report or dashboard) | An ordered set of blocks: text, live view embeds (table, board, chart, KPI, timeline), query results, diagrams, images. Live, or snapshotted on a schedule | None | Weekly sales report, ops dashboard, board update, project status page |
| **Interactive** | An HTML UI (single file, or a static bundle from an agent's computer) plus optional mruby endpoints and bindings | HTML/JS in the sandbox; mruby on the server | Quote calculator, approval portal, inventory scanner, client portal, internal tool, custom form UI |

Pages cover most "agent made me a report" needs with no code at all. That makes them safe, live,
cheap, and resistant to the sprawl the
[Retool 2026 report](https://retool.com/blog/ai-build-vs-buy-report-2026) documents. Interactive
artifacts are the escape hatch.

### Built-in block and renderer types

These re-home today's package renderers as first-class, workspace-scoped features:

| Block | Implementation |
|---|---|
| Chart | Vega-Lite, rendered client-side |
| Diagram | Mermaid, rendered client-side |
| Document export | Typst to PDF on the server, stored in Files with permissions |
| Others | Markdown, KPI, table, board, timeline, query result, image, file |

The public-disk PNG output and the `charts/` and `svg/` cleanup jobs are deleted. Outputs become
Files.

### Manifest (one family with apps)

An artifact manifest declares:

- **Identity:** slug, kind, title, owner (a human sponsor plus the authoring agent) and Space.
- **Bindings:** named requirements, for example `LEADS: table, read+create`. They resolve to concrete
  workspace resources at install or creation time. Requested operations are a ceiling, not a grant.
  This keeps the accepted decision from the Cloudflare investigation.
- **Endpoints:**

  | Field | Values |
  |---|---|
  | Script | An mruby script |
  | Method | HTTP method |
  | Auth | `viewer` (workspace member), `share` (signed link) or `public` |
  | Side effect | `read`, `internal_write` or `external` |
  | Schemas | Request and response JSON schema |
  | Limits | Rate limit, cache TTL |

- **UI:** a `ui/` entry (HTML) and its CSP needs: connect, resource and frame domains, default deny.
- **Runtime AI:** allowed or not, with a per-artifact quota.
- **Publishing:** the allowed publishing levels.

The accepted **endpoint catalog** (typed and permission-filtered; `list_`, `describe_` and
`run_artifact_endpoint`; no per-endpoint tools) and **artifact templates** (copy source, tests,
assets and requirements, never authority, data or history) are kept from the existing plan.

### Storage, versions and lifecycle

- **Storage.** Bundles are files in the single Files store, under an artifact-owned system area that
  is exposed in the VFS at `/artifacts/<slug>/`. Agents edit them like a small repository.
- **Change sets.** Edits go through **platform change sets**, which replace the separate
  `artifact_change_sets` in the old plan. Drafts are mutable. Publishing creates an **immutable
  version** at a **stable URL**. Restoring a version creates a new version.
- **Runs and events.** Endpoint calls and refreshes are runs, and every call is an event in the event
  log. This replaces the old `artifact_runs` and `artifact_events` tables.
- **Lifecycle:**
  - Every artifact has an owner, a status (draft, current or archived), a last-viewed date and a
    last-data-touched date.
  - A stale sweep routine proposes archiving artifacts nobody uses.
  - Archiving removes the artifact from search and agent context and keeps its URL as a tombstone.
- **Remix.** Allowed within the workspace only. Remixing copies the source, never data or authority.
  Public remixing helped spread phishing kits on Lovable
  ([Proofpoint](https://www.proofpoint.com/us/blog/threat-insight/cybercriminals-abuse-ai-website-creation-app-phishing),
  2025-08-20).

## Runtime and security

### UI sandbox

- **Separate origin.** Artifacts render in an iframe on a dedicated user-content origin, ideally one
  subdomain per artifact (`{hash}.{usercontent-domain}`), through a sandbox proxy on a different
  origin from the app. That origin never carries session cookies. Google recommends this pattern
  ([web.dev](https://web.dev/articles/securely-hosting-user-data)), and the MCP Apps specification
  requires it for web hosts
  ([MCP Apps spec 2026-01-26](https://github.com/modelcontextprotocol/ext-apps/blob/main/specification/2026-01-26/apps.mdx)).
- **Self-hosters** configure `ARTIFACT_ORIGIN`. If they can't provide a second hostname, OpenCompany
  falls back to a `CSP: sandbox` header with an opaque-origin iframe, and documents the reduced
  isolation in Admin.
- **Default-deny CSP** comes from the manifest, plus `object-src 'none'` and no nested frames.
  Approved library presets (charts, maps, grid) are served from the user-content origin.
- **Bridge.** The MCP Apps `ui/*` bridge (`tools/call`, `ui/message`, `ui/update-model-context`).
  Only tools marked **app-visible** are callable. That set is the artifact's bound workspace
  operations, its endpoints, and the host AI bridge.
- **Untrusted messages.** Anything an artifact sends toward an agent (`ui/message`,
  `ui/update-model-context`) is tagged as untrusted content.

### Identity

| Viewer | Effective access |
|---|---|
| **Workspace member** | The artifact's bindings ∩ the viewer's own access (on-behalf-of, pillar 5). Mutations are recorded as "artifact X on behalf of Y" |
| **Share-link visitor** | Only endpoints and **published queries** marked share-safe. A published query is a field-allowlisted, read-only projection that the owner approves |
| **Public visitor** | An anonymous principal. It can use only public endpoints, published queries and form submission, all rate-limited. No AI and no connections unless an admin explicitly allows them for that artifact. Claude blocks public links for artifacts that use AI or connections ([Claude publishing](https://support.claude.com/en/articles/9547008-publish-and-share-artifacts)) |

### Server logic (mruby endpoints)

- **Endpoint profile.** A new `endpoint` mruby profile: short wall clock (about 5–10 s),
  `ctx.request`, binding handles, a viewer principal, and a synchronous runner that still produces a
  run and an event.
- **Long work** is queued as a routine or event and runs asynchronously.
- **Writes** go through change sets. Reversible internal writes auto-apply per the governance
  preset. External actions follow normal approvals. Endpoints whose side effects need approval cannot
  run unattended.
- **Re-homing.** `CodeBridge` and the catalog builder move from integration-core into the app before
  this ships.

### AI at runtime

The host AI bridge (`ai.complete`, `ai.extract`) is billed to the workspace budget with a quota per
artifact. It is off for public visitors by default.

### Built on a computer

Agents with a computer may use real frameworks (Vite, React, Svelte) and publish only the **static
build output** as artifact assets. Server logic stays in mruby endpoints. A long-running app server
on the agent's computer is a "preview" or development mode only, never the publishing path.
Cloudflare Dynamic Workers or edge isolates could later be an optional Cloud adapter, never a
self-host requirement.

## Publishing and governance

| Level | Who sees it | Gate (all presets unless noted) |
|---|---|---|
| Draft | Owner and authoring agent | None (reversible) |
| Space | Space members | Auto in Personal and Team (internal, reversible); per Space policy in Company |
| Workspace | All members | Same as Space |
| Signed link | Anyone holding the link (password and expiry optional) | `exposure_widening`: Personal asks, Team needs an admin, Company needs an admin plus a second approver |
| Public | Anyone; can be listed or unlisted | Same as signed link, **and** the workspace must have public publishing enabled (off by default) |

- **Escalations.** New write endpoints, new bindings or wider operations, new egress domains, enabling
  AI or connections for visitors, and custom domains are all approval-gated `exposure_widening`.
- **Unpublish keeps the data.** Republishing is allowed.
- **Published surfaces registry.** Admin gets one list of every artifact and form reachable from
  outside the workspace. It shows owner, level, last change, traffic and kill switch, and supports
  per-workspace and instance-level takedown.
- **Abuse controls for public surfaces:**
  - rate limits and captcha;
  - a "report" link;
  - password-type fields and brand-impersonation patterns blocked or approval-gated;
  - a pre-publish scan;
  - an instance-admin kill switch.

  Public-by-default publishing caused the large leaks found on vibe-coding platforms
  ([Axios](https://axios.com/2026/05/07/loveable-replit-vibe-coding-privacy), 2026-05-07, **[2nd]**).

## Forms

### The answer: both, through one pipeline

- **Form is a first-class view type** over any Table or app record type. Each submission is a
  record. That record is a change set by the `form visitor` principal and emits a `form.submitted`
  event. Routines, agents, approvals, the VFS and search then work with no extra code. This is the
  Notion and Airtable model, and it fits the rule that apps never own storage.
- **Artifacts can render fully custom form UIs**, but they must post to the same **form submission
  endpoint** (by form ID). Validation, spam protection, consent, retention, upload quarantine and
  audit therefore live in one place.
- **Not a separate app.** Every Table can have forms, and add-on apps use them too (a CRM lead form,
  a Recruiting application form, a Helpdesk ticket form). Forms appear in a Table's views and in the
  Space's Artifacts list, so everything published lives in one place.

### Form definition (JSON, agent-authorable)

- **Fields:** mapped to typed record fields. Text, long text, number, email, phone, URL, date, select,
  multi-select, checkbox, rating, file upload, signature, person, and relation via a lookup.
- **Structure and logic:** pages and sections; conditional show/hide and jumps; calculations.
- **Prefill and context:** hidden and prefilled fields from URL parameters, for example a campaign
  source or a record ID for update forms.
- **Validation and consent:** required fields, patterns and min/max; consent blocks with stored
  consent text and version.
- **Submission behavior:** partial-save policy; thank-you message, redirect, or "show the created
  record to the submitter".
- **Retention:** a retention and auto-delete policy.
- **Later:** payments.

Agents generate forms on request ("make an intake form for Leads with budget and timeline") as a
**plan, then preview, then approve** proposal, the same pattern AppSheet and Airtable Canvas use.
Humans edit forms in a builder in the new client.

### Render modes (same definition)

1. **Page:** Tally-style.
2. **One question at a time:** Typeform-style.
3. **Conversational intake:** a public chat agent whose only tool is "submit to this form" (and read
   published FAQs), the pattern of
   [Jotform AI Agents](https://www.jotform.com/ai/agents/features). Its output is still validated by
   the form schema.
4. **Embed:** an iframe or JS popup on a customer's site.
5. **Internal:** members fill a form in the app or in Telegram (Mini App). This replaces ad-hoc
   request flows, for example "request a purchase" creates an Issue plus an approval.

### Submission pipeline

1. **Receive:** the public origin, which never carries auth cookies.
2. **Spam check:** captcha (ALTCHA by default, which is MIT-licensed, self-hosted and needs no
   third-party processor; Turnstile, hCaptcha and reCAPTCHA are pluggable), a honeypot, and
   per-IP and per-form throttles.
3. **Validate** against the schema.
4. **Quarantine uploads:** size caps, MIME sniffing, files served only as attachments, and an
   optional malware scan hook.
5. **Create the record** through a change set by the form-visitor principal, plus the
   `form.submitted` event.
6. **Untrusted by design:** the payload is tagged untrusted, so routines it triggers start without
   automatic approval for external actions.
7. **Notify and respond:** notifications, an optional confirmation email through Mail, and the
   thank-you or redirect.

**Data rights.** Submitters' data supports export and erasure, and retention jobs auto-delete it.
Self-hosting gives data residency out of the box, which is an advantage over hosted form tools.

### Prior art to borrow from, not fork

- [OpnForm](https://github.com/OpnForm/opnform) (Laravel plus Nuxt, AGPL-3.0-or-later core; exclude
  the proprietary `api/app/Enterprise/`) for field and logic semantics and AI generation ideas.
- The [SurveyJS](https://surveyjs.io/Licenses) MIT form library as a possible renderer reference.
  Its Creator is commercial; avoid it.
- **Typebot** is FSL-licensed and must not be copied.
- [Tally](https://tally.so/pricing) is the UX bar: unlimited free forms, logic, payments, hidden
  fields, partial submissions, custom domains, EU hosting.

## Where artifacts show up in the UI

- **Sidebar.** Each Space's tools become **Issues, Tables, Docs, Files, Artifacts and Routines**.
  The Artifacts tool lists pages, interactive artifacts and the Space's forms.
- **Chat and context pane.** When an agent builds an artifact, the chat shows an artifact card. The
  artifact opens in the **context pane** with a live preview that refreshes as the agent edits the
  draft (split screen). "Publish" is a governed action on the card.
- **Make view.** Each person sees a list of finished outputs from their agents, separate from
  chat scrollback, following Claude Duet's Make tab
  ([Duet](https://support.claude.com/en/articles/17631495)).
- **Admin.** The published surfaces registry.

## Portability: MCP Apps

The MCP Apps standard (stable since 2026-01-26) is supported by Claude, ChatGPT, VS Code,
Microsoft 365 Copilot and Goose
([overview](https://modelcontextprotocol.io/extensions/apps/overview.md),
[ChatGPT](https://developers.openai.com/apps-sdk/mcp-apps-in-chatgpt)). It provides the bridge and
packaging format, but no persistence, publishing, versioning or anonymous access. OpenCompany adds
those.

Later (Phase 5), OpenCompany's MCP server exposes interactive artifacts as `ui://` resources. A
workspace's artifacts then render inside Claude or ChatGPT, using the user's own OpenCompany access.

## What changes from the old plan

| Old plan ([automation-endpoints-artifacts.md](automation-endpoints-artifacts.md)) | Now |
|---|---|
| Liquid server templates plus a no-build SPA runtime | Pages (declarative blocks) plus HTML on the MCP Apps bridge; static bundles from computers |
| Lua backend scripts | mruby endpoints (new `endpoint` profile) |
| `artifact_change_sets` | Platform change sets |
| `artifact_runs` and `artifact_events` | Runs and the workspace event log |
| Endpoint automations and the Lab "Automations" tab | Routines and Triggers; endpoints may enqueue routines |
| Lab section and Inertia viewer routes | The Space Artifacts tool, the context pane and Make, in the new client |
| `integration_resource` bindings | Connections with scoped grants and on-behalf-of access |
| Write endpoints disabled for shared viewers in v1 | Share and public visitors get share-safe endpoints, published queries and form submission only |
| Dedicated artifact storage outside Files | The single Files store, artifact-owned area, VFS `/artifacts` |
| `form` as an artifact type | Form is a view type over Tables; artifacts may render custom form UI through the same pipeline |

**Kept:** binding requirements, templates, the endpoint catalog, one endpoint runner, immutable
versions and publishing gates, iframe isolation, signed and password shares, and the later items
(custom domains, Git export, payments).

## Phasing (proposed amendment)

| Phase | Artifacts and forms work |
|---|---|
| 0 | User-content origin and sandbox proxy design; move `CodeBridge` into the app; move renderer outputs to Files with permissions |
| 1 · Messenger | Pages (blocks, live views, charts, diagrams, snapshots); interactive artifacts at private, Space and workspace level with read bindings; artifact cards and live preview in the context pane; versions and stable URLs; `/artifacts` VFS mount |
| 2 · Computer | mruby `endpoint` profile and write bindings through change sets; AI bridge with quotas; static bundles built on agents' computers; **Forms v1** (form view, page mode, internal and public submission, captcha, uploads, `form.submitted` events) |
| 3 · Governance | Signed and public publishing with published queries; published surfaces registry, abuse controls and kill switch; lifecycle sweeps; **Forms v2** (logic, partial saves, one-question mode, conversational intake, embeds, consent and retention) |
| 4 · Work apps | Artifacts as custom views in app manifests; artifact-to-app packaging; templates |
| 5 · Cloud | Custom domains, MCP Apps export to Claude and ChatGPT, Git export, payments in forms and artifacts, optional edge-isolate adapter for Cloud |

## Open questions

- The default user-content domain scheme for Cloud, and documentation for self-hosters.
- Whether page snapshots are stored as Files (PDF or HTML) or as frozen query results.
- Payment provider for forms and artifacts (Stripe first?), and how it fits the `financial` action
  class.
- Custom-domain verification and TLS automation for self-hosters.
