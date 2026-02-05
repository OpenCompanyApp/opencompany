# Agent Detail

> Full profile and management page for a single AI agent, with 8 tabbed sections covering identity, tasks, configuration, memory, and settings.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/agent/{id}` |
| **Name** | `agent.show` |
| **Auth** | Required |
| **Layout** | AppLayout |
| **Props** | `id: string` (agent UUID) |

---

## Layout

```
┌──────────────────────────────────────────────────────────────────┐
│  [<- Back]                                                       │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  [emoji]  Agent Name  [type badge]       [Message] [Pause] │  │
│  │  14x14    Status label                                     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────┬───────┬─────────────┬──────────────┬──────────┐       │
│  │ Over │ Tasks │ Personality │ Instructions │ Capab... │ ...   │
│  └──────┴───────┴─────────────┴──────────────┴──────────┘       │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │                                                            │  │
│  │                    Tab Content Area                        │  │
│  │                    (min-h 500px)                           │  │
│  │                                                            │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Header Detail

```
┌────────────────────────────────────────────────────────────────┐
│  ┌──────┐                                                      │
│  │ emoji│  Agent Name   [type badge]     [Message]  [Pause]    │
│  │ 14x14│  Working on current task...                          │
│  │  [*] │                                  ^Link     ^Toggle   │
│  └──────┘                                /messages   pause/    │
│    ^status dot                            /{id}      resume    │
└────────────────────────────────────────────────────────────────┘
```

### Tab: Overview

```
┌────────────────────────────────────────────────────────────────┐
│  AgentIdentityCard                                             │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [emoji] Name [type]     Efficiency: 94%  Tasks: 127     │  │
│  │         Status          Sessions: 89                     │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Current Task (if active)                                      │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Implementing user authentication                         │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Recent Activity                              [View all ->]    │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [icon] Activity description             timestamp        │  │
│  │ [icon] Activity description             timestamp        │  │
│  │ ...up to 5 items                                         │  │
│  └──────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

### Tab: Tasks

```
┌────────────────────────────────────────────────────────────────┐
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [status] [type]                              [type icon] │  │
│  │ Task Title                                   2/5 steps   │  │
│  │ Description preview...                                   │  │
│  └──────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [status] [type]                              [type icon] │  │
│  │ Task Title                                   3/3 steps   │  │
│  └──────────────────────────────────────────────────────────┘  │
│  ...                                                           │
└────────────────────────────────────────────────────────────────┘

Clicking a task opens TaskDetailDrawer (right-side panel, 480px)
```

### Tab: Personality / Instructions

```
┌────────────────────────────────────────────────────────────────┐
│  Personality / Instructions   [Unsaved changes]  [Save]        │
│  Define behavior guidelines...                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [Edit] [Preview]                                         │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │                                                          │  │
│  │  Markdown textarea (16 rows, monospace)                  │  │
│  │  or rendered HTML preview                                │  │
│  │                                                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│  Last updated Jan 30, 2025                                     │
└────────────────────────────────────────────────────────────────┘
```

### Tab: Capabilities

```
┌────────────────────────────────────────────────────────────────┐
│  Available Tools                                               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [icon] Code execution            [Approval required]  [v]│  │
│  │        Run Node.js and Python code                       │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ [icon] File operations                                [v]│  │
│  │        Read, write, and edit files                       │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ [icon] Production deployment                          [x]│  │
│  │        Deploy to production servers                      │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Tool Notes                                        [Edit]      │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Preferred test framework: Jest                           │  │
│  │ Code style: ESLint + Prettier                            │  │
│  └──────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

### Tab: Memory

```
┌────────────────────────────────────────────────────────────────┐
│  Current Session                                               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Started 2h ago                [View History] [New Session]│  │
│  │ 24 messages                                              │  │
│  │                                                          │  │
│  │ Context Usage                         45k / 128k         │  │
│  │ [=============================--------] 35%              │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Persistent Memory                                    [+ Add]  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ API credentials stored in vault        Jan 30    [trash] │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ User prefers TypeScript over JS        Jan 28    [trash] │  │
│  └──────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

### Tab: Activity

```
┌────────────────────────────────────────────────────────────────┐
│  Activity Log                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [icon] Implementing auth module    Jan 5, 2:30  [badge]  │  │
│  │ [icon] Fixed DB timeout            Jan 5, 2:00  [badge]  │  │
│  │ [icon] Reviewed PR #234            Jan 5, 12:30 [badge]  │  │
│  │ ...scrollable, max-h 600px                               │  │
│  └──────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

### Tab: Settings

```
┌────────────────────────────────────────────────────────────────┐
│  Behavior Mode                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ [Autonomous] [Supervised] [Strict]                       │  │
│  │ Agent requests approval for significant decisions...     │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Session Reset                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Reset mode: [Daily at specific hour v]                   │  │
│  │ Reset at:   [4:00 AM v]                                  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                │
│  Danger Zone                                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Reset Memory   Clear all persistent memories    [Reset]  │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ Pause Agent    Stop all current tasks           [Pause]  │  │
│  ├──────────────────────────────────────────────────────────┤  │
│  │ Delete Agent   Permanently remove agent        [Delete]  │  │
│  └──────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

---

## Components

| Component | Purpose |
|-----------|---------|
| `AgentIdentityCard` | Summary card with avatar, name, type badge, status indicator, and stats row (efficiency, tasks, sessions) |
| `AgentPersonalityEditor` | Markdown editor with Edit/Preview toggle for personality guidelines; tracks unsaved changes |
| `AgentInstructionsEditor` | Markdown editor with Edit/Preview toggle for operating instructions; identical layout to personality editor |
| `AgentCapabilities` | Lists agent tools with enabled/disabled status, approval badges, and editable tool notes section |
| `AgentMemoryView` | Displays current session context usage bar, persistent memory entries list, and "Add Memory" modal |
| `AgentSettingsPanel` | Behavior mode selector (autonomous/supervised/strict), session reset policy, and danger zone actions |
| `TaskDetailDrawer` | Right-side slide-over drawer (480px) showing task metadata, step timeline, result/error, and action buttons |
| `SharedSkeleton` | Placeholder shimmer used during loading state |
| `Icon` | Shared Iconify wrapper using Phosphor icons (`ph:` prefix) |

---

## Features & Interactions

### Navigation
- **Back button** at top-left calls `window.history.back()`
- **Message button** links to `/messages/{agentId}` (redirects to unified chat)

### Tab Switching
- 8 tabs: Overview, Tasks, Personality, Instructions, Capabilities, Memory, Activity, Settings
- Pill-style tab buttons with active state (dark bg, white text)
- Tabs horizontally scrollable with `overflow-x-auto`

### Pause/Resume Toggle
- Button text and icon change based on `agent.status`
- Working agents show "Pause" (amber); idle/paused agents show "Resume" (green)

### Agent Tasks
- Fetched via `useApi().fetchAgentTasks({ agentId })`
- Each task card shows status badge, type label, title, description, and step progress
- Clicking a task opens `TaskDetailDrawer`
- Task statuses: pending, active, paused, completed, failed, cancelled
- Task types: ticket, request, analysis, content, research, custom

### Personality & Instructions Editors
- Markdown content stored as string, rendered via `marked` library
- Unsaved changes indicator (amber text)
- Save button appears only when changes detected
- Last updated timestamp shown below editor

### Capabilities Management
- Read-only list of tool capabilities with enabled/disabled and approval-required badges
- Tool notes section editable inline with save/cancel buttons

### Memory Management
- Current session displays: start time, message count, context token usage bar
- Context usage shows percentage and warning when above 80%
- "New Session" and "View History" buttons
- Persistent memory entries listed with delete-on-hover icon
- "Add Memory" opens a modal with content textarea and category select (note, fact, preference, context)

### Settings
- Behavior mode: autonomous, supervised, strict (three toggle buttons)
- Session reset: daily at hour, after idle period, or manual
- Danger zone: reset memory, pause agent, delete agent (red-bordered section)

---

## States

| State | Description |
|-------|-------------|
| **Loading** | Skeleton placeholders: rounded rectangle for header (h-32), 7 small pill placeholders for tabs, large rectangle for content (h-96) |
| **Loaded** | Full agent profile with all tabs functional |
| **Not Found** | Centered robot icon, "Agent not found" heading and description |
| **Empty Tasks** | Centered check-square icon, "No tasks assigned to this agent" text |
| **Empty Activity** | Centered activity icon, "No activity recorded" text |
| **Empty Memory** | Brain icon, "No persistent memories" with hint text |
| **Empty Capabilities** | Wrench icon, "No capabilities configured" text |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Desktop** | `max-w-4xl` centered content with `p-6` padding; TaskDetailDrawer is 480px wide |
| **Mobile** | Content fills width; tabs scroll horizontally; TaskDetailDrawer is full-width |

---

## API Calls

| Endpoint | Method | Trigger |
|----------|--------|---------|
| Agent detail | Mock/simulated | `onMounted`, `watch(props.id)` |
| `/api/agent-tasks?agentId={id}` | GET | After agent data loads |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Agent/Show.vue` | Page component with tabs, header, and data orchestration |
| `resources/js/Components/agents/AgentIdentityCard.vue` | Agent summary card with avatar and stats |
| `resources/js/Components/agents/AgentPersonalityEditor.vue` | Markdown editor for personality config |
| `resources/js/Components/agents/AgentInstructionsEditor.vue` | Markdown editor for instructions config |
| `resources/js/Components/agents/AgentCapabilities.vue` | Capabilities list with tool notes |
| `resources/js/Components/agents/AgentMemoryView.vue` | Session info and persistent memory management |
| `resources/js/Components/agents/AgentSettingsPanel.vue` | Behavior mode, reset policy, danger zone |
| `resources/js/Components/tasks/TaskDetailDrawer.vue` | Slide-over drawer for task detail with step timeline |
| `resources/js/composables/useApi.ts` | API composable providing `fetchAgentTasks` |
