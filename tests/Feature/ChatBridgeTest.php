<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\IntegrationSetting;
use App\Models\Message;
use App\Services\Chat\ChatAdapterFactory;
use App\Services\Chat\ChatBridge;
use App\Services\Integrations\IntegrationConnectionTester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OpenCompany\Chatogrator\Cards\Modal;
use OpenCompany\Chatogrator\Chat;
use OpenCompany\Chatogrator\Contracts\Adapter;
use OpenCompany\Chatogrator\Messages\Author;
use OpenCompany\Chatogrator\Messages\FileUpload;
use OpenCompany\Chatogrator\Messages\Message as ChatMessage;
use OpenCompany\Chatogrator\Messages\PostableMessage;
use OpenCompany\Chatogrator\Messages\SentMessage;
use OpenCompany\Chatogrator\Threads\Thread;
use OpenCompany\Chatogrator\Types\ChannelInfo;
use OpenCompany\Chatogrator\Types\FetchOptions;
use OpenCompany\Chatogrator\Types\FetchResult;
use OpenCompany\Chatogrator\Types\ListThreadsOptions;
use OpenCompany\Chatogrator\Types\ListThreadsResult;
use OpenCompany\Chatogrator\Types\ThreadInfo;
use Tests\TestCase;

/**
 * Protects the boundary between the generic Chatogrator bridge and app-owned
 * chat provider runtimes.
 */
class ChatBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatogrator_bridge_ignores_telegram_inbound_events(): void
    {
        $adapter = new RecordingBridgeAdapter('telegram');
        $thread = new Thread('telegram-thread', $adapter, Chat::make('bridge-test'));
        $message = $this->message('telegram-thread', 'telegram-user-1');

        app(ChatBridge::class)->handleInbound($thread, $message, $this->workspace->id);

        $this->assertDatabaseCount('channels', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertSame([], $adapter->postedMessages);
        $this->assertSame([], $adapter->subscribedThreads);
    }

    public function test_chatogrator_bridge_does_not_apply_telegram_allowlist_to_other_providers(): void
    {
        IntegrationSetting::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'integration_id' => 'slack',
            'enabled' => true,
            'config' => [
                'allowed_telegram_users' => ['some-other-user'],
            ],
        ]);

        $adapter = new RecordingBridgeAdapter('slack', ['channel' => 'C-general']);
        $thread = new Thread('slack-thread', $adapter, Chat::make('bridge-test'));
        $message = $this->message('slack-thread', 'slack-user-1');

        app(ChatBridge::class)->handleInbound($thread, $message, $this->workspace->id);

        $this->assertSame(1, Channel::where('external_provider', 'slack')->count());
        $this->assertSame(1, Message::where('source', 'slack')->count());
        $this->assertSame(['slack-thread'], $adapter->subscribedThreads);
    }

    public function test_telegram_connection_test_uses_app_owned_token_check_not_chatogrator_adapter(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456,
                    'first_name' => 'OpenCompany',
                    'username' => 'OC1212BOT',
                ],
            ]),
        ]);

        $result = app(IntegrationConnectionTester::class)->test(
            Request::create('/', 'POST', ['apiKey' => '123456:test-token']),
            'telegram',
        );

        $this->assertTrue($result->success);
        $this->assertSame('OpenCompany', $result->meta['botName']);
        $this->assertSame('OC1212BOT', $result->meta['username']);
        $this->assertNull(ChatAdapterFactory::create(new IntegrationSetting([
            'integration_id' => 'telegram',
            'config' => ['api_key' => '123456:test-token'],
        ])));
    }

    private function message(string $threadId, string $userId): ChatMessage
    {
        return new ChatMessage(
            id: 'message-1',
            threadId: $threadId,
            text: 'hello from chat',
            formatted: null,
            raw: [],
            author: new Author(
                userId: $userId,
                userName: 'external-user',
                fullName: 'External User',
                isBot: false,
                isMe: false,
            ),
            metadata: [],
            attachments: [],
            isMention: true,
        );
    }
}

/**
 * Minimal provider fake for testing ChatBridge without relying on Chatogrator's
 * own test helpers being available in OpenCompany's Composer autoload.
 */
class RecordingBridgeAdapter implements Adapter
{
    /** @var list<array{threadId: string, message: PostableMessage}> */
    public array $postedMessages = [];

    /** @var list<string> */
    public array $subscribedThreads = [];

    /**
     * @param  array<string, mixed>  $decodedThread
     */
    public function __construct(
        private string $adapterName,
        private array $decodedThread = ['chatId' => 'chat-1'],
    ) {}

    public function name(): string
    {
        return $this->adapterName;
    }

    public function userName(): string
    {
        return "{$this->adapterName}-bot";
    }

    public function botUserId(): ?string
    {
        return "{$this->adapterName}-bot-id";
    }

    public function initialize(Chat $chat): void {}

    public function handleWebhook(Request $request, Chat $chat): Response
    {
        return new Response('ok');
    }

    public function parseMessage(mixed $raw): ChatMessage
    {
        throw new \BadMethodCallException('Not used by this test.');
    }

    public function postMessage(string $threadId, PostableMessage $message): SentMessage
    {
        $this->postedMessages[] = compact('threadId', 'message');

        return new SentMessage(
            id: 'sent-1',
            threadId: $threadId,
            text: $message->getText() ?? '',
            formatted: null,
            raw: [],
            author: new Author($this->botUserId(), $this->userName(), 'Bot', true, true),
            metadata: [],
            attachments: [],
            isMention: false,
        );
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        return $this->postMessage($threadId, $message);
    }

    public function deleteMessage(string $threadId, string $messageId): void {}

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        return new FetchResult;
    }

    public function fetchMessage(string $threadId, string $messageId): ?ChatMessage
    {
        return null;
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        return new ThreadInfo(id: $threadId, channelId: $this->decodedThread['channel'] ?? null);
    }

    public function encodeThreadId(array $data): string
    {
        return (string) ($data['chatId'] ?? $data['channel'] ?? 'encoded-thread');
    }

    public function decodeThreadId(string $threadId): array
    {
        return $this->decodedThread;
    }

    public function addReaction(string $threadId, string $messageId, string $emoji): void {}

    public function removeReaction(string $threadId, string $messageId, string $emoji): void {}

    public function startTyping(string $threadId, ?string $status = null): void {}

    public function renderFormatted(string $markdown): string
    {
        return $markdown;
    }

    public function openDM(string $userId): ?string
    {
        return null;
    }

    public function postEphemeral(string $threadId, string $userId, PostableMessage $message): ?SentMessage
    {
        return null;
    }

    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array
    {
        return null;
    }

    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage
    {
        return null;
    }

    public function postChannelMessage(string $channelId, PostableMessage $message): ?SentMessage
    {
        return null;
    }

    public function fetchChannelMessages(string $channelId, ?FetchOptions $options = null): ?FetchResult
    {
        return null;
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        return null;
    }

    public function listThreads(string $channelId, ?ListThreadsOptions $options = null): ?ListThreadsResult
    {
        return null;
    }

    public function channelIdFromThreadId(string $threadId): ?string
    {
        return $this->decodedThread['channel'] ?? null;
    }

    public function isDM(string $threadId): bool
    {
        return false;
    }

    public function onThreadSubscribe(string $threadId): void
    {
        $this->subscribedThreads[] = $threadId;
    }

    public function sendFile(string $threadId, FileUpload $file): ?SentMessage
    {
        return null;
    }

    public function pinMessage(string $threadId, string $messageId): void {}

    public function unpinMessage(string $threadId, string $messageId): void {}
}
