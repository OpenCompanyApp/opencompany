# Organization

> Visualize the organizational hierarchy of humans and agents in tree or chart view, with summary statistics.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/org` |
| **Name** | `org` |
| **Auth** | Required |
| **Layout** | AppLayout |

---

## Layout

```
+--------------------------------------------------------------------+
| min-h-screen p-6                                                   |
| ┌────────────────────────────────────────────────────────────────┐ |
| │ max-w-7xl mx-auto                                              │ |
| │                                                                │ |
| │ Header                                                         │ |
| │ "Organization"                    [Tree View] [Chart View]     │ |
| │ "View and manage your ..."                                     │ |
| │                                                                │ |
| │ ── Tree View ──────────────────────────────────────────────    │ |
| │                                                                │ |
| │ ┌──────────────────────────────────────────────────────────┐   │ |
| │ │ [avatar] CEO Name                               [2] [v] │   │ |
| │ │   ├── [avatar] CTO Name   Agent Type            [3] [v] │   │ |
| │ │   │     ├── [avatar] Agent1  Coder   "Working"           │   │ |
| │ │   │     ├── [avatar] Agent2  Researcher                  │   │ |
| │ │   │     └── [avatar] Agent3  Ephemeral                   │   │ |
| │ │   └── [avatar] Designer Name  email@...                  │   │ |
| │ └──────────────────────────────────────────────────────────┘   │ |
| │                                                                │ |
| │ ── Chart View ─────────────────────────────────────────────    │ |
| │                                                                │ |
| │                  ┌───────────────┐                              │ |
| │                  │  CEO Name     │                              │ |
| │                  └───────┬───────┘                              │ |
| │              ┌───────────┼───────────┐                         │ |
| │        ┌─────┴─────┐          ┌─────┴─────┐                   │ |
| │        │  CTO      │          │ Designer  │                   │ |
| │        └─────┬─────┘          └───────────┘                   │ |
| │         ┌────┼────┐                                            │ |
| │       [A1] [A2] [A3]                                           │ |
| │                                                                │ |
| │ ────────────────────────────────────────────────────────────   │ |
| │                                                                │ |
| │ Stats (4-column grid)                                          │ |
| │ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────────┐   │ |
| │ │ Total    │ │ Humans   │ │ Agents   │ │ Active Agents    │   │ |
| │ │ Members  │ │          │ │          │ │                  │   │ |
| │ └──────────┘ └──────────┘ └──────────┘ └──────────────────┘   │ |
| └────────────────────────────────────────────────────────────────┘ |
+--------------------------------------------------------------------+
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `OrgTreeNode` (TreeNode) | `Components/org/TreeNode.vue` | Recursive tree node with connector lines, expand/collapse |
| `OrgChartNode` (ChartNode) | `Components/org/ChartNode.vue` | Recursive chart node with vertical/horizontal connectors |
| `Button` | `Components/shared/Button.vue` | Shared button for view mode toggle |
| `Skeleton` | `Components/shared/Skeleton.vue` | Loading skeleton placeholders |
| `StatCard` | `Components/shared/StatCard.vue` | Stat card for summary metrics |
| `AgentAvatar` | `Components/shared/AgentAvatar.vue` | Avatar with status indicator |
| `Icon` | `Components/shared/Icon.vue` | Phosphor icon wrapper |

---

## Features & Interactions

### View Mode Toggle
- Two modes: "Tree View" and "Chart View", toggled via header buttons
- Active mode uses `primary` variant; inactive uses `secondary`
- State stored in `viewMode` ref (`'tree' | 'chart'`)

### Tree View (`OrgTreeNode`)
- Recursive component rendering each node as a clickable card with connector lines
- **Node card**: avatar (with status badge for agents), name (links to `/profile/{id}`), agent type badge, ephemeral badge (amber), email (humans) or current task / status (agents)
- **Expand/collapse**: nodes with children show a count and caret icon; click toggles children visibility
- **Auto-expand**: first 2 levels expanded by default (`depth < 2`)
- **Connector lines**: vertical `border-l` lines for depth, horizontal `border-t` connector to each node
- **Keyboard**: Enter and Space toggle expand; card is focusable with `tabindex="0"`

### Chart View (`OrgChartNode`)
- Recursive component rendering a top-down org chart with centered alignment
- **Node card**: avatar, human/agent icon, name (links to `/profile/{id}`), agent type or email
- **Root styling**: root node has a darker border (`border-neutral-900`)
- **Ephemeral badge**: positioned absolutely at top-right corner with amber styling
- **Connectors**: vertical lines (`w-0.5 h-6`) between parent and children row; horizontal line spanning across children
- **Hover**: scale(1.02) with subtle shadow and upward translate

### Hierarchy Data
- Fetched from `GET /api/users` on mount
- Builds tree from flat user list: users with `managerId = null` become root nodes
- Each node contains: `id`, `name`, `avatar`, `type` (human/agent), `agentType`, `status`, `currentTask`, `email`, `isEphemeral`, `managerId`, `children`

### Summary Statistics
- 4-column grid below the hierarchy, separated by a top border
- Stats computed by flattening the tree: Total Members, Humans, Agents, Active Agents (agents with `status === 'working'`)
- Each stat rendered as a `StatCard` with label, value, and icon

---

## States

| State | Description |
|-------|-------------|
| **Loading** | 4 skeleton rows with `Skeleton` preset `avatar-text` |
| **Loaded** | Tree or chart view rendered with fetched hierarchy |
| **Empty** | If API returns empty array, hierarchy is empty and stats show all zeros |
| **Error** | Console error logged; hierarchy set to empty array |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| **Desktop (md+)** | Stats grid shows 4 columns (`md:grid-cols-4`); both views scroll horizontally if content overflows (`overflow-x-auto`) |
| **Mobile (<md)** | Stats grid collapses to single column; tree/chart content scrolls horizontally within `min-w-max` container |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Org.vue` | Page component with view toggle, data fetching, stats |
| `resources/js/Components/org/TreeNode.vue` | Recursive tree node with connectors and expand/collapse |
| `resources/js/Components/org/ChartNode.vue` | Recursive chart node with centered layout and connectors |
| `resources/js/Components/shared/Button.vue` | View mode toggle buttons |
| `resources/js/Components/shared/Skeleton.vue` | Loading skeletons |
| `resources/js/Components/shared/StatCard.vue` | Summary stat cards |
| `resources/js/Components/shared/AgentAvatar.vue` | Avatar with optional status indicator |
