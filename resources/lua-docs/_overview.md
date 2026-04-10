# Lua Scripting API Overview

Lua scripts in OpenCompany run in a sandboxed environment with access to workspace data through the `app.*` namespace. Scripts are lightweight alternatives to full AI agent executions — ideal for deterministic automations like notifications, data transforms, and routing.

## Namespace Structure

```
app.{namespace}.*              — Internal workspace apps
app.integrations.{name}.*     — Integration-specific tools
app.mcp.{server}.*            — MCP server tools
json.decode(string) / json.encode(value)  — JSON parsing and serialization
regex.match(s, p) / regex.match_all(s, p) — PCRE regex matching
regex.gsub(s, p, r)           — PCRE regex substitution
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

### `json.decode(string)`

Parses a JSON string into a Lua table. Uses PHP's `json_decode` under the hood, so it handles all standard JSON types including nested objects and arrays.

```lua
-- Parse a JSON string
local data = json.decode('{"items": [1, 2, 3]}')
print(data.items[1])  -- 1

-- Parse JSON received from an integration
local raw = app.http.get({url = "https://api.example.com/data"})
local parsed = json.decode(raw.body)
print(parsed.status)
```

Raises an error on invalid JSON. Use `pcall` for error handling:

```lua
local ok, data = pcall(json.decode, raw_string)
if not ok then
    print("Invalid JSON: " .. tostring(data))
end
```

### `json.encode(value)`

Serializes a Lua table (or any value) to a JSON string. Produces pretty-printed output with unescaped Unicode.

```lua
print(json.encode({name = "test", count = 42}))
-- {
--     "name": "test",
--     "count": 42
-- }
```

### `regex.match(subject, pattern [, flags])`

Tests whether `subject` matches the PCRE `pattern`. Returns a table of captures on match, or `nil` on no match. Supports all PCRE features (lookaheads, non-greedy quantifiers, Unicode properties, named groups) that Lua's built-in patterns lack.

```lua
local m = regex.match("hello world 42", "(\\w+) (\\d+)")
-- m = {"world 42", "world", "42"}  (full match, then captures)

local m = regex.match("no digits here", "\\d+")
-- m = nil

-- Named capture groups
local m = regex.match("price: $19.99", "(?P<currency>\\$)(?P<amount>[\\d.]+)")
-- m = {"$19.99", "$", "19.99"}
```

### `regex.match_all(subject, pattern [, flags])`

Returns all matches of `pattern` in `subject`. Default flag behavior (`PREG_PATTERN_ORDER`) returns captures grouped by group index.

```lua
local matches = regex.match_all("foo123bar456baz", "(\\d+)")
-- matches[1] = all full matches, matches[2] = first capture group, etc.
```

### `regex.gsub(subject, pattern, replacement [, limit])`

Replaces all occurrences of `pattern` in `subject` with `replacement`. Returns the resulting string. Supports PCRE backreferences (`$1`, `$2`, etc.).

```lua
local cleaned = regex.gsub("  hello   world  ", "\\s+", " ")
-- cleaned = " hello world "

local s = regex.gsub("aaa", "a", "b", 2)
-- s = "bba"
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