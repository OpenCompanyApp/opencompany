# Lua Scripting API Overview

Lua scripts in OpenCompany run in a sandboxed environment with access to workspace data through the `app.*` namespace. Scripts are lightweight alternatives to full AI agent executions — ideal for deterministic automations like notifications, data transforms, and routing.

## Namespace Structure

```
app.{namespace}.*              — Internal workspace apps
app.integrations.{name}.*     — Integration-specific tools
app.mcp.{server}.*            — MCP server tools
```

Available namespaces are determined by the agent's permissions. Use `lua_list_docs` to see what's available.

## Quick Start

```lua
-- Send a message to a channel
app.chat.send(channelId, "Hello from Lua!")

-- Query a data table
local rows = app.tables.get_rows({ tableId = "...", limit = 10 })

-- Create a calendar event
app.calendar.create({
    title = "Team standup",
    startTime = "2025-01-15T09:00:00",
    duration = 15,
})
```

## Sandbox Limits

| Limit | Default |
|-------|---------|
| CPU time | 5 seconds |
| Memory | 32 MB |
| Network | None (use `app.http.*` for pre-approved webhooks) |
| File system | None |
| OS access | None |

## Return Values

All `app.*` functions return Lua tables (objects/arrays) on success. On failure, they return `nil, error_message`. Use `pcall` for error handling:

```lua
local ok, result = pcall(function()
    return app.chat.send(channelId, "Hello!")
end)

if not ok then
    print("Error: " .. result)
end
```

## Guides

- **context** — The `ctx` object available in automation scripts
- **errors** — Error handling patterns and common error codes
- **examples** — Complete real-world automation examples
