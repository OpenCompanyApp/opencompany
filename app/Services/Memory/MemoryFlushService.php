<?php

namespace App\Services\Memory;

use App\Agents\OpenCompanyAgent;
use App\Ai\Prompting\SystemPromptBag;
use App\Models\ConversationSummary;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Gives an agent one chance to save durable memories before compaction.
 *
 * A flush is a silent model call whose text output is ignored; only memory tool
 * calls matter. Keep this separate from compaction so useful facts can be saved
 * before older transcript details are summarized away.
 */
class MemoryFlushService
{
    public function __construct(
        private ContextBudget $contextBudget,
    ) {}

    /**
     * Check if a memory flush should be triggered.
     *
     * Flush happens when we're within the "soft zone" — close to the compaction
     * threshold but not yet exceeding it — AND we haven't already flushed for
     * this compaction cycle.
     *
     * @param  iterable<mixed>  $messages
     */
    public function shouldFlush(string $channelId, User $agent, iterable $messages, ?string $systemPrompt = null): bool
    {
        if (! config('memory.memory_flush.enabled', true)) {
            return false;
        }

        // Limit flushes per compaction cycle. Without this guard, a long-running
        // conversation hovering near the threshold could repeatedly spend model
        // calls trying to save the same memories.
        $summary = ConversationSummary::where('channel_id', $channelId)
            ->where('agent_id', $agent->id)
            ->first();

        $maxFlushes = config('memory.memory_flush.max_flushes_per_cycle', 1);
        if ($summary && $summary->flush_count >= $maxFlushes) {
            return false;
        }

        try {
            $budget = $this->contextBudget->snapshotForAgent($agent, $messages, $systemPrompt);
        } catch (\Throwable) {
            return false;
        }

        return (bool) $budget['is_above_flush'] && ! (bool) $budget['is_above_compaction'];
    }

    /**
     * Execute a silent memory flush.
     *
     * Runs the agent with a flush prompt instructing it to save important
     * memories before compaction. The agent's text response is discarded —
     * only tool calls (save_memory) produce side effects.
     */
    public function flush(string $channelId, User $agent): void
    {
        $agentInstance = OpenCompanyAgent::for($agent, $channelId);

        // The flush prompt is still an agent run and needs the same split
        // system prompts as a normal response, but it intentionally avoids
        // creating a chat message for the model's textual answer.
        app()->instance(SystemPromptBag::class, new SystemPromptBag(
            $agentInstance->systemPrompts()
        ));
        $agentInstance->prompt($this->buildFlushPrompt());

        // Increment flush count on the summary record because compaction resets
        // this counter. That makes the "once per cycle" rule durable across
        // queue retries and worker restarts.
        $summary = ConversationSummary::firstOrCreate(
            ['channel_id' => $channelId, 'agent_id' => $agent->id],
            ['summary' => '', 'workspace_id' => $agent->workspace_id ?? workspace()->id]
        );
        $summary->increment('flush_count');

        Log::info('Memory flush completed', [
            'agent' => $agent->name,
            'channel' => $channelId,
        ]);
    }

    /**
     * Build the prompt that instructs the agent to save important memories.
     */
    private function buildFlushPrompt(): string
    {
        return <<<'PROMPT'
Pre-compaction memory flush. Your conversation context is about to be compacted (older messages will be summarized and compressed).

Review the conversation for durable context worth preserving. Use save_memory (target: "log") to save important observations, decisions, preferences, or learnings to your daily log before they are compressed.

Only save to target: "core" if you discovered truly high-value permanent facts (user preferences, key decisions) that should always be in your system prompt.

If nothing needs saving, respond with exactly: [FLUSH_COMPLETE]
PROMPT;
    }
}
