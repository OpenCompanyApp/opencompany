<?php

namespace App\Agents;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Agents\Providers\DynamicProviderResolver;
use App\Agents\Tools\ToolRegistry;
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
    ) {
        $this->resolvedProvider = $this->providerResolver->resolve($this->agent);
    }

    /**
     * Create an agent instance for a given user and channel.
     */
    public static function for(User $agent, string $channelId): static
    {
        return app(static::class, [
            'agent' => $agent,
            'channelId' => $channelId,
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
            return "You are {$this->agent->name}, a helpful AI assistant working within a company workspace. You can search documents, send messages, and track task progress.";
        }

        $prompt = "# Project Context\n\n";
        $prompt .= "You are an AI agent operating within a company workspace.\n\n";

        $order = ['IDENTITY', 'SOUL', 'USER', 'AGENTS', 'TOOLS', 'MEMORY', 'HEARTBEAT', 'BOOTSTRAP'];

        foreach ($order as $type) {
            $file = $identityFiles->firstWhere('title', "{$type}.md");
            if ($file && !empty(trim($file->content))) {
                $prompt .= "## {$type}.md\n\n{$file->content}\n\n";
            }
        }

        $prompt .= "## Available Tools\n\n";
        $prompt .= "You have access to the following tools:\n";

        foreach ($this->tools() as $tool) {
            $isWrapped = $tool instanceof Tools\ApprovalWrappedTool;
            $innerTool = $isWrapped ? $tool->getInnerTool() : $tool;
            $name = strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst(class_basename($innerTool))));
            $desc = $innerTool->description();
            $approval = $isWrapped ? ' (requires approval)' : '';
            $prompt .= "- **{$name}**: {$desc}{$approval}\n";
        }

        $prompt .= "\nUse these tools when appropriate to help users effectively.\n";

        // Add approval workflow context if any tools require approval
        $hasApprovalTools = false;
        foreach ($this->tools() as $tool) {
            if ($tool instanceof Tools\ApprovalWrappedTool) {
                $hasApprovalTools = true;
                break;
            }
        }

        if ($hasApprovalTools) {
            $prompt .= "\n## Approval Workflow\n\n";
            if ($this->agent->must_wait_for_approval) {
                $prompt .= "When you use a tool that requires approval, you will automatically pause until the approval is decided. Tell the user you are waiting for their decision.\n";
            } else {
                $prompt .= "When you use a tool that requires approval, decide whether to wait for the decision or continue working:\n";
                $prompt .= "- If your next steps depend on the tool's result, call `wait_for_approval` with the approval ID to pause until it's decided.\n";
                $prompt .= "- If the action is a side-effect and you can continue without the result, keep working — the action will execute when approved.\n";
            }
        }

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
