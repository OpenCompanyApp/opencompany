# Discord Integration

Bidirectional message bridge between Discord and OpenCompany. Agents can respond to Discord users, forward messages, and handle approval workflows with interactive buttons.

## Agent Modes

| Mode | Description |
|------|-------------|
| **Community manager** | Agent responds to @mentions in any channel on a Discord server |
| **Channel-locked** | Agent only listens to specific Discord channels |
| **User-locked** | Agent only responds to specific Discord users |

Modes are configured via **listen mode** + **allowed channels/users** in the integration settings. These can be combined (e.g., mentions-only + specific users).

---

## Architecture

```
INBOUND:  Discord Gateway → Node.js sidecar → POST /api/webhooks/discord → Laravel
OUTBOUND: Agent Message → MessageSent event → ForwardMessageToDiscord → Discord REST API
BUTTONS:  Discord button click → Gateway interactionCreate → sidecar → Laravel
```

### Why a sidecar?

Telegram provides native HTTP webhooks — Telegram POSTs messages directly to your server. Discord does **not** support this. Receiving messages requires a persistent WebSocket connection to Discord's Gateway API.

Since Laravel is request-based (not persistent), a lightweight **Node.js sidecar process** handles the Gateway connection. It receives events from Discord and forwards them as HTTP requests to a Laravel webhook endpoint. All business logic — filtering, permissions, message storage, agent dispatch — lives in Laravel.

### What's reused from existing infrastructure

These components are provider-agnostic and work for Discord without modification:

- `Channel` model — `external_provider='discord'`, `external_id`, `external_config`
- `UserExternalIdentity::resolveUser('discord', id)` — user identity linking
- `send_channel_message` / `list_channels` agent tools — work for any external channel
- `AgentRespondJob` — agent response pipeline
- `/api/integrations/link-user` endpoint — linking Discord users to system accounts
- `/api/channels?type=external&provider=discord` — channel filtering
- `IntegrationSetting` model — encrypted config storage

---

## Sidecar Deep Dive

### What the sidecar is

- A ~80-line Node.js script (`discord-bot/bot.js`) using [discord.js](https://discord.js.org/) v14
- Connects to Discord's Gateway via WebSocket
- **Stateless relay** — it forwards ALL non-bot events to Laravel without filtering
- All business logic (listen mode, allowed users, message storage) lives in Laravel
- Polls a Laravel health endpoint every 30s — automatically connects/disconnects based on the integration's enabled state
- Config changes in the UI (including enable/disable and token changes) take effect without restarting the sidecar

### How it starts

**Development:**
```bash
cd discord-bot
npm install
cp .env.example .env
# Fill in DISCORD_BOT_TOKEN, OPENCOMPANY_WEBHOOK_URL, OPENCOMPANY_WEBHOOK_SECRET
node bot.js
```

**Production with pm2:**
```bash
cd discord-bot
npm install --production
pm2 start bot.js --name opencompany-discord
pm2 save
pm2 startup  # auto-start on reboot
```

**Production with systemd:**
```ini
[Unit]
Description=OpenCompany Discord Bot
After=network.target

[Service]
Type=simple
WorkingDirectory=/path/to/opencompany/discord-bot
ExecStart=/usr/bin/node bot.js
Restart=always
RestartSec=5
EnvironmentFile=/path/to/opencompany/discord-bot/.env

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl enable opencompany-discord
sudo systemctl start opencompany-discord
```

### How it stays running

- **Reconnection**: discord.js handles Gateway reconnection automatically. If the WebSocket drops, it reconnects and resumes the session. Missed events during the brief disconnect are replayed by Discord (Gateway resume).
- **Crash recovery**: pm2 or systemd restarts the process if it crashes. The sidecar is stateless, so a restart loses nothing.
- **No state to persist**: The sidecar holds no data. It's safe to restart, redeploy, or replace at any time.

### Integration-aware lifecycle

The sidecar polls a Laravel health endpoint (`GET /api/webhooks/discord/status`) every 30 seconds. This endpoint returns `{ "enabled": true/false, "token": "..." }`. The sidecar uses this to manage its own Discord connection:

- **Integration enabled** → sidecar connects to Discord Gateway (or stays connected)
- **Integration disabled** → sidecar disconnects from Discord Gateway (stops receiving events, releases the WebSocket)
- **Integration re-enabled** → sidecar reconnects automatically on the next poll

This means:

- The sidecar process is always running (managed by pm2/systemd), but it only holds an active Discord connection when the integration is enabled.
- Toggling the integration off in the UI disconnects the bot from Discord within ~30 seconds.
- No manual process management is needed — the enable/disable toggle in the UI controls everything.
- The sidecar also reads the bot token from the status endpoint, so token changes in the UI take effect without a restart.

```
┌─────────────────────────────────────────────────────┐
│  Sidecar lifecycle                                  │
│                                                     │
│  Process starts → polls /status                     │
│       │                                             │
│       ├─ enabled: true  → connect to Discord GW     │
│       │                    forward events to Laravel │
│       │                    keep polling /status      │
│       │                                             │
│       ├─ enabled: false → disconnect from Discord   │
│       │                    keep polling /status      │
│       │                    (idle, no resources used) │
│       │                                             │
│       └─ /status unreachable → stay disconnected    │
│                                 retry on next poll  │
└─────────────────────────────────────────────────────┘
```

### When the sidecar is stopped

- **Inbound messages are not received** — no Gateway connection means no events from Discord.
- **Outbound messages still work** — `DiscordService` uses Discord's REST API directly, independent of the sidecar.
- **Approval button clicks are not processed** — button interactions come via the Gateway.
- **No data loss** — Discord does not queue Gateway events. After the sidecar restarts, new events are received normally.

### Environment variables

```bash
# Discord bot token from the Developer Portal
DISCORD_BOT_TOKEN=

# Laravel webhook endpoint
OPENCOMPANY_WEBHOOK_URL=http://opencompany.test/api/webhooks/discord

# Shared secret (generated via "Generate Sidecar Config" in the UI)
OPENCOMPANY_WEBHOOK_SECRET=
```

### Sidecar events

The sidecar forwards two types of events to Laravel:

| Event | Discord event | Payload `type` | Handler |
|-------|--------------|-----------------|---------|
| Message | `messageCreate` | `message_create` | `handleMessage()` |
| Button click | `interactionCreate` | `interaction` | `handleInteraction()` |

Bot-authored messages are skipped at the sidecar level (no forwarding).

---

## Discord Bot Setup (Prerequisites)

### 1. Create a Discord Application

1. Go to [Discord Developer Portal](https://discord.com/developers/applications)
2. Click **New Application** → name it (e.g., "OpenCompany Bot")
3. Go to the **Bot** tab → click **Reset Token** → copy the bot token

### 2. Enable Privileged Intents

In the **Bot** tab, under **Privileged Gateway Intents**, enable:
- **MESSAGE CONTENT INTENT** — required to read message text. Without this, `message.content` will be empty.

The sidecar connects with these Gateway intents:
- `Guilds` — server/channel metadata
- `GuildMessages` — messages in server channels
- `MessageContent` — actual message text (privileged)
- `DirectMessages` — DMs with the bot

### 3. Generate Invite URL

In the **OAuth2** tab → **URL Generator**:
- **Scopes**: `bot`
- **Bot Permissions**: Send Messages, Read Message History, View Channels, Attach Files, Add Reactions
- **Permission integer**: `274877975552`

Copy the generated URL → open in browser → select your server → authorize.

### 4. Get the Guild ID

In Discord (desktop/web):
1. Settings → Advanced → enable **Developer Mode**
2. Right-click your server name → **Copy Server ID**

---

## Configuration (OpenCompany UI)

Navigate to **Integrations → Discord → Configure**.

| Field | Description |
|-------|-------------|
| **Bot Token** | Discord bot token from the Developer Portal |
| **Default Agent** | Which agent responds to Discord messages |
| **Guild ID** | Discord server ID (right-click server → Copy ID) |
| **Listen Mode** | How the bot decides which messages to process (see below) |
| **Allowed Channels** | Channel IDs to monitor (only in `specific_channels` mode) |
| **Allowed Users** | Discord user IDs — empty means all users allowed |
| **Approvals Channel** | Channel ID where approval notification buttons are sent |

### Actions

- **Test Connection** — validates the bot token by calling Discord's `GET /users/@me` endpoint
- **Generate Sidecar Config** — creates a webhook secret and displays the `.env` values to copy into the sidecar's environment

---

## Listen Modes

| Mode | Behavior | Use Case |
|------|----------|----------|
| `mentions_only` | Bot only processes messages where it is @mentioned. The bot mention (`<@botId>`) is stripped from the content before sending to the agent. | Community manager in a public server. Bot stays quiet unless directly asked. |
| `specific_channels` | Bot only processes messages from channels listed in `allowed_channels`. All messages in those channels are processed (no @mention required). | Dedicated bot channel like `#ask-agent` or `#support`. |
| `all_messages` | Bot processes every message in the server. | Small private servers or support-only servers. Use with caution on active servers — the agent will receive every message. |

### Combining with allowed_users

Listen modes can be combined with the `allowed_users` whitelist:

- `allowed_users` **empty** → all users can interact with the bot
- `allowed_users` **has IDs** → only those users trigger the bot, even in `all_messages` mode

Examples:
- `mentions_only` + empty `allowed_users` = anyone can @mention the bot (community manager)
- `specific_channels` + `allowed_users: ["123"]` = only user 123 in those channels
- `all_messages` + empty `allowed_users` = every message from every user (support bot)

---

## Inbound Message Flow

```
Discord user sends message (or @mentions bot)
    │
    ▼
Discord Gateway → sidecar receives messageCreate
    │
    ▼
Sidecar skips bot authors, POSTs payload to Laravel
    │
    ▼
DiscordWebhookController::handle()
    ├── Verify X-Discord-Webhook-Secret header
    ├── Check integration is enabled
    ├── Dedup via cache lock (prevents processing retries)
    ├── Apply listen mode filter (mentions_only / specific_channels / all_messages)
    ├── Apply allowed_users filter
    ├── Find/create Channel (external_provider='discord', external_id=channelId)
    ├── Resolve user via UserExternalIdentity or create shadow user
    ├── Create Message (source='discord')
    ├── Broadcast MessageSent event
    └── Dispatch AgentRespondJob
            │
            ▼
    Agent generates response → new Message created
            │
            ▼
    ForwardMessageToDiscord listener
        ├── Skip if source='discord' (echo loop prevention)
        ├── Send chart/attachment images via Discord REST API
        └── Send text with **authorName** prefix (Discord supports markdown natively)
```

---

## Outbound Message Flow

When an agent sends a message to a Discord channel (via `send_channel_message` tool or direct Message creation):

1. `Message::create()` → `MessageSent` event broadcast
2. `ForwardMessageToDiscord` listener picks it up
3. Checks: `channel.type === 'external'` and `channel.external_provider === 'discord'`
4. Sends via `DiscordService::sendMessage()` (REST API `POST /channels/{id}/messages`)
5. Echo prevention: messages with `source === 'discord'` are skipped (they came FROM Discord)

Discord supports markdown natively, so no HTML conversion is needed (unlike Telegram which requires HTML formatting).

---

## Approval Buttons

Discord supports **Message Components** (interactive buttons) via the REST API. When the bot sends an approval notification, it includes an Action Row with Approve and Reject buttons.

### How it looks in Discord

```
┌────────────────────────────────────────┐
│  Approval Required                     │
│                                        │
│  Deploy to production                  │
│  Type: Tool Access                     │
│  From: Atlas                           │
│                                        │
│  [ Approve ]  [ Reject ]              │
└────────────────────────────────────────┘
```

### Button click flow

1. User clicks a button in Discord
2. Discord sends `interactionCreate` event via the Gateway
3. Sidecar forwards to Laravel with `type: 'interaction'`
4. `DiscordWebhookController::handleInteraction()`:
   - Parses `custom_id` (`"approve:{uuid}"` or `"reject:{uuid}"`)
   - Finds the `ApprovalRequest` by ID
   - Resolves the Discord user who clicked (via `UserExternalIdentity`)
   - Updates approval status and `responded_by_id`
   - If approved with `tool_execution_context`: executes the approved tool
   - Edits the original message to remove buttons and show the result

This mirrors the Telegram callback_query handling in `TelegramWebhookController::handleCallbackQuery()`.

### Button component structure

```php
$components = [[
    'type' => 1, // ACTION_ROW
    'components' => [
        ['type' => 2, 'style' => 3, 'label' => 'Approve', 'custom_id' => "approve:{$approvalId}"],
        ['type' => 2, 'style' => 4, 'label' => 'Reject',  'custom_id' => "reject:{$approvalId}"],
    ],
]];
```

Button styles: 1 = Primary (blurple), 2 = Secondary (grey), 3 = Success (green), 4 = Danger (red).

---

## User Identity Linking

Discord users are resolved in this order:

1. Check `UserExternalIdentity` for `provider='discord'` and `external_id=discordUserId`
2. If found → use the linked system user
3. If not → create an **ephemeral shadow user**:
   - Email: `discord-{discordUserId}@external.opencompany`
   - Name: `{displayName} (Discord)`
   - `is_ephemeral: true`

Shadow users appear in the UI with the user's Discord display name. They can be linked to real system users via the Discord config modal's **User Mappings** section (same mechanism as Telegram). When linked:
- The shadow user is merged into the real user
- All messages and channel memberships are reassigned
- The shadow user is deleted

---

## Implementation Reference

### New files

| File | Purpose |
|------|---------|
| `discord-bot/package.json` | Node.js project, single dependency: discord.js |
| `discord-bot/.env.example` | Sidecar environment template |
| `discord-bot/bot.js` | Gateway sidecar (~80 lines) — connects to Discord, forwards events to Laravel |
| `app/Services/DiscordService.php` | Discord REST API wrapper: `sendMessage`, `sendFile`, `editMessage`, `triggerTyping`, `getMe` |
| `app/Http/Controllers/Api/DiscordWebhookController.php` | Receives sidecar events, handles messages + button interactions. Also serves `GET /status` for sidecar health polling. |
| `app/Listeners/ForwardMessageToDiscord.php` | Outbound message listener (ShouldQueue) |
| `app/Jobs/SendApprovalToDiscordJob.php` | Approval notification with button components |
| `resources/js/Components/integrations/DiscordConfigModal.vue` | Configuration UI modal |

### Modified files

| File | Change |
|------|--------|
| `config/integrations.php` | Add `'discord'` entry |
| `routes/api.php` | Add `POST /webhooks/discord` and `GET /webhooks/discord/status` routes |
| `app/Http/Controllers/Api/IntegrationController.php` | Discord config, test connection, webhook setup branches |
| `app/Observers/ApprovalRequestObserver.php` | Dispatch `SendApprovalToDiscordJob` |
| `app/Agents/Tools/Workspace/ManageIntegration.php` | Discord test, webhook, config support |
| `app/Agents/Tools/ToolRegistry.php` | Update chat app group description to mention Discord |
| `resources/js/Pages/Integrations.vue` | Wire up `DiscordConfigModal` |

### Config fields (IntegrationSetting.config)

| Key | Type | Description |
|-----|------|-------------|
| `api_key` | string | Discord bot token |
| `default_agent_id` | uuid | Agent that responds to messages |
| `guild_id` | string | Discord server ID |
| `listen_mode` | enum | `mentions_only` / `specific_channels` / `all_messages` |
| `allowed_channels` | string[] | Discord channel IDs (for `specific_channels` mode) |
| `allowed_users` | string[] | Discord user IDs (empty = all users allowed) |
| `notify_channel_id` | string | Channel for approval button notifications |
| `webhook_secret` | string | Shared secret for sidecar ↔ Laravel auth |
| `bot_username` | string | Cached from `/users/@me` after test connection |
