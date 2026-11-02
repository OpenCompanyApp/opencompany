<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved version of a document. Version history is append-only (pillar 11:
 * history must round-trip losslessly through `.ocarchive`); purging a document
 * removes its versions through the database cascade, never row by row.
 */
class DocumentVersion extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Document versions are append-only.'));
        static::deleting(fn () => throw new \LogicException('Document versions are append-only.'));
    }

    /** @use HasFactory<\Database\Factories\DocumentVersionFactory> */
    use HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'document_id',
        'title',
        'content',
        'author_id',
        'version_number',
        'change_description',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
