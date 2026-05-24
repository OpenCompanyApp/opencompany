# Developer Tools

> Workspace developer surfaces for the Lua tool API reference and the Luau console.

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
