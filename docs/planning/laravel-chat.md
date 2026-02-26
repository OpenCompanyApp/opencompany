# opencompany/laravel-chat

Laravel Chat Aggregator Package — port of the [Vercel Chat SDK](https://github.com/vercel/chat/) to PHP/Laravel.

A standalone, open-source package that lets any Laravel developer build multi-platform chat bots with a single unified API. Write bot logic once, deploy to Slack, Discord, Microsoft Teams, Google Chat, GitHub, and Linear.

- Package: `opencompany/laravel-chat`
- Namespace: `OpenCompany\LaravelChat`
- Location: `tmp/laravel-chat/`

---

## Architecture Overview

```text
opencompany/laravel-chat
├── src/
│   ├── Chat.php                          # Orchestrator (handler registration, webhook routing)
│   ├── ChatServiceProvider.php           # Laravel auto-discovery
│   ├── Facades/Chat.php                  # Optional facade
│   │
│   ├── Contracts/                        # Interfaces
│   │   ├── Adapter.php                   # Platform adapter contract
│   │   ├── StateAdapter.php              # Persistence (cache, locks, subscriptions)
│   │   └── FormatConverter.php           # Platform ↔ markdown conversion
│   │
│   ├── Messages/                         # Domain models (immutable)
│   │   ├── Message.php                   # Normalized incoming message
│   │   ├── SentMessage.php               # Message with edit/delete/react methods
│   │   ├── Author.php                    # User identity (userId, userName, fullName, isBot, isMe)
│   │   ├── Attachment.php                # File/image attachment with fetchData callback
│   │   ├── FileUpload.php               # Outbound file (buffer, filename, mimeType)
│   │   └── PostableMessage.php           # Outbound: text, formatted, card, stream, files
│   │
│   ├── Threads/                          # Conversation context
│   │   ├── Thread.php                    # Thread with state, messages, post/reply
│   │   └── Channel.php                   # Channel (thread container) with own state
│   │
│   ├── Markdown/                         # Markdown AST utilities (mdast)
│   │   ├── Markdown.php                  # parseMarkdown, stringifyMarkdown, toPlainText
│   │   ├── AstBuilder.php               # text(), strong(), emphasis(), paragraph(), root(), etc.
│   │   ├── AstWalker.php                # walkAst() traversal
│   │   └── TypeGuards.php               # isTextNode(), isParagraphNode(), isStrongNode(), etc.
│   │
│   ├── Cards/                            # Rich message builder (replaces JSX)
│   │   ├── Card.php                      # Card::make('Title')->section(...)->actions(...)
│   │   ├── Elements/
│   │   │   ├── Text.php                  # Styles: plain, bold, muted
│   │   │   ├── Image.php                 # url, alt text
│   │   │   ├── Divider.php
│   │   │   ├── Section.php
│   │   │   ├── Fields.php                # Label/value pairs
│   │   │   └── Actions.php
│   │   ├── Interactive/
│   │   │   ├── Button.php                # Styles: primary, danger, default
│   │   │   ├── LinkButton.php            # External URL button
│   │   │   ├── Select.php               # Dropdown with SelectOption
│   │   │   ├── RadioSelect.php
│   │   │   └── TextInput.php            # For modals (multiline, maxLength, optional)
│   │   └── Modal.php                     # Modal form builder with validation responses
│   │
│   ├── Events/                           # Handler event objects
│   │   ├── ActionEvent.php               # Button/select click + openModal()
│   │   ├── ReactionEvent.php             # Emoji reaction add/remove
│   │   ├── SlashCommandEvent.php         # /command invocation + respond()
│   │   ├── ModalSubmitEvent.php          # Form values + relatedThread/Message/Channel
│   │   ├── ModalCloseEvent.php           # User closed without submitting
│   │   └── ModalResponse.php             # Return: close, errors, update, push
│   │
│   ├── State/                            # State adapter implementations
│   │   ├── CacheStateAdapter.php         # Laravel Cache (any driver)
│   │   ├── RedisStateAdapter.php         # Direct Redis (distributed locks)
│   │   └── ArrayStateAdapter.php         # In-memory (testing)
│   │
│   ├── Adapters/                         # Platform implementations
│   │   ├── Concerns/                     # Shared traits
│   │   │   ├── VerifiesWebhooks.php      # HMAC-SHA256 / Ed25519 verification
│   │   │   ├── HandlesRateLimits.php     # Retry with backoff on 429
│   │   │   └── ConvertsMarkdown.php      # Common markdown utilities
│   │   ├── BaseFormatConverter.php       # Abstract: renderPostable, cardToFallbackText
│   │   ├── Slack/
│   │   │   ├── SlackAdapter.php
│   │   │   ├── SlackFormatConverter.php  # Slack mrkdwn ↔ markdown
│   │   │   └── SlackCardRenderer.php     # Card → Block Kit
│   │   ├── Discord/
│   │   │   ├── DiscordAdapter.php
│   │   │   ├── DiscordFormatConverter.php # <@id>, <#id>, <t:unix> conversions
│   │   │   ├── DiscordCardRenderer.php   # Card → Embeds + Components
│   │   │   └── Gateway/                  # WebSocket forwarder (optional)
│   │   │       ├── DiscordGatewayCommand.php
│   │   │       ├── GatewayConnection.php
│   │   │       └── GatewayEventForwarder.php
│   │   ├── Teams/
│   │   │   ├── TeamsAdapter.php
│   │   │   ├── TeamsFormatConverter.php
│   │   │   └── TeamsCardRenderer.php     # Card → Adaptive Cards
│   │   ├── GoogleChat/
│   │   │   ├── GoogleChatAdapter.php
│   │   │   ├── GoogleChatFormatConverter.php
│   │   │   └── GoogleChatCardRenderer.php
│   │   ├── GitHub/
│   │   │   ├── GitHubAdapter.php
│   │   │   ├── GitHubFormatConverter.php
│   │   │   └── GitHubCardRenderer.php    # Card → Markdown table fallback
│   │   └── Linear/
│   │       ├── LinearAdapter.php
│   │       ├── LinearFormatConverter.php
│   │       └── LinearCardRenderer.php    # Card → Markdown fallback
│   │
│   ├── Emoji/
│   │   ├── Emoji.php                     # ~105 normalized constants + per-platform mapping
│   │   └── EmojiResolver.php             # Resolves {{emoji:name}} placeholders in text
│   │
│   ├── Errors/                           # Error hierarchy
│   │   ├── ChatError.php                 # Base error (core package)
│   │   ├── RateLimitError.php            # retryAfter field
│   │   ├── LockError.php                 # Thread lock failures
│   │   ├── NotImplementedError.php       # Unsupported adapter capability
│   │   ├── AdapterError.php              # Base adapter error (shared)
│   │   ├── AuthenticationError.php       # Invalid tokens/credentials
│   │   ├── PermissionError.php           # Missing permissions
│   │   ├── ResourceNotFoundError.php     # Channel/thread/message not found
│   │   ├── ValidationError.php           # Invalid input
│   │   └── NetworkError.php              # Connection/timeout failures
│   │
│   └── Http/
│       └── ChatWebhookController.php     # Universal webhook router
│
├── config/
│   └── laravel-chat.php
├── routes/
│   └── webhooks.php
├── composer.json
├── README.md
└── tests/
```

---

## Core Abstractions

### 1. Chat (Orchestrator)

```php
use OpenCompany\LaravelChat\Chat;
use OpenCompany\LaravelChat\Adapters\Slack\SlackAdapter;
use OpenCompany\LaravelChat\Adapters\Discord\DiscordAdapter;

$chat = Chat::make('my-bot')
    ->adapter('slack', SlackAdapter::fromConfig([
        'bot_token' => config('services.slack.bot_token'),
        'signing_secret' => config('services.slack.signing_secret'),
    ]))
    ->adapter('discord', DiscordAdapter::fromConfig([
        'bot_token' => config('services.discord.bot_token'),
        'public_key' => config('services.discord.public_key'),
        'application_id' => config('services.discord.application_id'),
    ]))
    ->state(new RedisStateAdapter())
    ->logger('debug')  // PSR-3 logger or shorthand string

    // New @mentions in threads the bot is NOT yet subscribed to
    ->onNewMention(function (Thread $thread, Message $message) {
        $thread->subscribe();
        $thread->post("Hello! I'm watching this thread now.");
    })

    // Messages in threads the bot IS subscribed to (excl. bot's own)
    ->onSubscribedMessage(function (Thread $thread, Message $message) {
        $thread->post("You said: {$message->text}");
    })

    // Pattern-matched messages (regex, any thread)
    ->onNewMessage('/deploy/', function (Thread $thread, Message $message) {
        $thread->post('Starting deployment...');
    })

    // Button/select clicks — filtered or catch-all (no ID)
    ->onAction('approve', function (ActionEvent $event) {
        $event->thread->post('Approved!');
    })
    ->onAction(function (ActionEvent $event) {
        // Catch-all for unhandled actions
    })

    // Slash commands — filtered or catch-all
    ->onSlashCommand('/help', function (SlashCommandEvent $event) {
        $event->respond('Available commands: /help, /status');
    })

    // Reactions — emoji filter or catch-all
    ->onReaction([Emoji::thumbsUp], function (ReactionEvent $event) {
        $event->thread->post('Thanks!');
    })

    // Modal submit — can return validation errors, update, push, or close
    ->onModalSubmit('feedback_form', function (ModalSubmitEvent $event) {
        if (empty($event->values['message'])) {
            return ModalResponse::errors(['message' => 'Required']);
        }
        $event->relatedThread?->post('Thanks for the feedback!');
        return ModalResponse::close();
    })

    // Modal close — user dismissed without submitting (notifyOnClose required)
    ->onModalClose('feedback_form', function (ModalCloseEvent $event) {
        // Optional cleanup
    });
```

**All handler types support both filtered (with ID/pattern) and catch-all (no filter) overloads.** All matching handlers execute sequentially — not first-wins.

**Handler types:**

| Method | Filter | Trigger |
| --- | --- | --- |
| `onNewMention(fn)` | — | @mention in unsubscribed thread |
| `onSubscribedMessage(fn)` | — | Any message in subscribed thread |
| `onNewMessage(regex, fn)` | Regex pattern | Message matching pattern |
| `onAction(id?, fn)` | Action ID or catch-all | Button/select click |
| `onReaction(emojis?, fn)` | Emoji list or catch-all | Reaction add/remove |
| `onSlashCommand(cmd?, fn)` | Command name or catch-all | Slash command |
| `onModalSubmit(callbackId?, fn)` | Callback ID or catch-all | Modal form submission |
| `onModalClose(callbackId?, fn)` | Callback ID or catch-all | Modal dismissed |

**Internal methods:**

- `handleWebhook(string $adapter, Request $request): Response` — Route webhook to adapter
- `processMessage(...)` — Dedup, lock, route to message handlers
- `processAction(...)`, `processReaction(...)`, `processSlashCommand(...)`
- `processModalSubmit(...)`, `processModalClose(...)`
- `openDM(string|Author $user): Thread` — Open DM, auto-infers adapter from user context

**Handler routing logic:**

1. Webhook arrives at `handleWebhook()`
2. Adapter parses payload and verifies signature
3. Dedup via state adapter (key: `dedupe:{adapter}:{messageId}`, 60s TTL)
4. Acquire distributed lock on thread (prevents concurrent processing)
5. Route to handlers:
   - Subscribed thread + not bot author → `onSubscribedMessage`
   - @mention in unsubscribed thread → `onNewMention`
   - Regex match → `onNewMessage`
6. All matching handlers execute sequentially
7. Lock extended during long processing; released after completion
8. Handler execution dispatched to Laravel queue for fast webhook response (equivalent of SDK's `waitUntil` pattern — adapter returns 200 immediately, processing continues in background)

### 2. Adapter Interface

```php
namespace OpenCompany\LaravelChat\Contracts;

interface Adapter
{
    // Identity (readonly properties)
    public string $name { get; }       // 'slack', 'discord', etc.
    public string $userName { get; }   // Bot display name
    public ?string $botUserId { get; } // For mention detection

    // Lifecycle (called by Chat during setup)
    public function initialize(Chat $chat): void;

    // Webhook handling
    public function handleWebhook(Request $request, Chat $chat): Response;

    // Message parsing (raw platform payload → normalized Message)
    public function parseMessage(mixed $raw): Message;

    // Message operations
    public function postMessage(string $threadId, PostableMessage $message): SentMessage;
    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage;
    public function deleteMessage(string $threadId, string $messageId): void;

    // Message fetching (supports forward/backward pagination)
    public function fetchMessages(string $threadId, FetchOptions $options): FetchResult;
    public function fetchMessage(string $threadId, string $messageId): ?Message;

    // Thread/channel metadata
    public function fetchThread(string $threadId): ThreadInfo;
    public function encodeThreadId(array $data): string;
    public function decodeThreadId(string $threadId): array;

    // Reactions
    public function addReaction(string $threadId, string $messageId, string $emoji): void;
    public function removeReaction(string $threadId, string $messageId, string $emoji): void;

    // Typing indicator
    public function startTyping(string $threadId): void;

    // Formatted content rendering (markdown → platform-specific format)
    public function renderFormatted(string $markdown): string;

    // Optional capabilities (return null / throw NotImplementedError if unsupported)
    public function openDM(string $userId): ?string;
    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage;
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array;
    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage;

    // Optional channel operations
    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage;
    public function fetchChannelMessages(string $channelId, FetchOptions $options): ?FetchResult;
    public function fetchChannelInfo(string $channelId): ?ChannelInfo;
    public function listThreads(string $channelId, ListThreadsOptions $options): ?ListThreadsResult;
    public function channelIdFromThreadId(string $threadId): ?string;
    public function isDM(string $threadId): bool;

    // Optional subscription hook (called when thread.subscribe() is called)
    public function onThreadSubscribe(string $threadId): void;
}
```

### 3. StateAdapter Interface

```php
namespace OpenCompany\LaravelChat\Contracts;

interface StateAdapter
{
    // Lifecycle
    public function connect(): void;
    public function disconnect(): void;

    // Key-value cache with optional TTL
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void;
    public function delete(string $key): void;

    // Distributed locking
    public function acquireLock(string $threadId, int $ttlSeconds = 30): ?Lock;
    public function extendLock(Lock $lock, int $ttlSeconds): bool;
    public function releaseLock(Lock $lock): void;

    // Thread subscriptions (persisted across restarts)
    public function subscribe(string $threadId): void;
    public function unsubscribe(string $threadId): void;
    public function isSubscribed(string $threadId): bool;
}
```

**Key prefixes used by Chat:**

- `dedupe:{adapter}:{messageId}` — 60s TTL, prevents duplicate processing
- `thread-state:{threadId}` — 30d TTL, persistent thread state
- `modal-context:{adapter}:{contextId}` — 24h TTL, stores thread/message/channel context for modal submit/close handlers

**Implementations:**

- `CacheStateAdapter` — wraps Laravel `Cache` facade (any driver). Uses `Cache::lock()` for distributed locks.
- `RedisStateAdapter` — direct Redis. Configurable key prefix for multi-app deployments.
- `ArrayStateAdapter` — in-memory for testing.

### 4. Thread

Full conversation context with persistent state, message access, and posting.

```php
// Posting — accepts string, Card, FileUpload, or iterable (streaming)
$thread->post('Hello!');
$thread->post(Card::make('Order #123')->section(Text::make('Total: $50')));
$thread->post($aiStream);  // Generator for streaming
$thread->post(PostableMessage::make('text')->files([
    FileUpload::make($buffer, 'report.pdf', 'application/pdf'),
]));

// Ephemeral messages (only visible to specific user)
// Falls back to DM if platform doesn't support ephemeral natively
$thread->postEphemeral($userId, 'Only you can see this', fallbackToDM: true);

// Subscription (controls which handlers fire)
$thread->subscribe();       // Future messages → onSubscribedMessage
$thread->unsubscribe();
$thread->isSubscribed();    // bool

// Persistent state (30d TTL, merged by default)
$state = $thread->state();                   // Get current state
$thread->setState(['mode' => 'ai']);          // Merge into state
$thread->setState(['mode' => 'ai'], replace: true);  // Replace entirely

// Message access
foreach ($thread->messages() as $msg) { ... }     // Newest first (backward)
foreach ($thread->allMessages() as $msg) { ... }  // Oldest first (forward)
$recent = $thread->recentMessages();               // Cached from last fetch
$thread->refresh();                                 // Re-fetch from platform

// Typing indicator
$thread->startTyping();

// User mentions (platform-specific syntax)
$mention = $thread->mentionUser($userId);  // Returns "<@U123>" for Slack, etc.

// Metadata
$thread->id;         // Full thread ID: "slack:C123:ts123"
$thread->channelId;  // Channel portion
$thread->isDM;       // bool
$thread->adapter;    // Adapter instance

// Channel access
$channel = $thread->channel;  // Parent Channel object

// Serialization (for queued jobs, workflow engines)
$json = $thread->toJSON();
$thread = Thread::fromJSON($json, $chat);
```

### 5. Channel

First-class entity — container for threads with its own state, message access, and posting.

```php
$channel = $chat->channel('slack', 'C123ABC');

// Channel-level posting (top-level message, not in a thread)
$channel->post('Announcement!');
$channel->postEphemeral($userId, 'Only you see this');

// Channel state (separate from thread state)
$state = $channel->state();
$channel->setState(['notifications' => true]);

// List threads in channel
foreach ($channel->threads() as $threadSummary) {
    echo $threadSummary->id;
    echo $threadSummary->lastActivity;
}

// Channel-level messages (top-level only, not thread replies)
foreach ($channel->messages() as $msg) { ... }

// Metadata
$info = $channel->fetchMetadata();  // ChannelInfo: name, topic, memberCount, etc.
$channel->isDM;

// Typing
$channel->startTyping();

// Serialization
$json = $channel->toJSON();
$channel = Channel::fromJSON($json, $chat);
```

### 6. Message (Immutable)

The canonical message format uses a Markdown AST (mdast) for rich content, with plain text as a convenience accessor.

```php
$message->id;           // Platform message ID
$message->threadId;     // Full thread ID
$message->text;         // Plain text (all formatting stripped)
$message->formatted;    // Markdown AST (mdast Root node) — canonical rich content
$message->raw;          // Platform-specific raw payload (escape hatch)
$message->author;       // Author { userId, userName, fullName, isBot, isMe }
$message->metadata;     // { dateSent, edited, editedAt }
$message->attachments;  // Attachment[] with fetchData() callback for authenticated download
$message->isMention;    // Whether this message @-mentions the bot

// Author properties:
$message->author->userId;    // Platform user ID
$message->author->userName;  // Username/handle
$message->author->fullName;  // Display name
$message->author->isBot;     // bool|'unknown' — some platforms can't determine this
$message->author->isMe;      // bool — is this the bot's own message?

// SentMessage extends Message with mutation methods:
$sent = $thread->post('Hello');
$sent->edit('Hello, updated!');
$sent->delete();
$sent->addReaction(Emoji::thumbsUp);
$sent->removeReaction(Emoji::thumbsUp);

// Serialization for queued jobs
$json = $message->toJSON();
$message = Message::fromJSON($json);
```

### 7. Markdown AST (mdast)

The SDK uses mdast (Markdown Abstract Syntax Tree) as the canonical format for message content, not plain markdown strings. This preserves structure across platforms.

```php
use OpenCompany\LaravelChat\Markdown\{Markdown, AstBuilder, AstWalker, TypeGuards};

// Parse markdown string to AST
$ast = Markdown::parse('**Hello** world');

// Stringify AST back to markdown
$md = Markdown::stringify($ast);

// Convert AST to plain text (strip formatting)
$text = Markdown::toPlainText($ast);

// Build AST programmatically
$ast = AstBuilder::root([
    AstBuilder::paragraph([
        AstBuilder::strong([AstBuilder::text('Hello')]),
        AstBuilder::text(' world'),
    ]),
]);

// Type guards for AST nodes
TypeGuards::isTextNode($node);       // bool
TypeGuards::isParagraphNode($node);  // bool
TypeGuards::isStrongNode($node);     // bool
TypeGuards::isEmphasisNode($node);   // bool
TypeGuards::isLinkNode($node);       // bool
TypeGuards::isCodeNode($node);       // bool
TypeGuards::isBlockquoteNode($node); // bool
TypeGuards::isListNode($node);       // bool

// Walk/traverse AST
AstWalker::walk($ast, function ($node) {
    if (TypeGuards::isLinkNode($node)) {
        // Process all links
    }
});
```

### 8. PostableMessage

All postable message variants support optional file uploads:

```php
// Simple string
$thread->post('Hello!');

// Markdown (parsed to AST internally)
$thread->post(PostableMessage::markdown('**Bold** text'));

// AST content directly
$thread->post(PostableMessage::formatted($ast));

// Card
$thread->post($card);

// With files attached
$thread->post(PostableMessage::make('See attached')
    ->files([
        FileUpload::make($buffer, 'chart.png', 'image/png'),
    ])
);

// Streaming (generator)
$thread->post($generator);

// Raw platform-specific content (escape hatch)
$thread->post(PostableMessage::raw($slackBlockKitJson));
```

### 9. Card Builder (replaces JSX)

```php
use OpenCompany\LaravelChat\Cards\Card;
use OpenCompany\LaravelChat\Cards\Elements\{Text, Image, Divider, Section, Fields};
use OpenCompany\LaravelChat\Cards\Interactive\{Button, LinkButton, Select, SelectOption};

$card = Card::make('Welcome!')
    ->subtitle('You have a new notification')
    ->imageUrl('https://example.com/banner.png')
    ->section(
        Text::bold('Order #1234'),
        Text::muted('Placed 5 minutes ago'),
    )
    ->divider()
    ->fields([
        'Status' => 'Pending',
        'Total' => '$42.00',
        'Items' => '3',
    ])
    ->actions(
        Button::make('approve', 'Approve')->primary(),
        Button::make('reject', 'Reject')->danger(),
        Button::make('details', 'Details'),  // "default" style (no modifier)
        LinkButton::make('https://example.com', 'View Online'),
        Select::make('assign', 'Assign to')->options([
            SelectOption::make('alice', 'Alice'),
            SelectOption::make('bob', 'Bob'),
        ]),
    );

$thread->post($card);

// Fallback text (for notifications, logging, platforms without card support)
$fallback = $card->toFallbackText();  // "Welcome! — Order #1234 ..."
```

**Button styles:** `primary`, `danger`, `default` (no modifier = default). There is no "secondary" style.

Each adapter renders cards to its native format:

- Slack → Block Kit JSON
- Discord → Embeds + Action Row components
- Teams → Adaptive Cards JSON
- Google Chat → Google Chat Cards v2
- GitHub/Linear → Markdown fallback table

### 10. Modal Builder

```php
use OpenCompany\LaravelChat\Cards\Modal;
use OpenCompany\LaravelChat\Cards\Interactive\{TextInput, Select, SelectOption, RadioSelect};

$modal = Modal::make('feedback_form', 'Send Feedback')
    ->submitLabel('Send')
    ->closeLabel('Cancel')
    ->notifyOnClose()  // Enables onModalClose handler
    ->privateMetadata(['context' => 'value'])  // Stored server-side, restored on submit/close
    ->input(TextInput::make('message', 'Your Feedback')->multiline()->maxLength(500))
    ->input(Select::make('rating', 'Rating')->options([
        SelectOption::make('5', 'Excellent'),
        SelectOption::make('3', 'Average'),
        SelectOption::make('1', 'Poor'),
    ]))
    ->input(RadioSelect::make('priority', 'Priority')->options([
        SelectOption::make('low', 'Low'),
        SelectOption::make('high', 'High'),
    ])->optional());
```

**Modal context persistence:** When a modal is opened from an action handler, the SDK stores the originating `thread`, `message`, and `channel` server-side (via state adapter, key: `modal-context:{adapter}:{contextId}`, 24h TTL). On submit/close, these are restored as `$event->relatedThread`, `$event->relatedMessage`, `$event->relatedChannel`.

**Modal validation responses:**

```php
->onModalSubmit('feedback_form', function (ModalSubmitEvent $event) {
    // Validate
    if (empty($event->values['message'])) {
        return ModalResponse::errors(['message' => 'Message is required']);
    }

    // Or update the modal content
    return ModalResponse::update($updatedModal);

    // Or push a new modal on top
    return ModalResponse::push($nextStepModal);

    // Or close (default if handler returns void)
    return ModalResponse::close();
});
```

**Supported by:** Slack (full), Discord (partial — supports opening modals from interactions), Teams (partial). Other platforms silently skip modal operations.

### 11. Emoji System

```php
use OpenCompany\LaravelChat\Emoji\Emoji;
use OpenCompany\LaravelChat\Emoji\EmojiResolver;

// ~105 normalized constants (object identity for comparison)
Emoji::thumbsUp;
Emoji::thumbsDown;
Emoji::wave;
Emoji::check;
Emoji::x;
Emoji::rocket;
Emoji::heart;
Emoji::eyes;
Emoji::fire;
// ... ~105 well-known emojis total

// In reaction handlers — object identity for comparison
$chat->onReaction([Emoji::thumbsUp, Emoji::heart], function (ReactionEvent $event) {
    if ($event->emoji === Emoji::thumbsUp) { ... }
});

// Platform-specific resolution
Emoji::fromSlack('+1');              // → Emoji::thumbsUp
Emoji::toSlack(Emoji::thumbsUp);    // → '+1'
Emoji::toDiscord(Emoji::thumbsUp);  // → '👍'

// Placeholder system for message text
$text = "Great job! {{emoji:thumbs_up}}";
$resolved = EmojiResolver::resolve($text, 'slack');   // "Great job! :+1:"
$resolved = EmojiResolver::resolve($text, 'discord'); // "Great job! 👍"
```

---

## Types

Key value objects used throughout the package:

```php
// Fetch options for message retrieval (forward/backward pagination)
class FetchOptions {
    public ?string $cursor;      // Pagination cursor
    public ?int $limit;          // Max results
    public string $direction;    // 'forward' | 'backward'
}

class FetchResult {
    public array $messages;      // Message[]
    public ?string $cursor;      // Next page cursor
    public bool $hasMore;
}

// Thread listing
class ThreadSummary {
    public string $id;
    public string $title;
    public ?string $lastActivity;
    public int $messageCount;
}

class ListThreadsOptions {
    public ?string $cursor;
    public ?int $limit;
}

class ListThreadsResult {
    public array $threads;       // ThreadSummary[]
    public ?string $cursor;
    public bool $hasMore;
}

// Channel metadata
class ChannelInfo {
    public string $id;
    public string $name;
    public ?string $topic;
    public ?int $memberCount;
    public bool $isDM;
}

// Thread metadata
class ThreadInfo {
    public string $id;
    public string $channelId;
    public bool $isDM;
    public ?string $title;
}
```

---

## Webhook Routing

The package registers routes automatically via the ServiceProvider:

```text
POST /webhooks/chat/{adapter}  →  ChatWebhookController@handle
```

Config (`config/laravel-chat.php`):

```php
return [
    'route_prefix' => 'webhooks/chat',
    'middleware' => [],  // No auth — webhooks are verified by each adapter
];
```

---

## Streaming Support

PHP generators as the equivalent of AsyncIterable:

```php
$stream = function () use ($prompt) {
    foreach ($aiClient->stream($prompt) as $chunk) {
        yield $chunk;
    }
};

$thread->post($stream());
```

**`Thread::post()` detects iterable and:**

1. If adapter supports native streaming (Slack) → delegates to `$adapter->stream()`
2. Otherwise → post initial "..." message, then edit with accumulated content every 500ms via queued job
3. Final edit after stream ends
4. Only edits when content actually changed (avoids API chatter)

Configurable via `streamingUpdateIntervalMs` option (default 500ms).

---

## Discord Gateway

The only adapter that needs a persistent process. Ships as an artisan command:

```bash
php artisan chat:discord-gateway
```

- Connects to Discord Gateway WebSocket via `ratchet/pawl` (ReactPHP)
- Forwards MESSAGE_CREATE, MESSAGE_UPDATE, MESSAGE_DELETE, MESSAGE_REACTION_ADD, MESSAGE_REACTION_REMOVE as HTTP POSTs to the webhook route
- Auto-generates a `gateway_secret` for authenticating forwarded events
- Full Gateway v10 protocol: HELLO, IDENTIFY, HEARTBEAT (with jitter), RESUME, RECONNECT, INVALID_SESSION
- Exponential backoff reconnection
- Graceful shutdown on SIGTERM/SIGINT
- Optional `--max-memory=128` for auto-restart (like `queue:work`)

Users who don't use Discord never run this command. The package works fine without it.

### Why Discord needs this

Discord does **not** provide HTTP webhooks for regular messages. Their Interactions Endpoint only covers slash commands, button clicks, and modals. Receiving MESSAGE_CREATE requires a persistent WebSocket to the Gateway.

The gateway command is a lightweight bridge: receives events from Discord's WebSocket and forwards them as HTTP POSTs. All business logic stays in Laravel.

### Running in production

```ini
[program:discord-gateway]
command=php /path/to/artisan chat:discord-gateway
autostart=true
autorestart=true
startretries=10
stopwaitsecs=30
stopsignal=SIGTERM
```

---

## Thread ID Format

| Adapter | Format | Example |
| --- | --- | --- |
| Slack | `slack:{channel}:{threadTs}` | `slack:C123:1234567.890` |
| Discord | `discord:{guild}:{channel}:{thread?}` | `discord:123:456:789` |
| Teams | `teams:{b64(conversationId)}:{b64(serviceUrl)}` | `teams:abc123:def456` |
| Google Chat | `gchat:{space}:{b64(thread)}` | `gchat:spaces/ABC:xyz` |
| GitHub | `github/{owner}/{repo}:{pr}` | `github/acme/app:42` |
| Linear | `linear:{issueId}` | `linear:ABC-123` |

---

## Platform Capabilities Matrix

| Feature | Slack | Discord | Teams | Google Chat | GitHub | Linear |
| --- | --- | --- | --- | --- | --- | --- |
| Messages | Yes | Yes | Yes | Yes | PR comments | Issue comments |
| Reactions | Yes | Yes (add + remove) | No (throws NotImplementedError) | Yes | Limited | Emoji |
| Threading | Native | Thread channels | Reply chain | Native | N/A | Comment threads |
| Cards | Block Kit | Embeds + Components | Adaptive Cards | Google Cards | Markdown | Markdown |
| Modals | Full | Partial (via interactions) | Partial | No | No | No |
| Ephemeral | Native | No (DM fallback) | No (DM fallback) | Native | No | No |
| Streaming | Native | Fallback | Fallback | Fallback | No | No |
| File uploads | Yes | Yes | Yes | Images only | N/A | Attachments |
| Slash commands | Yes | Yes | No | No | No | No |
| DMs | Yes | Yes | Yes | Yes (delegation) | No | No |
| Typing indicator | Yes | Yes | Yes | No | No | No |
| Message history | Yes | Yes | Yes | Yes | Yes | Yes |
| Webhook delivery | Yes | Interactions only* | Yes | Yes | Yes | Yes |

\* Discord requires Gateway for MESSAGE_CREATE. `chat:discord-gateway` handles this.

---

## Adapter Authentication

| Adapter | Webhook Verification | Auth for API Calls |
| --- | --- | --- |
| Slack | HMAC-SHA256 (`hash_hmac`) | `Bearer {bot_token}` |
| Discord | Ed25519 (`sodium_crypto_sign_verify_detached`) | `Bot {token}` |
| Teams | Bot Framework JWT validation | App ID + Password |
| Google Chat | Bearer token / Pub/Sub push | Service Account JWT |
| GitHub | HMAC-SHA256 | `Bearer {token}` or GitHub App |
| Linear | HMAC-SHA256 | `Bearer {api_key}` |

All verification uses PHP built-ins (`hash_hmac`, `hash_equals`, `sodium_*`). No external packages needed.

---

## Error Hierarchy

Two error categories:

**Core errors** (thrown by Chat orchestrator):
- `ChatError` — base error
- `RateLimitError` — rate limit hit, includes `retryAfter` seconds
- `LockError` — thread lock acquisition failed
- `NotImplementedError` — adapter doesn't support the requested capability

**Adapter errors** (thrown by individual adapters, from shared base):
- `AdapterError` — base adapter error with error code and metadata
- `AuthenticationError` — invalid tokens/credentials
- `PermissionError` — missing bot permissions
- `ResourceNotFoundError` — channel/thread/message not found
- `ValidationError` — invalid input to adapter
- `NetworkError` — connection timeouts, DNS failures

---

## Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0 || ^12.0",
        "illuminate/http": "^11.0 || ^12.0",
        "illuminate/cache": "^11.0 || ^12.0",
        "illuminate/routing": "^11.0 || ^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0 || ^10.0"
    },
    "suggest": {
        "ratchet/pawl": "Required for Discord Gateway WebSocket (chat:discord-gateway command)",
        "ext-sodium": "Required for Discord adapter (Ed25519 verification)"
    }
}
```

All adapters use Laravel's `Http` facade. `ratchet/pawl` is suggested, not required.

---

## Implementation Order

### Phase 1 — Core Framework

1. Package scaffolding: composer.json, ServiceProvider, config, routes
2. Contracts: `Adapter`, `StateAdapter`, `FormatConverter`
3. Value objects: `FetchOptions`, `FetchResult`, `ThreadSummary`, `ThreadInfo`, `ChannelInfo`, `ListThreadsOptions`, `ListThreadsResult`
4. Domain models: `Message`, `SentMessage`, `Author` (with `isBot: bool|'unknown'`), `Attachment`, `FileUpload`, `PostableMessage`
5. Markdown AST: `Markdown`, `AstBuilder`, `AstWalker`, `TypeGuards`
6. `Thread` class: state, messages (forward/backward), subscribe, post, stream, serialize
7. `Channel` class: state, messages, threads, post, metadata, serialize
8. `Chat` orchestrator: adapter registration, all handler types, dedup, locking, queue dispatch
9. State adapters: `CacheStateAdapter`, `ArrayStateAdapter`
10. Error hierarchy: core errors + adapter-shared errors
11. `ChatWebhookController`

### Phase 2 — Card & Modal System

1. Card builder: `Card`, `Text`, `Image`, `Divider`, `Section`, `Fields`, `Actions`
2. Interactive: `Button` (primary/danger/default), `LinkButton`, `Select`, `RadioSelect`, `TextInput`
3. `Modal` builder with `notifyOnClose`, `privateMetadata`, validation
4. `ModalResponse`: close, errors, update, push
5. Modal context persistence (store/restore thread/message/channel)
6. `Emoji` constants (~105) + per-platform mapping
7. `EmojiResolver` for `{{emoji:name}}` placeholder resolution
8. `BaseFormatConverter` with `cardToFallbackText()`

### Phase 3 — Slack Adapter (reference implementation)

1. `SlackAdapter` — webhook handling, HMAC verification, message parsing (`parseMessage`)
2. `SlackFormatConverter` — mrkdwn ↔ markdown, mention resolution
3. `SlackCardRenderer` — Card → Block Kit
4. Native streaming support
5. Ephemeral messages, modals (full), reactions, typing, DMs, file uploads, message history

### Phase 4 — Discord Adapter

1. `DiscordAdapter` — interactions endpoint (Ed25519), gateway event handling
2. `DiscordFormatConverter` — `<@id>`, `<#id>`, `<t:unix>`, custom emoji
3. `DiscordCardRenderer` — Card → Embeds + Components
4. Gateway: `GatewayConnection`, `GatewayEventForwarder`, `DiscordGatewayCommand`
5. Rate limit handling with `X-RateLimit-*` headers, ephemeral DM fallback
6. Reactions (add + remove), modals (via interactions)

### Phase 5 — Remaining Adapters

1. Teams — Adaptive Cards, Bot Framework JWT auth, reply chains, reactions throw NotImplementedError
2. Google Chat — Pub/Sub + direct webhooks, Google Cards, domain-wide delegation for DMs
3. GitHub — PR/review comments, HMAC, GitHub App multi-tenant, markdown-only cards
4. Linear — Issue comments, HMAC, OAuth client credentials auto-refresh, markdown-only cards

### Phase 6 — Polish

1. Tests: unit + integration with mocked HTTP responses
2. README with getting started guide and per-adapter setup instructions
3. Packagist registration

---

## Key Design Decisions

1. **Markdown AST (mdast) as canonical format** — Messages use `formatted` (mdast Root node), not plain markdown strings. This preserves structure across platforms and enables programmatic content manipulation via `AstBuilder` and `AstWalker`.
2. **Fluent builder for cards** (not JSX) — PHP has no JSX equivalent. Fluent API gives autocompletion and is idiomatic PHP.
3. **Laravel Cache as default state adapter** — Zero config. Works with Redis, Memcached, file, database.
4. **Single webhook route** with `{adapter}` parameter — Clean URLs, one route per platform.
5. **`ratchet/pawl` as suggest** — Only needed for Discord Gateway. Package works without it.
6. **No database migrations** — State via cache/Redis. Lightweight, no migration conflicts.
7. **Closures for handlers** — Simple, familiar. Handler classes also supported via invokable classes.
8. **Generators for streaming** — PHP's natural equivalent of AsyncIterable.
9. **Queue dispatch for handler execution** — Fast webhook response (200 immediately), processing in background. Equivalent of SDK's `waitUntil` pattern.
10. **Ephemeral DM fallback** — When platform doesn't support native ephemeral messages, opt-in fallback to sending a DM via `fallbackToDM: true`.
11. **Modal context server-side** — Thread/message/channel context stored in state adapter when modal opens, restored on submit/close. Enables `$event->relatedThread` pattern.
12. **`Author.isBot` as `bool|'unknown'`** — Some platforms can't determine bot status; the union type makes this explicit.

---

## Reference

- Vercel Chat SDK: `inspiration/vercel-chat/`
- Core SDK: `inspiration/vercel-chat/packages/chat/src/`
- Adapters: `inspiration/vercel-chat/packages/adapter-*/src/`
- Adapter shared: `inspiration/vercel-chat/packages/adapter-shared/src/`
- State adapters: `inspiration/vercel-chat/packages/state-*/src/`
- Example bot: `inspiration/vercel-chat/examples/nextjs-chat/src/lib/bot.tsx`
