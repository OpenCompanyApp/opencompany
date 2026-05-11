<?php

namespace App\Services;

use App\Agents\OpenCompanyAgent;
use App\Ai\Prompting\SystemPromptBag;
use App\Models\User;

class AgentChatService
{
    public function respond(User $agent, string $channelId, string $userMessage): string
    {
        $agentInstance = OpenCompanyAgent::for($agent, $channelId);

        app()->instance(SystemPromptBag::class, new SystemPromptBag(
            $agentInstance->systemPrompts()
        ));

        return $agentInstance->prompt($userMessage)->text;
    }
}
