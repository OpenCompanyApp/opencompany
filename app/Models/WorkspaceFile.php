<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Workspace file/folder metadata for the virtual file manager.
 *
 * The row stores hierarchy and ownership; physical bytes live on the referenced
 * WorkspaceDisk/storage_path. Agent file tools should authorize against this
 * metadata before using FileSystemService to read or write bytes.
 *
 * Deletes are soft (governance model: delete means trash); FileSystemService
 * deleteFile() removes bytes and therefore force-deletes. `content_hash` is a
 * cache of sha256(bytes) maintained only by writers that set it in the same
 * save (the VFS write path); any other update clears it so a stale hash can
 * never stand in for the real bytes in a version token.
 *
 * @property array<string, mixed>|null $metadata
 * @property string|null $content_hash
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class WorkspaceFile extends Model
{
    use BelongsToWorkspace, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'parent_id',
        'name',
        'description',
        'is_folder',
        'storage_disk',
        'workspace_disk_id',
        'storage_path',
        'mime_type',
        'size',
        'owner_id',
        'metadata',
        'content_hash',
    ];

    protected static function booted(): void
    {
        static::updating(function (WorkspaceFile $file): void {
            // Invariant: content_hash is only trusted when the writer that
            // changed the row also set it. Writers outside the VFS (uploads,
            // FileSystemService::writeFile, touch, rename) leave it untouched,
            // so it is cleared and the next version token reads the bytes.
            if (! $file->isDirty('content_hash')) {
                $file->content_hash = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['isFolder'] = $this->is_folder;
        $array['parentId'] = $this->parent_id;
        $array['mimeType'] = $this->mime_type;
        $array['ownerId'] = $this->owner_id;
        $array['createdAt'] = $this->created_at;
        $array['updatedAt'] = $this->updated_at;
        $array['storageDisk'] = $this->storage_disk;
        $array['diskId'] = $this->workspace_disk_id;

        return $array;
    }

    /** @return BelongsTo<WorkspaceDisk, $this> */
    public function workspaceDisk(): BelongsTo
    {
        return $this->belongsTo(WorkspaceDisk::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Build the virtual path by walking up the parent chain.
     */
    public function getVirtualPath(): string
    {
        $segments = [$this->name];
        $current = $this;

        // Build paths from metadata rather than storage_path. storage_path is an
        // implementation detail and may not reflect the user-visible folder tree.
        while ($current->parent_id) {
            $current = $current->parent;
            if ($current) {
                array_unshift($segments, $current->name);
            } else {
                break;
            }
        }

        return '/'.implode('/', $segments);
    }

    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, 'image/');
    }

    public function isPreviewable(): bool
    {
        if ($this->is_folder) {
            return false;
        }

        return $this->isImage()
            || $this->mime_type === 'application/pdf'
            || ($this->mime_type && str_starts_with($this->mime_type, 'text/'));
    }
}
