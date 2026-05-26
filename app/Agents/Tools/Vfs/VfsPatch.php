<?php

namespace App\Agents\Tools\Vfs;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsPatch implements Tool
{
    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Patch writable virtual content with stale-write protection. /docs patches require explicit document-folder scope; /files patches require file write scope; tasks/lists accept limited structured fields. Supports JSON {"search":"old","replace":"new"} patches or SEARCH/REPLACE blocks.';
    }

    public function handle(Request $request): string
    {
        $started = microtime(true);
        $ok = false;
        $errorCode = null;
        try {
            $path = (string) ($request['path'] ?? '');
            $patch = (string) ($request['patch'] ?? '');
            if ($path === '' || $patch === '') {
                $errorCode = 'invalid_request';

                return $this->encodeError('invalid_request', 'path and patch are required.');
            }

            $result = $this->vfs->patch($this->agent, $path, $patch, $request['version'] ?? null);
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
                'vfs_patch',
                (string) ($request['path'] ?? ''),
                null,
                $ok,
                $errorCode,
                (int) round((microtime(true) - $started) * 1000),
            ));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required()->description('Virtual path to patch, preferably canonical. /docs/by-id/... requires an explicit allowed document-folder scope; unrestricted document read access is not enough to patch.'),
            'patch' => $schema->string()->required()->description('Patch content. Use JSON {"search":"old","replace":"new"} or a <<<<<<< SEARCH / ======= / >>>>>>> REPLACE block.'),
            'version' => $schema->string()->description('Optional version token from stat. If provided, the patch fails when stale.'),
        ];
    }

    private function encodeError(string $code, string $message, array $context = []): string
    {
        return json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message, 'context' => $context]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
