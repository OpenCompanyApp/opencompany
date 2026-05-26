<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsRead implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for reading a VFS file as text with byte limits, version, and metadata.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = (string) ($request['path'] ?? '');
            $budget = $this->budgetFromRequest($request);
            $content = $this->vfs->read($this->agent, $path, $budget);
            $stat = $this->vfs->stat($this->agent, $path);

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'content' => $content,
                'bytes' => strlen($content),
                'version' => $stat['version'] ?? null,
                'mime' => $stat['metadata']['mime_type'] ?? 'text/plain',
                'encoding' => 'utf-8',
                'truncated' => str_ends_with($content, "\n[... truncated]"),
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
        ];
    }
}
