# Layout Patterns

> Common layout patterns used across OpenCompany pages, with ASCII diagrams, responsive behavior, and component references. Use this when designing a new page or understanding how existing pages are structured.

Status: "Today" sections describe the current Inertia pages. The [Target: One Rail, Main and Context Pane](#target-one-rail-main-and-context-pane-accepted-not-implemented) section is the accepted design (2026-11-02), not implemented. Navigation verified against `SidebarNav.vue` and `routes/web.php` on 2026-11-02.

> **Direction (2026-11-02, accepted).** The locked [Product Direction](../PRODUCT_DIRECTION.md) (pillars 4 and 7) and [UI Structure and Agent Editing](../planning/ui-structure-and-agent-editing.md) define the target shell: one 260px rail, a main pane and a resizable context pane, with **Home** as the attention page. Mockups: [sidebar mockup](mockups/sidebar-2026-11.html) and [primitives plus apps mockup](mockups/apps-and-primitives-2026-11.html). The shell below, with Dashboard as home and Chat as one of 13 equal destinations, is what exists **today**.
>
> - **API-first now, Inertia frozen:** no new Inertia pages or page props. The target shell is a new Vue 3 + Reka UI single-page app on the versioned public API (OpenAPI) and the realtime stream ([API-First Client Split](../architecture/api-first-client-split.md)). Old Inertia pages are deleted as the new client reaches parity. The layouts below stay accurate for today's pages until then.
> - **One concept per job:** Dashboard, Activity and Workload become Home; Lists folds into Issues; Tasks (agent runs) become runs inside threads and the agent profile; Automation becomes Routines; Org becomes Team; three file stores become one Files store; Integrations becomes Connections (Admin) plus Apps. Agent behaviour modes are deleted in favour of the policy model (pillar 5).
---

## App Shell

The master layout wraps every authenticated page. It provides a persistent sidebar and a flexible main content area.

**Component:** `resources/js/Layouts/AppLayout.vue`

```
+------------------------------------------------------------------+
| TooltipProvider > RealtimeProvider                                |
| +------------+---------------------------------------------------+
| |            |                                                   |
| | AppSidebar |  <slot />  (main content area)                   |
| | (w-60)     |  flex-1, flex flex-col, overflow-hidden           |
| |            |                                                   |
| | - Logo     |  Each page fills this slot and controls           |
| | - Nav      |  its own scrolling, headers, and columns.         |
| | - Bottom   |                                                   |
| | - UserMenu |                                                   |
| |            |                                                   |
| +------------+---------------------------------------------------+
+------------------------------------------------------------------+
  CommandPalette (overlay, triggered via keyboard shortcut)
```

| Property | Value |
|----------|-------|
| Root container | `flex h-screen bg-white dark:bg-neutral-900 overflow-hidden` |
| Sidebar width | `w-60` (expanded), `w-16` (collapsed) |
| Sidebar visibility | `hidden md:flex` on desktop; `Slideover side="left"` on mobile |
| Main content | `flex-1 flex flex-col overflow-hidden pt-14 md:pt-0` |
| Mobile header | Fixed `h-14` bar with hamburger menu and centered logo |
| Presence tracking | `usePresence` composable initialized on mount |

The sidebar is collapsible via a toggle button. When collapsed, nav items show only icons with tooltips. The `AppSidebar` component (`resources/js/Components/layout/AppSidebar.vue`) wraps `SidebarNav` and provides the header, bottom links (Automation, Integrations, Settings), and user menu.

---

## Navigation Structure

The sidebar navigation is defined in `resources/js/Components/layout/SidebarNav.vue`. Items are grouped into semantic sections separated by subtle dividers. There are no visible section titles -- only visual spacing via `border-t border-neutral-200 dark:border-neutral-800`.

```
+--------------------------------------------+
|  [O] OpenCompany          [collapse icon]  |  <- Header (AppSidebar)
+--------------------------------------------+
|                                            |
|  * Dashboard                               |  <- Always at top (today's home)
|                                            |
|  ----------------------------------------  |  <- border-t divider
|                                            |
|  * Tasks             (Agent Work)          |
|  * Approvals  [3]                          |
|  * Organization                            |
|  * Automation                              |
|                                            |
|  ----------------------------------------  |  <- border-t divider
|                                            |
|  * Chat       [15]  (Office)               |
|  * Docs                                    |
|  * Files                                   |
|  * Tables                                  |
|  * Calendar                                |
|  * Lists                                   |
|                                            |
|  ----------------------------------------  |  <- border-t divider
|                                            |
|  v Agents            (Collapsible)         |
|    * Logic       [busy indicator]          |
|    * Writer                                |
|    * Analyst                               |
|    * ...                                   |
|                                            |
+--------------------------------------------+
|  * Integrations      (Bottom section)      |  <- AppSidebar bottom
|  * Settings                                |
+--------------------------------------------+
|  [avatar] User Name                        |  <- UserMenu
|           user@email.com                   |
+--------------------------------------------+
```

### Navigation Groups

| Group | Items | Icons (ph: prefix) |
|-------|-------|---------------------|
| Top | Dashboard | `house` / `house-fill` |
| Agent Work | Tasks, Approvals, Organization, Automation | `check-square`, `seal-check`, `tree-structure`, `lightning` |
| Office | Chat, Docs, Files, Tables, Calendar, Lists | `chat-circle`, `file-text`, `folder`, `table`, `calendar`, `kanban` |
| Agents | Dynamic list from API | `AgentAvatar` component with status dot |
| Bottom | Integrations, Settings | `plugs-connected`, `gear` |

Activity has a page (`/w/{workspace}/activity`) but no sidebar entry today. The badge counts on Approvals and Chat are hardcoded in `SidebarNav.vue`, not live counts.

### Active State

Active nav items use `bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white` and switch to the `-fill` variant of their icon. Matching is done via `page.url.startsWith(path)`, with Dashboard using an exact match on the workspace dashboard/root URL.

### Agents Section

The Agents section uses Reka UI `CollapsibleRoot`. It defaults to expanded and persists its open state in `localStorage` (`sidebar-agents-open`). It shows an online agent count indicator with a green dot. Each agent entry shows an `AgentAvatar` with status and its agent type, and navigates to the agent detail page on click. All loaded agents are listed.

**Target:** this sidebar and Chat's 320px conversation list are replaced by one 260px rail (see [Target: One Rail, Main and Context Pane](#target-one-rail-main-and-context-pane-accepted-not-implemented)).

---

## Three-Column Layout

> **Legacy pattern.** `Chat.vue` now renders `AssistantChatShell` (a two-column `ConversationSidebar` + `AssistantConversation` layout with inline approval cards). The three-column `ChannelList` / `Area` / `ChannelInfo` components below still exist in the tree but are not the active Chat shell. See [pages/chat.md](pages/chat.md) for the current layout.

Historically used by the **Chat** page to display a channel list, the active conversation, and an optional info panel.

**Page:** `resources/js/Pages/Chat.vue`

```
+------------------------------------------------------------------+
|  Mobile Toolbar (md:hidden)                                      |
|  [hamburger]  #channel-name                        [info icon]   |
+------------------------------------------------------------------+
|                                                                  |
| +----------+------------------------------+----------+           |
| |          |                              |          |           |
| | Channel  |  Chat Area                   | Channel  |           |
| | List     |                              | Info     |           |
| | (w-72)   |  Messages                    | (w-72)   |           |
| |          |  Thread panel                |          |           |
| | Grouped: |  Typing indicator            | Members  |           |
| | - public |                              | Details  |           |
| | - private|  +-----------------------+   |          |           |
| | - DMs    |  | Message input         |   |          |           |
| | - ext    |  +-----------------------+   |          |           |
| |          |                              |          |           |
| +----------+------------------------------+----------+           |
|                                                                  |
+------------------------------------------------------------------+
```

| Column | Width | Component | Visibility |
|--------|-------|-----------|------------|
| Channel List | `w-72` | `ChatChannelList` | `hidden md:flex` on desktop; `Slideover side="left" size="sm"` on mobile |
| Chat Area | `flex-1` | `ChatArea` | Always visible when a channel is selected |
| Channel Info | `w-72` (default) | `ChatChannelInfo` | `hidden md:flex`, toggled via button; `Slideover side="right" size="md"` on mobile |

### Key Behaviors

- The `isMobile` composable (`useIsMobile`) determines whether to render inline panels or Slideover drawers.
- When no channel is selected, a centered empty state replaces the chat area.
- Thread panel is embedded within the ChatArea component (not a separate column).
- Channel info panel is conditionally rendered (`v-if="selectedChannel && showChannelInfo"`).
- Real-time updates via `useRealtime` composable for new messages, reactions, and pin events.

---

## Two-Column with Drawer

Used by **Tasks** and **Lists** pages. The main content area shows a list or board view, and selecting an item opens a detail drawer sliding in from the right.

**Pages:** `resources/js/Pages/Tasks.vue`, `resources/js/Pages/Lists.vue`

### Tasks Page

```
+------------------------------------------------------------------+
| Header: "Tasks" [Workload link]     Stats    [Filters] [+New]    |
+------------------------------------------------------------------+
|                                                                  |
| +--------------------------------------------+  +-------------+ |
| |                                            |  |             | |
| |  Task List (scrollable)                    |  | Task Detail | |
| |                                            |  | Drawer      | |
| |  +--------------------------------------+  |  | (480px)     | |
| |  | Task Card                            |  |  |             | |
| |  | [type] [status]  title     [priority]|  |  | Status      | |
| |  | description               [date]    |  |  | Agent       | |
| |  | [agent avatar]            [steps]   |  |  | Steps       | |
| |  +--------------------------------------+  |  | Actions     | |
| |                                            |  |             | |
| |  +--------------------------------------+  |  |             | |
| |  | Task Card                            |  |  |             | |
| |  +--------------------------------------+  |  |             | |
| |                                            |  |             | |
| +--------------------------------------------+  +-------------+ |
|                                                                  |
+------------------------------------------------------------------+
```

| Element | Details |
|---------|---------|
| Task list | `flex-1 overflow-auto p-4 md:p-6`, stacked cards with `space-y-3` |
| Task detail | Dedicated `/tasks/{id}` page via `resources/js/Pages/Tasks/Show.vue` |
| Filters | Inline toggle group (`bg-neutral-100 rounded-lg p-1`), options: All / Pending / Active / Completed |
| Create modal | Standard `Modal` component with form |

### Lists Page

```
+------------------------------------------------------------------+
| Header: "Lists"    Stats          [Filters] [View toggle] [+New] |
+------------------------------------------------------------------+
|                                                                   |
| +----------+----------------------------------+  +-------------+  |
| |          |                                  |  |             |  |
| | Project  |  Board View (Kanban columns)     |  | Item Detail |  |
| | List     |  OR                              |  | Drawer      |  |
| | (w-64)   |  List View (table)               |  | (Slideover) |  |
| |          |  OR                              |  |             |  |
| | Tree     |  Timeline View (coming soon)     |  |             |  |
| | structure|                                  |  |             |  |
| |          |                                  |  |             |  |
| +----------+----------------------------------+  +-------------+  |
|                                                                   |
+-------------------------------------------------------------------+
```

| Element | Details |
|---------|---------|
| Project sidebar | `w-64 shrink-0`, `hidden md:block` -- contains project tree for filtering |
| Board view | Kanban columns via `ListsItemBoard` |
| List view | Desktop: `grid-cols-12` table layout; Mobile: stacked cards |
| Detail drawer | `ListsItemDetail` component |
| View toggle | Board / List / Timeline via `ListsItemFilters` |

---

## Sidebar + Content

Used by the **Docs** page. A persistent document tree on the left with a document viewer filling the remaining space. Optional right-side panels (version history, comments, attachments) can slide in.

**Page:** `resources/js/Pages/Docs.vue`

```
+------------------------------------------------------------------+
|  Mobile Toolbar (md:hidden)                                      |
|  [Docs btn]    Document Title         [paperclip] [comments]     |
+------------------------------------------------------------------+
|                                                                  |
| +----------+---------------------------+-----------+             |
| |          |                           |           |             |
| | Doc Tree |  Document Viewer          | Version   |             |
| | (w-72)   |  (flex-1)                 | History   |             |
| |          |                           | (w-80)    |             |
| | Folders  |  Markdown editor/viewer   |           |             |
| | Files    |  with edit/save controls  | OR        |             |
| |          |                           |           |             |
| | [+Doc]   |                           | Comments  |             |
| | [+Folder]|                           | (w-80)    |             |
| |          |                           |           |             |
| |          |                           | OR        |             |
| |          |                           |           |             |
| |          |                           | Attach-   |             |
| |          |                           | ments     |             |
| |          |                           | (w-80)    |             |
| |          |                           |           |             |
| +----------+---------------------------+-----------+             |
|                                                                  |
|                          [paperclip FAB] [comments FAB]          |
+------------------------------------------------------------------+
```

| Column | Width | Component | Notes |
|--------|-------|-----------|-------|
| Doc list | `w-72` on desktop, full-screen overlay on mobile | `DocsDocList` | Fixed in sidebar position, `hidden md:flex` |
| Viewer | `flex-1` | `DocsDocViewer` | Supports edit mode with save/cancel |
| Right panels | `w-80` each | Version History, Comments, Attachments | Slide in via `Transition name="slide-left"`, full-screen on mobile, `md:relative md:w-80` on desktop |

### Key Behaviors

- Only one right panel can be visible at a time (version history, comments, or attachments).
- Panels use a `slide-left` transition (`translateX(20px)` enter/leave).
- Floating action buttons (FABs) for attachments and comments are positioned `fixed bottom-6 right-6` on desktop, hidden on mobile (toolbar buttons used instead).
- Real-time updates via `useRealtime` for comment and document update events.

---

## Full-Width Grid

Used by **Dashboard**, **Workload**, and similar pages that display content in responsive grid layouts with no sidebars.

**Pages:** `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/Workload.vue`

### Dashboard

```
+------------------------------------------------------------------+
| h-full overflow-y-auto                                           |
| +--------------------------------------------------------------+ |
| | max-w-5xl mx-auto p-4 md:p-6                                | |
| |                                                              | |
| | Header: "Dashboard"                                          | |
| | Welcome back. Here's what's happening.                       | |
| |                                                              | |
| | [Pending Approvals banner - conditional]                     | |
| |                                                              | |
| | Stats Row                                                    | |
| | +----------+ +----------+ +----------+ +----------+         | |
| | |  Agents  | |  Tasks   | | Messages | |   ...    |         | |
| | +----------+ +----------+ +----------+ +----------+         | |
| |                                                              | |
| | grid grid-cols-1 lg:grid-cols-3 gap-6                       | |
| | +----------------------------------+ +--------------------+  | |
| | |  Activity Feed (lg:col-span-2)   | | Quick Actions      |  | |
| | |                                  | | Working Agents     |  | |
| | +----------------------------------+ +--------------------+  | |
| |                                                              | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

### Workload

```
+------------------------------------------------------------------+
| Header: Tasks > "Workload"                           [Refresh]   |
+------------------------------------------------------------------+
|                                                                  |
| flex-1 overflow-y-auto p-6                                       |
|                                                                  |
| Summary Cards                                                    |
| grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4            |
| +----------+ +----------+ +----------+ +----------+             |
| |  Active  | | Tasks In | | Completed| |   Avg    |             |
| |  Agents  | | Progress | | This Week| | Efficiency|            |
| +----------+ +----------+ +----------+ +----------+             |
|                                                                  |
| Agent Cards                                                      |
| grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4            |
| +----------------+ +----------------+ +----------------+        |
| | Agent Card     | | Agent Card     | | Agent Card     |        |
| | - Avatar/Name  | | - Avatar/Name  | | - Avatar/Name  |        |
| | - Workload bar | | - Workload bar | | - Workload bar |        |
| | - Efficiency   | | - Efficiency   | | - Efficiency   |        |
| | - Metrics grid | | - Metrics grid | | - Metrics grid |        |
| +----------------+ +----------------+ +----------------+        |
|                                                                  |
+------------------------------------------------------------------+
```

| Property | Dashboard | Workload |
|----------|-----------|----------|
| Container | `h-full overflow-y-auto` | `h-full flex flex-col` |
| Content width | `max-w-5xl mx-auto` | Full width with `p-6` |
| Grid columns | `grid-cols-1 lg:grid-cols-3` | `grid-cols-1 lg:grid-cols-2 xl:grid-cols-3` |
| Header | Inline, no border | Bordered `border-b`, with back-link to Tasks |
| Auto-refresh | No | Yes, every 30 seconds |

---

## Tabbed Content

Used by the **Agent Detail** page. A header area with tab navigation is followed by tab-specific content. Settings uses sidebar section navigation instead of tabs.

**Pages:** `resources/js/Pages/Agent/Show.vue`

### Agent Detail (6 Tabs)

```
+------------------------------------------------------------------+
| h-full overflow-y-auto                                           |
| +--------------------------------------------------------------+ |
| | max-w-4xl mx-auto p-6                                       | |
| |                                                              | |
| | [< Back]                                                     | |
| |                                                              | |
| | +--------+  Agent Name              [Message] [Pause]       | |
| | | Avatar |  Status label                                    | |
| | +--------+                                                   | |
| |                                                              | |
| | [Overview] [Tasks] [Identity] [Capabilities] ...             | |
| |                                                              | |
| | +----------------------------------------------------------+| |
| | |                                                          || |
| | |  Tab Content (min-h-[500px])                             || |
| | |                                                          || |
| | |  Overview: Identity card + current task + activity       || |
| | |  Tasks: Filtered task list with task-page links          || |
| | |  Identity: Identity file editor                          || |
| | |  Capabilities: Toggle list with approval flags           || |
| | |  Activity: Timestamped log with type badges              || |
| | |  Settings: Behavior mode*, cost limit, danger zone       || |
| | |                                                          || |
| | +----------------------------------------------------------+| |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

\* Behaviour modes are deleted in Phase 0 in favour of the policy model (pillar 5).

| Property | Value |
|----------|-------|
| Tab style | Pill buttons: `px-3 py-1.5 rounded-md text-sm font-medium` |
| Active tab | `bg-neutral-900 dark:bg-white text-white dark:text-neutral-900` |
| Inactive tab | `text-neutral-500 hover:text-neutral-900 hover:bg-neutral-100` |
| Tabs list | `flex gap-1 mb-6 overflow-x-auto pb-1` (scrollable on mobile) |
| Tab count | 6: Overview, Tasks, Identity, Capabilities, Activity, Settings |
| Content area | `min-h-[500px]` to prevent layout shift during tab switches |

### Settings Page

The Settings page uses sidebar section navigation with `SettingsSection` components inside each section.

```
+------------------------------------------------------------------+
| h-full overflow-y-auto                                           |
| +--------------------------------------------------------------+ |
| | max-w-5xl mx-auto p-4 md:p-6                               | |
| |                                                              | |
| | Header: "Settings"                                           | |
| |                                                              | |
| | +-- Organization ------------------------------------------+ | |
| | | Name, Email, Timezone                                    | | |
| | +----------------------------------------------------------+ | |
| |                                                              | |
| | +-- Agent Defaults ----------------------------------------+ | |
| | | Behavior mode*, Auto-spawn toggle                        | | |
| | +----------------------------------------------------------+ | |
| |                                                              | |
| | +-- Action Policies ---------------------------------------+ | |
| | | Policy list with edit/delete actions                     | | |
| | +----------------------------------------------------------+ | |
| |                                                              | |
| | +-- Notifications -----------------------------------------+ | |
| | | Email, Slack flag, Daily Summary toggles                 | | |
| | +----------------------------------------------------------+ | |
| |                                                              | |
| | +-- Memory / Storage / Debug ------------------------------+ | |
| | | Memory models, disks, diagnostics                        | | |
| | +----------------------------------------------------------+ | |
| |                                                              | |
| | +-- Danger Zone -------------------------------------------+ | |
| | | Pause All, Reset Memory, Delete Organization             | | |
| | +----------------------------------------------------------+ | |
| |                                                              | |
| |                             Section-level save buttons       | |
| +--------------------------------------------------------------+ |
+------------------------------------------------------------------+
```

\* Behaviour modes are deleted in Phase 0 in favour of the policy model (pillar 5).

---

## Centered Content

Used by **Auth pages** (login, register, forgot password) via the `GuestLayout`. Content is centered both vertically and horizontally with decorative background elements.

**Layout:** `resources/js/Layouts/GuestLayout.vue`

```
+------------------------------------------------------------------+
|                                                                  |
|  bg-neutral-100 dark:bg-neutral-950                              |
|  (dot grid overlay + noise texture)                              |
|                                                                  |
|                        [Logo]                                    |
|                                                                  |
|               +--------------------+                             |
|               |  max-w-sm          |                             |
|               |  bg-white          |                             |
|               |  rounded-xl        |                             |
|               |  border + shadow   |                             |
|               |                    |                             |
|               |  <slot />          |                             |
|               |  (form content)    |                             |
|               |                    |                             |
|               +--------------------+                             |
|                                                                  |
|              <slot name="footer" />                              |
|              (e.g., "Already have an account?")                  |
|                                                                  |
+------------------------------------------------------------------+
```

| Property | Value |
|----------|-------|
| Centering | `flex min-h-screen flex-col items-center justify-center` |
| Card width | `max-w-sm` |
| Card styling | `bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 shadow-sm p-6` |
| Background | Noise texture (SVG `feTurbulence`) + dot grid (`radial-gradient`, `24px` spacing) |
| Stacking | All content uses `relative z-10` to sit above background overlays |

---

## Responsive Breakdowns

All layouts share a consistent responsive strategy using Tailwind breakpoints.

### Breakpoints

| Breakpoint | Width | Prefix |
|------------|-------|--------|
| Mobile | < 768px | (default) |
| Tablet | >= 768px | `md:` |
| Desktop | >= 1024px | `lg:` |
| Wide | >= 1280px | `xl:` |

### How Layouts Collapse

```
Desktop (>= 768px)                    Mobile (< 768px)
+----------+---------------+          +---------------------+
| Sidebar  | Content       |          | [=] Title      [...] |  <- Fixed header h-14
| (w-60)   |               |          +---------------------+
|          |               |          |                     |
| Always   | Full layout   |          | Content (stacked)   |
| visible  | with columns  |          | pt-14 for header    |
|          |               |          |                     |
+----------+---------------+          +---------------------+
                                        Sidebar -> Slideover (left)
                                        Info panels -> Slideover (right)
                                        Tables -> Card lists
                                        Grids -> Single column
```

### Per-Layout Responsive Behavior

| Layout | Desktop | Mobile |
|--------|---------|--------|
| **App Shell** | Sidebar inline (`hidden md:flex`) | Fixed header bar + Slideover from left |
| **Three-Column (Chat)** | All columns inline | Toolbar + Slideover for channel list (left) and info panel (right) |
| **Two-Column + Drawer (Tasks)** | Inline list + fixed drawer overlay | Full-width list, full-width drawer overlay |
| **Two-Column + Drawer (Lists)** | Project sidebar + board/list + drawer | No project sidebar (`hidden md:block`), full-width board, full-width drawer |
| **Sidebar + Content (Docs)** | Doc list sidebar + viewer + optional right panel | Full-screen overlay for doc list, full-screen overlay for right panels |
| **Full-Width Grid** | Multi-column grids (`lg:grid-cols-3`) | Single column stacking |
| **Tabbed Content** | Tabs in a row | Horizontally scrollable tab bar (`overflow-x-auto`) |
| **Centered (Auth)** | Centered card | Same centered card with `px-4` padding |

### Mobile Patterns

- **Hamburger menu**: The AppLayout provides a fixed `h-14` mobile header with a hamburger button that opens the sidebar as a `Slideover side="left" size="sm"`.
- **Slideover navigation**: Panels that are inline on desktop become Slideover drawers on mobile, preserving the same component but changing its container.
- **Stacked cards**: Table-like layouts (e.g., Lists page list view) render as stacked cards on mobile instead of grid rows.
- **Toolbar pattern**: Pages like Chat and Docs show a mobile toolbar (`md:hidden`) with icon buttons for accessing side panels.

---

## Modal and Drawer Patterns

OpenCompany uses two overlay patterns: centered modals for focused tasks and slideover drawers for detail views.

### Modal Component

**Component:** `resources/js/Components/shared/Modal.vue`

Built on Reka UI `DialogRoot`, renders a centered overlay dialog with backdrop blur.

```
+------------------------------------------------------------------+
|                       (backdrop: bg-black/50)                    |
|                                                                  |
|                   +------------------------+                     |
|                   |  [title]          [x]  |                     |
|                   |------------------------|                     |
|                   |                        |                     |
|                   |  Content (<slot />)    |                     |
|                   |                        |                     |
|                   |------------------------|                     |
|                   |  Footer (optional)     |                     |
|                   +------------------------+                     |
|                                                                  |
+------------------------------------------------------------------+
```

| Size | CSS Class | Max Width | Common Usage |
|------|-----------|-----------|--------------|
| `sm` | `max-w-sm` | 384px | Confirmation dialogs, simple forms |
| `md` | `max-w-lg` | 512px | Create forms (default size) |
| `lg` | `max-w-2xl` | 672px | Complex forms, editors |
| `xl` | `max-w-4xl` | 896px | Multi-column dialogs, diff viewers |
| `full` | `max-w-[calc(100%-2rem)] h-[calc(100%-2rem)]` | Near full-screen | Document previews, large editors |

**Props:** `title`, `description`, `icon`, `size`, `closeOnEscape`

**Animations:** Fade-in/out backdrop, zoom-in/out + slide content, 200ms duration.

### Slideover Component

**Component:** `resources/js/Components/shared/Slideover.vue`

Built on Reka UI `DialogRoot`, renders a panel sliding in from the left or right edge.

```
Right Slideover                         Left Slideover
+------------------+-------+           +-------+------------------+
|                  |       |           |       |                  |
|    (backdrop)    | Panel |           | Panel |    (backdrop)    |
|                  |       |           |       |                  |
|                  | +---+ |           | +---+ |                  |
|                  | |Hdr| |           | |Hdr| |                  |
|                  | +---+ |           | +---+ |                  |
|                  | |   | |           | |   | |                  |
|                  | |Body |           | |Body |                  |
|                  | |   | |           | |   | |                  |
|                  | +---+ |           | +---+ |                  |
|                  | |Ftr| |           | |Ftr| |                  |
|                  | +---+ |           | +---+ |                  |
+------------------+-------+           +-------+------------------+
```

| Size | CSS Class | Max Width | Common Usage |
|------|-----------|-----------|--------------|
| `sm` | `max-w-sm` | 384px | Mobile sidebar navigation |
| `md` | `max-w-lg` | 512px | Channel info, mobile panels |
| `lg` | `max-w-2xl` | 672px | Detail views |
| `xl` | `max-w-4xl` | 896px | Wide detail views |
| `full` | `w-full` | 100% | Full-screen mobile overlays |

| Side | Animation | Border | Common Usage |
|------|-----------|--------|--------------|
| `right` | Slide in/out from right | `border-l` | Detail drawers, info panels |
| `left` | Slide in/out from left | `border-r` | Mobile navigation, channel lists |

**Props:** `title`, `description`, `side`, `size`, `showClose`, `closeOnEscape`

**Slots:** `header`, `body` (or default slot), `footer`

### Custom Drawers

Some pages use custom `Transition` wrappers instead of the Slideover component for more control. Prefer the shared slideover for secondary panels and a dedicated route when the detail surface is substantial.

| Property | Value |
|----------|-------|
| Shared component | `resources/js/Components/shared/Slideover.vue` |
| Dedicated task detail | `resources/js/Pages/Tasks/Show.vue` |
| Animation | Shared slideover transitions or local Vue `Transition` classes |
| Border | `border-l border-neutral-200 dark:border-neutral-700 shadow-xl` when a right panel is used |

### Usage Guidelines

| Pattern | When to Use |
|---------|-------------|
| **Modal** | Creating new items, confirmations, focused forms, settings that need immediate attention |
| **Right Slideover** | Viewing/editing item details, info panels, secondary content |
| **Left Slideover** | Mobile navigation, mobile sidebar content |
| **Custom Drawer** | When you need custom animation timing or non-standard layout within the panel |

---

## Target: One Rail, Main and Context Pane (accepted, not implemented)

> **Accepted design, not implemented.** Source: [UI Structure and Agent Editing](../planning/ui-structure-and-agent-editing.md) (section 3 and Appendix A) and [Product Direction](../PRODUCT_DIRECTION.md) pillar 4. Mockups: [sidebar mockup](mockups/sidebar-2026-11.html) and [primitives plus apps mockup](mockups/apps-and-primitives-2026-11.html). Built in the new Vue 3 + Reka UI client on the public API and realtime stream, never as Inertia pages. Component names are provisional; see [components.md](components.md#planned-components-new-client).

### The rail (260px)

One left rail replaces today's 240px app sidebar plus Chat's 320px conversation list. Sections, top to bottom:

```
+--------------------------------+
| [O] Acme v          [+ chat]   |  1. Workspace switcher, new chat
| [ Search            Cmd K ]    |     and search / command palette
|                                |
| (o) Home          [3 need you] |  2. Home (attention page)
|                                |
| PINNED                         |  3. Per-person pins: any thread,
|   # launch-plan                |     table, issue or doc
|   Leads (table)                |
|                                |
| AGENTS                         |  4. Opening an agent starts a DM.
|   [A] Ada  * working  🖥  [2]  |     Status dot (working, needs
|       "Updating Leads..."      |     you, idle, paused), current
|   [B] Bram * needs you         |     task, unread, 🖥 = has a
|   [C] Cleo * idle              |     computer. ~5 recent/unread;
|                                |     the rest via Team and Cmd K
| PEOPLE                         |  5. Presence + unread, ~5 recent
|   (R) Rutger  online      [1]  |
|                                |
| GROUPS & CHANNELS              |  6. Group chats can mix agents
|   Launch team (3 agents, 2 ppl)|     and people
|   # general                    |
|                                |
| APPS                    [+ add]|  7. Installed add-on apps with
|   Mail                    [12] |     counts (Mail, CRM, Calendar,
|   CRM            8 deals due   |     Helpdesk)
|                                |
| SPACES                         |  8. Flat, never nested. Each has
|   v Sales                      |     the same six tools plus
|       Issues                   |     scoped app views
|       Tables                   |
|       Docs                     |
|       Files            Drive   |
|       Artifacts            3   |     Artifacts: pages, interactive
|       Routines             5   |     artifacts and the Space's forms
|       Deals (CRM view)         |
|   > Engineering                |
|--------------------------------|
| Team   Admin   [avatar]        |  9. Footer: Team, Admin, user menu
+--------------------------------+
```

- Every section can be collapsed and reordered per user. Spaces the user is not a member of are hidden.
- Each Space has six tools: Issues, Tables, Docs, Files, Artifacts and Routines ([Artifacts and Forms](../planning/artifacts-and-forms.md)). The Artifacts tool lists pages, interactive artifacts and the Space's forms.
- Pins are the only way an individual object appears at the top level of the rail.
- Badges are live counts from the realtime stream (today's hardcoded badges are deleted).
- Admin content follows the governance preset: Personal shows no policy or role screens; Team and Company add a Governance area (policies, exceptions, approvers, budgets, audit). See [Governance Model](../architecture/governance-model.md).

### Three panes

```
+-----------+--------------------------------------+------------------------------+
| Rail      | Main                                 | Context pane                 |
| (260px)   |                                      | (resizable, collapsible)     |
|           | CHAT-FIRST: a conversation           | - the object being discussed |
|           |  messages, threads, typed run        |   (table, issue, doc, file)  |
|           |  activity, inline task / record /    | - the agent's computer:      |
|           |  approval cards                      |   watch, take over, hand     |
|           |                                      |   back (computer agents only)|
|           |                                      | - the run: plan, activity,   |
|           |                                      |   change set                 |
|           |                                      | - thread details             |
|           +--------------------------------------+------------------------------+
|           | OBJECT-FIRST: the object (e.g. a     | Agent panel: chat with an    |
|           |  full table, with live staged edits  | agent scoped to the object   |
|           |  in the agent's color)               | and the current selection    |
+-----------+--------------------------------------+------------------------------+
```

| Mode | Main pane | Context pane |
|------|-----------|--------------|
| **Chat-first** | A conversation (DM, group chat or channel) | The object being discussed, including an **artifact with a live preview** that refreshes as the agent edits the draft; the agent's **computer** (watch, take over, hand back — only for agents that have one; VFS-only agents simply have no computer view); the **run** (plan checklist, activity, change set); or thread **details** (members, routines, files) |
| **Object-first** | The object: a table, issue, doc, file or app view | The **agent panel**: a chat with an agent scoped to that object and the current selection (rows, columns, filter, text range) |

Opening an object from a chat card, or opening chat from an object, gives the same layout with the panes swapped. Approval cards appear inline at the point in the run where they were raised, and go only to the responsible human's DM or Home, never to group chats.

### Home

Home is the attention page and the default screen. It is **not** called "Inbox" (Mail and agent inboxes keep that word). It ranks **needs you** (approvals, questions, takeovers, sign-in requests), then **results**, then **FYI**; the daily brief lands here. It replaces Dashboard, Activity and Workload.

### Mobile

- A bottom bar holds **Home, Chats, Spaces, Team**.
- Cards open as full-screen sheets; the computer view opens full screen.
- Home is the home screen.

### Theming

Dark and light themes come from semantic tokens, not hand-paired `dark:` classes (see [design-system.md](design-system.md#target-token-design-system-new-client)).

---

## Layout Selection Guide

Use this table when deciding which layout pattern to use for a new page. These patterns apply to today's Inertia pages only, which are frozen. New UI is built in the new client from the [Target: One Rail, Main and Context Pane](#target-one-rail-main-and-context-pane-accepted-not-implemented).

| If your page needs... | Use this pattern | Example pages |
|-----------------------|------------------|---------------|
| Simple content with scrolling | Full-Width Grid | Dashboard, Workload |
| A list/tree sidebar with main viewer | Sidebar + Content | Docs |
| A list with detail drawer | Two-Column with Drawer | Tasks, Lists |
| Three side-by-side panels | Three-Column Layout | Chat |
| Multiple content sections under tabs | Tabbed Content | Agent Detail |
| Stacked form sections | Centered Content (authenticated variant) | Settings |
| Unauthenticated form | Centered Content (guest) | Login, Register |
