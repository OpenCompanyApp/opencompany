# Lua Scripting Examples

## 1. Daily Summary Report (Scheduled)

```lua
-- Scheduled: 0 9 * * 1-5 (weekdays at 9am)
-- Gathers data from a tracking table and posts a summary

local rows = app.tables.get_rows({
    tableId = "project-tracker-id",
    limit = 50,
})

local completed = {}
local in_progress = {}

for _, row in ipairs(rows) do
    if row.status == "done" then
        table.insert(completed, row)
    elseif row.status == "in_progress" then
        table.insert(in_progress, row)
    end
end

local lines = { "**Daily Summary** — " .. os.date("%Y-%m-%d") .. "\n" }

table.insert(lines, "### Completed (" .. #completed .. ")")
for _, row in ipairs(completed) do
    table.insert(lines, "- " .. row.title .. " (" .. (row.assignee or "Unassigned") .. ")")
end

table.insert(lines, "\n### In Progress (" .. #in_progress .. ")")
for _, row in ipairs(in_progress) do
    table.insert(lines, "- " .. row.title .. " (" .. (row.assignee or "Unassigned") .. ")")
end

app.chat.send_channel_message({
    channel_id = channelId,
    content = table.concat(lines, "\n"),
})
```

## 2. New Row Notification (Event-Triggered)

```lua
-- Trigger: webhook
-- Notifies a channel when a new row is added via webhook

local payload = ctx.event.body

if not payload or not payload.title then return end

app.chat.send_channel_message({
    channel_id = notifyChannelId,
    content = "New item added: **" .. payload.title .. "** by " .. (payload.author or "Unknown"),
})
```

## 3. Auto-Route Incoming Messages (Event-Triggered)

```lua
-- Trigger: external_message
-- Routes incoming messages to different agents based on keywords

local message = ctx.event.message
local content = message.content or ""
local lower = content:lower()

local routing = {
    { keywords = {"bug", "error", "broken", "fix"}, agent = supportAgentId },
    { keywords = {"feature", "request", "idea", "suggestion"}, agent = productAgentId },
    { keywords = {"invoice", "payment", "billing"}, agent = financeAgentId },
}

for _, route in ipairs(routing) do
    for _, keyword in ipairs(route.keywords) do
        if lower:find(keyword) then
            app.agents.contact({
                agent_id = route.agent,
                message = content,
                channel_id = ctx.event.channel_id,
            })
            return
        end
    end
end

-- No match — route to default agent
app.agents.contact({
    agent_id = defaultAgentId,
    message = content,
    channel_id = ctx.event.channel_id,
})
```

## 4. Lead Scoring (Scheduled)

```lua
-- Scheduled: 0 */6 * * * (every 6 hours)
-- Scores leads in a data table

local leads = app.tables.get_rows({
    tableId = "leads-table-id",
    where = { status = "new" },
})

local hot = {}

for _, lead in ipairs(leads) do
    local score = 0
    if lead.company_size and lead.company_size > 100 then score = score + 30 end
    if lead.source == "referral" then score = score + 25 end
    if lead.budget and lead.budget > 10000 then score = score + 20 end

    app.tables.update_row({
        tableId = "leads-table-id",
        rowId = lead.id,
        data = { score = score },
    })

    if score >= 60 then table.insert(hot, lead) end
end

if #hot > 0 then
    local names = {}
    for _, l in ipairs(hot) do
        table.insert(names, "- **" .. l.name .. "** (score: " .. l.score .. ")")
    end
    app.chat.send_channel_message({
        channel_id = salesChannelId,
        content = #hot .. " hot leads flagged:\n" .. table.concat(names, "\n"),
    })
end
```

## 5. Webhook Processor (Webhook-Triggered)

```lua
-- Trigger: webhook
-- Processes incoming webhook data and logs to a table

local payload = ctx.event.body

if not payload or not payload.event then
    error("Missing event in webhook payload")
end

app.tables.add_row({
    tableId = "webhook-log-id",
    data = {
        event = payload.event,
        source = ctx.event.headers["X-Source"] or "unknown",
        received_at = os.date("%Y-%m-%dT%H:%M:%S"),
        raw = tostring(payload),
    },
})

if payload.event == "payment_received" then
    app.chat.send_channel_message({
        channel_id = financeChannelId,
        content = "Payment received: $" .. payload.amount .. " from " .. payload.customer,
    })
end
```

## 6. Hybrid: Lua Filters, Agent Reasons (Event-Triggered)

```lua
-- Trigger: webhook
-- Lua does cheap filtering, only escalates to agent when reasoning is needed

local payload = ctx.event.body

-- Fast deterministic checks
if payload.priority == "low" then
    app.tables.add_row({
        tableId = "inbox-table-id",
        data = { title = payload.subject, priority = "low", status = "backlog" },
    })
    return
end

if payload.category == "billing" and payload.priority == "urgent" then
    app.agents.contact({
        agent_id = financeAgentId,
        message = "Urgent billing issue: " .. payload.subject,
        channel_id = alertChannelId,
    })
    app.chat.send_channel_message({
        channel_id = alertChannelId,
        content = "Urgent billing issue routed to finance agent.",
    })
    return
end

-- Complex case: need agent reasoning to decide
app.agents.contact({
    agent_id = triageAgentId,
    message = "Please triage: " .. payload.subject .. "\n\n" .. (payload.body or ""),
})
```

## 7. External Channel Monitor (Scheduled)

```lua
-- Scheduled: */5 * * * * (every 5 minutes)
-- Check for unread messages in external channels

local channels = app.chat.list_channels()

for _, channel in ipairs(channels) do
    if channel.unread_count and channel.unread_count > 10 then
        app.chat.send_channel_message({
            channel_id = alertChannelId,
            content = "Channel **" .. channel.name .. "** has " ..
                channel.unread_count .. " unread messages",
        })
    end
end
```

## 8. Calendar Reminder (Scheduled)

```lua
-- Scheduled: 0 8 * * * (daily at 8am)
-- Posts today's calendar events to a channel

local events = app.calendar.list_events({
    from = os.date("%Y-%m-%d") .. "T00:00:00",
    to = os.date("%Y-%m-%d") .. "T23:59:59",
})

if #events == 0 then
    app.chat.send_channel_message({
        channel_id = teamChannelId,
        content = "No events scheduled for today.",
    })
    return
end

local lines = { "**Today's Schedule** (" .. #events .. " events)\n" }
for _, event in ipairs(events) do
    table.insert(lines, "- " .. event.start_time .. " — " .. event.title)
end

app.chat.send_channel_message({
    channel_id = teamChannelId,
    content = table.concat(lines, "\n"),
})
```
