<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'title' => $this->title,
            'content' => $this->content,
            'isFolder' => $this->is_folder,
            'parentId' => $this->parent_id,
            'color' => $this->color,
            'icon' => $this->icon,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'author' => $this->whenLoaded('author', fn() => new UserResource($this->author)),
            'parent' => $this->whenLoaded('parent', fn() => new DocumentResource($this->parent)),
            'children' => $this->whenLoaded('children', fn() => DocumentResource::collection($this->children)),
            'permissions' => $this->whenLoaded('permissions', fn() => $this->permissions->map(fn($p) => [
                'id' => $p->id,
                'userId' => $p->user_id,
                'role' => $p->role,
                'user' => $p->relationLoaded('user') ? new UserResource($p->user) : null,
            ])),
        ];
    }
}
