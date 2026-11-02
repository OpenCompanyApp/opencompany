<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\ToolRegistry;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\User;

/**
 * Captures where an approval request came from, so its outcome can be posted
 * back to the same conversation and the same run can be resumed.
 *
 * Sources, strongest first:
 * - an explicit `channelId` tool parameter (legacy callers);
 * - the ToolRegistry channel/task context that OpenCompanyAgent and
 *   GenericSubagent bind around every tool call;
 * - the durable agent run (ActiveAgentRun, from the ccd1997 control plane),
 *   whose record also carries the run's channel.
 *
 * Invariant: a channel is only returned when it belongs to the agent's
 * workspace. The origin never authorizes anything; it only says where the
 * outcome message goes. Never read it from approval parameters supplied by a
 * webhook at resolution time.
 */
class VfsApprovalOrigin
{
    public function __construct(private readonly ToolRegistry $tools) {}

    /**
     * @param  array<string, mixed>  $parameters  Tool parameters of the gated call
     * @return array{run_id: string|null, task_id: string|null, channel_id: string|null}
     */
    public function capture(User $agent, array $parameters = []): array
    {
        $runId = app()->bound(ActiveAgentRun::class) ? app(ActiveAgentRun::class)->runId : null;
        $channelId = is_string($parameters['channelId'] ?? null) && $parameters['channelId'] !== ''
            ? $parameters['channelId']
            : $this->tools->getChannelContext();
        $taskId = $this->tools->getTaskContext();

        if ($channelId === null && $runId !== null) {
            $run = AgentRunRecord::query()
                ->whereKey($runId)
                ->where('workspace_id', $agent->workspace_id)
                ->first(['id', 'channel_id', 'task_id']);
            $channelId = $run?->channel_id;
            $taskId ??= $run?->task_id;
        }

        return [
            'run_id' => $runId,
            'task_id' => $taskId,
            'channel_id' => $this->workspaceChannel($agent, $channelId),
        ];
    }

    private function workspaceChannel(User $agent, ?string $channelId): ?string
    {
        if ($channelId === null || $channelId === '') {
            return null;
        }

        return Channel::query()
            ->whereKey($channelId)
            ->where('workspace_id', $agent->workspace_id)
            ->exists() ? $channelId : null;
    }
}
