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
        $identityFiles = $this->docService->getIdentityFiles($this->agent);

        if ($identityFiles->isEmpty()) {
            $fallback = "You are {$this->agent->name}, a helpful AI assistant working within a company workspace.\n\n";
            $fallback .= "## You\n\n";
            $fallback .= "- **ID**: {$this->agent->id}\n";
            $fallback .= "- **Name**: {$this->agent->name}\n";
            $fallback .= "- **Type**: {$this->agent->agent_type}\n";
            $fallback .= "- **Brain**: {$this->agent->brain}\n";
            $fallback .= "- **Behavior**: {$this->agent->behavior_mode}\n\n";
            $fallback .= $this->buildTimeContext();
            $fallback .= $this->buildChannelContext();
            $fallback .= $this->buildTaskContext();
            $fallback .= "## Apps\n\n";
            $fallback .= "**IMPORTANT: Before calling ANY tool you have not used in this conversation, call `get_tool_info(name)` first to get its parameters. Do NOT guess parameters.**\n\n";
            $fallback .= $this->toolRegistry->getAppCatalog($this->agent) . "\n\n";
            $fallback .= "To generate images: use render_mermaid, render_plantuml, render_svg, or create_jpgraph_chart. To generate PDFs: use render_typst. NEVER fabricate image/document URLs.\n";
            return $fallback;
        }

        $prompt = "# Project Context\n\n";
        $prompt .= "You are an AI agent operating within a company workspace.\n\n";
        $prompt .= "## You\n\n";
        $prompt .= "- **ID**: {$this->agent->id}\n";
        $prompt .= "- **Name**: {$this->agent->name}\n";
        $prompt .= "- **Type**: {$this->agent->agent_type}\n";
        $prompt .= "- **Brain**: {$this->agent->brain}\n";
        $prompt .= "- **Behavior**: {$this->agent->behavior_mode}\n\n";

        $order = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'MEMORY', 'HEARTBEAT', 'BOOTSTRAP'];

        foreach ($order as $type) {
            $file = $identityFiles->firstWhere('title', "{$type}.md");
            if ($file && !empty(trim($file->content))) {
                $prompt .= "## {$type}.md\n\n{$file->content}\n\n";
            }
        }

        // Add current time, channel, and task context
        $prompt .= $this->buildTimeContext();
        $prompt .= $this->buildChannelContext();
        $prompt .= $this->buildTaskContext();

        $prompt .= "## Apps\n\n";
        $prompt .= "**IMPORTANT: Before calling ANY tool you have not used in this conversation, call `get_tool_info(name)` first to get its parameters. Do NOT guess parameters.**\n\n";
        $prompt .= $this->toolRegistry->getAppCatalog($this->agent) . "\n\n";
        $prompt .= "To generate images: use render_mermaid, render_plantuml, render_svg, or create_jpgraph_chart. To generate PDFs: use render_typst. NEVER fabricate image/document URLs.\n";
        $prompt .= "Tools marked with * require approval.\n";

        return $prompt;
    }

    /**
     * Get the conversation history for context.
     */
    public function messages(): iterable
    {
        return $this->conversationLoader->load($this->channelId, $this->agent);
    }

    /**
     * Get the tools available to this agent.
     */
    public function tools(): iterable
    {
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
                $prompt .= "- [{$step->status}] {$step->name}\n";
            }
        }

        $prompt .= "\nTask management is internal — NEVER mention tasks, steps, or task status in your response to the user.\n";
        $prompt .= "ALWAYS call update_current_task(action: \"update_task\") FIRST, before doing anything else, to set:\n";
        $prompt .= "- title: a short action summary (e.g., \"Generate pirate jokes\"), NOT the user's raw message\n";
        $prompt .= "- description: brief context of what was requested and what you plan to do\n\n";

        return $prompt;
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
