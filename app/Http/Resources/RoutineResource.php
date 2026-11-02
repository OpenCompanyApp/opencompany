<?php

namespace App\Http\Resources;

use App\Models\Routine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API shape of a routine. `mode` is reported but not yet enforced (Phase 3).
 *
 * @mixin Routine
 */
class RoutineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind,
            'builtInKey' => $this->built_in_key,
            'agentId' => $this->agent_id,
            'instructions' => $this->instructions,
            'version' => $this->version,
            'mode' => $this->mode,
            'notify' => $this->notify,
            'script' => $this->script,
            'scriptRuntime' => $this->script_runtime,
            'scriptDigest' => $this->script_digest,
            'scriptEngineDigest' => $this->script_engine_digest,
            'scriptValidatedAt' => $this->script_validated_at?->toIso8601String(),
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
