# Integrations

> Connect external services, manage webhooks and API keys, and browse an integration library.

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
| h-full overflow-y-auto                                             |
| ┌────────────────────────────────────────────────────────────────┐ |
| │ max-w-4xl mx-auto p-6                                         │ |
| │                                                                │ |
| │ Header: "Integrations"                                         │ |
| │ "Connect external services and manage API access"              │ |
| │                                                                │ |
| │ ┌──────────────┐ ┌──────────────┐                              │ |
| │ │  Installed   │ │   Library    │  <- Tab buttons              │ |
| │ └──────────────┘ └──────────────┘                              │ |
| │                                                                │ |
| │ ── Installed Tab ──────────────────────────────────────────    │ |
| │                                                                │ |
| │ Webhooks                                      [+ Add webhook]  │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ GitHub PR Notifications          Active    [edit] [del]  │   │ |
| │ │ POST /api/webhooks/wh-1                                  │   │ |
| │ ├──────────────────────────────────────────────────────────┤   │ |
| │ │ Stripe Payment Events           Disabled   [edit] [del]  │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ API Keys                                    [Generate key]     │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ Production API Key    sk_live_••••4f2a   [copy] [revoke] │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ Connected Services                                             │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [icon] GitHub   Connected  [disconnect]                  │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ ── Library Tab ────────────────────────────────────────────    │ |
| │                                                                │ |
| │ [Search integrations...]                                       │ |
| │                                                                │ |
| │ AI Models                                                      │ |
| │ ┌─────────────────────────┐ ┌─────────────────────────────┐   │ |
| │ │ Z.AI API      [Install]│ │ Z.AI Coding Plan   [Install]│   │ |
| │ └─────────────────────────┘ └─────────────────────────────┘   │ |
| │                                                                │ |
| │ Communication                                                  │ |
| │ ┌─────────────────────────┐ ┌─────────────────────────────┐   │ |
| │ │ Slack          [Install]│ │ Discord            [Install]│   │ |
| │ └─────────────────────────┘ └─────────────────────────────┘   │ |
| │ ...more categories...                                          │ |
| └────────────────────────────────────────────────────────────────┘ |
+--------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `IntegrationCard` | `Components/integrations/IntegrationCard.vue` | Card for each integration in the library grid |
| `ProviderConfigModal` | `Components/integrations/ProviderConfigModal.vue` | Configuration modal for standard AI model providers |
| `CodexConfigModal` | `Components/integrations/CodexConfigModal.vue` | OAuth configuration modal for Codex |
| `DynamicConfigModal` | `Components/integrations/DynamicConfigModal.vue` | Metadata-driven configuration modal for package integrations |
| `AiGatewayConfigModal` | `Components/integrations/AiGatewayConfigModal.vue` | API key and model access modal for the OpenCompany AI Gateway |
| `McpConfigModal` | `Components/integrations/McpConfigModal.vue` | Configuration modal for MCP servers |
| `SearchInput` | `Components/shared/SearchInput.vue` | Clearable search input for library filtering |
| `Modal` | `Components/shared/Modal.vue` | Shared modal for webhook creation/editing |
| `Icon` | `Components/shared/Icon.vue` | Phosphor icon wrapper |

---

## Features & Interactions

### Tab Navigation
- Two tabs: "Installed" and "Library"
- Installed tab shows a badge with total count (webhooks + API keys + connected services)
- Active tab styled with dark fill; inactive tabs use ghost hover style

### Webhooks (Installed Tab)
- **List**: bordered card with name, active/disabled badge, endpoint URL, last triggered time, weekly call count
- **Add**: "Add webhook" button opens Modal with name, target type (agent/channel/task), and target selector
- **Edit**: pencil icon opens the same modal pre-filled with webhook data
- **Delete**: trash icon removes the webhook from the list
- **Empty state**: webhook icon with "No webhooks configured" message

### API Keys (Installed Tab)
- **List**: card with name, masked key (`sk_live_••••4f2a`), creation date, last used time
- **Generate**: "Generate key" button creates a new key entry
- **Copy**: copy icon (placeholder for clipboard API)
- **Revoke**: trash icon removes the key
- **Empty state**: key icon with "No API keys" message

### Connected Services (Installed Tab)
- **List**: green-tinted icon, service name, description, green "Connected" badge, disconnect button
- **Disconnect**: plug icon removes the service from connected list
- **Empty state**: linked-plugs icon with "No connected services" and a link to the Library tab

### Integration Library (Library Tab)
- **Search**: `SearchInput` filters across all categories by name and description
- **Categories**: AI Models, Communication, Developer Tools, Productivity, Data & APIs
- **Cards**: 2-column grid (`md:grid-cols-2`); each `IntegrationCard` shows icon, name, description, "Popular" badge, and install/configure/uninstall actions
- **Install flow**: provider integrations open `ProviderConfigModal`, Codex opens `CodexConfigModal`, package integrations open `DynamicConfigModal`, MCP suggestions open `McpConfigModal`, and the gateway opens `AiGatewayConfigModal`.
- **Configure**: gear icon on installed integrations opens configuration
- **Uninstall**: trash icon sets `installed` to false
- **Empty search**: magnifying glass icon with "No integrations found" message

### Provider Configuration Modal
- Fields: API Key (password/text toggle), API URL, Default Model (dropdown)
- "Test Connection" button sends POST to `/api/integrations/{id}/test`
- Test result shown as success (green) or error (red) banner
- Enable/disable toggle for the integration
- "Save Configuration" sends PUT to `/api/integrations/{id}/config`
- Loads existing config on open via GET `/api/integrations/{id}/config`

### Integration Status Loading
- On mount, fetches `/api/integrations` to sync real integration status from backend
- Updates the `installed` flag on matching library integrations

---

## States

| State | Description |
|-------|-------------|
| **Empty webhooks** | Webhook icon, "No webhooks configured" text, hint to add one |
| **Empty API keys** | Key icon, "No API keys" text, hint to generate one |
| **Empty services** | Plugs icon, "No connected services" text, link to Library tab |
| **Empty search** | Magnifying glass, "No integrations found for ..." message |
| **Provider testing** | "Test Connection" button shows loading state; result appears below |
| **Provider saving** | "Save Configuration" button shows loading spinner |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Desktop (md+)** | Library grid shows 2 columns (`md:grid-cols-2`) |
| **Mobile (<md)** | Library grid collapses to single column; full-width scrollable page |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Integrations.vue` | Page component with tabs, webhook/key management, library |
| `resources/js/Components/integrations/IntegrationCard.vue` | Integration card with install/configure/uninstall |
| `resources/js/Components/integrations/ProviderConfigModal.vue` | AI provider configuration modal |
| `resources/js/Components/integrations/CodexConfigModal.vue` | Codex OAuth configuration modal |
| `resources/js/Components/integrations/DynamicConfigModal.vue` | Package integration configuration modal |
| `resources/js/Components/integrations/AiGatewayConfigModal.vue` | OpenCompany AI Gateway configuration modal |
| `resources/js/Components/integrations/McpConfigModal.vue` | MCP server configuration modal |
| `resources/js/Components/shared/SearchInput.vue` | Search input with clear button |
| `resources/js/Components/shared/Modal.vue` | Shared modal dialog |
