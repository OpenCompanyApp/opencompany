# Integration Strategy: MCP as the Bus, Integrations as Data

Status: **Research input, accepted and superseded** (2026-11-02). The owner accepted a broader version
of this recommendation. The binding design is [Connections, Events and Routines](connections-events-routines.md),
which adds deep connectors (Tier 1) and deletes the package system outright instead of migrating it.
Nothing here is implemented.
Verified: protocol landscape researched on 2026-11-02; current code read on 2026-11-02 from the
`feat/vfs` working tree and `../integrations-mruby`.
Direction: [Product Direction](../PRODUCT_DIRECTION.md). Related: [Agent Primitives Catalog](agent-primitives-catalog.md).

## Problem

OpenCompany integrations today are hand-built PHP packages. There are about 600 of them, with a
median of about 1.3k lines each and about 2.5M lines in total.

- **Loading and enablement:** every package loads for every workspace. Credentials exist per
  workspace only; there are none per user. Agents reach integrations only through mruby Code Mode.
- **Catalog:** the tool catalog is built by regex-parsing PHP. As a result, 24,339 of 41,493 tools
  show agents no parameters (`build-catalog.php`, `ToolRegistry.php:558-567`).
- **OAuth:** only Google and TickTick have browser OAuth, implemented as package-owned controllers
  coupled to `App\Models`. Each workspace must register its own OAuth app.
- **MCP client** (`app/Services/Mcp/McpClient.php`):
  - speaks protocol `2025-03-26` over Streamable HTTP only, with static bearer or header auth and no
    OAuth;
  - hardcodes every tool as `write`;
  - registers servers globally at boot, so slugs collide across workspaces.
- **Skills:** none.

Adding integrations one package at a time cannot keep up with the ecosystem. Since 2025 the ecosystem
has standardized on MCP, Agent Skills and plugin bundles.

## Landscape (sources checked 2026-11-02)

- **MCP 2026-07-28 is final.** It has:
  - a stateless core (no `initialize` handshake, `server/discover`);
  - OAuth 2.1 with Protected Resource Metadata, PKCE and RFC 8707 `resource`;
  - CIMD preferred over DCR (DCR deprecated);
  - URL-mode elicitation for credentials and payments;
  - extensions: MCP Apps (stable), Tasks (beta), Skills over MCP (beta).

  Sources: [spec](https://modelcontextprotocol.io/specification/latest),
  [changelog](https://modelcontextprotocol.io/specification/2026-07-28/changelog),
  [authorization](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization),
  [extensions](https://modelcontextprotocol.io/extensions/overview).
- **Official MCP Registry** (preview, metadata only): about 43k active servers, of which 64% list a
  remote URL and 96% of those use Streamable HTTP
  ([LocalCan census, 2026-10-15](https://www.localcan.com/blog/state-of-mcp-server-development)).
- **First-party remote MCP servers** exist for GitHub, Linear, Notion, Atlassian, Slack, Stripe,
  Google Workspace (preview) and others.
  - Slack and Google require a pre-registered OAuth client, so admins must be able to enter their own
    client ID and secret ([Slack](https://docs.slack.dev/ai/mcp-server/),
    [Linear](https://linear.app/docs/mcp), [Stripe](https://docs.stripe.com/mcp)).
- **`laravel/mcp` 1.x** ships a server and a client. The client supports stdio and Streamable HTTP,
  OAuth (PKCE, refresh, client credentials) and the 2026-07-28 protocol, and `laravel/ai` agents can
  consume its tools ([releases](https://github.com/laravel/mcp/releases),
  [Laravel blog](https://laravel.com/blog/laravel-ai-agents-now-support-mcp-servers)).
  - The official `mcp/sdk` PHP SDK is pre-1.0 ([repo](https://github.com/modelcontextprotocol/php-sdk)).
- **Agent Plugins 1.0 (2026-08-06):**
  - It is a cross-vendor bundle: root `plugin.json` plus `skills/` plus `mcp.json`. It covers only
    skills and MCP.
  - Clients: ChatGPT/Codex, Cursor, Copilot, Kiro, VS Code
    ([Vercel](https://vercel.com/blog/introducing-agent-plugins), [spec](https://agent-plugins.org/)).
  - Claude Code uses `.claude-plugin/plugin.json` and `marketplace.json`
    ([docs](https://code.claude.com/docs/en/plugin-marketplaces)), and Codex reads both formats
    ([OpenAI](https://developers.openai.com/codex/plugins/build)).
  - **The portable core is SKILL.md plus MCP config.** Hooks, commands and `bin/` are not portable
    and run code on the host.
- **Agent Skills (SKILL.md)** is an AAIF project with about 40 clients ([agentskills.io](https://agentskills.io/)).
  - It has a serious supply-chain problem. 13.4% of 3,984 scanned skills had critical issues
    ([Snyk](https://snyk.io/blog/toxicskills-malicious-ai-agent-skills-clawhub/)), and malicious
    skills evaded VirusTotal and ClawScan
    ([Unit 42](https://unit42.paloaltonetworks.com/openclaw-ai-supply-chain-risk/)).
- **Managed long-tail platforms:**

  | Platform | Coverage | License and hosting | Source |
  |---|---|---|---|
  | Activepieces | about 770 apps over MCP | MIT community edition, fully self-hostable | [repo](https://github.com/activepieces/activepieces) |
  | Nango | 1,000+ APIs | Elastic License, run as a separate service only | [repo](https://github.com/NangoHQ/nango) |
  | Composio | 1,500+ toolkits | Hosted | [pricing](https://composio.dev/pricing) |
  | Pipedream Connect | 3,000+ APIs | Hosted, Workday-owned, per-end-user `external_user_id` | [docs](https://pipedream.com/docs/connect) |
  | Zapier MCP | 9,000+ apps | Hosted | [page](https://zapier.com/mcp) |

  The market is consolidating, and Smithery shut down its hosted services on 2026-10-31
  ([notice](https://smithery.ai/future)).
- **Not reusable:** n8n nodes are under the Sustainable Use License
  ([n8n](https://docs.n8n.io/faircode-license)).
- **OpenAPI-to-tools:** one tool per endpoint explodes the context (Zoom: 183 tools and about 89k
  tokens). Search-plus-execute meta-tools need about 400 tokens
  ([Scalar](https://scalar.com/learn/mcp/generate-mcp-server-from-openapi)).
- **Other protocols:** A2A v1.0 (Linux Foundation, no PHP SDK) is for delegating to external agents,
  not for tools. ACP and AG-UI are not relevant to integrations.

## Recommendation

**Principle: MCP is the universal integration bus. Integrations become data, not PHP packages.
Governance (credentials, approvals, audit) stays in OpenCompany.**

### 1. Rebuild the MCP client properly (highest coverage per effort)

- **Library:** build on `laravel/mcp`'s client, or `mcp/sdk`. Support 2026-07-28 with fallback to
  2025-11-25 and 2025-03-26.
- **OAuth 2.1:** PRM discovery, RFC 8707 `resource`, PKCE, `iss` check and step-up scopes.
  Client registration order:
  1. CIMD, with each instance publishing its own client metadata document;
  2. DCR;
  3. admin-entered pre-registered client credentials (required for Slack and Google).
- **Connections:** support both per-user and shared (service account) connections, stored through the
  vault and credential broker from the primitives catalog. Today only per-workspace credentials exist.
- **Per-workspace registration:** register servers at request or job time. This replaces the global
  boot-time registration that lets slugs collide.
- **Approvals:**
  - Treat `readOnlyHint` and `destructiveHint` as hints only. Writes stay approval-gated by default.
  - Hash-pin tool definitions and re-approve when they change (rug-pull defense).
- **Elicitation:** map URL-mode elicitation and `input_required` onto approval cards and secure forms.
- **Safety and catalog:** apply `WebRequestGuard` (SSRF), redirect limits and size limits. Use an admin
  catalog sourced from the MCP Registry (remote servers) plus a curated list of first-party vendor
  servers.
- **stdio servers:** run them only on an agent's computer or a sidecar, never in the web process.

### 2. Skills and plugin bundles

- **Format:** support SKILL.md through a `/skills` VFS mount with progressive disclosure, reusing the
  `code_list_docs`/`code_read_doc` path. Skill scripts run only through mruby `code_exec` (or on the
  agent's computer), so permissions and approvals apply.
- **Bundles:** import Agent Plugins 1.0 `plugin.json` and `.claude-plugin/plugin.json` /
  `marketplace.json`, taking **only skills and MCP config**. Drop hooks, commands, `bin/` and
  monitors. This picks up vendor plugins such as Stripe and every ChatGPT/Codex directory plugin,
  because those must use remote MCP.
- **Security:**
  - approvals bound to the skill's digest;
  - no automatic install from public registries; curated or allowlisted sources only;
  - scanning before approval;
  - `userConfig` secrets routed to the vault.

### 3. Convert native packages to data

- **Freeze new hand-written packages** except where quality truly matters.
- **One generic `OperationToolProvider`:** generalize the existing generated `*Operations.php`
  pattern (about 43 packages) into one provider in integration core. It reads an OpenAPI-subset
  manifest with a fixed auth-strategy enum (replacing about 55 free-text values) and infers
  read/write from the HTTP method, with overrides. Agents see search-plus-execute meta-tools, not one
  tool per endpoint.
- **Catalog:** build it from loaded definitions as JSON Schema, not regex scraping.
- **Existing packages:**
  - Keep the best ~30–50 hand-written packages as curated, tested first-party integrations.
  - Migrate the rest to manifests over time.
  - Where an official vendor MCP server exists, prefer it and retire the package.
- **Generic OAuth:** a shared OAuth broker in the app replaces package-owned controllers.

### 4. Optional long-tail providers, connected as MCP servers

- **Self-host recommendation:** Activepieces (MIT, self-hosted, about 770 apps) as an optional
  sidecar in docker-compose.
- **User-supplied keys:** Composio, Pipedream Connect, Nango (run separately) and Zapier MCP as plain
  MCP endpoints with the user's own key.
- **Never a hard dependency**, because of vendor churn, data leaving the self-hosted boundary, and
  per-call costs.

### 5. Expose OpenCompany outward (cheap, later)

- An OpenCompany MCP server via `laravel/mcp`, so Claude, ChatGPT and Codex users can reach their
  workspace. This aligns with the API-first split (direction pillar 4).
- Render MCP Apps (`ui://` resources in sandboxed iframes) as inline cards in the messenger. ChatGPT,
  Claude and VS Code have converged on this format.
- A2A Agent Cards per durable agent, when external-agent delegation becomes a requirement.

### Not recommended

ACP (Zed), AG-UI, commerce protocols (until payments), WebMCP, NLWeb, agents.json, and reusing n8n
nodes.

## Prerequisites (Phase 0)

- Fix the suspected cross-workspace credential leak: package services are bound as singletons with
  credentials read once (security finding, 2026-11-02).
- Per-workspace MCP registration, the MCP SSRF guard, and an approval default for unclassified
  `action` tools.
- Reinstall vendor from `integrations-mruby`. The owner must decide the fate of the dirty
  `../integrations` main tree (about 9.9k uncommitted paths of bulk regeneration).

## Open questions

- Per-user versus shared connections as the default for each integration category.
- Whether the curated first-party set lives in this repository or stays in `../integrations`.
- Whether Activepieces ships in the default docker-compose or only in docs.
