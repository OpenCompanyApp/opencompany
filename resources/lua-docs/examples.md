# Lua Scripting Examples

## 1. Daily Standup Summary (Scheduled)

```lua
-- Scheduled: 0 9 * * 1-5 (weekdays at 9am)
-- Gathers yesterday's completed tasks and today's active tasks

local completed = app.tasks.query({
    status = "completed",
    completed_after = ctx.last_run_at,
})

local active = app.tasks.query({
    status = { "active", "paused" },
})

local lines = { "**Daily Standup** — " .. os.date("%Y-%m-%d") .. "\n" }

table.insert(lines, "### Completed Since Last Run (" .. #completed .. ")")
for _, task in ipairs(completed) do
    local agent = task.agent and task.agent.name or "Unassigned"
    table.insert(lines, "- " .. task.title .. " (" .. agent .. ")")
end

table.insert(lines, "\n### Active Today (" .. #active .. ")")
for _, task in ipairs(active) do
    local agent = task.agent and task.agent.name or "Unassigned"
    table.insert(lines, "- " .. task.title .. " (" .. agent .. ")")
end

app.chat.send(channelId, table.concat(lines, "\n"))
```

## 2. Task Completion Notification (Event-Triggered)

```lua
-- Trigger: task_completed
-- Notifies the task's channel when a task completes

local task = ctx.event.task

-- Skip if no channel assigned
if not task.channel_id then return end

local agent_name = task.agent and task.agent.name or "Someone"

app.chat.send(task.channel_id,
    "**" .. task.title .. "** completed by " .. agent_name
)
```

## 3. Auto-Assign Tasks by Type (Event-Triggered)

```lua
-- Trigger: task_created
-- Routes tasks to agents based on task type

local task = ctx.event.task

-- Skip if already assigned
if task.agent_id then return end

local assignment = {
    ticket   = "agent-id-nova",
    research = "agent-id-sage",
    content  = "agent-id-muse",
}

local agent_id = assignment[task.type]
if agent_id then
    app.tasks.update(task.id, { agent_id = agent_id })
end
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
    app.chat.send(salesChannelId,
        #hot .. " hot leads flagged:\n" .. table.concat(names, "\n")
    )
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
    app.chat.send(financeChannelId,
        "Payment received: $" .. payload.amount .. " from " .. payload.customer
    )
end
```

## 6. Hybrid: Lua Filters, Agent Reasons (Event-Triggered)

```lua
-- Trigger: task_created
-- Lua does cheap filtering, only escalates to agent when reasoning is needed

local task = ctx.event.task

-- Fast deterministic checks
if task.priority == "low" then
    app.tasks.update(task.id, { agent_id = defaultAgentId })
    return
end

if task.type == "ticket" and task.priority == "urgent" then
    app.tasks.update(task.id, { agent_id = novaAgentId })
    app.chat.send(task.channel_id, "Urgent ticket routed to Nova.")
    return
end

-- Complex case: need agent reasoning to decide
app.agent.ask(triageAgentId, {
    title = "Triage: " .. task.title,
    description = task.description,
    priority = task.priority,
})
```

## 7. External Channel Monitor (Scheduled)

```lua
-- Scheduled: */5 * * * * (every 5 minutes)
-- Check for unread messages in external channels

local channels = app.chat.list_channels()

for _, channel in ipairs(channels) do
    if channel.unread_count and channel.unread_count > 10 then
        app.chat.send(alertChannelId,
            "Channel **" .. channel.name .. "** has " ..
            channel.unread_count .. " unread messages"
        )
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
    app.chat.send(teamChannelId, "No events scheduled for today.")
    return
end

local lines = { "**Today's Schedule** (" .. #events .. " events)\n" }
for _, event in ipairs(events) do
    table.insert(lines, "- " .. event.start_time .. " — " .. event.title)
end

app.chat.send(teamChannelId, table.concat(lines, "\n"))
```
