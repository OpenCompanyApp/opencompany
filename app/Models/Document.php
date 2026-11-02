<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\RecordsAgentChanges;
use App\Models\Contracts\RevertibleContent;
use App\Models\Concerns\Trashable;
use App\Models\Concerns\TrashesInBatches;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Workspace document or folder.
 *
 * Documents serve both user-authored knowledge and system-owned agent identity
 * files. System documents are guarded from ordinary deletion because deleting
 * an agent's identity/memory tree can break prompt assembly.
 *
 * `delete()` moves a document (and a folder's subtree) to trash; trashed
 * documents leave queries and their search chunks are dropped, then rebuilt
 * on restore. `forceDelete()` is the purge (governance-model.md).
 * Folder subtrees trash and restore as one batch (TrashesInBatches), so
 * plain `$folder->delete()` callers stay correct.
 *
 * @property Carbon|null $deleted_at
 */
class Document extends Model implements RevertibleContent
{
    /** @use HasFactory<Factory<static>> */
    use BelongsToWorkspace, HasFactory, RecordsAgentChanges, Trashable, TrashesInBatches;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'title',
        'content',
        'content_format',
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
                // AgentDocumentService intentionally unsets is_system before
                // deleting an agent tree; ordinary UI/controller deletes should
                // fail here instead of removing prompt-critical files.
                throw new \LogicException('System documents cannot be deleted.');
            }
            // Trashing a folder takes its subtree along; it must not carry
            // system files with it (checked before anything is trashed).
            if (! $doc->isForceDeleting()) {
                foreach ($doc->liveDescendants() as $descendant) {
                    if ($descendant->is_system) {
                        throw new \LogicException('System documents cannot be deleted.');
                    }
                }
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
        $array['contentFormat'] = $this->content_format ?? 'markdown';
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

    /**
     * Optimistic-concurrency token for this document's content.
     *
     * Invariant: identical to the version the VFS reports and checks
     * (ResolvesVfsEntries::version), so human editors (document API) and
     * agents (VFS) detect each other's writes with one token.
     */
    public function contentVersion(): string
    {
        return hash('sha256', ($this->updated_at?->toIso8601String() ?? '').'|'.hash('sha256', (string) ($this->content ?? '')));
    }
}
