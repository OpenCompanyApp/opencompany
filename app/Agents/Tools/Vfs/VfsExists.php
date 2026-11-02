<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Code Mode helper `app.vfs.exists` (`vfs_exists`, script-only, read).
 *
 * Existence check that reports not_found as exists: false instead of an error. Governed by the `vfs_exec` permission
 * (ToolRegistry `permissionAs`); authorization and audit are the
 * OpenCompanyVfs facade's. Description and schema come from
 * VfsOperationSpecRegistry.
 */
class VfsExists implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_exists';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

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
}
