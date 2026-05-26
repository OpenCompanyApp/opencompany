<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Document write helper for VFS document content updates.
 */
trait MutatesVfsDocuments
{
    private function writeDocument(User $agent, array $segments, string $path, string $content, string $mode, ?string $version): array
    {
        $doc = (($segments[1] ?? null) === 'by-id' && isset($segments[2]))
            ? $this->documentFromByIdSegments($segments, $path)
            : $this->resolveDocumentPath(array_slice($segments, 1), $path);

        if ($doc->is_folder) {
            throw VfsError::notWritable($path);
        }
        if ($doc->is_system) {
            throw VfsError::permissionDenied($path, 'system documents require dedicated agent identity or memory tools');
        }
        if (! $this->canPatchDocument($agent, $doc)) {
            throw VfsError::permissionDenied($path, 'document patch requires explicit folder scope');
        }
        $this->assertVersion($version, $this->version($doc->updated_at?->toIso8601String(), $doc->content ?? ''), $path);

        $previousContent = (string) ($doc->content ?? '');

        if ($previousContent !== $content) {
            $lastVersion = DocumentVersion::where('document_id', $doc->id)->max('version_number') ?? 0;

            DocumentVersion::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $doc->id,
                'title' => $doc->title,
                'content' => $previousContent,
                'author_id' => $agent->id,
                'version_number' => $lastVersion + 1,
                'change_description' => 'VFS write snapshot before update',
            ]);
        }

        $doc->update(['content' => $content]);

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/docs/by-id/{$doc->id}",
            'version' => $this->version($doc->fresh()->updated_at?->toIso8601String(), $content),
        ];
    }
}
