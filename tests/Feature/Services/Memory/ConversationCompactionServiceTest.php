<?php

namespace Tests\Feature\Services\Memory;

use App\Agents\Providers\DynamicProviderResolver;
use App\Jobs\IndexDocumentJob;
use App\Models\AppSetting;
use App\Models\Channel;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\User;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\ModelContextRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Messages\UserMessage;
use Mockery;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Tests\TestCase;

class ConversationCompactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConversationCompactionService $service;

    private User $agent;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);

        $this->agent = User::factory()->agent()->create([
            'name' => 'compact-agent',
            'brain' => 'openai:gpt-4o',
        ]);

        $this->channel = Channel::factory()->dm()->create([
            'creator_id' => User::factory()->create()->id,
        ]);

        // Use a mock resolver that always returns a valid provider/model
        $resolver = Mockery::mock(DynamicProviderResolver::class);
        $resolver->shouldReceive('resolve')
            ->andReturn(['provider' => 'openai', 'model' => 'gpt-4o']);
        $resolver->shouldReceive('resolveFromParts')
            ->andReturn(['provider' => 'openai', 'model' => 'gpt-4o']);

        $this->service = new ConversationCompactionService(
            app(ModelContextRegistry::class),
            $resolver,
        );
    }

    private function createMessages(int $count, ?string $channelId = null, ?string $authorId = null): void
    {
        $channelId = $channelId ?? $this->channel->id;
        $authorId = $authorId ?? User::factory()->create()->id;

        for ($i = 0; $i < $count; $i++) {
            Message::factory()->create([
                'channel_id' => $channelId,
                'author_id' => $authorId,
                'content' => "Test message number {$i} with some content to estimate tokens.",
            ]);
        }
    }

    private function createLongMessages(int $count, ?string $channelId = null): void
    {
        $channelId = $channelId ?? $this->channel->id;
        $authorId = User::factory()->create()->id;

        for ($i = 0; $i < $count; $i++) {
            Message::factory()->create([
                'channel_id' => $channelId,
                'author_id' => $authorId,
                'content' => str_repeat("This is a long message number {$i} with detailed content. ", 100),
            ]);
        }
    }

    public function test_needs_compaction_returns_false_when_disabled(): void
    {
        AppSetting::setValue('memory_compaction_enabled', false, 'memory');

        $messages = [new UserMessage('Hello, how are you?')];

        $result = $this->service->needsCompaction(
            $this->channel->id,
            $this->agent,
            $messages,
        );

        $this->assertFalse($result);
    }

    public function test_needs_compaction_returns_false_for_few_messages(): void
    {
        $messages = [
            new UserMessage('Short message.'),
            new UserMessage('Another short one.'),
        ];

        $result = $this->service->needsCompaction(
            $this->channel->id,
            $this->agent,
            $messages,
        );

        $this->assertFalse($result);
    }

    public function test_needs_compaction_returns_true_when_exceeds_threshold(): void
    {
        // gpt-4o has 128K context. With default config: available = 128K - sysPromptTokens - 4096
        // We need messages whose tokens * 1.2 > available * 0.75
        // Use a very short system prompt so available is large, but fill with tons of messages
        $longMessages = [];
        for ($i = 0; $i < 500; $i++) {
            $longMessages[] = new UserMessage(str_repeat("Word ", 200)); // ~200 words * 1.3 = 260 tokens each
        }
        // 500 * 260 = 130,000 tokens total. After safety margin: 130K * 1.2 = 156K
        // Available with short system prompt: 128K - ~100 - 4096 ≈ 123K
        // Threshold: 123K * 0.75 ≈ 92K. 156K > 92K → should need compaction

        $result = $this->service->needsCompaction(
            $this->channel->id,
            $this->agent,
            $longMessages,
            'Short system prompt.',
        );

        $this->assertTrue($result);
    }

    public function test_needs_compaction_uses_system_prompt_for_token_estimate(): void
    {
        // Large system prompt eats into available context
        $largeSystemPrompt = str_repeat('System prompt content. ', 5000); // ~10K words
        $messages = [new UserMessage('Just a small message.')];

        // Large system prompt → lots of tokens consumed → available shrinks
        // But small messages → still under threshold
        $result = $this->service->needsCompaction(
            $this->channel->id,
            $this->agent,
            $messages,
            $largeSystemPrompt,
        );

        $this->assertFalse($result);
    }

    public function test_needs_compaction_uses_fallback_reserve_without_prompt(): void
    {
        // When no system prompt is provided, fallback reserve of 10K is used
        $messages = [new UserMessage('Short message.')];

        $result = $this->service->needsCompaction(
            $this->channel->id,
            $this->agent,
            $messages,
            null, // No system prompt → uses fallback reserve
        );

        $this->assertFalse($result);
    }

    public function test_compact_returns_null_for_fewer_than_five_messages(): void
    {
        $this->createMessages(4);

        $result = $this->service->compact($this->channel->id, $this->agent);

        $this->assertNull($result);
    }

    public function test_compact_uses_token_budget_for_split(): void
    {
        $user = User::factory()->create();
        $this->createMessages(10, authorId: $user->id);

        // Each message is ~13 tokens. Set budget to ~40 tokens → keeps ~3 recent messages
        config(['memory.compaction.keep_recent_tokens' => 40]);

        Prism::fake([
            TextResponseFake::make()->withText('Summary of the conversation so far.'),
        ]);

        $summary = $this->service->compact($this->channel->id, $this->agent);

        $this->assertNotNull($summary);
        $this->assertEquals('Summary of the conversation so far.', $summary->summary);
        $this->assertGreaterThan(0, $summary->messages_summarized);
        // With ~40 token budget and ~13 tokens/msg, should summarize most messages
        $this->assertGreaterThanOrEqual(7, $summary->messages_summarized);
    }

    public function test_compact_returns_null_when_all_messages_fit_in_budget(): void
    {
        $user = User::factory()->create();
        $this->createMessages(10, authorId: $user->id);

        // Each message is ~13 tokens. 10 * 13 = ~130 tokens. Budget is 20K → all fit
        $result = $this->service->compact($this->channel->id, $this->agent);

        $this->assertNull($result);
    }

    public function test_compact_creates_conversation_summary(): void
    {
        $user = User::factory()->create();
        $this->createMessages(10, authorId: $user->id);
        config(['memory.compaction.keep_recent_tokens' => 40]);

        Prism::fake([
            TextResponseFake::make()->withText('Conversation summary text.'),
        ]);

        $this->assertEquals(0, ConversationSummary::count());

        $summary = $this->service->compact($this->channel->id, $this->agent);

        $this->assertNotNull($summary);
        $this->assertEquals(1, ConversationSummary::count());
        $this->assertEquals($this->channel->id, $summary->channel_id);
        $this->assertEquals($this->agent->id, $summary->agent_id);
        $this->assertEquals(1, $summary->compaction_count);
    }

    public function test_compact_increments_compaction_count(): void
    {
        $user = User::factory()->create();
        $this->createMessages(10, authorId: $user->id);
        config(['memory.compaction.keep_recent_tokens' => 40]);

        Prism::fake([
            TextResponseFake::make()->withText('First summary.'),
            TextResponseFake::make()->withText('Second summary.'),
        ]);

        $first = $this->service->compact($this->channel->id, $this->agent);
        $this->assertEquals(1, $first->compaction_count);

        // Advance time so second batch has later created_at than first batch
        $this->travel(5)->seconds();

        // Add more messages for second compaction
        $this->createMessages(10, authorId: $user->id);

        $second = $this->service->compact($this->channel->id, $this->agent);
        $this->assertNotNull($second, 'Second compaction should find messages after the compaction point');
        $this->assertEquals(2, $second->compaction_count);
    }
}
