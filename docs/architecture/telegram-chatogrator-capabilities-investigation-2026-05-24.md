# Telegram and Chatogrator Capabilities Investigation

Research-only snapshot for OpenCompany, KosmoKrator, and Hermes Agent as of 2026-05-24. This is not an implementation plan.

Implementation note: later work moved OpenCompany Telegram to the app-owned
`App\Domain\Chat\Telegram` runtime behind `/api/webhooks/chat/telegram`, retired
`/api/webhooks/telegram` to a non-mutating migration response, and kept
Chatogrator as the generic bridge for non-Telegram chat providers.

## Scope and Sources

Local code reviewed:

- OpenCompany app: `app/Services/TelegramService.php`, `app/Http/Controllers/Api/TelegramWebhookController.php`, `app/Http/Controllers/Api/ChatWebhookController.php`, `app/Services/Chat/*`, `app/Listeners/SyncToChat.php`, `config/chat_integrations.php`, `tests/Feature/ChatWebhookControllerTest.php`, `docs/external-channel-sync.md`.
- OpenCompany local Chatogrator package: `tmp/chatogrator` at `a2fe134` / `v1.2.0`.
- KosmoKrator: `/Users/rutger/Projects/kosmokrator` on `dev`, with an unrelated existing dirty file in `website/src/lib/analytics-snippets.mjs`.
- Hermes Agent: `/Users/rutger/Projects/kosmokrator/tmp/hermes-agent`, pulled fast-forward from `ef41d3bd4` to `186bf25cb` before review.
- Telegram official docs: [Bot API changelog](https://core.telegram.org/bots/api-changelog) and [Bot API reference](https://core.telegram.org/bots/api).

## OpenCompany Current Telegram UX

OpenCompany has two Telegram paths.

1. Legacy direct path:
   - Route: `POST /api/webhooks/telegram`.
   - Controller: `TelegramWebhookController`.
   - Service: `TelegramService`.
   - Setup helpers: `telegram:test`, `telegram:set-webhook`, `telegram:sync`, `telegram:send`.

2. Generic Chatogrator path:
   - Route: `POST /api/webhooks/chat/{adapter}`.
   - Telegram URL from `telegram:set-webhook`: `/api/webhooks/chat/telegram`.
   - Workspace resolution: `X-Telegram-Bot-Api-Secret-Token` matched against enabled `IntegrationSetting` rows.
   - Runtime: `ChatManager` registers enabled workspace chat integrations, `ChatBridge` handles inbound message/action/command dispatch, and `SyncToChat` mirrors outbound message events.

Telegram-specific user flow currently present:

- Bot token and webhook secret live in workspace integration settings.
- `/start` returns the Telegram user ID so an admin can allowlist/link the user.
- `/status` reports workspace agent/task/message status and current conversation compaction metadata when a channel exists.
- `/compact` dispatches conversation compaction for the external channel.
- Normal inbound text creates or reuses a workspace `Channel` with `type='external'`, `external_provider='telegram'`, and external chat/thread identity.
- Inbound users resolve through `UserExternalIdentity`; otherwise OpenCompany creates an ephemeral shadow user.
- Allowed users are enforced before message creation. The newer Chatogrator bridge reads `allowed_users` and falls back to legacy `allowed_telegram_users`; the legacy controller only reads `allowed_telegram_users`.
- A default agent is picked from integration config. Chatogrator fallback is workspace-scoped; the legacy controller still has an older global first-agent fallback visible in code comments.
- Inbound messages become normal OpenCompany `Message` rows, are broadcast, and queue `AgentRespondJob`.
- Agent replies in external channels are mirrored out by `SyncToChat` unless the message originated from that provider.
- Approval buttons are supported by Telegram inline keyboards. Both direct and Chatogrator paths acknowledge callback queries, update the `ApprovalRequest`, execute or reject the waiting tool flow, and edit the original Telegram approval message to remove action buttons.
- Outbound sync covers text, workspace-file/image/document upload, edit, delete, pin, and reaction where the adapter supports the action.

OpenCompany Telegram formatting and sending:

- `TelegramService` uses HTML parse mode, converts Markdown-ish output to Telegram-safe HTML, splits long messages at 4096 characters, avoids splitting `<pre>` blocks, sends photos/documents, deletes, pins, sets reactions, sets command menu, sets bot profile photo, and sends typing actions.
- Chatogrator `TelegramAdapter` uses an HTML formatter, supports long-message splitting, photo/document upload, inline cards/buttons, callback actions, message reactions, typing, pins, and basic attachment parsing.
- Chatogrator Telegram does not fetch historical messages or list threads because the Bot API does not expose those operations.

## Chatogrator Provider Differences

Chatogrator exposes one broad adapter contract, but providers diverge materially.

| Area | Telegram | Slack | Discord | Teams | Google Chat | GitHub chat | Linear chat |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Inbound transport | Native HTTP webhook | Signed HTTP events/interactions | Ed25519 interactions or gateway-forwarded events | Bot Framework-style activity payload | Webhook/service-account flow | Webhook comments/events | Webhook comments/events |
| Workspace proof in OpenCompany | Telegram secret header | Slack signature + team ID | application ID + Ed25519 or gateway secret | recipient app ID + secret/app password | Generic webhook secret | Generic webhook secret | Generic webhook secret |
| Normal text send | Implemented | Implemented | Implemented | Implemented | Implemented | Implemented | Implemented |
| Edit/delete | Implemented in adapter | Implemented | Implemented | Implemented | Mostly API-backed, provider-specific | Comment APIs, limited by issue/PR surface | Comment APIs, limited by issue surface |
| Reactions | `setMessageReaction`; bot-set reaction replaces/clears | Add/remove reaction APIs | Add/remove reaction APIs | Not consistently available | Not consistently available | Not native in same chat sense | Not native in same chat sense |
| Typing | `sendChatAction` | API capability differs from Slack UX | typing endpoint | not equivalent | not equivalent | not applicable | not applicable |
| Threads/channels | Chat ID plus optional `message_thread_id`; no history/list thread API | Channels and `thread_ts` are first-class | Channels/threads are first-class | Conversations/activities | Spaces/threads | Repos/issues/PRs as pseudo channels/threads | Issues/comments as pseudo threads |
| Ephemeral/modals | No native ephemeral or modal dialogs | Native ephemeral and modal patterns | Interaction responses/modals differ | Adaptive cards, not Slack-style modal parity | Cards/dialogs differ | Not native | Not native |
| Attachments | Photo/document/video/audio/voice/sticker incoming metadata; outbound photo/document in Chatogrator | Files supported | Files supported | Attachments/cards supported | Cards/attachments supported | Comment attachment support is limited | Comment attachment support is limited |

Current OpenCompany docs already state the core risk accurately: Telegram is the most mature app-specific path; other providers have adapter and webhook plumbing but still need provider-by-provider verification before broad claims.

## KosmoKrator Current Telegram UX

KosmoKrator implements a dedicated Telegram gateway rather than a generic provider adapter.

Key surfaces:

- CLI commands: `gateway:telegram`, `gateway:telegram:worker`, `gateway:telegram:configure`, `gateway:telegram:status`.
- Configuration: `TelegramGatewayConfig` reads enabled flag, token, session mode, allowed users, allowed chats, admin users, mention gating, free-response chats, polling timeout, reply mode, link preview behavior, fresh-final threshold, progress notices, and reactions.
- Transport: long polling through `getUpdates`; it deletes any existing webhook on startup.
- Routing: `TelegramSessionRouter` maps Telegram chat/thread/user identity to a Kosmo session route key. Supported session modes are `chat`, `chat_user`, `thread`, and `thread_user`.
- Concurrency: one active worker process per route. New messages while a route is busy are queued for the next turn.
- Commands: `/help`, `/status`, `/new`, `/resume`, `/approve`, `/deny`, `/cancel`, plus a supported subset of Kosmo slash commands through `TelegramSlashCommandBridge`.
- Inline controls: help/status messages include a keyboard for edit/plan/ask modes, guardian/argus/prometheus, compact/status/cancel, new/resume. Tool approvals use inline buttons for approve, always, guardian, prometheus, deny.
- Security: allowlist checks cover users/chats; approval callbacks can be resolved by admins, allowlisted users, the requester, or private-chat fallback depending on context.
- Group UX: mention gating is supported. In groups, messages require mention/reply unless the chat is configured for free response or mention gating is off.
- Output UX: `TelegramGatewayRenderer` streams by sending/editing Telegram messages, keeps a status message, sends typing actions, reports progress notices, sends a fresh final after long runs, and reacts to the input message with eyes/thumbs when enabled.
- Media out: `MEDIA:` tags are stripped from visible text and delivered as photos, voice/audio, or documents.

KosmoKrator gaps compared with Hermes:

- No webhook mode in current reviewed code; polling only.
- No native `sendMessageDraft` streaming.
- No private-chat topic management beyond preserving incoming `message_thread_id` in route keys.
- No Telegram group observed-context mode; unmentioned group messages are ignored rather than recorded as context.
- No incoming media download/caching path comparable to Hermes. The normalizer primarily accepts text/callback updates; OpenCompany Chatogrator has richer attachment metadata parsing than KosmoKrator, but Hermes goes further by downloading/cacheing media for tools.
- No media album/text burst batching.
- Mention detection has a raw substring fallback that is weaker than Hermes' entity-first, bot-command-aware, multi-bot-exclusive routing.
- Polling recovery has generic backoff, not Hermes' explicit 409 conflict recovery, stale connection pool drain, network reconnect probe, and fatal supervisory signaling.

## Hermes Current Telegram UX

Hermes has the richest Telegram implementation among the reviewed projects.

Transport and reliability:

- Supports polling and webhook mode. Webhook mode requires a secret and refuses startup without one.
- Handles polling conflicts caused by a previous `getUpdates` session, with retries and an explicit fatal error if another process still owns the bot token.
- Handles transient network failures with reconnect backoff, HTTP pool draining, and a delayed heartbeat probe to detect wedged polling.
- Supports custom Telegram Bot API `base_url`, local mode, proxy detection, fallback IP transport, and tuned HTTP pool/timeouts.

Session and routing UX:

- Supports DMs, groups, supergroups, forum topics, channels, and private-chat topics.
- Handles Telegram's General topic edge case where send and typing actions require different `message_thread_id` treatment.
- Supports configured DM topics, creation through `createForumTopic`, config persistence for created topic IDs, hot reload of topic config, topic-to-skill binding, and a `/topic` research/spec path for user-managed multi-session DM topics.
- Supports `direct_messages_topic_id` fallback metadata for Telegram direct-message topics where plain `message_thread_id` is unreliable.
- Supports chat/topic-specific prompts and auto-skill selection.

Message handling:

- Text message batching merges Telegram client-side split messages before dispatch.
- Photo burst and media group batching merge albums into a single logical agent event.
- Incoming media is downloaded and cached for downstream tools: photos, image documents, voice/audio, video, supported documents, and stickers. Text files can be injected into the message text up to a cap.
- Stickers are vision-described and cached by `file_unique_id`; animated/video stickers get fallback descriptions.
- Location/venue pins are normalized into a text prompt with coordinates and map link.
- Reply context uses Telegram's native quote where available before falling back to the full replied-to message.

Group behavior:

- Supports `allowed_chats`, `group_allowed_chats`, `allowed_topics`, `ignored_threads`, `free_response_chats`, `require_mention`, `mention_patterns`, `guest_mode`, and exclusive multi-bot mention routing.
- Can observe unmentioned group chatter into shared context without dispatching the agent, then use that context when a later message explicitly triggers the bot.
- Mention parsing prefers Telegram `MessageEntity` data and handles `/command@botname` correctly.

Outbound UX:

- MarkdownV2 formatter handles links, code, headers, tables, spoilers, blockquotes, and escaping. It falls back to plain text on parse failures.
- `sendMessageDraft` support exists for native draft streaming when the SDK/API exposes it; otherwise it falls back to edit-based streaming.
- Long streaming edits split across continuation messages instead of silently truncating or duplicating.
- Supports text, photo URL/upload, image files, media groups up to Telegram's album limit, documents, video, animation, voice/audio, chat actions, status edit-in-place, delete, reactions, and silent/important notification modes.
- Interactive inline flows include update prompts, command approvals, slash confirmations, clarify choices, model picker, and Gmail triage callbacks.

## Telegram Bot API Current Feature Surface

Important current Telegram platform features relevant to chat-agent UX:

- Webhooks support `secret_token`, delivered in `X-Telegram-Bot-Api-Secret-Token`, and `getUpdates` cannot be used while a webhook is set. Official docs also note webhooks only support ports 443, 80, 88, and 8443.
- `allowed_updates` defaults exclude `message_reaction` and `message_reaction_count`; a bot must explicitly request these updates, and reaction updates require the bot to be an administrator in the chat.
- `getMe` exposes bot capabilities including `can_read_all_group_messages`, `supports_guest_queries`, `supports_inline_queries`, `can_connect_to_business`, `has_main_web_app`, `has_topics_enabled`, and `allows_users_to_create_topics` depending on feature state/version.
- Bot API 9.3 added private-chat topics, `sendMessageDraft`, private-chat `message_thread_id` / `is_topic_message`, and topic-aware sends/chat actions/forward/copy for many methods.
- Bot API 9.4 allowed bots to create topics in private chats, added user control over private-chat topic creation, added `setMyProfilePhoto` / `removeMyProfilePhoto`, and added styled/custom-emoji button fields.
- Bot API 9.5 allowed all bots to use `sendMessageDraft` and added tag-related group member features.
- Bot API 10.0 added guest mode, `guest_message`, `answerGuestQuery`, managed bot access/token methods, bot-to-bot communication from business bots, empty draft text, live photos, prepared keyboard buttons, and richer poll behavior.
- Since 2025, Telegram has expanded direct messages in channels, suggested posts, checklists, gifts/Stars, business account management, main mini apps, message effects, paid media/post fields, and broader media/live-photo surfaces. These are not all chat-agent essentials, but they affect what a full Telegram provider can represent.

## Cross-Project Gaps Observed

OpenCompany vs KosmoKrator:

- OpenCompany is workspace/channel-native and provider-agnostic; KosmoKrator is session/route-native and Telegram-specific.
- OpenCompany handles webhook tenancy well through provider proof before workspace binding; KosmoKrator avoids tenancy because it is local/personal.
- KosmoKrator has a stronger Telegram live-run UX: route queues, active worker status, progress editing, fresh final resend, mode/control keyboard, and inline control callbacks.
- OpenCompany has stronger multi-provider architecture and normal chat model integration, but Telegram-specific run UX is lighter.

OpenCompany vs Hermes:

- OpenCompany lacks Hermes' current Telegram transport hardening: polling/webhook dual mode in one adapter, conflict recovery, network reconnection probes, proxy/fallback-IP/local Bot API support.
- OpenCompany lacks Hermes' private-topic/session-lane sophistication: DM topics, direct-message topic fallback, topic-to-skill binding, hot-loaded topic config, and topic-aware edge-case handling.
- OpenCompany lacks Hermes' inbound media pipeline depth: downloading/cacheing media for agent tools, sticker vision, location normalization, media album batching, and text split batching.
- OpenCompany's group handling is simpler: no observed unmentioned group context, no topic allowlist/ignored-thread layer, no guest-mode routing, and less robust mention entity handling.
- OpenCompany uses edit/send behavior but not native `sendMessageDraft` draft streaming.
- OpenCompany and Chatogrator support basic reactions, but Telegram reaction updates require explicit `allowed_updates` and admin state; this is easy to miss in provider setup.

Chatogrator vs Hermes:

- Chatogrator is an intentionally broad adapter abstraction. It covers common send/edit/delete/react/pin/file/typing primitives across providers, but its Telegram adapter is a pragmatic Bot API wrapper.
- Hermes is a product-specific Telegram client with many Telegram-only affordances and edge-case fixes that do not map cleanly to Slack/Discord/Teams/GitHub/Linear.
- Some Chatogrator contract methods are necessarily fake or not implemented for Telegram, GitHub, Linear, and Google Chat because the provider does not expose equivalent history/thread/modal/ephemeral primitives.

Provider abstraction gaps:

- "Thread" does not mean the same thing across providers. Telegram has chat ID plus optional topic ID and no history/list API; Slack has `thread_ts`; Discord has channel/thread IDs and a gateway; GitHub/Linear threads are issue/PR/comment resources.
- "Reaction" differs: Telegram replaces/clears the bot's reaction set; Slack/Discord add/remove named reactions; other providers may not have native reactions.
- "Ephemeral" and "modal" are Slack/Discord-style interaction concepts; Telegram substitutes inline keyboards and callback queries but has no true ephemeral messages or modal dialogs.
- "Typing" is not portable. Telegram has `sendChatAction`; Discord has typing endpoints; Slack/Teams/Google Chat semantics differ or are limited.
- "Files" are provider-specific. Telegram has photo/document/media-group distinctions and captions; Slack/Discord file APIs behave differently; GitHub/Linear comment surfaces are not equivalent file stores.
- "History fetch" is not portable. Telegram Bot API cannot fetch arbitrary prior messages or list threads, so OpenCompany must rely on messages it has seen/stored.
- "Webhook verification" differs by provider and cannot be reduced to one shared secret model without losing security: Telegram secret header, Slack HMAC, Discord Ed25519/gateway secret, Teams auth/secret, and issue-tracker webhook signatures have different trust anchors.

## Evidence Notes

- OpenCompany originally registered both legacy `/api/webhooks/telegram` and generic `/api/webhooks/chat/{adapter}` routes. Current implementation keeps `/api/webhooks/telegram` as a non-mutating migration response, while `telegram:set-webhook` and `SetupIntegrationWebhook` use `/api/webhooks/chat/telegram`.
- `ToolRegistry::INTEGRATION_APPS` still contains only `telegram`, even though `config/chat_integrations.php` lists Slack, Discord, Teams, Google Chat, GitHub chat, and Linear chat.
- `docs/external-channel-sync.md` already describes Telegram as the most complete provider and warns that other providers require verification.
- Chatogrator `TelegramAdapter` parses media metadata but does not download files for downstream tools during inbound handling.
- Chatogrator `TelegramAdapter::handleWebhook()` includes `message_reaction`, but OpenCompany's `TelegramService::setWebhook()` only allows `message` and `callback_query`; reaction update delivery would need an allowed-updates check in any live setup that expects inbound reaction signals.
- Hermes' docs and code are internally aligned on Telegram privacy mode: if group context is desired, the bot must receive ordinary group messages through BotFather privacy-off or admin status, then local mention gates decide whether to dispatch.
