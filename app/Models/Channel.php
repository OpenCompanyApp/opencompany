<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Channel extends Model
{
    use HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'type',
        'description',
        'creator_id',
        'is_temporary',
        'external_provider',
        'external_id',
        'external_config',
    ];

    protected function casts(): array
    {
        return [
            'is_temporary' => 'boolean',
            'external_config' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_members')
            ->withPivot('unread_count', 'joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
