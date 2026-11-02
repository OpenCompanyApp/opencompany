<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\DataTableRowVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Object history for VFS-written documents, workspace files and table rows.
 *
 * Owns two things:
 *
 * 1. Snapshot recording. Every committed VFS write that changes an object
 *    stores the object's previous state as version n (DocumentVersion,
 *    WorkspaceFileVersion, DataTableRowVersion), attributed to the acting
 *    agent, the active run (ActiveAgentRun, when the write happens inside a
 *    run) and the version tokens before and after the change. This is the
 *    foundation a change-set object and "revert this run" build on: the
 *    earliest snapshot a run wrote for an object is that object's state
 *    before the run.
 *
 * 2. The read-only `.history/` view. Appending `/.history/` to ANY path that
 *    resolves to one of those objects (friendly or by-id) lists its snapshots
 *    newest first as `v{n}--{author-slug}--{YYYYMMDDTHHMMSSZ}{ext}`. `v{n}` is
 *    the content that change n replaced; the author and time are those of
 *    change n. Reading an entry (the full name or just `v{n}`) returns that
 *    content. Restore is a normal governed write of the old content with the
 *    object's current version, so it creates a new version itself.
 *
 * Authorization: the view resolves the object through stat(), so it inherits
 * the object's read checks exactly; history never widens read access.
 *
 * Numbering: snapshots are written while the caller holds a row lock on the
 * object, and a unique (object, version_number) index plus a savepoint retry
 * covers writers outside the VFS that do not take that lock.
 */
trait RecordsVfsHistory
{
    /**
     * Who and which run a VFS write is attributed to.
     *
     * @return array{author_id: string, run_id: string|null}
     */
    private function vfsWriteAttribution(User $agent): array
    {
        $run = app()->bound(ActiveAgentRun::class) ? app(ActiveAgentRun::class) : null;

        return [
            'author_id' => (string) $agent->id,
            'run_id' => $run instanceof ActiveAgentRun ? $run->runId : null,
        ];
    }

    /**
     * Store a document's previous title/content as the next version.
     * Call while holding the document row lock.
     */
    private function snapshotDocumentVersion(Document $doc, string $previousTitle, string $previousContent, User $agent, ?string $beforeVersion, ?string $afterVersion, string $description): DocumentVersion
    {
        $attribution = $this->vfsWriteAttribution($agent);

        /** @var DocumentVersion */
        return $this->insertNumberedSnapshot(
            fn (): int => (int) (DocumentVersion::where('document_id', $doc->id)->max('version_number') ?? 0),
            fn (int $number): Model => DocumentVersion::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $doc->id,
                'title' => $previousTitle,
                'content' => $previousContent,
                'author_id' => $attribution['author_id'],
                'run_id' => $attribution['run_id'],
                'version_number' => $number,
                'before_version' => $beforeVersion,
                'after_version' => $afterVersion,
                'change_description' => $description,
            ]),
        );
    }

    /**
     * Store a file's previous bytes as the next version. Bytes above the
     * content ceiling are not snapshotted (the history then has a gap rather
     * than a truncated copy); non-UTF-8 bytes are stored base64 encoded.
     * Call while holding the file row lock.
     */
    private function snapshotWorkspaceFileVersion(WorkspaceFile $file, string $previousBytes, User $agent, ?string $beforeVersion, ?string $afterVersion, string $description): ?WorkspaceFileVersion
    {
        if (strlen($previousBytes) > (int) config('vfs.max_content_bytes', 20 * 1024 * 1024)) {
            return null;
        }

        $attribution = $this->vfsWriteAttribution($agent);
        $isUtf8 = mb_check_encoding($previousBytes, 'UTF-8');

        /** @var WorkspaceFileVersion */
        return $this->insertNumberedSnapshot(
            fn (): int => (int) (WorkspaceFileVersion::where('workspace_file_id', $file->id)->max('version_number') ?? 0),
            fn (int $number): Model => WorkspaceFileVersion::create([
                'workspace_id' => $file->workspace_id,
                'workspace_file_id' => $file->id,
                'version_number' => $number,
                'name' => $file->name,
                'content' => $isUtf8 ? $previousBytes : base64_encode($previousBytes),
                'encoding' => $isUtf8 ? 'utf8' : 'base64',
                'content_hash' => hash('sha256', $previousBytes),
                'size' => strlen($previousBytes),
                'author_id' => $attribution['author_id'],
                'run_id' => $attribution['run_id'],
                'before_version' => $beforeVersion,
                'after_version' => $afterVersion,
                'change_description' => $description,
            ]),
        );
    }

    /**
     * Store a row's previous data (keyed by column id) as the next version.
     * Call while holding the row lock.
     *
     * @param  array<string, mixed>  $previousData
     */
    private function snapshotTableRowVersion(DataTable $table, DataTableRow $row, array $previousData, User $agent, ?string $beforeVersion, ?string $afterVersion, string $description): DataTableRowVersion
    {
        $attribution = $this->vfsWriteAttribution($agent);

        /** @var DataTableRowVersion */
        return $this->insertNumberedSnapshot(
            fn (): int => (int) (DataTableRowVersion::where('row_id', $row->id)->max('version_number') ?? 0),
            fn (int $number): Model => DataTableRowVersion::create([
                'workspace_id' => $table->workspace_id,
                'table_id' => $table->id,
                'row_id' => $row->id,
                'version_number' => $number,
                'data' => $previousData,
                'author_id' => $attribution['author_id'],
                'run_id' => $attribution['run_id'],
                'before_version' => $beforeVersion,
                'after_version' => $afterVersion,
                'change_description' => $description,
            ]),
        );
    }

    /**
     * Insert max+1 inside a savepoint; on a unique collision (a writer that
     * did not hold our row lock took the number) retry with a fresh max.
     *
     * @param  callable(): int  $currentMax
     * @param  callable(int): Model  $insert
     */
    private function insertNumberedSnapshot(callable $currentMax, callable $insert): Model
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(fn (): Model => $insert($currentMax() + 1));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  list<string>  $segments
     */
    private function isHistoryPath(array $segments): bool
    {
        return in_array('.history', $segments, true);
    }

    /**
     * Resolve the object a `.history` path belongs to.
     *
     * @param  list<string>  $segments
     * @return array{type: string, id: string, object_path: string, rest: list<string>, name: string}
     */
    private function historyTarget(User $agent, array $segments, string $path): array
    {
        $index = (int) array_search('.history', $segments, true);
        $objectPath = '/'.implode('/', array_slice($segments, 0, $index));
        $rest = array_slice($segments, $index + 1);

        if ($index === 0) {
            throw VfsError::notFound($path);
        }

        $stat = $this->stat($agent, $objectPath);
        $type = (string) ($stat['backend_type'] ?? '');
        $id = (string) ($stat['backend_id'] ?? '');
        if (! in_array($type, ['document', 'workspace_file', 'data_table_row'], true) || $id === '' || ($stat['type'] ?? null) !== 'file') {
            throw new VfsError('not_found', "No history for {$objectPath}: only documents, workspace files and table rows keep versions.", [
                'path' => $path,
                'hint' => 'Append /.history/ to a document, file or table row path.',
            ]);
        }

        return ['type' => $type, 'id' => $id, 'object_path' => $objectPath, 'rest' => $rest, 'name' => (string) ($stat['name'] ?? basename($objectPath))];
    }

    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listHistory(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        $target = $this->historyTarget($agent, $segments, $path);
        if ($target['rest'] !== []) {
            throw VfsError::notReadable($path);
        }

        $base = rtrim($target['object_path'], '/').'/.history';

        return collect($this->historyVersions($target, $budget->maxEntries))
            ->map(function (Model $version) use ($target, $base): VfsEntry {
                $name = $this->historyEntryName($version, $target);

                return new VfsEntry(
                    name: $name,
                    path: "{$base}/{$name}",
                    type: 'file',
                    backendType: $target['type'].'_version',
                    backendId: (string) $version->getKey(),
                    capabilities: ['read'],
                    metadata: [
                        'version_number' => (int) $version->getAttribute('version_number'),
                        'author_id' => $version->getAttribute('author_id'),
                        'run_id' => $version->getAttribute('run_id'),
                        'before_version' => $version->getAttribute('before_version'),
                        'after_version' => $version->getAttribute('after_version'),
                        'change_description' => $version->getAttribute('change_description'),
                        'created_at' => $version->getAttribute('created_at')?->toIso8601String(),
                        ...$this->readOnlyMetadata(
                            'History entries are immutable snapshots.',
                            'vfs_write',
                            'To restore, read this entry and write its content to the object path with the object\'s current version.',
                        ),
                    ],
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $segments
     */
    private function readHistory(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        $target = $this->historyTarget($agent, $segments, $path);
        if ($target['rest'] === []) {
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->listHistory($agent, $segments, $path, $budget));
        }
        if (count($target['rest']) > 1 || preg_match('/^v(\d+)(?:--|\.|$)/', $target['rest'][0], $m) !== 1) {
            throw new VfsError('not_found', "Path not found: {$path}", [
                'path' => $path,
                'hint' => 'History entries are named v{n}--{author}--{time}; `v{n}` alone also works. List '.$target['object_path'].'/.history/ first.',
            ]);
        }

        $version = $this->historyVersionQuery($target)->where('version_number', (int) $m[1])->first()
            ?? throw VfsError::notFound($path);

        $content = match ($target['type']) {
            'document' => (string) $version->getAttribute('content'),
            'workspace_file' => $version instanceof WorkspaceFileVersion ? $version->bytes() : '',
            default => $this->historicalRowJson($version),
        };

        return VfsText::fit($content, $budget, $path);
    }

    /**
     * @param  array{type: string, id: string}  $target
     * @return list<Model>
     */
    private function historyVersions(array $target, int $limit): array
    {
        return $this->historyVersionQuery($target)
            ->with('author:id,name')
            ->orderByDesc('version_number')
            ->limit(max(1, $limit))
            ->get()
            ->all();
    }

    /**
     * @param  array{type: string, id: string}  $target
     * @return Builder<covariant Model>
     */
    private function historyVersionQuery(array $target): Builder
    {
        return match ($target['type']) {
            'document' => DocumentVersion::query()->where('document_id', $target['id']),
            'workspace_file' => WorkspaceFileVersion::query()->where('workspace_file_id', $target['id']),
            default => DataTableRowVersion::query()->where('row_id', $target['id']),
        };
    }

    /**
     * @param  array{type: string, name: string}  $target
     */
    private function historyEntryName(Model $version, array $target): string
    {
        $author = $version->getRelationValue('author');
        $authorSlug = $author instanceof User ? $this->slug((string) $author->name) : 'unknown';
        $time = $version->getAttribute('created_at')?->utc()->format('Ymd\THis\Z') ?? 'unknown';
        $extension = match ($target['type']) {
            'document' => '.md',
            'data_table_row' => '.json',
            default => ($ext = pathinfo($target['name'], PATHINFO_EXTENSION)) !== '' ? '.'.$ext : '',
        };

        return 'v'.(int) $version->getAttribute('version_number')."--{$authorSlug}--{$time}{$extension}";
    }

    /**
     * Render a historical row in the same name-keyed shape as rows/by-id.
     */
    private function historicalRowJson(Model $version): string
    {
        $data = (array) $version->getAttribute('data');
        $table = DataTable::forWorkspace()->with('columns')->find($version->getAttribute('table_id'));
        $named = $table !== null ? $this->tableRowDataByColumnName($table->columns, $data)['data'] : $data;

        return json_encode([
            'version_number' => (int) $version->getAttribute('version_number'),
            'data' => $named,
            'data_by_column_id' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }
}
