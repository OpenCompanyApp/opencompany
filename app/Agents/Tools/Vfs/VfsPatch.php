<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\OpenCompany\Approvals\VfsPendingApproval;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsPatch implements ReturnsVfsEnvelope, Tool
{
    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function description(): string
    {
        return 'Patch writable virtual content with stale-write protection. VFS composes this primitive with existing domain permissions: table row patches also require update_table_row, agent identity patches also require update_agent_identity_file, and approval-gated backing tools create normal approval requests. Supports JSON {"search":"old","replace":"new"} patches, table row {"data": {...}} patches, or SEARCH/REPLACE blocks.';
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

            $result = $this->vfs->patch($this->agent, $path, $patch, $request['version'] ?? null, $request->toArray());
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
            'path' => $schema->string()->required()->description('Virtual path to patch, preferably canonical. /docs/by-id/... requires an explicit allowed document-folder scope; table rows require update_table_row; agent identity/memory paths enforce relationship-specific write rules.'),
            'patch' => $schema->string()->required()->description('Patch content. Use JSON {"search":"old","replace":"new"} (search must match exactly once unless "replace_all": true), table row JSON {"data": {...}} with optional "base": {...} of values you read, or a <<<<<<< SEARCH / ======= / >>>>>>> REPLACE block.'),
            'version' => $schema->string()->description('Optional version token from stat/read. If stale, record patches fail with conflict; text patches still apply when the search text matches exactly once in the current content (result.stale_base: true).'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function encodeError(string $code, string $message, array $context = []): string
    {
        // Approval-pending is not an error: one shape for every VFS tool
        // (owned by VfsPendingApproval, Stream C2).
        if ($code === 'approval_required' && ($pending = VfsPendingApproval::fromError(new VfsError($code, $message, $context))) !== null) {
            return json_encode($pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message, 'context' => $context]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
