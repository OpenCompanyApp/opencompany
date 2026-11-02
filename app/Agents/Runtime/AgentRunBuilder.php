<?php

namespace App\Agents\Runtime;

use App\Agents\OpenCompanyAgent;
use App\Agents\Runtime\Context\AgentContextPipeline;
use App\Models\User;

/**
 * Factory for runtime-ready agent runs.
 *
 * OpenCompanyAgent still owns prompt/tool setup; this builder binds that agent
 * instance to runtime options and the context snapshot pipeline used by jobs,
 * live tests, and future orchestrators.
 */
class AgentRunBuilder
{
    public function __construct(private AgentContextPipeline $context) {}

    public function build(User $agent, string $channelId, ?string $taskId = null, ?AgentRunOptions $options = null): AgentRun
    {
        $options ??= new AgentRunOptions;
        $runtimeAgent = clone $agent;

        $instance = OpenCompanyAgent::for($runtimeAgent, $channelId, $taskId)
            ->withMaxSteps($options->maxTurns);

        if ($options->resumeFromTask && $taskId !== null) {
            // Resume state is only meaningful when the caller has a durable
            // task ID. Fresh runs should not inherit previous continuation data.
            $instance->resumeFrom($taskId);
        }

        return new AgentRun($runtimeAgent, $instance, $options, $this->context, $taskId);
    }
}
