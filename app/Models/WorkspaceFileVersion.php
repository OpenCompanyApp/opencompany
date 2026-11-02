<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a workspace file's bytes taken before a VFS write replaced them.
 *
 * Same contract as DocumentVersion and DataTableRowVersion: `version_number`
 * counts changes per file, `content` is the state BEFORE change n, and the row
 * names the actor and run that made change n plus the version tokens on both
 * sides of it. That attribution is what a later change-set object and
 * "revert this run" build on. Non-UTF-8 bytes are stored base64 encoded
 * (`encoding` = base64). Written only by the VFS file write path.
 *
 * @property string $workspace_file_id
 * @property int $version_number
 * @property string $content
 * @property string $encoding
 */
class WorkspaceFileVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'workspace_file_id',
        'version_number',
        'name',
        'content',
        'encoding',
        'content_hash',
        'size',
        'author_id',
        'run_id',
        'before_version',
        'after_version',
        'change_description',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'size' => 'integer',
        ];
    }

    /** The snapshot bytes, decoded. */
    public function bytes(): string
    {
        return $this->encoding === 'base64'
            ? (string) base64_decode($this->content, true)
            : $this->content;
    }

    /** @return BelongsTo<WorkspaceFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
