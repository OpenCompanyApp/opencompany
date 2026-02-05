<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ListItem - Items in a kanban board/list (renamed from Task)
 * For discrete work tracking, see the Task model instead.
 */
class ListItem extends Model
{
    use HasFactory;

    protected $table = 'list_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'parent_id',
        'is_folder',
        'title',
        'description',
        'status',
        'assignee_id',
        'creator_id',
        'priority',
        'cost',
        'estimated_cost',
        'channel_id',
        'position',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'cost' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Override toArray to add camelCase versions for frontend compatibility
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Add camelCase versions of snake_case fields
        $array['parentId'] = $this->parent_id;
        $array['isFolder'] = $this->is_folder;
        $array['createdAt'] = $this->created_at;
        $array['completedAt'] = $this->completed_at;
        $array['estimatedCost'] = $this->estimated_cost;
        $array['assigneeId'] = $this->assignee_id;
        $array['creatorId'] = $this->creator_id;
        $array['channelId'] = $this->channel_id;

        return $array;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function collaboratorPivots(): HasMany
    {
        return $this->hasMany(ListItemCollaborator::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_item_collaborators', 'list_item_id', 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ListItemComment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ListItem::class, 'parent_id');
    }

    /**
     * Convert this list item to a Task (cases-style work item)
     */
    public function convertToTask(User $requester, ?User $agent = null): Task
    {
        return Task::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => $this->title,
            'description' => $this->description,
            'type' => 'custom',
            'status' => 'pending',
            'priority' => $this->priority ?? 'normal',
            'agent_id' => $agent?->id ?? $this->assignee_id,
            'requester_id' => $requester->id,
            'channel_id' => $this->channel_id,
            'list_item_id' => $this->id,
        ]);
    }
}
