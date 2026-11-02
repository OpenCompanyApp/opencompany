# UI Structure and Agent Editing

Status: **Accepted** (owner, 2026-11-02). Nothing here is implemented. The binding summary is in
[Product Direction](../PRODUCT_DIRECTION.md), pillars 4 and 7. Two amendments were accepted with it:
the attention page is called **Home**, not Inbox, and optional **add-on apps** are built from the
core primitives (see the appendix below).
Mockups: [sidebar](../ui/mockups/sidebar-2026-11.html) and
[primitives plus apps](../ui/mockups/apps-and-primitives-2026-11.html).
Verified: current UI read from code on 2026-11-02 (screenshots were not possible: the local app
returned HTTP 500 during a concurrent migration change). Patterns researched on the web on 2026-11-02.
Direction: [Product Direction](../PRODUCT_DIRECTION.md), pillars 3, 4, 7 and 9.
Related: [Messenger UI](messenger-ui.md), [Agent-native work apps](agent-native-work-apps.md),
[API-first client split](../architecture/api-first-client-split.md),
[Connections, Events and Routines](connections-events-routines.md).

Three goals:

1. The UI matches the messenger-first direction.
2. Agents can edit structured data (tables, issues, docs) in a way humans can watch, review and undo.
3. OpenCompany avoids Notion's "big mess": it is structured by default and easy to keep tidy.

## 1. Current UI: findings

Evidence paths are relative to `resources/js/` unless noted.

**Navigation**

- `Components/layout/SidebarNav.vue:172-187` has **13 equal top-level destinations**, and about
  6 more pages are reachable only by URL or the command palette.
- Dashboard is home (`routes/web.php:93`). Chat is the sixth item.
- Agent rows link to the agent profile, not to a conversation.
- Approval and chat badges are hardcoded (`badge: 3`, `badge: 15`).

**Layout**

- There is no split-view or context-pane pattern. Every app is a full Inertia page or a modal
  `shared/Slideover.vue`.
- In Chat, two left rails (240px app sidebar plus 320px conversation list) take about 560px before
  the conversation starts.

**Chat** (`Pages/Chat.vue`, `Components/chat/assistant/*`)

- It is modelled on ChatGPT, not a team messenger: a centered column, "New assistant chat", an agent
  picker in the composer, an "✦ Assistant" pill, and a flat time-sorted list.
- Unread counts, mentions, threads and sections are missing.
- Channels with an agent member are misclassified as agent conversations
  (`ConversationSidebar.vue:129`).
- Tool steps are polled every 3 seconds (`Chat.vue:915`).
- About 4.6k lines of the legacy chat stack are dead code (`chat/Area.vue`, `Message.vue`,
  `MessageInput.vue`, `ChannelInfo.vue` and others).

**Approvals**

- They render as a queue at the bottom of the conversation, not at the point in the run where they
  were raised.
- The chat card ignores `can_resolve`.
- No card shows what will happen (tool arguments), why, or where the request came from.
- `Approvals.vue:312` listens for an event nobody emits.

**Tables** (`Pages/Tables/Show.vue`, `Components/tables/*`)

- A custom `<table>` with no virtualization and unpaginated rows.
- Eight column types, with no person/agent, relation, attachment or long-text types.
- The Filter and Sort buttons are dead.
- There is no row detail, history, undo or `updated_by`.
- **Agent edits made through the VFS only appear after a manual reload**, with no highlight or
  attribution.

**Docs**

- TipTap with no collaboration.
- Autosave sends no version check, so **an open editor can silently overwrite an agent's edit**.
- Content is stored as mixed HTML and markdown.
- Nesting depth is unlimited. Agent identity and memory files appear in the user-visible tree.

**Realtime**

- 7 broadcast events in total.
- Tables, Docs, Lists and Approvals have no realtime at all.
- `ApprovalCreated` and `AgentStatusUpdated` are broadcast on **public** channels. This was sent to
  Phase 0 as a security fix.

**Overlapping concepts (the beginning of a Notion-style mess)**

- Tasks (agent runs) vs Lists (human work, with components named `Task*`) vs the planned Issues.
- Automation vs list rules vs agent schedules.
- Dashboard vs Activity vs Workload vs the agent Activity tab.
- Three file stores (`WorkspaceFile`, `DocumentAttachment`, `MessageAttachment`).
- Calendar vs the Tables calendar view.
- Org vs agent hierarchy vs the "Manager" setting.

**Design system**

- Tokens are used twice. There are 9.4k raw `neutral-*` classes and 5.8k hand-paired `dark:`
  classes.
- The documented palette doesn't match `app.css`.
- Native elements outnumber the shared wrappers by about 10 to 1.
- About 2.2k lines of unused shared components.

## 2. Anti-mess information architecture

The Notion lesson: "everything is a page", unlimited nesting and free-form content become a mess
within a year. Notion's own guidance says search "makes the mess usable, but does not resolve it"
([Notion, 2026-02-20](https://www.notion.com/ko/feature/enterprise-search/ai-search-works-better-with-ia)).
Large cleanups are needed after migrations ([Will Larson, 2026-02](https://lethain.com/refactoring-internal-docs-notion/)).
Opinionated tools avoid this with fixed objects and workflows
([Linear Method](https://linear.app/method/introduction)), a small fixed toolset per project
([Basecamp](https://basecamp.com/features)) and typed objects and relations
([Attio](https://docs.attio.com/docs/attribute-types/attribute-types-record-reference)).

**Rules for OpenCompany:**

1. **A fixed set of object types**:
   - Thread (conversation)
   - Issue
   - Table and Record
   - Doc
   - File
   - Routine
   - Agent
   - Person

   No generic "page" and no user-defined object kinds. Docs are not containers.
2. **One level of containers.** Workspace → **Spaces** (teams or projects, flat, never nested) →
   objects. Every object belongs to exactly one Space or to the workspace. Files keep folders, because
   they mirror real file systems and mounts such as Drive. Docs are flat within a Space and organized
   by typed properties and collections, not by a page tree.
3. **Typed everything.**
   - Docs have a type from templates: Spec, Policy, Meeting notes, Wiki and others, with
     properties.
   - Tables gain Person/Agent, Relation (to records, issues and docs), Attachment and Long-text
     columns. Formula comes later.
   - Embedding means linking to the canonical object, never copying it.
4. **Owner, status and review are required.**
   - Every Doc, Table and Issue has a human owner and a status (draft / current / archived).
   - Docs have a review date. A stale sweep, which agents can propose, flags expired items.
   - Archived items leave search and agent context and redirect to their replacement.
5. **Governed creation.**
   - Within policy, agents can create records, issues and doc drafts freely.
   - These are always a proposal a human approves: creating Spaces, Tables, fields or relations,
     changing a schema, and deleting anything.
6. **System files are not content.** Agent identity, instructions and memory live on the agent
   profile and under the VFS `/agents` mount, not in the Docs tree.
7. **One concept per job.** These merges and removals end the overlaps:

   | Today | Becomes |
   |---|---|
   | Lists | Issues |
   | Tasks (agent runs) | Runs, shown in threads, the activity feed and the agent profile. No longer a top-level area |
   | Automation | Routines, on the agent profile and in Spaces |
   | Dashboard, Activity, Workload | Home and the activity feed |
   | Org | Team: a people and agents directory with manager relations |
   | Three file stores | One Files store; attachments are Files |
   | Calendar | The Calendar add-on app over calendar connections, plus an agenda on Home |

## 3. Navigation and layout

**Top level** (one rail, plus search and the command palette):

| Area | Contents |
|---|---|
| **Home** | Needs you (approvals, questions, takeovers), then results, then FYI. The daily brief lands here. Not called Inbox, to avoid confusion with Mail and agent inboxes |
| **Chats** | DMs with agents and people, group chats, channels. Sections, unread counts, mentions, threads |
| **Spaces** | Each Space holds Issues, Tables, Docs, Files, Artifacts and Routines |
| **Team** | People and agents; agent profiles (identity, memory, grants, computer, routines, budget, runs) |
| **Admin** | Connections, policies, members, billing |

**Three-pane layout:** rail | main | context pane (resizable, collapsible).

- **Chat-first.** The main pane is a conversation. The context pane shows:
  - the **object** the conversation is about (table, issue, doc or file);
  - the agent's **computer** (live screen, takeover and hand-back; only for agents that have one);
  - the **run** (plan checklist, activity and change set);
  - thread **details** (members, routines, files).
- **Object-first.** The main pane is the object, such as a full table. The context pane is the
  **agent panel**: a chat with an agent scoped to that object and the current selection. This
  follows the Copilot-in-Excel and Gemini-in-Sheets pattern.
- **Same components in both.** Opening an object from a chat card or opening chat from an object
  gives the same layout with the panes swapped.
- **Mobile.** Cards open full-screen sheets, and the computer opens full screen. Home is the home
  screen.

The pattern is established: Slack split view
([docs](https://docs.slack.dev/surfaces/split-view)), Canvas-style side-by-side editors
([VentureBeat](https://venturebeat.com/ai/chatgpts-canvas-now-shows-tracked-changes)), and Grok Bot's
computer panel with takeover ([xAI](https://docs.x.ai/grok-bot/get-started)).

## 4. Agentic editing of tables (and issues and docs)

**Platform primitive: change sets.** Every agent edit to structured data produces a **change set**.
A change set is a first-class object holding items (old and new values per cell, field or text
range), the run that produced it, its status and its policy decision. This applies to edits made
through the VFS, the API and mruby alike.

**Flow:**

1. **Ask.** In a chat, or in the agent panel of an open table. The agent receives the current
   selection (rows, columns or a filter) as context.
2. **Plan, for bulk or structural work.**
   - A plan card states the scope ("update 214 rows in Leads: set Status from X to Y"), shows a
     **dry-run preview** of about 10 sample rows old→new, and estimates the cost.
   - The human approves, edits the plan or cancels. This follows Excel and Gemini plan mode and
     Airtable Canvas ([Google](https://workspaceupdates.googleblog.com/2026/04/build-and-edit-complex-spreadsheets-with-Gemini-in-Google-Sheets.html),
     [Airtable](https://community.airtable.com/announcements-6/fall-release-omni-2-0-airtable-canvas-a-bigger-mcp-agent-audit-logs-and-upcoming-agent-permissions-48850)).
3. **Live staging.**
   - Edits stream into the change set and appear in the grid immediately, highlighted in the agent's
     color.
   - A presence chip shows progress ("Ada · editing row 12 of 214"), using the same presence system
     as humans, with a timeout so a crashed run shows as stale
     ([Liveblocks](https://liveblocks.io/docs/use-cases/presence)).
4. **Review.**
   - Filter to "changed only". Hover a cell to see old→new.
   - Accept or reject per cell, per row or all at once
     ([Hex](https://learn.hex.tech/docs/explore-data/notebook-view/notebook-agent)).
5. **Apply.**
   - Applied changes are committed with person-or-agent attribution and a run link in the record
     history.
   - **"Revert this run"** undoes the whole run in one action, at any time, as long as the affected
     cells have not changed since.
6. **Policy thresholds.**
   - Small edits within the agent's grants can auto-apply, but they still form one revertible batch.
   - Large changes, structural changes and deletes always require review.
     *(Reconciled 2026-11-02 with pillar 5, which was locked later: deletes go to a 30-day trash,
     which auto-applies in Personal and Team and is a proposal in Company. Purge needs approval. Bulk
     edits over about 50 rows and schema changes are proposals in Team and Company.)*
   - Thresholds are set per Space and per agent.
7. **Human edits win.**
   - An agent never overwrites a cell a human changed after the agent read it
     ([Airtable rule](https://support.airtable.com/docs/ai-assist-field-overview)).
   - Conflicts show up in review rather than being resolved by last write wins.

**Issues** use the same model for field changes. The agent works as the issue's **delegate** while a
human stays the owner ([Linear](https://linear.app/developers/agent-best-practices)).

**Docs** use collaborative editing (for example Yjs), with the agent as a collaborator writing in
**suggestion mode** (tracked changes, accept or reject). Supporting decisions:

- One canonical stored format (structured editor JSON) with a markdown projection for the VFS.
- Version checks on every save. This fixes today's silent-overwrite bug.

**Prerequisites:**

- realtime for every object from the event log;
- agent presence;
- record history with actor and run;
- a virtualized grid with server-side paging;
- a row detail pane;
- the new column types;
- a versioned API for change sets.

## 5. Messenger details

- **Team messenger, not an assistant.**
  - Sections (Agents, People, Groups, Channels), unread counts, mentions and reply threads.
  - Agents carry an "Agent" label and their own avatar. The word "Assistant" is dropped.
- **Typed run activity**, following
  [Linear's agent activities](https://linear.app/developers/agent-interaction).
  - Activity types: thought (collapsed), action, needs input, result, error.
  - A live plan checklist and states (working, needs you, done, stale).
  - Stop and redirect on every run; stopping says what was already done.
  - Delivered over the realtime stream, not polling.
- **Approvals.**
  - Approvals appear inline at the point in the run where they were raised. Each shows what will
    happen (rendered from the arguments), why, the source, an untrusted-content flag and a run link.
  - Options are allow once, this run, always within scope, or deny. An admin-locked "ask first"
    always wins.
  - Approvals and secret requests go only to the responsible human's DM or Home, never to group
    chats ([Grok Bot](https://docs.x.ai/grok-bot/chat-and-collaboration)).
  - Remembered approvals must stick, to avoid approval fatigue
    ([Cursor forum](https://forum.cursor.com/t/authorization-death-by-1000-clicks/170087)).
- **Notifications.** Notify only when a human is needed. Desktop first, then mobile after a delay.
  The daily brief, then quiet.

## 6. Build approach

Recommended, given that there are no users and the direction is API-first:

- **Build a new client.** A Vue 3 + Reka UI SPA on the versioned API and realtime stream, with a
  token-based design system from day one. Use semantic tokens and wrapper components, with dark mode
  through tokens rather than hand-paired classes.
- **Freeze the old UI.** Stop polishing Inertia pages, and delete them as the new client reaches
  parity.
- **Reuse selectively:** the TipTap editor setup, table view logic, the approval and diff viewers,
  and the Telegram Mini App.
- **Delete now:** dead code (about 7k lines of legacy chat and unused shared components), fake badges,
  dead buttons and dead listeners.
- **Build order:**
  1. Shell and Chats, Home, typed run activity, inline approvals.
  2. Context pane with object views and change sets for Tables.
  3. Agent panel on objects, Issues and Docs suggestion mode.
  4. Computer pane (Phase 2), Spaces and the anti-mess rules.

## Open questions

- Whether the new client lives in this repository (`clients/web`) or its own repository.
- Collaboration engine for Docs (Yjs with Hocuspocus versus alternatives) and the canonical document
  format.
- Change-set auto-apply thresholds per Space.

## Appendix A: the sidebar (accepted)

Mockup: [sidebar-2026-11.html](../ui/mockups/sidebar-2026-11.html), with the Apps section from
[apps-and-primitives-2026-11.html](../ui/mockups/apps-and-primitives-2026-11.html). The rail is
260px and replaces today's 240px app sidebar plus the 320px conversation list. Sections, top to
bottom:

1. **Workspace switcher**, a new-chat button, and search (⌘K).
2. **Home**, with a "N need you" badge.
3. **Pinned:** any thread, table, issue or doc. Pins are per person and are the only way an object
   appears at the top level of the rail.
4. **Agents:** opening one starts a DM. Each row shows a status dot (working, needs you, idle,
   paused), the current task while the agent works, unread counts, and 🖥 when the agent has its own
   computer. The rail shows about 5 recent or unread agents; the rest are in Team and ⌘K.
5. **People:** presence and unread counts, about 5 recent.
6. **Groups and channels:** group chats can mix agents and people.
7. **Apps:** installed add-ons such as Mail, CRM, Calendar and Helpdesk, with counts.
8. **Spaces:** flat. Each expands to Issues, Tables, Docs, Files, Artifacts and Routines, plus scoped
   app views such as Deals. Artifacts were added as a core primitive on 2026-11-02; see
   [Artifacts and Forms](artifacts-and-forms.md).
9. **Footer:** Team, Admin, and the user menu.

Every section can be collapsed and reordered per user. Spaces the user isn't a member of are hidden.
On mobile, a bottom bar holds Home, Chats, Spaces and Team.

## Appendix B: core primitives and add-on apps (accepted)

- **Core primitives, always on:** Threads, Issues, Tables and Records, Docs, Files, Artifacts,
  Routines, Agents and People. Artifacts are the only code-bearing primitive
  ([Artifacts and Forms](artifacts-and-forms.md)).
- **Apps, optional and per workspace:** Mail, CRM, Calendar, Helpdesk, Recruiting, Invoices, and
  third-party apps later.
- **An app manifest declares:**
  - record types (schema-locked tables with relations);
  - views from a fixed catalog: list, board/pipeline, table, calendar, mail thread, record detail,
    timeline, form;
  - artifacts, which act as the app's custom views and endpoints;
  - routine templates;
  - an agent pack;
  - required connections;
  - roles;
  - the events it emits.
- **Apps never bring their own storage.** Agents, search, approvals, change sets, revert and the event
  log work on app data automatically. App data appears in the VFS under `/apps/<app>/…`.
- **Placement:** apps live at workspace level and can show scoped views inside Spaces.
- **Lifecycle:** uninstalling archives the data, and upgrades migrate the schema.
- **First-party order:** Mail (Phase 2, doubles as the agent inbox), Calendar (Phase 3), CRM
  (Phase 4), then Helpdesk, Recruiting and Invoices.
- **Third-party apps** come only as signed, declarative manifests reviewed before install. Their
  custom views are interactive artifacts on the MCP Apps bridge.
