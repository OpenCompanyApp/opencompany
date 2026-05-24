# Chat

> Unified conversation surface for assistant chats, direct messages, and normal
> channels. The current page renders `AssistantChatShell` for every selected
> channel; the older separate `ChannelList` / `Area` / `ChannelInfo` layout
> components still exist in the tree but are not the active page shell.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/w/{workspace}/chat` |
| **Name** | `chat` |
| **Auth** | Required (`auth`, `verified`) |
| **Layout** | AppLayout |
| **Query params** | `?channel={id}` selects a channel; `?dm={userId}` selects or creates a DM with a human; `?agent={agentId}` selects or creates an agent DM |

Legacy `/w/{workspace}/messages` and `/w/{workspace}/messages/{id}` routes
redirect into this page; see [messages.md](messages.md).

---

## Layout

### Desktop (`md+`)

```
+------------------------------------------------------------------+
| h-full flex flex-col                                              |
| +---------------------------+----------------------------------+  |
| | ConversationSidebar       | AssistantConversation             |  |
| | w-80 / collapsed w-16     |                                  |  |
| |                           | Header: sidebar toggle,          |  |
| | Header: OpenCompany       | conversation title, refresh       |  |
| | Search conversations      |                                  |  |
| | New assistant chat        | Scroll area:                      |  |
| | DM / Channel buttons      | - Empty assistant prompt state    |  |
| |                           | - Message list                    |  |
| | Conversation rows:        | - Inline approval cards           |  |
| | - Agent chats             | - Thinking/runtime panel          |  |
| | - Human DMs               |                                  |  |
| | - Channels                | PromptComposer                    |  |
| +---------------------------+----------------------------------+  |
+------------------------------------------------------------------+
  CreateChannelModal / CreateDmModal overlays live at page level.
```

### Mobile (`< md`)

```
+------------------------------------------------------------------+
| AssistantConversation                                             |
| [sidebar icon] title/subtitle                         [refresh]   |
+------------------------------------------------------------------+
| Messages, approvals, runtime activity, empty states                |
+------------------------------------------------------------------+
| PromptComposer                                                     |
+------------------------------------------------------------------+

  Slideover (left): ConversationSidebar
  CreateChannelModal / CreateDmModal overlays
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `AssistantChatShell` | `Components/chat/assistant/AssistantChatShell.vue` | Active page shell. Owns the desktop/sidebar split, mobile conversation slideover, and shell events. |
| `ConversationSidebar` | `Components/chat/assistant/ConversationSidebar.vue` | Conversation list for agent chats, DMs, and channels. Includes search, new assistant chat, DM, channel buttons, and desktop collapse state. |
| `AssistantConversation` | `Components/chat/assistant/AssistantConversation.vue` | Main conversation panel with header, empty states, messages, inline approvals, runtime activity, and composer. |
| `AssistantMessage` | `Components/chat/assistant/AssistantMessage.vue` | Current rendered message row for the unified shell. |
| `PromptComposer` | `Components/chat/assistant/PromptComposer.vue` | Composer with attachment support, agent picker for assistant channels, stop/compact/status actions, and send event. |
| `ThinkingPanel` | `Components/chat/assistant/ThinkingPanel.vue` | Shows active/pending task runtime status under the conversation. |
| `EmptyState` | `Components/chat/assistant/EmptyState.vue` | Agent selection and suggested prompt state before a conversation has messages. |
| `ApprovalCard` | `Components/chat/ApprovalCard.vue` | Inline approval card rendered in the unified conversation approval queue. |
| `CreateChannelModal` | `Components/chat/CreateChannelModal.vue` | Modal for creating a channel. |
| `CreateDmModal` | `Components/chat/CreateDmModal.vue` | Modal for creating a DM. |
| `AddMemberModal` | `Components/chat/AddMemberModal.vue` | Still mounted by the page, but the current shell does not expose the old channel-info add-member trigger. |

---

## Data & API

`resources/js/Pages/Chat.vue` uses `useApi()` for the live page data. Current
rendered shell actions use the same channel/message APIs for assistant and
non-assistant conversations.

| Composable Call | Purpose |
|-----------------|---------|
| `fetchChannels()` | Load all conversations the user belongs to |
| `fetchMessages(channelId)` | Load messages for the selected channel |
| `sendMessage({ content, channelId, authorId, attachmentIds?, replyToId? })` | Send a message, assistant prompt, or command into the selected channel |
| `markChannelRead(channelId)` | Mark selected channel as read |
| `uploadMessageAttachment(file, channelId, userId)` | Upload files before sending |
| `fetchAgents()` | Populate assistant-agent selection and new assistant chat behavior |
| `fetchAgentTasks()` | Poll runtime tasks for the selected assistant channel |
| `fetchApprovals()` | Poll pending approval requests for inline approval cards |
| `respondToApproval(id, status)` | Approve or reject an inline approval |
| `cancelAgentTask(id)` | Stop the active assistant task |
| `compactChannel(channelId)` | Run `/compact` through the assistant command path |
| `fetchWorkspaceStatus()` | Support runtime/status refreshes |

Older helpers for reactions, pinned messages, thread panels, typing indicators,
and member removal still exist in `Chat.vue` and/or legacy chat components, but
the current template no longer renders the old `Area` / `ChannelInfo` controls
that exposed those interactions.

---

## Real-Time & Polling

| Mechanism | Current behavior |
|-----------|------------------|
| Echo private channel `chat.{channelId}` | Selected-channel watcher subscribes through `useRealtime().privateChannel()` and leaves the previous channel on selection change. |
| `.MessageSent` | Bridges into `message:new`, then refreshes messages, assistant runtime, and channel unread state. |
| `.text_start`, `.text_delta`, `.text_end`, `.stream_end`, `.stream_failed` | Bridges provider stream events into local `message:stream:*` events. The page creates a temporary `stream-{message_id|invocation_id}` assistant message, appends deltas, and clears its `streaming` flag on end/error. |
| `useTypingIndicator()` | Still initialized for chat typing support, though the current unified shell does not render the old typing bar. |
| Runtime poll | Every 3 seconds, refreshes assistant runtime for the selected channel and refreshes messages when pending/active/paused tasks exist. |
| Channel watcher | Refreshes messages and marks the selected channel as read. Assistant channels also refresh runtime state. |

---

## Features & Interactions

### Conversation Selection

- Desktop uses `ConversationSidebar`; mobile opens the same sidebar in a left
  `Slideover`.
- `?agent=` selects or creates an agent DM, then treats it as an assistant
  channel.
- `?dm=` selects or creates a human DM.
- `?channel=` selects an existing channel.
- If no query-selected channel is found, the page prefers the first assistant
  conversation, then falls back to the first available channel.

### Messaging And Assistant Prompts

- All sends route through `handleUnifiedSend()`.
- If no channel is selected and an agent is selected, the page creates or opens
  the agent DM before sending.
- Attachments are uploaded before `sendMessage()`.
- `/compact` and `/status` are sent through the same composer path as normal
  prompts.
- Retry sends a prompt asking the assistant to retry and improve the selected
  response.

### Assistant Runtime

- Assistant channels are detected as DMs that include an agent member.
- Assistant channels show agent-aware empty states, inline approvals, runtime
  activity, task thinking state, stop, compact, and status controls.
- Provider stream deltas are rendered immediately as a temporary assistant
  message with animated streaming dots until a stream end or failure event
  arrives.
- Non-assistant DMs/channels use the same message/composer surface, but hide
  assistant-only composer controls.

### Approvals

- Pending approvals for the selected channel are rendered below messages.
- Approve/reject calls `respondToApproval()`, then refreshes approvals, runtime
  state, and messages.

### Legacy Chat Controls

The legacy `ChannelList`, `Area`, `ChannelInfo`, `MessageInput`, and
`TypingIndicator` components remain in `resources/js/Components/chat/`, but the
current `Chat.vue` template does not render them. Treat their behavior as legacy
or reusable component behavior, not current page behavior, until the page shell
uses them again.

---

## States

| State | Description |
|-------|-------------|
| **No channel selected** | Agent chooser and suggested prompts inside `EmptyState`. |
| **Assistant channel with no messages** | Agent-aware prompt suggestions unless runtime work is already active. |
| **Non-assistant channel with no messages** | "Start the conversation" centered state. |
| **Runtime before first visible response** | Small runtime activity panel while an assistant task is active. |
| **Streaming response** | Temporary assistant message with incremental markdown content and pulsing dots. |
| **No conversations** | Sidebar empty state with "Start with an agent, DM, or channel." |
| **Sidebar collapsed** | Desktop sidebar shrinks to icon-only `w-16`; state is persisted in `localStorage`. |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< md` | Sidebar is hidden by default and opens as a left `Slideover` from the conversation header. |
| `md+` | Sidebar is visible and collapsible; main conversation fills the remaining width. |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Chat.vue` | Page owner: query-param selection, channel/message/runtime data, modal state, and unified send/approval handlers |
| `resources/js/Components/chat/assistant/AssistantChatShell.vue` | Active chat shell |
| `resources/js/Components/chat/assistant/ConversationSidebar.vue` | Active conversation sidebar |
| `resources/js/Components/chat/assistant/AssistantConversation.vue` | Active conversation panel |
| `resources/js/Components/chat/assistant/AssistantMessage.vue` | Active message renderer |
| `resources/js/Components/chat/assistant/PromptComposer.vue` | Active composer |
| `resources/js/Components/chat/assistant/ThinkingPanel.vue` | Runtime task panel |
| `resources/js/Components/chat/assistant/EmptyState.vue` | Assistant/channel empty state |
| `resources/js/Components/chat/ApprovalCard.vue` | Inline approval |
| `resources/js/Components/chat/CreateChannelModal.vue` | Create channel modal |
| `resources/js/Components/chat/CreateDmModal.vue` | Create DM modal |
| `resources/js/Components/chat/AddMemberModal.vue` | Mounted legacy modal path |
| `resources/js/Components/chat/ChannelList.vue` | Legacy sidebar component, not rendered by the current page shell |
| `resources/js/Components/chat/Area.vue` | Legacy chat area component, not rendered by the current page shell |
| `resources/js/Components/chat/ChannelInfo.vue` | Legacy channel info component, not rendered by the current page shell |
| `resources/js/composables/useApi.ts` | API composable |
| `resources/js/composables/useRealtime.ts` | WebSocket event subscriptions |
| `resources/js/composables/useTypingIndicator.ts` | Typing indicator state |
| `resources/js/composables/useMediaQuery.ts` | `useIsMobile()` hook |
