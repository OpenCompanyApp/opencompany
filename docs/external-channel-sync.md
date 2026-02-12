# Bidirectional External Channel Sync

Making agents full community participants — not just chatbots.

## The Problem

Agents can send messages to external channels (Telegram, Discord) and that's it. Reactions, pins, edits, and deletes are workspace-only — they never sync to the external platform. Agents can't browse Discord server channels, can't react to a Telegram message, can't edit their own response after sending. They're chatbots, not community members.

## The Vision

Agents should feel like **real team members** on Discord and Telegram — browsing channels, reacting to messages, editing responses, pinning important content, moving between channels strategically. The workspace is the brain; external platforms are the hands.

---

## Current State

| Capability | Internal channels | External channels |
|---|---|---|
| Send messages | Yes | Yes (auto-forwards) |
| Read messages | Yes | Yes (from DB only) |
| Edit messages | **No tool exists** | No |
| Add reactions | Yes (DB only) | **Not synced** |
| Pin messages | Yes (DB only) | **Not synced** |
| Delete messages | Yes (DB only) | **Not synced** |
| Browse channels | Yes (`list_channels`) | **DB-stored only** — can't discover new Discord channels |
| Search messages | **No tool exists** | No |

### Root cause

No external message ID tracking. When a message is sent TO Telegram, the returned `message_id` is discarded. When a message comes FROM Telegram, its `message_id` is used for dedup but never stored. Without this mapping, the system can't target a specific message on the external platform for edit/react/pin/delete.

### Current agent tools (chat group)

| Tool | What it does | External support |
|------|-------------|-----------------|
| `send_channel_message` | Post message to any channel | Yes — auto-forwards to Telegram/Discord |
| `read_channel` | Read recent messages, threads, pinned | Yes — reads from workspace DB |
| `list_channels` | List accessible channels by type | Yes — shows external channels from DB |
| `manage_message` | Delete, pin, add/remove reactions | **Workspace DB only** — nothing syncs to platform |

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

### Files to modify

| File | Change |
|------|--------|
| New migration | Add `external_message_id` to `messages` |
| `app/Models/Message.php` | Add to `$fillable` |
| `app/Services/TelegramService.php` | Return response from `sendMessage()` etc. |
| `app/Listeners/ForwardMessageToTelegram.php` | Store returned message ID |
| `app/Http/Controllers/Api/TelegramWebhookController.php` | Store inbound message ID |

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
