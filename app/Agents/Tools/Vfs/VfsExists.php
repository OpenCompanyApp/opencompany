<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsExists implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for checking whether a VFS path exists without throwing on not_found.';
    }

    public function handle(Request $request): string
    {
        $path = (string) ($request['path'] ?? '/');
        try {
            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'exists' => true,
                'stat' => $this->vfs->stat($this->agent, $path),
            ]);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return $this->encodeResult([
                    'path' => $this->vfs->normalizePath($path),
                    'exists' => false,
                ]);
            }

            return $this->errorResponse($e);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('Virtual path to check.'),
        ];
    }
}
