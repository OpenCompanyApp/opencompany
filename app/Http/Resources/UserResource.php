<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'type' => $this->type,
            'agentType' => $this->agent_type,
            'status' => $this->status,
            'presence' => $this->presence,
            'lastSeenAt' => $this->last_seen_at,
            'currentTask' => $this->current_task,
            'isAgent' => $this->type === 'agent',
            'role' => $this->pivot->role ?? null,
        ];
    }
}
