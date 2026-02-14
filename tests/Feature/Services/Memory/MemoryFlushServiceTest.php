<?php

namespace Tests\Feature\Services\Memory;

use App\Agents\Providers\DynamicProviderResolver;
use App\Jobs\IndexDocumentJob;
use App\Models\Channel;
use App\Models\ConversationSummary;
use App\Models\User;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\MemoryFlushService;
use App\Services\Memory\ModelContextRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Messages\UserMessage;
use Mockery;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Tests\TestCase;

class MemoryFlushServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemoryFlushService $service;

    private ConversationCompactionService $compactionService;

    private User $agent;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([IndexDocumentJob::class]);

        $this->agent = User::factory()->agent()->create([
            'name' => 'flush-agent',
            'brain' => 'openai:gpt-4o',
        ]);

        $this->channel = Channel::factory()->dm()->create([
            'creator_id' => User::factory()->create()->id,
        ]);

        $resolver = Mockery::mock(DynamicProviderResolver::class);
        $resolver->shouldReceive('resolve')
            ->andReturn(['provider' => 'openai', 'model' => 'gpt-4o']);
        $resolver->shouldReceive('resolveFromParts')
            ->andReturn(['provider' => 'openai', 'model' => 'gpt-4o']);

        $this->compactionService = new ConversationCompactionService(
            app(ModelContextRegistry::class),
            $resolver,
        );

        $this->service = new MemoryFlushService(
            $this->compactionService,
            app(ModelContextRegistry::class),
            $resolver,
        );
    }

    /**
     * Helper: create messages that produce a specific approximate token count.
     * Each word ≈ 1.3 tokens.
     */
    private function makeMessagesWithTokens(int $targetTokens): array
    {
        // Each "Word " = 1 word ≈ 1.3 tokens
        $wordsNeeded = (int) ceil($targetTokens / 1.3);
        $content = str_repeat('Word ', $wordsNeeded);

        return [new UserMessage($content)];
    }

    public function test_should_flush_returns_false_when_disabled(): void
    {
        config(['memory.memory_flush.enabled' => false]);

        $messages = [new UserMessage('Hello')];

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
        );

        $this->assertFalse($result);
    }

    public function test_should_flush_returns_false_when_flush_count_at_max(): void
    {
        config(['memory.memory_flush.max_flushes_per_cycle' => 1]);

        ConversationSummary::create([
            'channel_id' => $this->channel->id,
            'agent_id' => $this->agent->id,
            'summary' => 'Previous summary.',
            'flush_count' => 1,
        ]);

        $messages = $this->makeMessagesWithTokens(80_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            'Short system prompt.',
        );

        $this->assertFalse($result);
    }

    public function test_should_flush_returns_false_when_below_soft_threshold(): void
    {
        // gpt-4o = 128K context. With short system prompt:
        // available ≈ 128K - ~10 - 4096 ≈ 123,894
        // compaction threshold = 123,894 * 0.75 ≈ 92,920
        // soft zone start = 92,920 - 4,000 = 88,920
        // We need adjusted tokens < 88,920 → raw tokens < 88,920 / 1.2 ≈ 74,100
        // Use ~50K tokens — well below soft zone
        $messages = $this->makeMessagesWithTokens(50_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            'Short system prompt.',
        );

        $this->assertFalse($result);
    }

    public function test_should_flush_returns_false_when_above_compaction_threshold(): void
    {
        // Need adjusted tokens > compaction threshold (≈92,920)
        // adjusted = raw * 1.2 → raw > 92,920 / 1.2 ≈ 77,433
        // Use ~100K raw tokens → adjusted ≈ 120K → well above compaction threshold
        $messages = $this->makeMessagesWithTokens(100_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            'Short system prompt.',
        );

        $this->assertFalse($result);
    }

    public function test_should_flush_returns_true_when_in_soft_zone(): void
    {
        // gpt-4o = 128K. Short prompt ≈ 10 tokens.
        // available ≈ 128,000 - 10 - 4,096 = 123,894
        // compaction threshold = 123,894 * 0.75 = 92,920
        // soft zone start = 92,920 - 4,000 = 88,920
        // Need adjusted tokens between 88,920 and 92,920
        // adjusted = raw * 1.2 → raw between 74,100 and 77,433
        // Use ~76,000 tokens → adjusted ≈ 91,200 (in soft zone)
        $messages = $this->makeMessagesWithTokens(76_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            'Short system prompt.',
        );

        $this->assertTrue($result);
    }

    public function test_should_flush_returns_true_with_no_existing_summary(): void
    {
        // No ConversationSummary exists → flush_count is effectively 0
        $messages = $this->makeMessagesWithTokens(76_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            'Short system prompt.',
        );

        $this->assertTrue($result);
    }

    public function test_should_flush_allows_second_flush_when_max_is_two(): void
    {
        config(['memory.memory_flush.max_flushes_per_cycle' => 2]);

        ConversationSummary::create([
            'channel_id' => $this->channel->id,
            'agent_id' => $this->agent->id,
            'summary' => 'Previous summary.',
            'flush_count' => 1,
        ]);

        $messages = $this->makeMessagesWithTokens(76_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            'Short system prompt.',
        );

        $this->assertTrue($result);
    }

    public function test_flush_increments_flush_count_on_existing_summary(): void
    {
        ConversationSummary::create([
            'channel_id' => $this->channel->id,
            'agent_id' => $this->agent->id,
            'summary' => 'Existing summary.',
            'flush_count' => 0,
        ]);

        // Fake the LLM call that flush() makes
        Prism::fake([
            TextResponseFake::make()->withText('[FLUSH_COMPLETE]'),
        ]);

        $this->service->flush($this->channel->id, $this->agent);

        $summary = ConversationSummary::where('channel_id', $this->channel->id)
            ->where('agent_id', $this->agent->id)
            ->first();

        $this->assertEquals(1, $summary->flush_count);
    }

    public function test_flush_creates_summary_if_none_exists(): void
    {
        $this->assertEquals(0, ConversationSummary::count());

        Prism::fake([
            TextResponseFake::make()->withText('[FLUSH_COMPLETE]'),
        ]);

        $this->service->flush($this->channel->id, $this->agent);

        $this->assertEquals(1, ConversationSummary::count());

        $summary = ConversationSummary::first();
        $this->assertEquals($this->channel->id, $summary->channel_id);
        $this->assertEquals($this->agent->id, $summary->agent_id);
        $this->assertEquals(1, $summary->flush_count);
        $this->assertEquals('', $summary->summary);
    }

    public function test_compaction_resets_flush_count(): void
    {
        $user = User::factory()->create();

        // Create enough messages for compaction
        for ($i = 0; $i < 10; $i++) {
            \App\Models\Message::factory()->create([
                'channel_id' => $this->channel->id,
                'author_id' => $user->id,
                'content' => "Test message number {$i} with some content.",
            ]);
        }

        // Low budget so short messages trigger compaction
        config(['memory.compaction.keep_recent_tokens' => 40]);

        // Set flush_count to 1
        ConversationSummary::create([
            'channel_id' => $this->channel->id,
            'agent_id' => $this->agent->id,
            'summary' => 'Old summary.',
            'flush_count' => 1,
        ]);

        Prism::fake([
            TextResponseFake::make()->withText('New compacted summary.'),
        ]);

        $summary = $this->compactionService->compact($this->channel->id, $this->agent);

        $this->assertNotNull($summary);
        $this->assertEquals(0, $summary->flush_count);
    }

    public function test_should_flush_uses_fallback_reserve_without_system_prompt(): void
    {
        // Without system prompt, fallback reserve of 10K is used
        // available ≈ 128,000 - 10,000 - 4,096 = 113,904
        // compaction threshold = 113,904 * 0.75 ≈ 85,428
        // soft zone start = 85,428 - 4,000 = 81,428
        // Need adjusted tokens between 81,428 and 85,428
        // adjusted = raw * 1.2 → raw between 67,856 and 71,190
        // Use ~70,000 tokens → adjusted ≈ 84,000
        $messages = $this->makeMessagesWithTokens(70_000);

        $result = $this->service->shouldFlush(
            $this->channel->id,
            $this->agent,
            $messages,
            null, // No system prompt
        );

        $this->assertTrue($result);
    }
}
