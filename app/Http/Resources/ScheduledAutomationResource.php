<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ScheduledAutomation
 */
class ScheduledAutomationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'agentId' => $this->agent_id,
            'prompt' => $this->prompt,
            'cronExpression' => $this->cron_expression,
            'timezone' => $this->timezone,
            'isActive' => $this->is_active,
            'lastRunAt' => $this->last_run_at,
            'nextRunAt' => $this->next_run_at,
            'runCount' => $this->run_count,
            'consecutiveFailures' => $this->consecutive_failures,
            'channelId' => $this->channel_id,
            'keepHistory' => $this->keep_history,
            'createdById' => $this->created_by_id,
            'lastResult' => $this->last_result,
            'agent' => $this->whenLoaded('agent', fn () => new UserResource($this->agent)),
            'channel' => $this->whenLoaded('channel'),
            'createdBy' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
        ];
    }
}
