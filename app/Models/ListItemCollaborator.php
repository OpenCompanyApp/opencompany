<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItemCollaborator extends Model
{
    protected $table = 'list_item_collaborators';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'list_item_id',
        'user_id',
    ];

    /** @return BelongsTo<ListItem, $this> */
    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
