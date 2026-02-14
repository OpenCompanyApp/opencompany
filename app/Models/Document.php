<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'content',
        'author_id',
        'parent_id',
        'is_folder',
        'color',
        'icon',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Document $doc) {
            if ($doc->is_system) {
                throw new \LogicException('System documents cannot be deleted.');
            }
        });
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['isFolder'] = $this->is_folder;
        $array['isSystem'] = $this->is_system;
        $array['parentId'] = $this->parent_id;
        $array['authorId'] = $this->author_id;
        $array['createdAt'] = $this->created_at;
        $array['updatedAt'] = $this->updated_at;
        $array['color'] = $this->color;
        $array['icon'] = $this->icon;

        return $array;
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_id');
    }

    /** @return HasMany<Document, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Document::class, 'parent_id');
    }

    /** @return HasMany<DocumentPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(DocumentPermission::class);
    }

    /** @return HasMany<DocumentComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return HasMany<DocumentAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }
}
