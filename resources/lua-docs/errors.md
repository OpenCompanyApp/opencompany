# Error Handling

## pcall / xpcall

Lua scripts should use `pcall` (protected call) to handle errors gracefully:

```lua
local ok, result = pcall(function()
    return app.chat.send_channel_message({ channel_id = channelId, content = "Hello!" })
end)

if ok then
    print("Message sent: " .. result.id)
else
    print("Failed: " .. tostring(result))
end
```

For more detailed error info, use `xpcall` with an error handler:

```lua
local ok, result = xpcall(function()
    return app.docs.get({ documentId = "nonexistent" })
end, function(err)
    return "Error: " .. tostring(err) .. "\n" .. debug.traceback()
end)
```

## Common Error Patterns

### Permission Denied

The agent running the script doesn't have access to the requested resource:

```lua
local ok, result = pcall(function()
    return app.chat.send_channel_message({ channel_id = restrictedChannelId, content = "Hello!" })
end)
-- result: "Permission denied: you do not have access to this channel."
```

### Resource Not Found

The requested item doesn't exist or has been deleted:

```lua
local ok, result = pcall(function()
    return app.docs.get({ documentId = "deleted-uuid" })
end)
-- result: "Error: Document 'deleted-uuid' not found."
```

### Validation Errors

Required parameters are missing or invalid:

```lua
local ok, result = pcall(function()
    return app.chat.send_channel_message({ content = "Hello!" })  -- missing channel_id
end)
-- result: "Error: Channel '' not found."
```

### Sandbox Limits

Scripts that exceed CPU or memory limits are terminated:

```lua
-- This will be killed after 5 seconds of CPU time
while true do end
-- Error: "CPU time limit exceeded"

-- This will be killed at 32MB
local t = {}
for i = 1, 1e8 do t[i] = "x" end
-- Error: "Memory limit exceeded"
```

## Retry Pattern

For transient errors, implement simple retry logic:

```lua
local function with_retry(fn, max_retries)
    max_retries = max_retries or 3
    for attempt = 1, max_retries do
        local ok, result = pcall(fn)
        if ok then return result end
        if attempt < max_retries then
            print("Attempt " .. attempt .. " failed, retrying...")
        else
            error("Failed after " .. max_retries .. " attempts: " .. tostring(result))
        end
    end
end

local result = with_retry(function()
    return app.tables.get_rows({ tableId = "my-table-id", limit = 10 })
end)
```
