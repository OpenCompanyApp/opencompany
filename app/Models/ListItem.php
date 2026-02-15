<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BelongsToWorkspace;

/**
 * ListItem - Items in a kanban board/list (renamed from Task)
 * For discrete work tracking, see the Task model instead.
 *
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string|null $priority
 * @property bool $is_folder
 * @property string|null $parent_id
 * @property string|null $assignee_id
 * @property string|null $creator_id
 * @property string|null $channel_id
 * @property int $position
 * @property int $item_count
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $due_date
 */
class ListItem extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, BelongsToWorkspace;

    protected $table = 'list_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'parent_id',
        'is_folder',
        'title',
        'description',
        'status',
        'assignee_id',
        'creator_id',
        'priority',
        'channel_id',
        'position',
        'completed_at',
        'due_date',
    ];

    protected static function booted(): void
    {
        static::creating(function (ListItem $item) {
            if (!$item->is_folder && !$item->parent_id) {
                throw new \InvalidArgumentException('List items must belong to a project (parent_id is required).');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'completed_at' => 'datetime',
            'due_date' => 'date',
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
        $array['assigneeId'] = $this->assignee_id;
        $array['creatorId'] = $this->creator_id;
        $array['channelId'] = $this->channel_id;
        $array['dueDate'] = $this->due_date;

        return $array;
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return HasMany<ListItemCollaborator, $this> */
    public function collaboratorPivots(): HasMany
    {
        return $this->hasMany(ListItemCollaborator::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_item_collaborators', 'list_item_id', 'user_id');
    }

    /** @return HasMany<ListItemComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ListItemComment::class);
    }

    /** @return BelongsTo<ListItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'parent_id');
    }

    /** @return HasMany<ListItem, $this> */
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
            'agent_id' => ($agent ? $agent->id : $this->assignee_id),
            'requester_id' => $requester->id,
            'channel_id' => $this->channel_id,
            'list_item_id' => $this->id,
            'workspace_id' => $this->workspace_id,
        ]);
    }
}
