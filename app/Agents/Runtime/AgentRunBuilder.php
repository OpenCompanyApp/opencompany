<?php

namespace App\Agents\Runtime;

use App\Agents\OpenCompanyAgent;
use App\Agents\Runtime\Context\AgentContextPipeline;
use App\Models\User;

class AgentRunBuilder
{
    public function __construct(private AgentContextPipeline $context) {}

    public function build(User $agent, string $channelId, ?string $taskId = null, ?AgentRunOptions $options = null): AgentRun
    {
        $options ??= new AgentRunOptions;
        $instance = OpenCompanyAgent::for($agent, $channelId, $taskId);

        if ($options->resumeFromTask && $taskId !== null) {
            $instance->resumeFrom($taskId);
        }

        return new AgentRun($agent, $instance, $options, $this->context, $taskId);
    }
}
