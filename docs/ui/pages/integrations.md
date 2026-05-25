# Integrations

> Connect AI providers, package integrations, chat platforms, remote MCP servers, and the OpenCompany AI Gateway from a searchable integration catalog.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/w/{workspace}/integrations` |
| **Name** | `integrations` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+--------------------------------------------------------------------+
| Header: "Integrations"       [Tool Catalog] [Lua Console]          |
| "Connect external services and manage API access"                  |
+--------------------------------------------------------------------+
| Sidebar / mobile pills                 | Main content              |
| [Search]                               | Search results            |
| All                                    | or category grids         |
| Installed                              | or Installed view         |
| Native categories                      |                          |
| MCP Servers                            | IntegrationCard grid      |
| AI Gateway                             | Webhooks/API Keys blocks  |
| Add MCP Server                         | Load more catalog button  |
+--------------------------------------------------------------------+
| Modals: provider, Codex, dynamic config, AI Gateway, MCP, webhook  |
+--------------------------------------------------------------------+
```

Desktop uses a left sidebar. Mobile uses search plus horizontal category pills.

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `IntegrationCard` | `Components/integrations/IntegrationCard.vue` | Card for native, package, catalog-only, and MCP entries |
| `ProviderConfigModal` | `Components/integrations/ProviderConfigModal.vue` | Standard AI provider configuration |
| `CodexConfigModal` | `Components/integrations/CodexConfigModal.vue` | Codex OAuth configuration |
| `DynamicConfigModal` | `Components/integrations/DynamicConfigModal.vue` | Metadata-driven package/chat integration configuration |
| `AiGatewayConfigModal` | `Components/integrations/AiGatewayConfigModal.vue` | OpenCompany AI Gateway enabled models and API keys |
| `McpConfigModal` | `Components/integrations/McpConfigModal.vue` | Remote MCP server configuration |
| `Modal` | `Components/shared/Modal.vue` | Webhook form modal in Installed view |
| `Icon` | `Components/shared/Icon.vue` | Phosphor icon wrapper |

---

## Features & Interactions

### Navigation & Search

- Sidebar categories: All, Installed, native integration categories, MCP Servers, AI Gateway, and Add MCP Server.
- Search filters across all integration entries by name/description.
- All view renders each native category, then MCP server suggestions.
- Category view renders only the selected category.
- Catalog controls can load more catalog integrations when available.
- Integration cards can carry both a UI-stable prefixed `id` and a raw `configId`.
  Current prefixes distinguish AI providers, package integrations, chat entries,
  static entries, and MCP entries so same-slug surfaces do not overwrite each
  other in the catalog UI.

### Installed View

- Connected Services is backed by the current integration catalog/status data.
- Webhooks have a persisted backend API (`/api/integration-webhooks`) plus public
  receiver (`POST /api/webhooks/{webhook}`), but the current `Integrations.vue`
  list/create/delete handlers still use local page state and are not wired to
  those endpoints yet.
- API Keys block is also local page state/mock data. Real AI Gateway API keys are managed in `AiGatewayConfigModal`.

### Install & Configure Flow

- Standard AI providers open `ProviderConfigModal`.
- Codex opens `CodexConfigModal`.
- Package integrations, chat platforms, and web-provider setup entries open
  `DynamicConfigModal`.
- MCP suggestions quick-install a server when possible, then open `McpConfigModal`.
- Existing MCP entries open `McpConfigModal`.
- AI Gateway opens `AiGatewayConfigModal`.
- Web providers are grouped under `web-providers` / "Web Providers" and use
  static entries such as `web.tavily`, `web.zai`, `web.firecrawl`, `web.exa`,
  `web.brave`, `web.parallel`, `web.jina`, `web.searxng`,
  `web.perplexity`, `web.openai_native`, and `web.anthropic_native`.
- Installed status is refreshed from `GET /api/integrations` and catalog metadata.

### Real Backend APIs Used

| Surface | Endpoints |
|---------|-----------|
| Integration status/catalog | `GET /api/integrations`, `GET /api/integrations/catalog`, `GET /api/integrations/catalog/{slug}` |
| AI provider config | `GET/PUT /api/ai/providers/{id}/config`, `POST /api/ai/providers/{id}/test`, `POST /api/ai/providers/{id}/fetch-models` |
| Package/chat/static config | `GET /api/integrations/{id}/config`, `PUT /api/integrations/{id}/config`, `POST /api/integrations/{id}/test`, `POST /api/integrations/{id}/toggle`, `POST /api/integrations/{id}/disconnect` |
| Provider models | `GET /api/integrations/models`, `GET /api/integrations/all-providers`, `GET /api/integrations/embedding-models`, `GET /api/integrations/reranking-models` |
| OAuth/account integrations | `GET/POST/PUT/DELETE /api/integrations/{id}/accounts...`, Codex auth endpoints, Google/TickTick OAuth routes |
| Webhook setup | `POST /api/integrations/{id}/setup-webhook` for supported integrations such as Telegram |
| Generic webhooks | `GET/POST/PATCH/DELETE /api/integration-webhooks`, public `POST /api/webhooks/{webhook}` receiver |
| MCP servers | `GET/POST/PATCH/DELETE /api/mcp-servers...`, plus test/discover endpoints |
| AI Gateway | `GET/PUT /api/ai-gateway/config`, `GET/POST/DELETE /api/ai-gateway/api-keys...` |

---

## States

| State | Description |
|-------|-------------|
| **Search results** | Shows result count and matching cards, or an empty search state |
| **No connected services** | Installed view prompts user to browse the library |
| **No MCP servers** | MCP category prompts user to add a remote MCP server |
| **Catalog unavailable/error** | Catalog controls show unavailable or error text |
| **Provider testing/saving** | Modal buttons and result banners live inside the relevant config modal |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Desktop (md+)** | Left sidebar, main content grid up to 3 columns |
| **Mobile (<md)** | Search plus horizontal category pills; cards collapse to one column |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Integrations.vue` | Page component with category navigation, search, installed view, catalog loading, and modal orchestration |
| `resources/js/Components/integrations/IntegrationCard.vue` | Integration card with install/configure/uninstall behavior |
| `resources/js/Components/integrations/ProviderConfigModal.vue` | AI provider configuration modal |
| `resources/js/Components/integrations/CodexConfigModal.vue` | Codex OAuth configuration modal |
| `resources/js/Components/integrations/DynamicConfigModal.vue` | Package/chat integration configuration modal |
| `resources/js/Components/integrations/AiGatewayConfigModal.vue` | OpenCompany AI Gateway settings and API keys |
| `resources/js/Components/integrations/McpConfigModal.vue` | Remote MCP server configuration modal |
| `app/Services/Integrations/IntegrationIdentity.php` | Applies and strips UI-facing integration ID prefixes around raw config slugs |
| `app/Http/Controllers/Api/IntegrationWebhookController.php` | Workspace-scoped persisted webhook CRUD API |
| `app/Http/Controllers/Api/IncomingIntegrationWebhookController.php` | Public per-webhook receiver with secret verification and receipt diagnostics |
