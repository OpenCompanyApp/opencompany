<?php

namespace App\Agents\Tools\Vfs;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsWrite implements Tool
{
    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Create or overwrite text content under writable /files paths. Use vfs_patch for targeted edits to domain-backed documents.';
    }

    public function handle(Request $request): string
    {
        $started = microtime(true);
        $ok = false;
        $errorCode = null;
        try {
            $path = (string) ($request['path'] ?? '');
            if ($path === '') {
                $errorCode = 'invalid_request';

                return $this->encodeError('invalid_request', 'path is required.');
            }

            $result = $this->vfs->write(
                $this->agent,
                $path,
                (string) ($request['content'] ?? ''),
                (string) ($request['mode'] ?? 'create'),
                $request['version'] ?? null,
            );
            $ok = true;

            return json_encode([
                'ok' => true,
                'result' => $result,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (VfsError $e) {
            $errorCode = $e->errorCode;

            return $this->encodeError($e->errorCode, $e->getMessage(), $e->context);
        } catch (\Throwable $e) {
            $errorCode = 'internal_error';

            return $this->encodeError('internal_error', $e->getMessage());
        } finally {
            event(VfsOperationLog::forAgent(
                $this->agent,
                'tool',
                'vfs_write',
                (string) ($request['path'] ?? ''),
                null,
                $ok,
                $errorCode,
                (int) round((microtime(true) - $started) * 1000),
                ['mode' => (string) ($request['mode'] ?? 'create')],
            ));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('Virtual /files path to create or overwrite. Raw writes to /docs are intentionally rejected; use vfs_patch for document edits.'),
            'content' => $schema->string()->required()->description('Text content to write.'),
            'mode' => $schema->string()->description('create or overwrite. Default: create.'),
            'version' => $schema->string()->description('Optional version token from stat for overwrite safety.'),
        ];
    }

    private function encodeError(string $code, string $message, array $context = []): string
    {
        return json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message, 'context' => $context]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
