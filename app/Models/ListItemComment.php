<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListItemComment extends Model
{
    use HasFactory;

    protected $table = 'list_item_comments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'list_item_id',
        'author_id',
        'content',
        'parent_id',
    ];

    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ListItemComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ListItemComment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    public function toArray()
    {
        $array = parent::toArray();

        $result = [
            'id' => $array['id'],
            'listItemId' => $array['list_item_id'],
            'authorId' => $array['author_id'],
            'content' => $array['content'],
            'parentId' => $array['parent_id'] ?? null,
            'createdAt' => $array['created_at'],
            'updatedAt' => $array['updated_at'],
        ];

        if ($this->relationLoaded('author')) {
            $result['author'] = $this->author;
        }

        if ($this->relationLoaded('replies')) {
            $result['replies'] = $this->replies;
        }

        return $result;
    }
}
