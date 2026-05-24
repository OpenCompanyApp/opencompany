# Bidirectional External Channel Sync

Making agents full community participants — not just chatbots.

## Implementation Status

| Phase | Telegram | Discord |
|-------|----------|---------|
| Phase 1: External message ID tracking | **Done** | N/A yet |
| Phase 2: Bidirectional sync (edit/delete/pin/react) | **Done** | Not started (OC-44) |
| Phase 3: External channel discovery | **Done** (monitored channels) | Not started (OC-44) |
| Phase 4: Message search | **Done** | Done (DB-level) |

**Key implementation files (Telegram - current tracked code):**
- `app/Events/MessageEdited.php`, `MessageDeleted.php`, `MessagePinned.php`, `MessageReactionAdded.php` — Sync events
- `app/Services/TelegramService.php` — Platform API methods (edit, delete, pin, react)
- `app/Agents/Tools/Chat/EditMessage.php`, `DeleteMessage.php`, `PinMessage.php`, `AddMessageReaction.php`, `RemoveMessageReaction.php` — Agent tools for message mutations
- `app/Agents/Tools/Chat/SearchMessages.php` — Full-text message search tool
- `app/Agents/Tools/Chat/ListExternalChannels.php`, `JoinExternalChannel.php`, `LeaveExternalChannel.php` — Browse and manage external platform channel membership
- `database/migrations/2026_02_14_200001_add_external_message_id_to_messages_table.php` — External ID tracking

**What's left:** Discord sync listener (`SyncToDiscord`), Discord channel discovery via REST API, Discord webhook controller for inbound events. Tracked as OC-44 in Plane.

---

## Architecture: Workspace as Hub

```
                    ┌─────────────────────────────────┐
                    │         Agent Tools              │
                    │  (provider-agnostic, as today)   │
                    │                                  │
                    │  send_channel_message             │
                    │  manage_message      (ENHANCED)  │
                    │  read_channel                     │
                    │  list_channels                    │
                    │  search_messages                  │
                    │  discover_external_channels       │
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

## Discord Implementation Plan

Discord sync follows the same consolidated listener pattern as Telegram. Files to create:

| File | Purpose |
|------|---------|
| `app/Listeners/SyncToDiscord.php` | Handles all sync types (send, edit, delete, pin, react) |
| `app/Services/DiscordService.php` | Add `addReaction`, `removeReaction`, `editMessage`, `pinMessage`, `deleteMessage`, `listGuildChannels` |

The sidecar architecture for Discord is documented in [discord.md](discord.md).

---

## What Full Sync Enables

With all phases complete for a platform, an agent can:

1. **Browse** all channels → *"There are 12 channels. #support has 3 unanswered questions."*
2. **Join** a new channel → *"I'll start monitoring #support to help answer questions."*
3. **React** to a user's message → the reaction appears natively on the platform
4. **Pin** an important announcement → pinned in both workspace and platform
5. **Edit** its own previous response → edited on the platform too
6. **Search** past conversations → *"Last week, user X asked about pricing."*
7. **Move between channels** strategically

The agent becomes a **real community participant** — not a bot stuck in one channel.
