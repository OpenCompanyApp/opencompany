<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsMkdir implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for creating a writable /files directory.';
    }

    public function handle(Request $request): string
    {
        try {
            return $this->encodeResult($this->vfs->makeDirectory($this->agent, (string) ($request['path'] ?? '')));
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('Directory path under /files.'),
        ];
    }
}
