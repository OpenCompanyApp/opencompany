<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsCp implements Tool
{
    use HandlesVfsJson;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Lua helper for copying writable workspace files.';
    }

    public function handle(Request $request): string
    {
        try {
            return $this->encodeResult($this->vfs->copy(
                $this->agent,
                (string) ($request['source'] ?? $request['src'] ?? ''),
                (string) ($request['destination'] ?? $request['dst'] ?? $request['dest'] ?? ''),
            ));
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->required()->description('Source /files path.'),
            'destination' => $schema->string()->required()->description('Destination /files path.'),
            'src' => $schema->string()->description('Alias for source.'),
            'dst' => $schema->string()->description('Alias for destination.'),
            'dest' => $schema->string()->description('Alias for destination.'),
        ];
    }
}
