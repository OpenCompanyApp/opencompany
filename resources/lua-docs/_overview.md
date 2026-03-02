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
app.chat.send_channel_message({ channel_id = channelId, content = "Hello from Lua!" })

-- Query a data table
local rows = app.tables.get_rows({ tableId = "...", limit = 10 })

-- Create a calendar event
app.calendar.create_event({
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
| Module loading | None (`require`, `loadfile`, `dofile` are not available) |

## Built-in Globals

### `print(...)`

Prints values to the output. Tables are automatically serialized to a readable format (not `table: 0x...`):

```lua
local result = app.integrations.plausible.list_sites()
print(result)
-- {
--   sites: [
--     {
--       domain: "example.com"
--     }
--   ]
-- }
```

### `dump(value)`

Prints a table's contents and returns the value (useful for chaining). Equivalent to `print()` for a single value:

```lua
local sites = dump(app.integrations.plausible.list_sites())
-- prints the table contents, then continues with sites as a variable
```

## Return Values

All `app.*` functions return Lua tables (objects/arrays) on success. On failure, they return `nil, error_message`. Use `pcall` for error handling:

```lua
local ok, result = pcall(function()
    return app.chat.send_channel_message({ channel_id = channelId, content = "Hello!" })
end)

if not ok then
    print("Error: " .. result)
end
```

## Guides

- **context** — The `ctx` object available in automation scripts
- **errors** — Error handling patterns and common error codes
- **examples** — Complete real-world automation examples
