<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsRead implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Code Mode helper for reading a VFS file as text with byte limits, version, and metadata.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = (string) ($request['path'] ?? '');
            $budget = $this->budgetFromRequest($request);
            $cursor = $this->pageCursor($request);
            $page = $this->vfs->readPage($this->agent, $path, $budget, $cursor);
            $content = $page->content;
            // Capabilities/metadata come from stat, but the version must
            // describe the bytes returned above: mounts record it on the
            // budget during the same read. Stat's version is only a fallback
            // for generated paths whose mount does not record one.
            $readVersion = $budget->lastReadVersion();
            $stat = $this->vfs->stat($this->agent, $path);

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'content' => $content,
                'bytes' => strlen($content),
                'version' => $readVersion ?? ($stat['version'] ?? null),
                'capabilities' => $stat['capabilities'] ?? [],
                'metadata' => $stat['metadata'] ?? [],
                'mime' => $stat['metadata']['mime_type'] ?? 'text/plain',
                'encoding' => 'utf-8',
                'cursor' => $cursor,
                'next_cursor' => $page->nextCursor,
                'seen_before' => $page->seenBefore,
                'returned' => $page->returned,
                'scanned' => $page->scanned,
                'skipped' => $page->skipped,
                'truncated' => $page->truncated || $budget->truncations() > 0 || str_ends_with($content, VfsText::TRUNCATION_SUFFIX),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('Virtual file path to read.'),
            'maxBytes' => $schema->integer()->description('Maximum bytes to return. Default: 100000.'),
            'limit' => $schema->integer()->description('Maximum records returned from a generated collection file. Default: 100.'),
            'maxFiles' => $schema->integer()->description('Backend scan ceiling for generated collection files. Default: 100.'),
            'cursor' => $schema->string()->description('Opaque cursor from a previous truncated generated-file response.'),
        ];
    }
}
