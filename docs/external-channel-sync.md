# Bidirectional External Channel Sync

Making agents full community participants — not just chatbots.

## Implementation Status

| Phase | Current status |
|-------|----------------|
| Phase 1: External message ID tracking | **Done** for workspace messages through `external_message_id` |
| Phase 2: Bidirectional sync (edit/delete/pin/react) | **Done through `SyncToChat` where the selected Chatogrator adapter implements the action** |
| Phase 3: External channel discovery | **Partly done** through external channel tools and adapter support; production readiness varies by provider |
| Phase 4: Message search | **Done** at the database/workspace-message level |

**Key implementation files (current tracked code):**
- `app/Events/MessageEdited.php`, `MessageDeleted.php`, `MessagePinned.php`, `MessageReactionAdded.php` — Sync events
- `app/Listeners/SyncToChat.php` — Consolidated outbound listener for send, edit, delete, pin, and reaction sync through chat adapters
- `app/Services/Chat/ChatAdapterFactory.php` — Maps `IntegrationSetting` rows to Telegram, Slack, Discord, Teams, Google Chat, GitHub, and Linear Chatogrator adapters
- `app/Http/Controllers/Api/ChatWebhookController.php` — Generic `/api/webhooks/chat/{adapter}` webhook entrypoint with workspace resolution
- `app/Services/TelegramService.php` — Legacy/direct Telegram helpers still used by Telegram-specific commands and flows
- `app/Agents/Tools/Chat/EditMessage.php`, `DeleteMessage.php`, `PinMessage.php`, `AddMessageReaction.php`, `RemoveMessageReaction.php` — Agent tools for message mutations
- `app/Agents/Tools/Chat/SearchMessages.php` — Full-text message search tool
- `app/Agents/Tools/Chat/ListExternalChannels.php`, `JoinExternalChannel.php`, `LeaveExternalChannel.php` — Browse and manage external platform channel membership
- `database/migrations/2026_02_14_200001_add_external_message_id_to_messages_table.php` — External ID tracking

**What's left:** provider-by-provider hardening. Telegram has the most complete
app-specific path. Slack, Discord, Teams, Google Chat, GitHub, and Linear have
Chatogrator adapter and webhook-resolution plumbing, but each provider still
needs verification for inbound events, outbound action parity, channel discovery,
signature/auth handling, and UI setup before broad marketing claims.

---

## Architecture: Workspace as Hub

```
                    ┌─────────────────────────────────┐
                    │         Agent Tools              │
                    │  (provider-agnostic, as today)   │
                    │                                  │
                    │  send_channel_message             │
                    │  edit/delete/pin/reaction tools   │
                    │  read_channel                     │
                    │  list_channels                    │
                    │  search_messages                  │
                    │  list_external_channels           │
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
     │   Telegram      │ │   Slack    │  │ Discord/    │
     │                 │ │            │  │ Teams/etc   │
     │  SyncToChat.php │ │SyncToChat  │  │SyncToChat   │
     │  via adapter    │ │via adapter │  │via adapter  │
     │  (most mature)  │ │(verify)    │  │(verify)     │
     └─────────────────┘ └────────────┘  └─────────────┘
```

**Key principles:**
- Agent tools stay **provider-agnostic** — they work on workspace models (Message, Channel, Reaction)
- The **sync layer** handles platform specifics through `SyncToChat` and the selected Chatogrator adapter
- Adding a new platform should mean adding/validating one adapter and webhook-resolution path, not changing agent tools
- The workspace is **source of truth** — external platforms are bidirectional mirrors

---

## Provider Hardening Plan

For each provider beyond Telegram, verify the adapter and app integration end to end:

| Area | What to prove |
|------|---------------|
| Webhook resolution | `ChatWebhookController` can authenticate and bind the correct workspace |
| Inbound events | Chatogrator adapter converts provider payloads into workspace messages/actions |
| Outbound actions | `SyncToChat` can send, edit, delete, pin, and react where the provider supports it |
| Channel discovery | Agent tools can list/join/leave provider channels where the provider API allows it |
| UI setup | Integration settings collect all required credentials and explain provider setup clearly |

The earlier Discord sidecar architecture is documented in [discord.md](discord.md);
cross-check it with the current Chatogrator path before implementing new Discord
work.

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
