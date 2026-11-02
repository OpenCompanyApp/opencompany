<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Code Mode helper `app.vfs.stat` (`vfs_stat`, script-only, read).
 *
 * Returns the raw stat entry (type, version, capabilities, canonical path, metadata). Governed by the `vfs_exec` permission
 * (ToolRegistry `permissionAs`); authorization and audit are the
 * OpenCompanyVfs facade's. Description and schema come from
 * VfsOperationSpecRegistry.
 */
class VfsStat implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_stat';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        try {
            $path = (string) ($request['path'] ?? '/');

            return $this->encodeResult($this->vfs->stat($this->agent, $path));
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
}
