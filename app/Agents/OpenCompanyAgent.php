<?php

namespace App\Agents;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Agents\Providers\DynamicProviderResolver;
use App\Agents\Tools\ToolRegistry;
use App\Models\AppSetting;
use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentDocumentService;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class OpenCompanyAgent implements Agent, HasTools, Conversational
{
    use Promptable;

    /** @var array<string, mixed> */
    private array $resolvedProvider;

    public function __construct(
        private User $agent,
        private string $channelId,
        private AgentDocumentService $docService,
        private ChannelConversationLoader $conversationLoader,
        private DynamicProviderResolver $providerResolver,
        private ToolRegistry $toolRegistry,
        private ?string $taskId = null,
    ) {
        $this->resolvedProvider = $this->providerResolver->resolve($this->agent);
    }

    /**
     * Create an agent instance for a given user and channel.
     */
    public static function for(User $agent, string $channelId, ?string $taskId = null): static
    {
        return app(static::class, [
            'agent' => $agent,
            'channelId' => $channelId,
            'taskId' => $taskId,
        ]);
    }

    /**
     * Get the instructions (system prompt) for this agent.
     *
     * Assembles from identity files in the same order as AgentChatService.
     */
    public function instructions(): string
    {
        return implode('', array_column($this->buildSections(), 'content'));
    }

    /**
     * Get a character-length breakdown of each system prompt section.
     * Used with actual prompt_tokens from the LLM response to compute proportional token allocation.
     *
     * @return array<int, array{label: string, chars: int}>
     */
    public function instructionsBreakdown(): array
    {
        return array_values(array_map(
            fn (array $s) => ['label' => $s['label'], 'chars' => mb_strlen($s['content'])],
            $this->buildSections(),
        ));
    }

    /**
     * Build the system prompt as labeled sections.
     *
     * @return array<int, array{label: string, content: string}>
     */
    private function buildSections(): array
    {
        $identityFiles = $this->docService->getIdentityFiles($this->agent);

        $sections = [];

        if ($identityFiles->isEmpty()) {
            $header = "You are {$this->agent->name}, a helpful AI assistant working within a company workspace.\n\n";
            $header .= "## You\n\n";
            $header .= "- **ID**: {$this->agent->id}\n";
            $header .= "- **Name**: {$this->agent->name}\n";
            $header .= "- **Type**: {$this->agent->agent_type}\n";
            $header .= "- **Brain**: {$this->agent->brain}\n";
            $header .= "- **Behavior**: {$this->agent->behavior_mode}\n\n";
            $sections[] = ['label' => 'Header', 'content' => $header];
        } else {
            $header = "# Project Context\n\n";
            $header .= "You are an AI agent operating within a company workspace.\n\n";
            $header .= "## You\n\n";
            $header .= "- **ID**: {$this->agent->id}\n";
            $header .= "- **Name**: {$this->agent->name}\n";
            $header .= "- **Type**: {$this->agent->agent_type}\n";
            $header .= "- **Brain**: {$this->agent->brain}\n";
            $header .= "- **Behavior**: {$this->agent->behavior_mode}\n\n";
            $sections[] = ['label' => 'Header', 'content' => $header];

            $order = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'MEMORY', 'HEARTBEAT', 'BOOTSTRAP'];

            $channel = Channel::find($this->channelId);
            $privateChannel = $channel && in_array($channel->type, ['dm', 'agent']);

            foreach ($order as $type) {
                if ($type === 'MEMORY' && !$privateChannel) {
                    continue;
                }

                $file = $identityFiles->firstWhere('title', "{$type}.md");
                if ($file && !empty(trim($file->content))) {
                    $sections[] = ['label' => "{$type}.md", 'content' => "## {$type}.md\n\n{$file->content}\n\n"];
                }
            }

            if ($privateChannel) {
                $sections[] = ['label' => 'Memory System', 'content' => $this->buildMemoryPrompt()];
            }
        }

        // Common sections
        $timeContext = $this->buildTimeContext();
        if ($timeContext) {
            $sections[] = ['label' => 'Current Time', 'content' => $timeContext];
        }

        $channelContext = $this->buildChannelContext();
        if ($channelContext) {
            $sections[] = ['label' => 'Current Context', 'content' => $channelContext];
        }

        $taskContext = $this->buildTaskContext();
        if ($taskContext) {
            $sections[] = ['label' => 'Current Task', 'content' => $taskContext];
        }

        $apps = "## Apps\n\n";
        $apps .= "**IMPORTANT: Before calling ANY tool you have not used in this conversation, call `get_tool_info(name)` first to get its parameters. Do NOT guess parameters.**\n\n";
        $apps .= $this->toolRegistry->getAppCatalog($this->agent) . "\n\n";
        $apps .= "To generate images: use render_mermaid, render_plantuml, render_svg, or render_vegalite. To generate PDFs: use render_typst. NEVER fabricate image/document URLs.\n";
        if (!$identityFiles->isEmpty()) {
            $apps .= "Tools marked with * require approval.\n";
        }
        $sections[] = ['label' => 'Apps', 'content' => $apps];

        return $sections;
    }

    /**
     * Get the conversation history for context.
     * Passes the system prompt for accurate token measurement during compaction checks.
     *
     * @return iterable<mixed>
     */
    public function messages(): iterable
    {
        return $this->conversationLoader->load($this->channelId, $this->agent, $this->instructions());
    }

    /**
     * Get the tools available to this agent.
     */
    public function tools(): iterable
    {
        $this->toolRegistry->setChannelContext($this->channelId);

        return $this->toolRegistry->getToolsForAgent($this->agent);
    }

    /**
     * Get the AI provider name for this agent.
     */
    public function provider(): string
    {
        return $this->resolvedProvider['provider'];
    }

    /**
     * Get the AI model name for this agent.
     */
    public function model(): string
    {
        return $this->resolvedProvider['model'];
    }

    /**
     * Get the timeout for LLM requests (in seconds).
     */
    public function timeout(): int
    {
        return (int) config('prism.request_timeout', 600);
    }

    /**
     * Build context about the current channel for the system prompt.
     */
    private function buildChannelContext(): string
    {
        $channel = Channel::with('users')->find($this->channelId);
        if (!$channel) {
            return '';
        }

        $prompt = "## Current Context\n\n";
        $prompt .= "You are responding in channel \"{$channel->name}\" (id: {$channel->id}, type: {$channel->type}).";

        if ($channel->type === 'external' && $channel->external_provider) {
            $prompt .= " This is a {$channel->external_provider} external channel.";
        }
        $prompt .= "\n";

        // List channel members
        $members = $channel->users->pluck('name')->toArray();
        if (!empty($members)) {
            $prompt .= "Channel members: " . implode(', ', $members) . "\n";
        }

        $prompt .= "\nYour response text is sent directly to this channel — you do NOT need to use send_channel_message or read_channel for the current conversation. Use those tools only to interact with OTHER channels.\n\n";

        return $prompt;
    }

    /**
     * Build context about the current task for the system prompt.
     */
    private function buildTaskContext(): string
    {
        if (!$this->taskId) {
            return '';
        }

        $task = Task::with('steps')->find($this->taskId);
        if (!$task) {
            return '';
        }

        $prompt = "## Current Task\n\n";
        $prompt .= "You are working on task \"{$task->title}\" (id: {$task->id}).\n";
        $prompt .= "Status: {$task->status} | Priority: {$task->priority} | Type: {$task->type}\n";

        if ($task->description) {
            $prompt .= "Description: {$task->description}\n";
        }

        if ($task->steps->isNotEmpty()) {
            $prompt .= "\nSteps:\n";
            foreach ($task->steps as $step) {
                /** @var \App\Models\TaskStep $step */
                $prompt .= "- [{$step->status}] {$step->description}\n";
            }
        }

        $prompt .= "\nTask management is internal — NEVER mention tasks, steps, or task status in your response to the user.\n";
        $prompt .= "ALWAYS call update_current_task(action: \"update_task\") FIRST, before doing anything else, to set:\n";
        $prompt .= "- title: a short action summary (e.g., \"Generate pirate jokes\"), NOT the user's raw message\n";
        $prompt .= "- description: brief context of what was requested and what you plan to do\n\n";

        return $prompt;
    }

    /**
     * Build memory system guidance for the system prompt.
     */
    private function buildMemoryPrompt(): string
    {
        return <<<'PROMPT'
## Memory System

You have two types of memory:

### Short-Term Memory (STM)
Your current conversation context. You can see recent messages directly. Older messages
are automatically summarized when the context window fills up. **You don't manage STM** —
the system handles it for you.

### Long-Term Memory (LTM)
Durable memories that persist across all conversations. You manage LTM explicitly:

- **MEMORY.md** (core memory) — Already loaded in your system prompt above. Contains
  curated, high-value facts. You can add to it via `save_memory` with `target: "core"`.
- **Daily logs** — Timestamped entries searchable via `recall_memory`. Written via
  `save_memory` with `target: "log"` (default).

### save_memory

Persist information to your long-term memory. Two targets:

| Target | Storage | Loaded | Best for |
|--------|---------|--------|----------|
| `"core"` | MEMORY.md | Always (system prompt) | User preferences, key decisions, organizational knowledge, durable facts |
| `"log"` (default) | Daily log | On demand via `recall_memory` | Running context, timestamped observations, session learnings |

Guidelines:
- Be specific: include who, what, why, and when
- Prefer `"core"` only for truly durable facts that should always be in context
- Prefer `"log"` for most saves — keeps MEMORY.md focused and manageable
- If someone says "remember this" — save it immediately (do not rely on conversation context)
- Use categories: preference, decision, learning, fact, general

### recall_memory

Search or browse your daily logs. Two modes:

**Semantic search** (default): `recall_memory(query: "deployment process")`
- Searches across all daily logs for relevant entries
- Use before answering questions about prior work, decisions, or preferences
- Use at the start of complex tasks to gather relevant history
- Use when a user references something from a previous conversation

**Date browse**: `recall_memory(date: "2026-02-14")`
- Returns the raw log for a specific day
- If the log is too large, you'll get a size warning — re-call with `max_chars` to truncate
- Example: `recall_memory(date: "2026-02-14", max_chars: 3000)`

If recall_memory returns nothing relevant, tell the user you checked but found no prior context.

### When to save vs when not to

**Save:**
- User expresses a preference or working style
- An important decision is made (with reasoning)
- Key facts about a project, person, or the organization
- Learnings or insights from the current conversation
- Anything the user explicitly asks you to remember

**Don't save:**
- Transient, obvious, or trivial information
- Information already in MEMORY.md
- Raw conversation snippets without context
- Temporary task state that won't matter later

PROMPT;
    }

    /**
     * Build current date/time context for the system prompt.
     */
    private function buildTimeContext(): string
    {
        $timezone = AppSetting::getValue('org_timezone', 'UTC');
        $now = now()->timezone($timezone);

        $prompt = "## Current Time\n\n";
        $prompt .= "- **DateTime**: {$now->format('l, F j, Y g:i A')} ({$timezone})\n";
        $prompt .= "- **ISO**: {$now->toIso8601String()}\n\n";

        return $prompt;
    }

    /**
     * Get the underlying agent User model.
     */
    public function getAgent(): User
    {
        return $this->agent;
    }

    /**
     * Get the channel this agent is operating in.
     */
    public function getChannelId(): string
    {
        return $this->channelId;
    }
}
