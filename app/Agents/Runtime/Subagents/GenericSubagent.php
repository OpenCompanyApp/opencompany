<?php

namespace App\Agents\Runtime\Subagents;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\AgentDocumentService;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Wraps a workspace agent so Laravel AI can expose it as a callable subagent.
 *
 * The wrapper gives each peer agent its own instructions and tool set while
 * deliberately withholding the parent conversation. Parent agents must pass the
 * necessary task context explicitly when delegating.
 */
class GenericSubagent implements Agent, CanActAsTool, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        private User $agent,
        private string $channelId,
        private ?string $taskId,
        private AgentDocumentService $documents,
        private ChannelConversationLoader $conversationLoader,
        private ToolRegistry $tools,
    ) {}

    public static function for(User $agent, string $channelId, ?string $taskId = null): self
    {
        return app(self::class, compact('agent', 'channelId', 'taskId'));
    }

    public function name(): string
    {
        $id = str($this->agent->id)->replace('-', '_')->toString();

        // Include the database ID so two agents with the same display name do
        // not collide in the Laravel AI tool registry.
        return 'subagent_'.str($this->agent->name)->slug('_')->toString().'_'.$id;
    }

    public function description(): Stringable|string
    {
        return "Delegate a clear, self-contained task to {$this->agent->name}. The subagent runs with isolated context and reports back a concise result.";
    }

    public function instructions(): string
    {
        return implode("\n\n", array_filter([
            "You are {$this->agent->name}, a generic OpenCompany subagent.",
            'Complete only the delegated task. Keep the result concise, factual, and directly usable by the parent agent.',
            'You run in isolated context. Do not assume access to the parent conversation except what is included in the task.',
            $this->documents->getIdentityFiles($this->agent)
                ->map(fn ($document) => "# {$document->title}\n\n{$document->content}")
                ->implode("\n\n"),
        ]));
    }

    public function messages(): iterable
    {
        // Keep delegated runs isolated. Pulling parent channel history here
        // would make subagent behavior harder to audit and could leak context
        // the parent did not intentionally include in the task.
        return [];
    }

    public function tools(): iterable
    {
        $this->tools->setChannelContext($this->channelId);
        $this->tools->setTaskContext($this->taskId);

        return $this->tools->getToolsForAgent($this->agent);
    }
}
