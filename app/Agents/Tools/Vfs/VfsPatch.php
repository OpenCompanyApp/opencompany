<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * `vfs_patch`: structured record patches (task/list fields, table rows with
 * `base`) and single text search/replace.
 *
 * Thin envelope over OpenCompanyVfs::patch(), which owns parsing, composed
 * permissions (vfs_patch + update_table_row / update_agent_identity_file),
 * approvals and the atomic version-checked write. The raw request array is
 * forwarded as tool parameters: approvals hash and replay exactly this call
 * (vfs_edit relies on that by submitting a vfs_patch-shaped parameter set).
 */
class VfsPatch implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_patch';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        $started = microtime(true);
        $ok = false;
        $errorCode = null;
        try {
            $input = $this->input($request);
            $path = trim($input->string('path'));
            $patch = $request['patch'] ?? '';
            // Code Mode callers naturally pass a Hash; accept it as the same
            // JSON the string form carries.
            if (is_array($patch)) {
                $patch = (string) json_encode($patch, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if ($path === '' || ! is_string($patch) || $patch === '') {
                throw VfsError::invalid('path and patch are required.', [
                    'field' => $path === '' ? 'path' : 'patch',
                    'hint' => 'Example: {"path": "/tasks/by-id/ID", "patch": "{\"status\":\"completed\"}"}. Use vfs_edit for text.',
                ]);
            }

            $result = $this->vfs->patch($this->agent, $path, $patch, $input->optionalString('version'), $request->toArray());
            $ok = true;

            return $this->encodeResult($result);
        } catch (\Throwable $e) {
            $errorCode = $e instanceof VfsError ? $e->errorCode : 'internal_error';

            return $this->errorResponse($e);
        } finally {
            event(VfsOperationLog::forAgent(
                $this->agent,
                // The caller's surface (tool, code, computer, approval_replay, …),
                // the same one the facade's object events carry.
                app(VfsOperationContext::class)->surface(),
                self::TOOL_SLUG,
                (string) ($request['path'] ?? ''),
                null,
                $ok,
                $errorCode,
                (int) round((microtime(true) - $started) * 1000),
            ));
        }
    }
}
