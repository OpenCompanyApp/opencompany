# Bidirectional External Channel Sync

Making agents full community participants — not just chatbots.

## Implementation Status (February 2026)

| Phase | Telegram | Discord |
|-------|----------|---------|
| Phase 1: External message ID tracking | **Done** | N/A yet |
| Phase 2: Bidirectional sync (edit/delete/pin/react) | **Done** | Not started |
| Phase 3: External channel discovery | **Done** (monitored channels) | Not started |
| Phase 4: Message search | **Done** | Done (DB-level) |

**Key implementation files:**
- `app/Listeners/SyncToTelegram.php` — Consolidated listener (replaces ForwardMessageToTelegram) handling message send, edit, delete, pin, and reaction sync
- `app/Events/MessageEdited.php`, `MessageDeleted.php`, `MessagePinned.php`, `MessageReactionAdded.php` — Sync events
- `app/Services/TelegramService.php` — Platform API methods (edit, delete, pin, react)
- `app/Agents/Tools/Chat/ManageMessage.php` — Agent tool with edit action + sync indicator
- `app/Agents/Tools/Chat/SearchMessages.php` — Full-text message search tool
- `app/Agents/Tools/Chat/DiscoverExternalChannels.php` — Browse external platform channels
- `database/migrations/2026_02_14_200001_add_external_message_id_to_messages_table.php` — External ID tracking

**What's left:** Discord sync listener (`SyncToDiscord`), Discord channel discovery via REST API, Discord webhook controller for inbound events.

---

## The Problem

Agents can send messages to external channels (Telegram, Discord) and that's it. Reactions, pins, edits, and deletes are workspace-only — they never sync to the external platform. Agents can't browse Discord server channels, can't react to a Telegram message, can't edit their own response after sending. They're chatbots, not community members.

## The Vision

Agents should feel like **real team members** on Discord and Telegram — browsing channels, reacting to messages, editing responses, pinning important content, moving between channels strategically. The workspace is the brain; external platforms are the hands.

---

## Current State

| Capability | Internal channels | External (Telegram) | External (Discord) |
|---|---|---|---|
| Send messages | Yes | Yes (auto-sync) | Yes (auto-forwards) |
| Read messages | Yes | Yes (from DB) | Yes (from DB) |
| Edit messages | Yes (`manage_message`) | **Yes (synced)** | No |
| Add reactions | Yes | **Yes (synced)** | Not synced |
| Pin messages | Yes | **Yes (synced)** | Not synced |
| Delete messages | Yes | **Yes (synced)** | Not synced |
| Browse channels | Yes (`list_channels`) | **Yes** (`discover_external_channels`) | DB-stored only |
| Search messages | **Yes** (`search_messages`) | **Yes** | **Yes** |

### Root cause (now resolved for Telegram)

~~No external message ID tracking. When a message is sent TO Telegram, the returned `message_id` is discarded. When a message comes FROM Telegram, its `message_id` is used for dedup but never stored. Without this mapping, the system can't target a specific message on the external platform for edit/react/pin/delete.~~

**Resolved:** The `external_message_id` column on the `messages` table now tracks platform message IDs for both inbound and outbound messages. Telegram sync is fully operational.

### Current agent tools (chat group)

| Tool | What it does | External support |
| ---- | ------------ | ---------------- |
| `send_channel_message` | Post message to any channel | Yes — auto-syncs to Telegram, auto-forwards to Discord |
| `read_channel` | Read recent messages, threads, pinned | Yes — reads from workspace DB (includes external message IDs) |
| `list_channels` | List accessible channels by type | Yes — shows external channels from DB |
| `manage_message` | Edit, delete, pin, add/remove reactions | **Telegram: fully synced** — Discord: workspace DB only |
| `search_messages` | Full-text search across channels | Yes — searches all channels including external |
| `discover_external_channels` | Browse external platform channels | **Telegram: implemented** — Discord: not yet |

---

## What Agents See Today (Exact Tool Output)

### `list_channels` — Can the agent tell channels apart?

**Yes.** External channels are clearly marked with `type: external` and `provider: {name}`. They also lack the `#` prefix that internal channels have.

```
Workspace channels:
- #general (id: 9a3f..., type: public, 15 members)
- #engineering (id: 2b7c..., type: private, 8 members)
- Telegram Support, provider: telegram (id: 4d1e..., type: external, 3 members, provider: telegram)
- founders-chat, provider: telegram (id: 7f2a..., type: external, 5 members, provider: telegram)
- discord-general, provider: discord (id: 8c5b..., type: external, 42 members, provider: discord)
```

The agent can filter by type:

```json
{ "type": "external" }
```

```
Workspace channels:
- Telegram Support, provider: telegram (id: 4d1e..., type: external, 3 members, provider: telegram)
- founders-chat, provider: telegram (id: 7f2a..., type: external, 5 members, provider: telegram)
- discord-general, provider: discord (id: 8c5b..., type: external, 42 members, provider: discord)
```

**What the agent CAN tell:**
- Which channels are internal (`#` prefix, `type: public/private`)
- Which are external (`type: external`, no `#` prefix)
- Which provider each external channel belongs to (`provider: telegram`, `provider: discord`)
- How many members are in each channel

**What the agent CANNOT tell:**
- Activity level (no message count or last activity timestamp)
- Unread count
- Which external channels exist on the platform but aren't monitored yet (see Phase 3)

### `read_channel` — What messages look like to the agent

```json
{ "channelId": "4d1e...", "action": "recent_messages", "limit": 5 }
```

```
Recent messages in Telegram Support:
[2025-02-11 09:15] Alice: Hey, I'm having trouble with my subscription
[2025-02-11 09:16] Atlas: Hi Alice! I'd be happy to help. Can you tell me what error you're seeing?
[2025-02-11 09:18] Alice: It says "payment method declined" but my card works fine
[2025-02-11 09:19] Atlas: Let me check your account. One moment...
[2025-02-11 09:20] Atlas: I see the issue — your card's 3D Secure verification expired. I've reset it.
```

**What the agent CAN tell:**
- Who said what, with timestamps
- The conversation flow and context
- Channel name (from the header line)

**What the agent CANNOT tell:**
- **Message IDs** — not shown. The agent has no way to reference a specific message for reactions, pins, or edits. This is a critical gap (see below).
- **Source/origin** — was Alice's message typed in Telegram or in the workspace UI? The agent can't tell. Both look identical.
- **Reactions on messages** — existing reactions are not displayed
- **Whether a message is pinned** — not indicated in the output

**Thread reading:**

```json
{ "channelId": "4d1e...", "action": "thread", "messageId": "msg-uuid-here" }
```

```
Thread for message by Alice:
[2025-02-11 09:15] Alice: Hey, I'm having trouble with my subscription
--- Replies (2) ---
[2025-02-11 09:16] Atlas: Hi Alice! I'd be happy to help.
[2025-02-11 09:18] Alice: It says "payment method declined"
```

### `send_channel_message` — Minimal feedback

```json
{ "channelId": "4d1e...", "content": "Your subscription has been renewed successfully!" }
```

```
Message sent successfully to channel 'Telegram Support'.
```

The agent gets no message ID back — so it can't immediately edit or pin the message it just sent.

### `manage_message` — Needs message IDs it can't get

```json
{ "messageId": "???", "action": "add_reaction", "emoji": "👍" }
```

```
Reaction added.
```

**The broken workflow:** `manage_message` requires a `messageId` parameter, but `read_channel` never shows message IDs. Today, agents can only use `manage_message` on messages whose IDs they received through other means (e.g., from an event payload in their task context). They cannot read a channel and then react to something they read — the IDs are invisible.

---

## What Agents Would See After Enhancement

### Enhanced `read_channel` (after Phase 1)

Message IDs and source indicators become visible:

```
Recent messages in Telegram Support:
[msg:a1b2c3] [2025-02-11 09:15] Alice (via telegram): Hey, I'm having trouble with my subscription
[msg:d4e5f6] [2025-02-11 09:16] Atlas: Hi Alice! I'd be happy to help. Can you tell me what error you're seeing?
[msg:g7h8i9] [2025-02-11 09:18] Alice (via telegram): It says "payment method declined" but my card works fine
[msg:j0k1l2] [2025-02-11 09:19] Atlas: Let me check your account. One moment...
[msg:m3n4o5] [2025-02-11 09:20] Atlas: I see the issue — your card's 3D Secure verification expired. I've reset it. 📌
```

**New information visible:**
- `[msg:a1b2c3]` — short message ID (first 6 chars of UUID) for easy referencing
- `(via telegram)` — source indicator, only shown for external-origin messages
- `📌` — pinned indicator
- Agents can now react: `{ "messageId": "a1b2c3...", "action": "add_reaction", "emoji": "👍" }`

### Enhanced `send_channel_message` (after Phase 1)

Returns the message ID so the agent can immediately reference it:

```
Message sent to 'Telegram Support' (msg:p6q7r8).
```

### Enhanced `manage_message` (after Phase 2)

**Edit action (new):**

```json
{ "messageId": "m3n4o5...", "action": "edit", "content": "Fixed: your 3D Secure verification was expired. I've reset it — try again now." }
```

```
Message edited. Synced to telegram.
```

The edit appears in both the workspace UI AND in the Telegram chat.

**Reaction with sync:**

```json
{ "messageId": "a1b2c3...", "action": "add_reaction", "emoji": "👍" }
```

```
Reaction added. Synced to telegram.
```

The thumbs up appears natively in Telegram on Alice's message.

### `discover_external_channels` (Phase 3)

```json
{ "provider": "discord", "action": "list_server_channels" }
```

```
Discord server channels (FounderMode Community):
  #general (id: 1234567890, status: monitoring, 1,240 messages)
  #introductions (id: 1234567891, status: monitoring, 89 messages)
  #support (id: 1234567892, status: not monitored)
  #hiring (id: 1234567893, status: not monitored)
  #off-topic (id: 1234567894, status: not monitored)
  #announcements (id: 1234567895, status: monitoring, 45 messages)
```

```json
{ "provider": "discord", "action": "join_channel", "channelId": "1234567892" }
```

```
Now monitoring Discord channel #support. Messages will appear in workspace.
```

### `search_messages` (Phase 4)

```json
{ "query": "payment declined", "limit": 5 }
```

```
Found 3 messages matching "payment declined":

1. [msg:g7h8i9] [Telegram Support] Alice (2025-02-11 09:18):
   "It says 'payment method declined' but my card works fine"

2. [msg:x9y0z1] [#support] @dave (2025-02-09 15:42):
   "Getting payment declined errors on the checkout page..."

3. [msg:w8v7u6] [discord-general] Bob (via discord) (2025-02-05 11:30):
   "Has anyone else had payment declined issues recently?"
```

---

## End-to-End Example: Agent as Community Manager

Here's a realistic scenario showing an agent using multiple tools together:

**1. Agent checks its channels:**
```
→ list_channels(type: "external")

Workspace channels:
- founders-chat, provider: telegram (id: 7f2a..., type: external, 5 members, provider: telegram)
- discord-general, provider: discord (id: 8c5b..., type: external, 42 members, provider: discord)
```

**2. Agent reads recent Discord activity:**
```
→ read_channel(channelId: "8c5b...", limit: 10)

Recent messages in discord-general:
[msg:aa1122] [2025-02-11 14:01] Sarah (via discord): Does anyone know how to set up webhooks?
[msg:bb3344] [2025-02-11 14:03] Mike (via discord): I think there's a guide somewhere
[msg:cc5566] [2025-02-11 14:05] Sarah (via discord): Can't find it 😕
```

**3. Agent searches for context:**
```
→ search_messages(query: "webhook guide")

Found 1 message matching "webhook guide":
1. [msg:zz9900] [#announcements] System (2025-01-15 10:00):
   "New webhook guide published: https://docs.example.com/webhooks"
```

**4. Agent responds with the link:**
```
→ send_channel_message(channelId: "8c5b...", content: "Hey Sarah! Here's the webhook guide: https://docs.example.com/webhooks — let me know if you have questions!")

Message sent to 'discord-general' (msg:dd7788).
```
*This message appears in Discord as a bot message.*

**5. Agent reacts to Sarah's original question:**
```
→ manage_message(messageId: "aa1122...", action: "add_reaction", emoji: "✅")

Reaction added. Synced to discord.
```
*The checkmark appears natively on Sarah's message in Discord.*

**6. Agent discovers a new channel to monitor:**
```
→ discover_external_channels(provider: "discord", action: "list_server_channels")

Discord server channels (FounderMode Community):
  #general (id: 8c5b..., status: monitoring, 1,240 messages)
  #support (id: 9d6c..., status: not monitored)
  ...

→ discover_external_channels(provider: "discord", action: "join_channel", channelId: "9d6c...")

Now monitoring Discord channel #support. Messages will appear in workspace.
```

---

## Architecture: Workspace as Hub

```
                    ┌─────────────────────────────────┐
                    │         Agent Tools              │
                    │  (provider-agnostic, as today)   │
                    │                                  │
                    │  send_channel_message             │
                    │  edit_message        (NEW)       │
                    │  manage_message      (ENHANCED)  │
                    │  read_channel                     │
                    │  list_channels       (ENHANCED)  │
                    │  search_messages     (NEW)       │
                    └──────────┬──────────────────────┘
                               │
                    ┌──────────▼──────────────────────┐
                    │     Workspace (Source of Truth)   │
                    │                                  │
                    │  Message  ←→  external_message_id │
                    │  Reaction ←→  synced to platform  │
                    │  Pin      ←→  synced to platform  │
                    │  Edit     ←→  synced to platform  │
                    └──────────┬──────────────────────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
     ┌────────▼───────┐ ┌─────▼──────┐  ┌──────▼──────┐
     │   Telegram      │ │  Discord   │  │  Future...  │
     │                 │ │            │  │  Slack etc  │
     │  SyncTo         │ │  SyncTo    │  │             │
     │  Telegram.php   │ │  Discord   │  │  SyncTo...  │
     │  (all actions)  │ │  (all)     │  │             │
     └─────────────────┘ └────────────┘  └─────────────┘
```

**Key principles:**
- Agent tools stay **provider-agnostic** — they work on workspace models (Message, Channel, Reaction)
- The **sync layer** handles platform specifics — one consolidated listener per platform
- Adding a new platform (Slack, WhatsApp) means adding one `SyncTo*` listener, not changing tools
- The workspace is **source of truth** — external platforms are bidirectional mirrors

---

## Phase 1: External Message ID Tracking

**Prerequisite for everything else.** Without knowing which workspace message maps to which Telegram/Discord message, you can't edit, react to, pin, or delete it on the external platform.

### What changes

1. **Migration**: Add `external_message_id` column to `messages` table (nullable string)
   - Combined with `channel.external_provider`, this uniquely identifies the external message

2. **Store inbound IDs**: `TelegramWebhookController::handleMessage()` already reads `$message['message_id']` for dedup. Store it on the created Message:

   ```php
   $msg = Message::create([...
       'external_message_id' => (string) $telegramMessageId,
   ]);
   ```

3. **Store outbound IDs**: `ForwardMessageToTelegram` must capture the returned message ID from `sendMessage()` and store it:

   ```php
   $result = $telegram->sendMessage($chatId, $text);
   $message->update(['external_message_id' => (string) $result['message_id']]);
   ```

4. **TelegramService::sendMessage()**: Currently returns void. Change to return the API response (which includes `message_id`). Same for `sendPhoto()`, `sendDocument()`.

5. **Enhance ReadChannel output**: Include message IDs and source indicators in the output so agents can reference specific messages. Currently `ReadChannel.php` formats messages as `[timestamp] Author: content` — change to `[msg:id] [timestamp] Author (via source): content`. Also include pinned indicator.

6. **Enhance SendChannelMessage output**: Return the message ID in the success response so the agent can immediately reference the message it just sent.

### Files to modify

| File | Change |
| ---- | ------ |
| New migration | Add `external_message_id` to `messages` |
| `app/Models/Message.php` | Add to `$fillable` |
| `app/Services/TelegramService.php` | Return response from `sendMessage()` etc. |
| `app/Listeners/ForwardMessageToTelegram.php` | Store returned message ID |
| `app/Http/Controllers/Api/TelegramWebhookController.php` | Store inbound message ID |
| `app/Agents/Tools/Chat/ReadChannel.php` | Add message IDs, source indicators, pin markers to output |
| `app/Agents/Tools/Chat/SendChannelMessage.php` | Return message ID in success response |

Same pattern applies to Discord when implemented — `DiscordService::sendMessage()` returns the message ID, `ForwardMessageToDiscord` stores it, `DiscordWebhookController` stores inbound IDs.

---

## Phase 2: Bidirectional Sync Layer

### Consolidated listener pattern

One listener per platform handles ALL sync types. Existing `ForwardMessageToTelegram` gets absorbed into `SyncToTelegram`:

```php
class SyncToTelegram implements ShouldQueue
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSent::class           => 'handleMessageSent',
            MessageEdited::class         => 'handleMessageEdited',
            MessageDeleted::class        => 'handleMessageDeleted',
            MessagePinned::class         => 'handleMessagePinned',
            MessageReactionAdded::class  => 'handleReactionAdded',
        ];
    }

    public function handleMessageSent(MessageSent $event): void
    {
        // Current ForwardMessageToTelegram logic moves here
    }

    public function handleReactionAdded(MessageReactionAdded $event): void
    {
        // Look up external_message_id → call setMessageReaction
    }

    // ... etc
}
```

Same pattern for `SyncToDiscord`. All platform-specific logic in one file per platform.

### 2a. Reaction sync

**Outbound** (workspace → platform):
- `ManageMessage` fires `MessageReactionAdded` event after adding a reaction
- `SyncToTelegram::handleReactionAdded()` calls Telegram `setMessageReaction` API
- `SyncToDiscord::handleReactionAdded()` calls Discord `PUT /channels/{id}/messages/{id}/reactions/{emoji}/@me`
- Requires `external_message_id` to target the correct message

**Inbound** (platform → workspace):
- Telegram: `message_reaction` update type → webhook controller creates `MessageReaction` in DB
- Discord: `messageReactionAdd` Gateway event → sidecar forwards → controller creates `MessageReaction`

**New API methods needed:**

```php
// TelegramService
public function setMessageReaction(string $chatId, int $messageId, string $emoji): array

// DiscordService
public function addReaction(string $channelId, string $messageId, string $emoji): void
public function removeReaction(string $channelId, string $messageId, string $emoji): void
```

### 2b. Edit sync

**New `edit` action in ManageMessage** (or separate `edit_message` tool):
- Agent provides `messageId` + `newContent`
- Updates Message content in DB
- Fires `MessageEdited` event
- `SyncToTelegram::handleMessageEdited()` calls `editMessageText()`
- `SyncToDiscord::handleMessageEdited()` calls `PATCH /channels/{id}/messages/{id}`

### 2c. Pin sync

When `ManageMessage` pins a message:
- Fires `MessagePinned` event
- `SyncToTelegram::handleMessagePinned()` calls `pinChatMessage` API
- `SyncToDiscord::handleMessagePinned()` calls `PUT /channels/{id}/pins/{message_id}`

### 2d. Delete sync

When `ManageMessage` deletes a message:
- Fires `MessageDeleted` event
- `SyncToTelegram::handleMessageDeleted()` calls `deleteMessage` API
- `SyncToDiscord::handleMessageDeleted()` calls `DELETE /channels/{id}/messages/{message_id}`

### Files

| File | Purpose |
|------|---------|
| `app/Events/MessageEdited.php` | New event |
| `app/Events/MessageDeleted.php` | New event |
| `app/Events/MessagePinned.php` | New event |
| `app/Events/MessageReactionAdded.php` | New event |
| `app/Listeners/SyncToTelegram.php` | Replaces `ForwardMessageToTelegram`, handles all sync types |
| `app/Listeners/SyncToDiscord.php` | Same pattern for Discord |
| `app/Agents/Tools/Chat/ManageMessage.php` | Fire new events after each action; add `edit` action |
| `app/Services/TelegramService.php` | Add `setMessageReaction`, `pinChatMessage`, `deleteMessage` |
| `app/Services/DiscordService.php` | Add `addReaction`, `removeReaction`, `editMessage`, `pinMessage`, `deleteMessage` |

---

## Phase 3: External Channel Discovery

Agents should be able to **browse a Discord server's channels** — not just ones already stored in the DB from received messages — and decide to monitor new ones.

### New tool: `discover_external_channels`

```
Parameters:
  - provider: 'discord' | 'telegram'
  - action: 'list_server_channels' | 'join_channel' | 'leave_channel'
  - channelId: (for join/leave — the external platform's channel ID)
```

### How it works

**`list_server_channels`**: Calls Discord REST API `GET /guilds/{guild_id}/channels` → returns all text channels in the server, marking which ones are already being monitored (have a workspace Channel record).

Example agent output:
```
Discord server channels:
  #general (id: 123456, monitoring: yes, 340 messages)
  #support (id: 123457, monitoring: no)
  #hiring (id: 123458, monitoring: no)
  #announcements (id: 123459, monitoring: yes, 12 messages)
```

**`join_channel`**: Creates a workspace Channel record for a Discord channel that isn't in the DB yet. The sidecar is already forwarding ALL events — Laravel just wasn't creating a Channel for messages in unmonitored channels. After joining, messages from that channel get processed.

**`leave_channel`**: Marks a Channel as inactive / stops processing messages from it. Does NOT delete history.

This lets an agent say: *"I see there's a #support channel with unanswered questions. Let me start monitoring it."*

### Files

| File | Purpose |
|------|---------|
| `app/Agents/Tools/Chat/DiscoverExternalChannels.php` | New tool |
| `app/Services/DiscordService.php` | Add `listGuildChannels()` |
| `app/Agents/Tools/ToolRegistry.php` | Register in chat group |

---

## Phase 4: Message Search

Agents need to research conversation history — essential for a community manager that needs context before responding.

### New tool: `search_messages`

```
Parameters:
  - query: search string (required)
  - channelId: scope to channel (optional)
  - authorId: filter by author (optional)
  - limit: max results, default 20 (optional)
```

Uses SQL full-text search or `LIKE` on `messages.content`. Returns matching messages with channel name, author, timestamp, and a content snippet with the match highlighted.

Example agent output:
```
Found 3 messages matching "pricing":
1. [#general] @alice (2025-05-10 14:23): "What's the pricing for the pro plan? I saw..."
2. [#support] @bob (2025-05-08 09:15): "Updated pricing page is live, check..."
3. [#announcements] @system (2025-05-01 12:00): "New pricing tiers announced..."
```

### Files

| File | Purpose |
|------|---------|
| `app/Agents/Tools/Chat/SearchMessages.php` | New tool |
| `app/Agents/Tools/ToolRegistry.php` | Register in chat group |

---

## Phasing Summary

| Phase | What | Unlocks | Depends on |
|-------|------|---------|------------|
| **1** | External message ID tracking | Edit, react, pin, delete on external platforms | Nothing |
| **2** | Bidirectional sync events + consolidated listeners | Agent reactions/pins/edits appear on Discord/Telegram | Phase 1 |
| **3** | External channel discovery | Agents browse and join Discord channels proactively | Nothing |
| **4** | Message search | Agents research conversation history | Nothing |

---

## What This Enables

With all phases complete, an agent can:

1. **Browse** all Discord channels → *"There are 12 channels in the server. #general is most active, #support has 3 unanswered questions."*
2. **Join** a new channel → *"I'll start monitoring #support to help answer questions."*
3. **React** to a user's message with a thumbs up → the reaction appears natively in Discord/Telegram
4. **Pin** an important announcement → pinned in both workspace and platform
5. **Edit** its own previous response → edited in Discord/Telegram too
6. **Search** past conversations → *"Last week, user X asked about pricing. Here's what was discussed..."*
7. **Move between channels** strategically → *"The conversation in #general is about our roadmap. Let me check #product-updates for context, then respond."*

The agent becomes a **real community participant** — not a bot stuck in one channel waiting for pings.

---

## Updated chat tool group (after all phases)

```php
'chat' => [
    'tools' => [
        'send_channel_message',
        'read_channel',
        'list_channels',
        'manage_message',           // enhanced: edit action, fires sync events
        'discover_external_channels', // NEW
        'search_messages',           // NEW
    ],
    'label' => 'send, read, list, manage, discover, search',
    'description' => 'Channel messaging with bidirectional external sync (Telegram, Discord)',
],
```
