# Developer Tools

> Workspace developer surfaces for the Lua tool API reference and the Luau console.

Status: "Direction" below follows the locked direction (aligned 2026-11-02); everything after it describes the page as it exists today.

## Direction (accepted, not implemented)

**Superseded.** The Lua/Luau console and reference are replaced by mruby Code Mode, the only Code Mode runtime (pillar 3). Integration package references are superseded by Connections and Apps (pillars 7 and 9); the package system is deleted. Runtime details are owned by the mruby planning docs, not this page. The integration renderer packages (Mermaid, PlantUML, Typst, Vega-Lite) are replaced by built-in chart and diagram blocks and Typst PDF export into Files ([Artifacts and Forms](../../planning/artifacts-and-forms.md)).

See [UI Structure and Agent Editing](../../planning/ui-structure-and-agent-editing.md), [Messenger UI](../../planning/messenger-ui.md) and the [sidebar mockup](../mockups/sidebar-2026-11.html). API-first (pillar 4): Inertia is frozen (no new Inertia pages or page props); new UI is built in the new Vue 3 + Reka UI client on the versioned public API and realtime stream, and old pages are deleted as it reaches parity ([API-First Client Split](../../architecture/api-first-client-split.md)).

---

## Route & Access

| Page | Route | Name | Notes |
|------|-------|------|-------|
| Tool Catalog | `/w/{workspace}/developer/tools` | `developer.tools` | Main reference for built-in, integration, and MCP tools |
| Lua Console | `/w/{workspace}/developer/lua-console` | `developer.lua-console` | Monaco-based Luau runner |

Both routes require `auth`, `verified`, and `resolve.workspace`. There is no current `/w/{workspace}/developer` index route; links should target `/developer/tools` or `/developer/lua-console` directly.

---

## Tool Catalog

```
+--------------------------------------------------------------------+
| Header: back to Integrations, title, search                        |
+----------------------+---------------------------------------------+
| Sidebar / mobile     | Static guide, app group, integration group, |
| pills                | or MCP server tool reference                |
| Guides               |                                             |
| Built-in             | Tool cards, parameters, returns, examples   |
| Integrations         |                                             |
| MCP Servers          |                                             |
+----------------------+---------------------------------------------+
```

### Behavior

- Loads the catalog from `GET /api/tools/catalog`.
- Search filters visible tool groups and tools.
- Desktop uses a fixed left sidebar grouped into Guides, Built-in, Integrations, and MCP Servers.
- Mobile uses horizontal pills for the same entries.
- Integration entries can be toggled between enabled-only and all integrations.
- Static docs are rendered through `useMarkdown`; tool groups render parameter/return/reference cards.

---

## Lua Console

```
+--------------------------------------------------------------------+
| Toolbar: back, Lua Console, Luau badge, API Reference, Run         |
+--------------------------------------------------------------------+
| Monaco editor                                                      |
+--------------------------------------------------------------------+
| ConsoleOutput                                                      |
+--------------------------------------------------------------------+
| Status bar: language, cursor, spaces, execution time, run status   |
+--------------------------------------------------------------------+
```

### Behavior

- Uses `MonacoEditor` with Luau completions from `useLuaCompletions`.
- `Cmd/Ctrl+Enter` executes the current code.
- `Run` posts `{ code }` to `POST /api/lua/execute`.
- Output and errors render through `ConsoleOutput`.
- `sessionStorage["lua-console-code"]` can prefill the editor once, then it is removed.
- The status bar shows cursor line/column, execution time, and Ready/Running/Error state.

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `MonacoEditor` | `Components/developer/MonacoEditor.vue` | Shared Monaco editor wrapper |
| `ConsoleOutput` | `Components/developer/ConsoleOutput.vue` | Lua execution output/error renderer |
| `SplitterGroup`, `SplitterPanel`, `SplitterResizeHandle` | `reka-ui` | Vertical editor/output split in the console |
| `Icon`, `Button` | `Components/shared/*` | Shared controls |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Developer/Tools.vue` | Tool catalog page |
| `resources/js/Pages/Developer/LuaConsole.vue` | Luau console page |
| `app/Http/Controllers/Api/ToolCatalogController.php` | Tool catalog API |
| `app/Http/Controllers/Api/LuaConsoleController.php` | Lua execution API |
