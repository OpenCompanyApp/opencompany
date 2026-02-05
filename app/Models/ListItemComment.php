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
        return $this->hasMany(ListItemComment::class, 'parent_id');
    }
}
