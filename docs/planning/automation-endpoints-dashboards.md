# Automation Endpoints & Agent-Built Dashboards

## Vision

Turn automations into API endpoints and let agents build full internal tools — HTML dashboards with Tailwind that read and write data through Lua-powered endpoints. The platform evolves from "agents that chat" into "agents that build internal apps."

## Core Concept

Three pillars — **Humans, Agents, Code** — working together:

1. **Lua endpoint automations** serve as serverless API functions
2. **HTML+Tailwind dashboard pages** act as the frontend
3. **Agents create both** end-to-end from a single user prompt

## Architecture

### Automation as API Endpoint

New trigger type `endpoint` (alongside existing `schedule`). Each gets a unique URL:

```
GET  /api/w/{workspace}/endpoints/{slug}
POST /api/w/{workspace}/endpoints/{slug}
```

The Lua code runs via the existing `LuaSandboxService`, uses the `app.*` bridge to query/mutate data, and returns a table that gets serialized to JSON.

**Read endpoint example:**

```lua
-- GET: return dashboard data
local tasks = app.tasks.list({ status = "active" })
local channels = app.channels.list()

return {
  active_tasks = #tasks,
  messages_today = app.messages.count({ since = "today" }),
  agents = app.agents.list(),
}
```

**Write endpoint example:**

```lua
-- POST: submit feedback
local input = request.body

app.tables.addRow("feedback_tracker", {
  title = input.title,
  category = input.category,
  submitted_by = request.user,
  status = "new",
  created_at = os.date()
})

-- Notify a channel
app.channels.send("general", "New feedback: " .. input.title)

return { success = true }
```

The Lua bridge context includes `request.body`, `request.method`, `request.query`, and `request.user` so the script knows what it received and who sent it.

### Dashboard Pages

A new entity stored in the workspace — plain HTML+Tailwind rendered in a sandboxed iframe (`srcdoc` with CSP). No build step needed; Tailwind and chart libraries loaded via CDN.

```html
<div class="p-6 grid grid-cols-3 gap-4">
  <div class="bg-white rounded-xl p-4 shadow">
    <div class="text-sm text-gray-500">Active Tasks</div>
    <div class="text-3xl font-bold" id="tasks">--</div>
  </div>
  <!-- more cards, tables, charts... -->
</div>

<script>
  const data = await fetch('/api/w/my-workspace/endpoints/metrics');
  document.getElementById('tasks').textContent = data.active_tasks;
</script>
```

Dashboards can also include forms that POST to write endpoints:

```html
<form onsubmit="submitFeedback(event)">
  <input name="title" placeholder="What's the issue?" class="border rounded px-3 py-2 w-full" />
  <select name="category" class="border rounded px-3 py-2">
    <option>Bug</option>
    <option>Feature Request</option>
    <option>Question</option>
  </select>
  <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Submit</button>
</form>
```

### Agent Workflow

User: *"Build me a feedback tracker with a submission form and a table of all submissions."*

The agent:
1. Creates a Tables table called "feedback_tracker" with columns (title, category, status, submitted_by, created_at)
2. Creates a GET endpoint automation that returns all rows from the table
3. Creates a POST endpoint automation that inserts a new row and notifies a channel
4. Creates an HTML dashboard page with a form (POST) and a data table (GET)
5. Wires them together

## Use Cases

- Feedback trackers
- Bug report forms
- Approval workflows with action buttons
- Survey/poll pages
- Simple CRMs
- Onboarding checklists
- Incident report forms
- KPI dashboards with live charts
- Agent activity monitors

## What Needs to Be Built

| Component | Effort | Details |
|-----------|--------|---------|
| `endpoint` trigger type on Automation model | Small | New trigger type, slug field, method (GET/POST/both) |
| Endpoint controller | Small | Runs Lua via `LuaSandboxService` with `LuaBridge`, returns JSON |
| Request context in Lua bridge | Small | Expose `request.body`, `request.method`, `request.query`, `request.user` |
| Dashboard model + CRUD | Medium | New model: name, slug, html_content, workspace_id |
| Dashboard viewer (iframe renderer) | Small | `srcdoc` iframe with CSP headers |
| Dashboard list page in UI | Medium | Vue page listing dashboards with create/edit/view |
| Agent tool: `create_dashboard` | Small | Creates/updates dashboard HTML |
| Agent tool: `create_endpoint` | Small | Creates endpoint automation with Lua code |
| Auth on endpoint routes | Small | Workspace-scoped, session or token auth |

## Design Principles

- **No build step** — plain HTML, Tailwind via CDN, Chart.js via CDN
- **Separation of concerns** — data logic (Lua) vs. presentation (HTML) cleanly split
- **Composable** — one dashboard can consume multiple endpoints
- **Agent-friendly** — LLMs are excellent at generating both Lua and HTML+Tailwind
- **Live data** — dashboards fetch on load or on interval, always fresh
- **Shareable** — anyone in the workspace can view them

## Future Considerations

- Visual dashboard editor (drag-and-drop cards/widgets)
- Real-time updates via WebSocket instead of polling
- Dashboard templates (pre-built layouts agents can customize)
- Public/shareable dashboard links (no auth required)
- QuickJS (JavaScript) as alternative to Lua for endpoint logic
