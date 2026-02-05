# Chat

> A full-height, three-panel messaging interface with channel list, message area, and channel info sidebar, supporting real-time messaging, threads, reactions, pinning, typing indicators, and file uploads.

---

## Route & Access

| Property | Value |
|----------|-------|
| **Route** | `/chat` |
| **Name** | `chat` |
| **Auth** | Required (`auth`, `verified`) |
| **Layout** | AppLayout |
| **Query params** | `?channel={id}` selects channel; `?dm={userId}` selects DM with user |

---

## Layout

### Desktop (md+)

```
+------------------------------------------------------------------+
| h-full flex flex-col                                              |
| +----------+------------------------------------+-----------+     |
| |          |                                    |           |     |
| | Channel  |  Chat Area                        | Channel   |     |
| | List     |                                    | Info      |     |
| | (w-60)   |  +------------------------------+ | (w-72)    |     |
| |          |  | Header: #name, description,   | |           |     |
| | Sections:|  | pin count, member count       | | About     |     |
| | - Pinned |  +------------------------------+ | Pinned    |     |
| | - Starred|  |                                | | Files     |     |
| | - DMs    |  | Messages area (scrollable)    | | Members   |     |
| | - Private|  | - Date separators             | | Notifs    |     |
| | - Public |  | - Grouped by author/time      | |           |     |
| | - Extern.|  | - Reactions, thread, pin      | | Footer:   |     |
| | - Archive|  |                                | | Mute/Pin  |     |
| |          |  +------------------------------+ | Leave     |     |
| | Search   |  | Typing indicator (if active)  | |           |     |
| | Filters  |  +------------------------------+ |           |     |
| |          |  | MessageInput (rich editor)     | |           |     |
| +----------+------------------------------------+-----------+     |
+------------------------------------------------------------------+
  CreateChannelModal (overlay)
  AddMemberModal (overlay)
  Thread Panel (absolute overlay on right side of ChatArea, w-80)
```

### Mobile (< md)

```
+------------------------------------------------------------------+
| Mobile Toolbar (h-auto)                                           |
| [hamburger] [#channel-icon channel-name] [info]                   |
+------------------------------------------------------------------+
| Chat Area (full width)                                            |
| Messages, typing indicator, message input                         |
+------------------------------------------------------------------+

  Slideover (left): Channel List
  Slideover (right): Channel Info
```

---

## Components

| Component | Path | Purpose |
|-----------|------|---------|
| `ChannelList` | `Components/chat/ChannelList.vue` | Sidebar listing channels grouped by type (Pinned, Starred, DMs, Private, Public, External, Archived). Includes search, type/status filters, quick filters, and collapsible sections. |
| `Area` | `Components/chat/Area.vue` | Main chat area with header, scrollable message list, date separators, pinned messages panel, thread panel, typing indicator, and message input. |
| `ChannelInfo` | `Components/chat/ChannelInfo.vue` | Right sidebar with About section, pinned messages, shared files, members list (with search and filter), notification settings, and footer actions (mute, pin, leave). |
| `Message` | `Components/chat/Message.vue` | Individual message bubble with author avatar, name, content, timestamp, reactions, and action buttons (react, thread, pin). |
| `MessageInput` | `Components/chat/MessageInput.vue` | Rich text input at bottom of chat area. Supports `compact` variant for thread replies. Emits `send` with content and optional attachments. |
| `TypingIndicator` | `Components/chat/TypingIndicator.vue` | Shows animated dots and user names when others are typing. |
| `ChannelItem` | `Components/chat/ChannelItem.vue` | Single channel row inside ChannelList sections. Shows icon, name, unread badge. |
| `ApprovalCard` | `Components/chat/ApprovalCard.vue` | Inline approval card rendered within chat messages. |
| `AddMemberModal` | `Components/chat/AddMemberModal.vue` | Modal to add members to the current channel. |
| `CreateChannelModal` | `Components/chat/CreateChannelModal.vue` | Modal for creating a new channel. |
| `Slideover` | `Components/shared/Slideover.vue` | Slide-in panel used on mobile for channel list (left) and channel info (right). |

---

## Data & API

| Composable Call | Purpose |
|-----------------|---------|
| `fetchChannels()` | Load all channels the user belongs to |
| `fetchMessages(channelId)` | Load messages for selected channel |
| `sendMessage({ content, channelId, authorId, attachmentIds?, replyToId? })` | Send a new message or thread reply |
| `markChannelRead(channelId)` | Mark channel as read on selection |
| `addMessageReaction(messageId, { emoji, userId })` | Add emoji reaction to a message |
| `fetchMessageThread(messageId)` | Load thread parent and replies |
| `removeChannelMember(channelId, memberId)` | Remove member from channel |
| `pinMessage(messageId, userId)` | Pin or unpin a message |
| `fetchPinnedMessages(channelId)` | Load pinned messages for channel |
| `sendTypingIndicator(...)` | Notify that current user is typing |
| `uploadMessageAttachment(file, channelId, userId)` | Upload file attachment |

---

## Real-time Events

| Event | Handler |
|-------|---------|
| `message:new` | Refresh messages if for current channel; always refresh channel list for unread counts |
| `message:reaction:added` | Refresh messages |
| `message:reaction:removed` | Refresh messages |
| `message:pinned` | Refresh messages and pinned messages for current channel |
| `message:unpinned` | Refresh messages and pinned messages for current channel |

Subscriptions are set up via `useRealtime()` in `onMounted` and cleaned up in `onUnmounted`. Typing indicators use the dedicated `useTypingIndicator` composable.

---

## Features & Interactions

### Channel Selection
- Desktop: Click channel in sidebar list
- Mobile: Open left slideover, tap channel, slideover auto-closes
- URL query params `?channel=` and `?dm=` are watched and synced
- First channel is auto-selected as fallback

### Messaging
- Send message via `MessageInput` component
- Supports file attachments (uploaded before send)
- Messages auto-scroll to bottom on new arrivals
- Date separators shown between messages on different days
- Avatar/name grouped when same author within 5 minutes

### Threads
- Click thread icon on a message to open the thread panel
- Thread panel slides in from right (w-80, absolute positioned)
- Shows parent message, reply count, all replies, and a compact input
- Thread replies sent with `replyToId` parameter

### Reactions
- Add emoji reactions to messages
- Triggers `addMessageReaction` API call then refreshes

### Pinned Messages
- Pin/unpin via message action menu
- Pinned messages panel toggleable in header (badge shows count)
- Click pinned message to scroll-to-message with highlight animation

### Channel Info Panel
- Collapsible sections: About, Pinned Messages, Shared Files, Members, Notifications
- Members section has search input (when > 5 members) and filters: All, Humans, Agents, Online
- Add/remove members, view profiles, send DM
- Notification preferences: All messages, Mentions only, Nothing
- Footer: Mute, Pin, Leave channel

### Channel List Features
- Search with live filtering
- Quick filters: Unread, DMs
- Advanced filter panel: Channel type (Public, Private, DMs, External) and status (Unread, Muted, Pinned, Starred)
- Unread badge in header
- Collapsible sections with counts
- Archived channels hidden by default with toggle

---

## States

| State | Description |
|-------|-------------|
| **No channel selected** | Large centered icon with "No channel selected" and "Select a channel to start chatting" |
| **Empty channel** | Chat icon with "No messages yet" and "Be the first to send a message in #name" |
| **No channels** | Empty state in channel list with "No channels yet" and create button |
| **No search results** | Magnifying glass icon with "No channels found" and clear button |
| **Loading channels** | Skeleton placeholders (section headers + channel rows) |
| **Typing** | Typing indicator bar appears above message input |

---

## Responsive Behavior

| Breakpoint | Changes |
|------------|---------|
| `< md` | Mobile toolbar visible. Channel list and info become slide-over panels. Chat area fills full width. |
| `md+` | Three-panel layout: channel list (w-60), chat area (flex-1), info sidebar (w-72, toggleable). Mobile toolbar hidden. |

---

## Files

| File | Purpose |
|------|---------|
| `resources/js/Pages/Chat.vue` | Page component |
| `resources/js/Components/chat/ChannelList.vue` | Channel sidebar |
| `resources/js/Components/chat/Area.vue` | Chat area with messages, thread, input |
| `resources/js/Components/chat/ChannelInfo.vue` | Channel details sidebar |
| `resources/js/Components/chat/Message.vue` | Individual message |
| `resources/js/Components/chat/MessageInput.vue` | Message composer |
| `resources/js/Components/chat/TypingIndicator.vue` | Typing dots |
| `resources/js/Components/chat/ChannelItem.vue` | Channel row |
| `resources/js/Components/chat/ApprovalCard.vue` | Inline approval |
| `resources/js/Components/chat/AddMemberModal.vue` | Add member modal |
| `resources/js/Components/chat/CreateChannelModal.vue` | Create channel modal |
| `resources/js/Components/shared/Slideover.vue` | Mobile slide panels |
| `resources/js/composables/useApi.ts` | API composable |
| `resources/js/composables/useRealtime.ts` | WebSocket event subscriptions |
| `resources/js/composables/useTypingIndicator.ts` | Typing indicator logic |
| `resources/js/composables/useMediaQuery.ts` | `useIsMobile()` hook |
