# Context Object (`ctx`)

Every Lua automation script receives a `ctx` object with information about what triggered the execution.

## Common Fields

```lua
ctx.trigger_type   -- "schedule" | "task_created" | "task_completed" | "webhook" | ...
ctx.automation_id  -- UUID of the automation
ctx.run_number     -- Execution count
ctx.last_run_at    -- ISO timestamp of previous run (nil if first)
```

## Event-Triggered Scripts

```lua
ctx.event          -- The triggering event data
ctx.event.task     -- Task object (for task_* triggers)
ctx.event.user     -- User who triggered the event
ctx.event.changes  -- What changed (for update triggers)
```

## Scheduled Scripts

```lua
ctx.schedule       -- Cron expression
ctx.timezone       -- Configured timezone
```

## External Message Triggers

```lua
ctx.event.message        -- The incoming message object
ctx.event.channel_id     -- External channel UUID
ctx.event.provider       -- "telegram" | "discord" | ...
ctx.event.agent_id       -- Agent assigned to this external channel
ctx.event.external_user  -- { id, username, display_name }
```

## Webhook Triggers

```lua
ctx.event.body           -- Parsed JSON body
ctx.event.headers        -- Request headers
ctx.event.method         -- "POST" | "GET"
```

## Configuration

Pre-registered URLs, secrets, and custom config for the automation:

```lua
ctx.webhooks             -- { slack_incoming = "https://...", ... }
ctx.secrets              -- { api_key = "...", ... } (encrypted at rest)
ctx.config               -- Custom key-value config for this automation
```

## Example: Using Context

```lua
-- Scheduled task that uses trigger context
if ctx.trigger_type == "schedule" then
    print("Running scheduled job #" .. ctx.run_number)
end

-- Event-triggered: react to task completion
if ctx.trigger_type == "task_completed" then
    local task = ctx.event.task
    app.chat.send(task.channel_id, "Task completed: " .. task.title)
end

-- Webhook: process incoming data
if ctx.trigger_type == "webhook" then
    local payload = ctx.event.body
    app.tables.add_row({
        tableId = "webhooks-log",
        data = { source = "external", payload = tostring(payload) },
    })
end
```
