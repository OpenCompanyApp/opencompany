# Lua Scripting for OpenCompany

Design document for adding embedded Lua scripting as a lightweight automation runtime, complementing the existing AI agent execution model.

## Problem

Every automation currently spawns a full AI agent — even trivial ones.

**Cost of a single scheduled automation run:**

| Component | Tokens | Cost (Claude) |
|-----------|--------|---------------|
| System prompt (identity, tools, memory) | 1,000–3,000 | $0.01–$0.03 |
| Conversation context | 500–5,000 | $0.01–$0.05 |
| Agent response + tool calls | 1,000–5,000 | $0.01–$0.10 |
| **Total per run** | **3,000–10,000** | **$0.05–$0.20** |

**Extrapolated cost:**

| Schedule | Runs/month | Token cost/month |
|----------|-----------|-----------------|
| Every 5 min | 8,640 | $430–$1,730 |
| Hourly | 720 | $36–$144 |
| Daily | 30 | $1.50–$6 |
| 10 automations, hourly each | 7,200 | $360–$1,440 |

A Lua script doing the same work: **$0.00**, sub-100ms execution, zero API calls.

**Not all automations need reasoning.** Roughly 30–40% of automation actions are deterministic:
- Status change → send notification
- Task created → assign to agent based on type
- Schedule → query data, format report, post to channel
- Approval granted → move item to next stage

These don't need Claude. They need `if/then/else`.

## Why Lua

| Criterion | Lua | JavaScript (V8) | Python | PHP eval |
|-----------|-----|-----------------|--------|----------|
| Embeddable | Excellent | Heavy (V8 ~30MB) | Heavy | Unsafe |
| Sandboxable | Native (metatables) | Requires isolates | Difficult | No |
| Startup time | <1ms | ~50ms | ~100ms | N/A |
| Memory footprint | ~200KB | ~30MB | ~15MB | Shared |
| Battle-tested embedding | Redis, Nginx, game engines | Node, Deno | Rare | Never |
| PHP integration | `php-lua` ext or FFI | Shell out | Shell out | Native |
| Agent can write it | Yes (simple syntax) | Yes | Yes | N/A |
| Learning curve | Very low | Low | Low | N/A |

Lua wins on embeddability, sandboxing, and startup cost. Its simplicity also makes it ideal for AI-generated code — fewer footguns than JavaScript or Python.

## Architecture

```
┌─────────────────────────────────────────────────┐
│                  Trigger Layer                    │
│  ┌──────────┐  ┌───────────┐  ┌──────────────┐  │
│  │  Cron    │  │  Event    │  │  Webhook     │  │
│  │ Scheduler│  │ Listeners │  │  Endpoint    │  │
│  └────┬─────┘  └─────┬─────┘  └──────┬───────┘  │
│       └───────────────┼───────────────┘          │
│                       ▼                          │
│              ┌────────────────┐                   │
│              │ AutomationRouter│                   │
│              │                │                   │
│              │ script_lang?   │                   │
│              │  ├─ "lua" ───────► LuaExecutor     │
│              │  ├─ "agent" ─────► AgentExecutor   │
│              │  └─ null ────────► PHPAction        │
│              └────────────────┘                   │
│                                                   │
│  ┌─────────────────────────────────────────────┐  │
│  │              LuaExecutor                     │  │
│  │                                             │  │
│  │  ┌─────────┐    ┌────────────────────────┐  │  │
│  │  │ Sandbox │    │ Platform API (read-only │  │  │
│  │  │         │    │ + whitelisted writes)   │  │  │
│  │  │ No fs   │    │                        │  │  │
│  │  │ No net  │    │ oc.chat.send()         │  │  │
│  │  │ No os   │    │ oc.tasks.query()       │  │  │
│  │  │ 5s max  │    │ oc.tasks.update()      │  │  │
│  │  │ 10MB mem│    │ oc.tables.query()      │  │  │
│  │  └─────────┘    │ oc.agent.spawn()       │  │  │
│  │                  │ oc.memory.save()       │  │  │
│  │                  │ oc.log()               │  │  │
│  │                  └────────────────────────┘  │  │
│  └─────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────┘
```

### Execution Model

1. Trigger fires (cron, event, webhook)
2. `AutomationRouter` checks `script_language` field on the automation
3. If `"lua"`: dispatches `RunLuaAutomationJob` (lightweight, no agent)
4. If `"agent"` or null: dispatches existing `RunScheduledAutomationJob` (full agent)
5. Lua executes in sandboxed runtime with platform API bindings
6. Results logged to `automation_execution_log` table

### PHP ↔ Lua Bridge

Two implementation options:

**Option A: `php-lua` PECL extension** (preferred)
```php
$lua = new \Lua();
$lua->assign('oc', $this->buildPlatformApi($context));
$lua->eval($script);
```
- Direct in-process execution, <1ms overhead
- Requires PECL extension installed on server

**Option B: External Lua binary via Process**
```php
$process = new Process(['lua5.4', $tempScriptPath]);
$process->setTimeout(5);
$process->run();
```
- No PHP extension needed
- Platform API passed as JSON stdin, results as JSON stdout
- ~10ms overhead per execution

## Platform API Exposed to Lua

Mapped from existing `ToolRegistry` groups. Each Lua function wraps the corresponding PHP tool's `handle()` method.

### `oc.chat` — Channel Messaging

```lua
-- Send a message to a channel
oc.chat.send(channelId, "Task completed: " .. task.title)

-- Send to channel by name
oc.chat.send_to("general", "Daily report ready")

-- Read recent messages from a channel
local messages = oc.chat.read(channelId, { limit = 10 })
for _, msg in ipairs(messages) do
    oc.log(msg.author .. ": " .. msg.content)
end
```

### `oc.tasks` — Task Management

```lua
-- Query tasks with filters
local active = oc.tasks.query({
    status = "active",
    agent_id = "a1",
})

-- Create a task
local task = oc.tasks.create({
    title = "Review PR #42",
    agent_id = "a2",
    priority = "high",
    source = "automation",
})

-- Update a task
oc.tasks.update(taskId, { status = "completed", result = "Done" })
```

### `oc.tables` — Data Tables

```lua
-- Query table rows (wraps query_table tool)
local rows = oc.tables.query("Leads", {
    where = { status = "new", score = { gte = 80 } },
    limit = 50,
})

-- Update a row
oc.tables.update_row(tableId, rowId, { status = "contacted" })
```

### `oc.lists` — Kanban Lists

```lua
-- Query list items
local items = oc.lists.query(listId, { status = "todo" })

-- Move item to different status
oc.lists.update_item(itemId, { status = "in_progress", assignee_id = "a3" })
```

### `oc.calendar` — Calendar Events

```lua
-- Query today's events
local events = oc.calendar.query({
    from = oc.today(),
    to = oc.today() .. "T23:59:59",
})

-- Create an event
oc.calendar.create({
    title = "Standup",
    start_time = oc.now(),
    duration = 15,  -- minutes
})
```

### `oc.memory` — Agent Memory

```lua
-- Save to daily log
oc.memory.save("Processed 42 leads today, 3 high-priority", {
    category = "learning",
    target = "log",
})

-- Recall memories
local results = oc.memory.recall("deployment process")
```

### `oc.agent` — Agent Escalation

```lua
-- Spawn an agent for complex reasoning (hybrid pattern)
local response = oc.agent.prompt(agentId, channelId,
    "Analyze this customer message and draft a response: " .. message.content
)

-- Delegate a task to an agent
oc.agent.delegate(agentId, {
    title = "Review flagged content",
    description = content,
    priority = "high",
})
```

### `oc.external` — External Channels & Platforms

```lua
-- List external channels (Telegram, Discord, etc.)
local channels = oc.external.channels({ provider = "telegram" })

-- Send a message to an external channel (auto-routes via provider)
oc.external.send(channelId, "Hello from automation!")

-- Send rich content to Telegram specifically
oc.external.telegram.send(chatId, {
    text = "New deployment complete",
    parse_mode = "html",  -- "html" or "markdown"
})

-- Send a photo to Telegram
oc.external.telegram.send_photo(chatId, {
    url = "https://example.com/chart.png",
    caption = "Daily metrics chart",
})

-- Pin a message in an external channel
oc.external.telegram.pin(chatId, messageId)

-- React to a message
oc.external.telegram.react(chatId, messageId, "👍")

-- Discover and join external channels
local available = oc.external.discover("discord")
oc.external.join(channelId)
```

### `oc.integrations` — Integration Config & Data

```lua
-- Check if an integration is configured and connected
local info = oc.integrations.status("plausible")
-- info.connected = true/false, info.config = { site_id = "...", ... }

-- Query a dynamic integration's data (Plausible analytics, etc.)
local stats = oc.integrations.query("plausible", "site_stats", {
    site_id = "opencompany.com",
    period = "day",
})

-- Call an integration action
oc.integrations.action("plausible", "get_realtime", {
    site_id = "opencompany.com",
})

-- Test an integration's connection
local ok, err = oc.integrations.test("telegram")
if not ok then
    oc.log("error", "Telegram disconnected: " .. err)
end
```

### `oc.http` — Outbound Webhooks (Sandboxed)

```lua
-- POST to a pre-approved webhook URL (registered in automation config)
oc.http.post(ctx.webhooks.slack_incoming, {
    text = "Build completed: " .. build.status,
})

-- POST to registered webhook with custom headers
oc.http.post(ctx.webhooks.custom_endpoint, {
    event = "task_completed",
    task_id = task.id,
}, {
    headers = { ["X-Api-Key"] = ctx.secrets.api_key },
})
```

> **Note:** `oc.http` only allows URLs pre-registered in the automation's webhook whitelist. No arbitrary network access.

### `oc.util` — Utilities

```lua
-- Current time
local now = oc.now()           -- ISO 8601
local today = oc.today()       -- YYYY-MM-DD
local day = oc.weekday()       -- "Monday", "Tuesday", etc.

-- Logging
oc.log("Processing complete")
oc.log("error", "Failed to send notification")

-- JSON handling
local data = oc.json.decode(rawString)
local str = oc.json.encode(data)

-- String templates
local msg = oc.template("Hello {{name}}, your task {{title}} is {{status}}", {
    name = task.agent.name,
    title = task.title,
    status = task.status,
})
```

### Context Object

Every Lua script receives a `ctx` object with trigger context:

```lua
-- Available in every script:
ctx.trigger_type   -- "schedule" | "task_created" | "task_completed" | "webhook" | ...
ctx.automation_id  -- UUID of the automation
ctx.run_number     -- Execution count
ctx.last_run_at    -- ISO timestamp of previous run (nil if first)

-- Event-triggered scripts also get:
ctx.event          -- The triggering event data
ctx.event.task     -- Task object (for task_* triggers)
ctx.event.user     -- User who triggered the event
ctx.event.changes  -- What changed (for update triggers)

-- Scheduled scripts also get:
ctx.schedule       -- Cron expression
ctx.timezone       -- Configured timezone

-- External message triggers also get:
ctx.event.message        -- The incoming message object
ctx.event.channel_id     -- External channel UUID
ctx.event.provider       -- "telegram" | "discord" | ...
ctx.event.agent_id       -- Agent assigned to this external channel
ctx.event.external_user  -- { id, username, display_name }

-- Webhook triggers also get:
ctx.event.body           -- Parsed JSON body
ctx.event.headers        -- Request headers
ctx.event.method         -- "POST" | "GET"

-- Automation config (pre-registered URLs and secrets):
ctx.webhooks             -- { slack_incoming = "https://...", ... }
ctx.secrets              -- { api_key = "...", ... } (encrypted at rest)
ctx.config               -- Custom key-value config for this automation
```

## Concrete Examples

### 1. Daily Standup Summary (Scheduled)

```lua
-- Scheduled: 0 9 * * 1-5 (weekdays at 9am)
-- Gathers yesterday's completed tasks and today's active tasks, posts summary

local yesterday = oc.date.add(oc.today(), -1, "day")

local completed = oc.tasks.query({
    status = "completed",
    completed_after = yesterday .. "T00:00:00",
})

local active = oc.tasks.query({
    status = { "active", "paused" },
})

local lines = { "**Daily Standup** — " .. oc.today() .. "\n" }

table.insert(lines, "### Completed Yesterday (" .. #completed .. ")")
for _, task in ipairs(completed) do
    local agent = task.agent and task.agent.name or "Unassigned"
    table.insert(lines, "- ✅ " .. task.title .. " (" .. agent .. ")")
end

table.insert(lines, "\n### Active Today (" .. #active .. ")")
for _, task in ipairs(active) do
    local agent = task.agent and task.agent.name or "Unassigned"
    local priority = task.priority == "urgent" and " 🔴" or ""
    table.insert(lines, "- 🔵 " .. task.title .. " (" .. agent .. ")" .. priority)
end

if #active == 0 and #completed == 0 then
    table.insert(lines, "\n_No task activity. Quiet day._")
end

oc.chat.send_to("general", table.concat(lines, "\n"))
```

### 2. Inbox Count Check (Scheduled)

```lua
-- Scheduled: */30 * * * * (every 30 minutes)
-- Checks unread email count via agent tool, only notifies if above threshold

local threshold = 20

local inbox = oc.tables.query("Email Inbox", {
    where = { is_read = false },
})

if #inbox > threshold then
    oc.chat.send_to("dm-rutger-atlas",
        "📬 You have **" .. #inbox .. "** unread emails " ..
        "(threshold: " .. threshold .. "). " ..
        "Top senders: " .. oc.util.top_values(inbox, "sender", 3)
    )
end
```

### 3. Task Completion → Notify Channel (Event-Triggered)

```lua
-- Trigger: task_completed
-- Notifies the task's channel when a task completes

local task = ctx.event.task

-- Skip if no channel assigned
if not task.channel_id then return end

-- Skip delegation tasks (agent-to-agent)
if task.source == "agent_delegation" then return end

local agent_name = task.agent and task.agent.name or "Someone"
local duration = oc.util.duration_human(task.started_at, task.completed_at)

oc.chat.send(task.channel_id,
    "✅ **" .. task.title .. "** completed by " .. agent_name ..
    (duration and " (" .. duration .. ")" or "")
)
```

### 4. Status Change → Auto-Assign Agent (Event-Triggered)

```lua
-- Trigger: task_created
-- Assigns tasks to agents based on type and priority

local task = ctx.event.task

-- Skip if already assigned
if task.agent_id then return end

local assignment = {
    ticket   = "a2",  -- Nova handles tickets
    research = "a3",  -- Sage handles research
    content  = "a4",  -- Muse handles content
    analysis = "a3",  -- Sage handles analysis
}

local agent_id = assignment[task.type]

if agent_id then
    oc.tasks.update(task.id, { agent_id = agent_id })
    oc.log("Auto-assigned " .. task.type .. " task to agent " .. agent_id)
else
    -- No mapping — assign to manager
    oc.tasks.update(task.id, { agent_id = "a1" })
    oc.log("No type mapping for '" .. task.type .. "', assigned to Atlas")
end
```

### 5. Lead Scoring with Table Query (Scheduled)

```lua
-- Scheduled: 0 */6 * * * (every 6 hours)
-- Scores leads in a data table and flags high-value ones

local leads = oc.tables.query("Leads", {
    where = { status = "new" },
})

local hot_leads = {}

for _, lead in ipairs(leads) do
    local score = 0

    if lead.company_size and lead.company_size > 100 then score = score + 30 end
    if lead.source == "referral" then score = score + 25 end
    if lead.budget and lead.budget > 10000 then score = score + 20 end
    if lead.responded then score = score + 15 end
    if lead.visited_pricing then score = score + 10 end

    oc.tables.update_row("Leads", lead.id, { score = score })

    if score >= 60 then
        table.insert(hot_leads, lead)
    end
end

if #hot_leads > 0 then
    local names = {}
    for _, l in ipairs(hot_leads) do
        table.insert(names, "- **" .. l.name .. "** (score: " .. l.score .. ")")
    end
    oc.chat.send_to("sales",
        "🔥 **" .. #hot_leads .. " hot leads** flagged:\n" .. table.concat(names, "\n")
    )
end
```

### 6. Hybrid: Lua Filters, Agent Reasons (Event-Triggered)

```lua
-- Trigger: task_created (source = "chat")
-- Lua does cheap filtering, only escalates to agent when reasoning is needed

local task = ctx.event.task

-- Fast deterministic checks — no agent needed
if task.priority == "low" then
    -- Low priority: just acknowledge, don't bother an agent
    oc.tasks.update(task.id, { agent_id = "a1" })
    return
end

if task.type == "ticket" and task.priority == "urgent" then
    -- Urgent tickets always go to Nova
    oc.tasks.update(task.id, { agent_id = "a2" })
    oc.chat.send(task.channel_id, "🚨 Urgent ticket routed to Nova.")
    return
end

-- Complex case: need agent reasoning to decide assignment
-- Only NOW do we pay the token cost
oc.agent.prompt("a1", task.channel_id,
    "New task needs assignment: \"" .. task.title .. "\"\n" ..
    "Type: " .. task.type .. ", Priority: " .. task.priority .. "\n" ..
    "Description: " .. (task.description or "none") .. "\n\n" ..
    "Decide which agent should handle this and assign it."
)
```

### 7. Webhook → Process External Data

```lua
-- Trigger: webhook (receives JSON body)
-- Processes incoming webhook from external service

local payload = ctx.event.body

if payload.event == "payment.received" then
    oc.chat.send_to("finance",
        "💰 Payment received: **$" .. payload.amount .. "** from " .. payload.customer_name
    )
    oc.tables.create_row("Payments", {
        customer = payload.customer_name,
        amount = payload.amount,
        date = oc.today(),
        reference = payload.reference_id,
    })

elseif payload.event == "payment.failed" then
    -- Payment failures need human judgment — escalate
    oc.agent.delegate("a1", {
        title = "Payment failure: " .. payload.customer_name,
        description = oc.json.encode(payload),
        priority = "high",
    })
end
```

### 8. Approval Workflow (Event-Triggered)

```lua
-- Trigger: approval_granted
-- Moves approved items to the next stage

local item = ctx.event.item
local approval = ctx.event.approval

local next_status = {
    draft     = "review",
    review    = "approved",
    approved  = "published",
}

local new_status = next_status[item.status]

if new_status then
    oc.lists.update_item(item.id, { status = new_status })
    oc.chat.send(item.channel_id or ctx.event.channel_id,
        "✅ **" .. item.title .. "** approved by " .. approval.user.name ..
        " → moved to *" .. new_status .. "*"
    )
else
    oc.log("warn", "No next status mapped for '" .. item.status .. "'")
end
```

## External Integration Examples

These examples show Lua scripts interacting with external platforms (Telegram, Discord, Plausible, webhooks, Slack) — the primary use case where lightweight scripts avoid costly agent spawns for routine cross-platform automation.

### 9. Telegram Digest → Forward to Channel (Scheduled)

```lua
-- Scheduled: 0 18 * * * (daily at 6pm)
-- Summarizes the day's activity and sends to the team's Telegram group

local completed = oc.tasks.query({
    status = "completed",
    completed_after = oc.today() .. "T00:00:00",
})

local active = oc.tasks.query({ status = "active" })

local lines = { "<b>📊 Daily Digest — " .. oc.today() .. "</b>\n" }

if #completed > 0 then
    table.insert(lines, "<b>Completed today (" .. #completed .. "):</b>")
    for _, task in ipairs(completed) do
        table.insert(lines, "✅ " .. task.title)
    end
end

if #active > 0 then
    table.insert(lines, "\n<b>Still active (" .. #active .. "):</b>")
    for _, task in ipairs(active) do
        local flag = task.priority == "urgent" and " 🔴" or ""
        table.insert(lines, "🔵 " .. task.title .. flag)
    end
end

table.insert(lines, "\n<i>Sent from OpenCompany</i>")

-- Send to Telegram group (external channel)
local tg_channels = oc.external.channels({ provider = "telegram" })
for _, ch in ipairs(tg_channels) do
    if ch.name:find("team") then
        oc.external.send(ch.id, table.concat(lines, "\n"))
        break
    end
end
```

### 10. Telegram Incoming → Route by Command (Event-Triggered)

```lua
-- Trigger: external_message (provider = "telegram")
-- Routes Telegram commands to platform actions without spawning an agent

local msg = ctx.event.message
local text = msg.content or ""

-- /status command — reply with task counts
if text:match("^/status") then
    local active = oc.tasks.query({ status = "active" })
    local paused = oc.tasks.query({ status = "paused" })

    oc.external.send(ctx.event.channel_id,
        "📋 Active: " .. #active .. " | Paused: " .. #paused
    )
    return
end

-- /urgent command — list urgent tasks
if text:match("^/urgent") then
    local urgent = oc.tasks.query({ priority = "urgent", status = "active" })
    if #urgent == 0 then
        oc.external.send(ctx.event.channel_id, "No urgent tasks 🎉")
        return
    end

    local lines = { "🚨 <b>Urgent tasks:</b>" }
    for _, t in ipairs(urgent) do
        local agent = t.agent and t.agent.name or "Unassigned"
        table.insert(lines, "• " .. t.title .. " (" .. agent .. ")")
    end
    oc.external.send(ctx.event.channel_id, table.concat(lines, "\n"))
    return
end

-- /find <query> — search workspace from Telegram
local query = text:match("^/find (.+)")
if query then
    local results = oc.tasks.query({ search = query, limit = 5 })
    if #results == 0 then
        oc.external.send(ctx.event.channel_id, "No tasks found for: " .. query)
        return
    end

    local lines = { "🔍 Results for <b>" .. query .. "</b>:" }
    for _, t in ipairs(results) do
        table.insert(lines, "• [" .. t.status .. "] " .. t.title)
    end
    oc.external.send(ctx.event.channel_id, table.concat(lines, "\n"))
    return
end

-- No command matched — let the agent handle it (only pay tokens when needed)
oc.agent.prompt(ctx.event.agent_id, ctx.event.channel_id, text)
```

### 11. Discord Channel Sync (Event-Triggered)

```lua
-- Trigger: task_completed
-- Cross-posts task completions to Discord channels tagged for updates

local task = ctx.event.task

-- Only sync tasks from specific lists
local sync_lists = { "Sprint Board", "Client Deliverables" }
local should_sync = false
for _, name in ipairs(sync_lists) do
    if task.list and task.list.name == name then
        should_sync = true
        break
    end
end

if not should_sync then return end

-- Find Discord channels
local discord_channels = oc.external.channels({ provider = "discord" })
for _, ch in ipairs(discord_channels) do
    if ch.name:find("updates") or ch.name:find("releases") then
        local agent_name = task.agent and task.agent.name or "Someone"
        oc.external.send(ch.id,
            "✅ **" .. task.title .. "** completed by " .. agent_name ..
            " (from " .. task.list.name .. ")"
        )
    end
end
```

### 12. Plausible Analytics → Morning Report (Scheduled)

```lua
-- Scheduled: 0 8 * * * (daily at 8am)
-- Pulls yesterday's analytics from Plausible and posts summary

local status = oc.integrations.status("plausible")
if not status.connected then
    oc.log("warn", "Plausible not connected, skipping report")
    return
end

local yesterday = oc.date.add(oc.today(), -1, "day")

local stats = oc.integrations.query("plausible", "site_stats", {
    period = "day",
    date = yesterday,
})

local realtime = oc.integrations.query("plausible", "get_realtime", {})

local top_pages = oc.integrations.query("plausible", "top_pages", {
    period = "day",
    date = yesterday,
    limit = 5,
})

local lines = {
    "📈 **Analytics Report — " .. yesterday .. "**\n",
    "**Visitors**: " .. (stats.visitors or 0),
    "**Pageviews**: " .. (stats.pageviews or 0),
    "**Bounce rate**: " .. (stats.bounce_rate or "N/A") .. "%",
    "**Avg visit duration**: " .. (stats.visit_duration or 0) .. "s",
    "**Currently online**: " .. (realtime.current_visitors or 0),
}

if top_pages and #top_pages > 0 then
    table.insert(lines, "\n**Top pages:**")
    for i, page in ipairs(top_pages) do
        table.insert(lines, i .. ". " .. page.name .. " — " .. page.visitors .. " visitors")
    end
end

-- Post to workspace channel
oc.chat.send_to("general", table.concat(lines, "\n"))

-- Also forward to Telegram if threshold exceeded
if stats.visitors and stats.visitors > 1000 then
    oc.external.send(ctx.config.telegram_channel_id,
        "🚀 " .. stats.visitors .. " visitors yesterday! New traffic high."
    )
end
```

### 13. Outbound Webhook → Slack Notification (Event-Triggered)

```lua
-- Trigger: approval_granted
-- Sends approval notifications to a Slack channel via incoming webhook

local item = ctx.event.item
local approval = ctx.event.approval

-- Build Slack Block Kit payload
oc.http.post(ctx.webhooks.slack_approvals, {
    blocks = {
        {
            type = "section",
            text = {
                type = "mrkdwn",
                text = "✅ *" .. item.title .. "* approved by " .. approval.user.name,
            }
        },
        {
            type = "context",
            elements = {
                {
                    type = "mrkdwn",
                    text = "Status: " .. item.status .. " → " ..
                           (item.next_status or "approved") ..
                           " | " .. oc.now(),
                }
            }
        }
    }
})

-- Also notify Microsoft Teams if configured
if ctx.webhooks.teams_general then
    oc.http.post(ctx.webhooks.teams_general, {
        ["@type"] = "MessageCard",
        summary = "Approval: " .. item.title,
        sections = {
            {
                activityTitle = "✅ Approved: " .. item.title,
                activitySubtitle = "by " .. approval.user.name,
                facts = {
                    { name = "Previous Status", value = item.status },
                    { name = "Time", value = oc.now() },
                },
            }
        }
    })
end
```

### 14. External Channel Health Monitor (Scheduled)

```lua
-- Scheduled: 0 */4 * * * (every 4 hours)
-- Checks all external integrations and reports any connectivity issues

local integrations = { "telegram", "plausible" }
local issues = {}

for _, name in ipairs(integrations) do
    local ok, err = oc.integrations.test(name)
    if not ok then
        table.insert(issues, "❌ **" .. name .. "**: " .. (err or "connection failed"))
    end
end

-- Check external channels for stale connections (no messages in 48h)
local ext_channels = oc.external.channels()
local stale_threshold = oc.date.add(oc.now(), -48, "hours")

for _, ch in ipairs(ext_channels) do
    if ch.last_message_at and ch.last_message_at < stale_threshold then
        table.insert(issues,
            "⚠️ **" .. ch.name .. "** (" .. ch.external_provider .. "): " ..
            "no messages for 48h+"
        )
    end
end

if #issues > 0 then
    oc.chat.send_to("admin",
        "🔌 **Integration Health Check**\n\n" .. table.concat(issues, "\n")
    )
else
    oc.log("All integrations healthy")
end
```

### 15. Hybrid: Telegram Approval Flow (Event-Triggered)

```lua
-- Trigger: approval_requested
-- Sends approval request to approver's Telegram, handles callback buttons

local item = ctx.event.item
local approver = ctx.event.approver

-- Check if approver has a linked Telegram identity
local tg_identity = oc.external.user_identity(approver.id, "telegram")

if not tg_identity then
    -- Fallback: notify in workspace
    oc.chat.send_to("dm-" .. approver.name:lower(),
        "📋 Approval needed: **" .. item.title .. "**"
    )
    return
end

-- Send to Telegram with inline keyboard buttons
oc.external.telegram.send(tg_identity.external_id, {
    text = "📋 <b>Approval Request</b>\n\n" ..
           "<b>" .. item.title .. "</b>\n" ..
           "Requested by: " .. ctx.event.requester.name .. "\n" ..
           "List: " .. (item.list_name or "N/A"),
    reply_markup = {
        inline_keyboard = {
            {
                { text = "✅ Approve", callback_data = "approve:" .. item.id },
                { text = "❌ Reject", callback_data = "reject:" .. item.id },
            }
        }
    },
    parse_mode = "html",
})

oc.log("Approval request sent to " .. approver.name .. " via Telegram")
```

## Agent-Generated Scripts

The most powerful pattern: **agents write Lua scripts on behalf of users**. Instead of the agent running every 5 minutes, it writes a script once and deploys it.

### The `generate_lua_script` Tool

A new tool available to agents that creates and deploys Lua automations:

```php
class GenerateLuaScript implements Tool
{
    public function description(): string
    {
        return 'Generate and deploy a Lua automation script. '
             . 'Use this when a user asks for a recurring/triggered automation '
             . 'that can be expressed as deterministic logic.';
    }

    public function handle(Request $request): string
    {
        $code = $request['code'];
        $name = $request['name'];
        $triggerType = $request['trigger_type']; // 'schedule' | 'task_created' | ...
        $triggerConfig = $request['trigger_config']; // cron expression or conditions

        // Validate Lua syntax
        $errors = LuaSandbox::validate($code);
        if ($errors) {
            return "Lua syntax error: {$errors}. Fix and try again.";
        }

        // Dry-run in sandbox (catches runtime errors)
        $dryRun = LuaSandbox::dryRun($code, $this->buildMockContext($triggerType));
        if ($dryRun->hasErrors()) {
            return "Runtime error in dry run: {$dryRun->error}. Fix and try again.";
        }

        // Deploy
        $automation = ScheduledAutomation::create([
            'name' => $name,
            'script_language' => 'lua',
            'script_content' => $code,
            'trigger_type' => $triggerType,
            'trigger_config' => $triggerConfig,
            'created_by_id' => $this->agent->id,
        ]);

        return "Lua automation deployed: '{$name}' (ID: {$automation->id}). "
             . "Trigger: {$triggerType}. Dry run passed.";
    }
}
```

### Agent System Prompt Addition

Add to the agent's TOOLS.md or system prompt:

```markdown
## Lua Automations

When a user asks for a recurring or triggered automation that follows deterministic
logic (no reasoning required), prefer generating a Lua script over running it yourself
repeatedly.

**Use Lua when:**
- The logic is if/then/else with no ambiguity
- The automation runs frequently (hourly or more)
- The task is data transformation, routing, or notification

**Use yourself (agent) when:**
- Content analysis or generation is needed
- The decision requires judgment or context
- The user explicitly wants your reasoning

**Lua API available:**
- `oc.chat.send(channelId, message)` — send message
- `oc.tasks.query(filters)` — query tasks
- `oc.tasks.create(data)` / `oc.tasks.update(id, data)` — manage tasks
- `oc.tables.query(tableName, filters)` — query data tables
- `oc.external.channels(filters)` — list external channels
- `oc.external.send(channelId, message)` — send to external channel
- `oc.external.telegram.send(chatId, opts)` — Telegram-specific messaging
- `oc.integrations.query(name, action, params)` — query integration data
- `oc.integrations.test(name)` — test integration connectivity
- `oc.http.post(webhookUrl, body)` — POST to pre-approved webhook URLs
- `oc.agent.prompt(agentId, channelId, prompt)` — escalate to agent
- `oc.log(message)` — log output
- `ctx.*` — trigger context (event data, schedule info, webhooks, secrets)

**Always validate:** Call generate_lua_script which dry-runs before deploying.
```

### Example Conversation: Agent Writes a Lua Script

```
User: Hey Atlas, can you check my inbox every 30 minutes and ping me
      if I have more than 20 unread emails?

Atlas: I'll set that up as a lightweight Lua script so it runs instantly
       without needing me each time.

       [calls generate_lua_script with:
        name: "Inbox threshold alert"
        trigger_type: "schedule"
        trigger_config: {"cron": "*/30 * * * *"}
        code: <the inbox check script from Example 2 above>
       ]

Atlas: Done! I've deployed "Inbox threshold alert" — it runs every 30
       minutes and will message you when unread count exceeds 20. You
       can manage it from the Automations page, or tell me to adjust
       the threshold.
```

### Example Conversation: Agent Writes an Integration Script

```
User: I want to get our Plausible stats posted to our Telegram group
      every morning at 8am. Include visitor count and top pages.

Atlas: That's a great fit for a Lua script — deterministic data pull
       and formatting, no reasoning needed. Let me set that up.

       [calls generate_lua_script with:
        name: "Plausible daily report → Telegram"
        trigger_type: "schedule"
        trigger_config: {"cron": "0 8 * * *"}
        code: <a tailored version of Example 12 above, with
               oc.external.send() targeting the Telegram channel>
       ]

Atlas: Deployed! "Plausible daily report → Telegram" runs at 8am
       daily. It pulls visitors, pageviews, bounce rate, and top 5
       pages from Plausible, then sends to your team Telegram group.
       Zero token cost per run. Want me to adjust anything?
```

### Example Conversation: Agent Builds a Telegram Command Router

```
User: Can you make it so when someone sends /urgent in our Telegram
      chat it shows all urgent tasks?

Atlas: I'll set up a Lua command router on that channel. It'll handle
       /urgent instantly without involving me each time. I can also
       add /status and /find commands while I'm at it.

       [calls generate_lua_script with:
        name: "Telegram command router"
        trigger_type: "external_message"
        trigger_config: {"provider": "telegram", "channel_id": "..."}
        code: <Example 10 above>
       ]

Atlas: Done! Your Telegram channel now responds to:
       • /status — shows active/paused task counts
       • /urgent — lists all urgent tasks
       • /find <query> — searches tasks by keyword
       Anything else falls through to me for a proper response.
```

### Prompt Patterns for Reliable Lua Generation

When agents generate Lua, include these constraints in the tool prompt:

```
Generate a Lua script for the OpenCompany platform.

Rules:
1. Use only the oc.* API — no require(), no io, no os, no loadfile
2. Keep scripts under 100 lines
3. Always handle nil values with `or` defaults
4. Use ctx.* for trigger context
5. Return early for no-op cases (guard clauses at top)
6. Log important actions with oc.log()
7. For complex decisions, use oc.agent.prompt() to escalate
8. For external messaging, use oc.external.send() or provider-specific APIs
9. For outbound webhooks, use oc.http.post() with ctx.webhooks URLs only
10. Check oc.integrations.status() before querying external services
```

## Staged Rollout

### Phase 1: Deterministic PHP Actions (Now)

No Lua yet. Wire up the existing `ListAutomationRule` model:

- Implement event listeners for `task_created`, `task_completed`, `task_assigned`
- Build PHP action executors: `CreateTaskAction`, `SendNotificationAction`, `UpdateTaskAction`, `AssignTaskAction`
- These read `action_config` JSON and execute directly — no agent, no script
- Add `trigger_conditions` evaluation using simple PHP matching (`array_intersect`, field comparisons)

**Effort:** ~2–3 days. Eliminates agent spawns for the 5 most common action types.

### Phase 2: Expression Evaluation (When needed)

Add Symfony ExpressionLanguage for `trigger_conditions`:

```php
$expr = new ExpressionLanguage();
$shouldFire = $expr->evaluate(
    'task.priority == "urgent" and task.type == "ticket"',
    ['task' => $task->toArray()]
);
```

- PHP-native, no extension needed
- Covers conditional logic without a full runtime
- Existing `trigger_conditions` JSON can store expression strings

**Effort:** ~1 day. Adds conditional logic to Phase 1 actions.

### Phase 3: Full Lua Runtime (When users need custom logic)

The full architecture described in this document:

- Install `php-lua` PECL extension (or use Process fallback)
- Build `LuaSandbox` class with API bindings
- Build `LuaExecutor` job class
- Add `script_language` and `script_content` columns to automations
- Build `generate_lua_script` agent tool
- Add script editor UI (Monaco with Lua syntax highlighting)

**Effort:** ~1–2 weeks. Full scripting capability.

### Phase 4: Agent-Assisted Script Management

- Agents can read, modify, and debug deployed Lua scripts
- Execution logs visible in automation detail page
- Script versioning (rollback to previous version)
- Test harness: run script with mock context in the UI

## Security

### Sandbox Constraints

```php
class LuaSandbox
{
    // Removed from Lua global environment:
    private const BLOCKED_GLOBALS = [
        'io', 'os', 'loadfile', 'dofile', 'require',
        'load', 'rawset', 'rawget', 'rawequal', 'rawlen',
        'collectgarbage', 'newproxy', 'debug',
    ];

    // Execution limits:
    private const MAX_EXECUTION_TIME = 5;     // seconds
    private const MAX_MEMORY = 10_485_760;    // 10MB
    private const MAX_OUTPUT_SIZE = 65_536;   // 64KB
    private const MAX_API_CALLS = 50;         // per execution

    // Rate limits per automation:
    private const MAX_RUNS_PER_MINUTE = 12;
    private const MAX_RUNS_PER_HOUR = 360;
}
```

### API Call Permissions

Each automation has a permission scope based on its creator:

```lua
-- Automations can only access:
-- 1. Channels the creator has access to
-- 2. Tasks in lists the creator can see
-- 3. Tables the creator can query
-- 4. Agent delegation only to agents the creator manages
```

### Error Handling

```php
class LuaExecutor
{
    public function execute(string $script, array $context): LuaResult
    {
        try {
            $sandbox = new LuaSandbox($script, $this->buildApi($context));
            $sandbox->setTimeLimit(self::MAX_EXECUTION_TIME);
            $sandbox->setMemoryLimit(self::MAX_MEMORY);

            $result = $sandbox->run();

            return new LuaResult(
                success: true,
                output: $result->output,
                api_calls: $result->apiCallCount,
                duration_ms: $result->durationMs,
            );
        } catch (LuaTimeoutException $e) {
            return LuaResult::failed("Script exceeded 5-second time limit");
        } catch (LuaMemoryException $e) {
            return LuaResult::failed("Script exceeded 10MB memory limit");
        } catch (LuaSyntaxException $e) {
            return LuaResult::failed("Syntax error: " . $e->getMessage());
        } catch (\Throwable $e) {
            return LuaResult::failed("Runtime error: " . $e->getMessage());
        }
    }
}
```

## Schema Changes

### New columns on `scheduled_automations`

```sql
ALTER TABLE scheduled_automations
    ADD COLUMN script_language VARCHAR(10) DEFAULT NULL,  -- 'lua' | null (null = agent prompt)
    ADD COLUMN script_content TEXT DEFAULT NULL;           -- Lua source code
```

### New columns on `list_automation_rules`

```sql
ALTER TABLE list_automation_rules
    ADD COLUMN script_language VARCHAR(10) DEFAULT NULL,
    ADD COLUMN script_content TEXT DEFAULT NULL;
```

### New table: `automation_execution_log`

```sql
CREATE TABLE automation_execution_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    automation_type VARCHAR(50) NOT NULL,     -- 'scheduled' | 'rule'
    automation_id UUID NOT NULL,
    script_language VARCHAR(10),              -- 'lua' | 'agent' | 'php'
    success BOOLEAN NOT NULL DEFAULT TRUE,
    duration_ms INTEGER,
    api_calls INTEGER DEFAULT 0,
    tokens_used INTEGER DEFAULT 0,            -- 0 for Lua, >0 for agent
    output TEXT,                              -- Script output / agent response
    error TEXT,                               -- Error message if failed
    context JSONB,                            -- Trigger context snapshot
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_exec_log_automation ON automation_execution_log(automation_type, automation_id);
CREATE INDEX idx_exec_log_created ON automation_execution_log(created_at);
```

This table replaces the current `last_result` JSON field for detailed execution history, and enables cost comparison dashboards (Lua $0 vs agent $X per run).
