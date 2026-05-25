<?php

namespace App\Services;

use App\Agents\OpenCompanyAgent;
use App\Ai\Prompting\SystemPromptBag;
use App\Models\User;

/**
 * Synchronous chat helper around OpenCompanyAgent.
 *
 * Jobs should prefer AgentRun/AgentRespondJob for durable task handling; this
 * service is for direct request/response paths that still need prompt sections.
 */
class AgentChatService
{
    public function respond(User $agent, string $channelId, string $userMessage): string
    {
        $agentInstance = OpenCompanyAgent::for($agent, $channelId);

        // Bind prompt sections for CachingTextGateway so provider-side cache
        // hints can distinguish stable identity text from volatile context.
        app()->instance(SystemPromptBag::class, new SystemPromptBag(
            $agentInstance->systemPrompts()
        ));

        return $agentInstance->prompt($userMessage)->text;
    }
}
