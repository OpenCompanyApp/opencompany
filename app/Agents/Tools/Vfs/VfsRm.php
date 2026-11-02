<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Code Mode helper `app.vfs.rm` (`vfs_rm`, script-only, write).
 *
 * File delete through the facade write policy (governance action class `delete`). Governed by the `vfs_write` permission
 * (ToolRegistry `permissionAs`); authorization and audit are the
 * OpenCompanyVfs facade's. Description and schema come from
 * VfsOperationSpecRegistry.
 */
class VfsRm implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_rm';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        try {
            // The full call (including idempotency_key) is the policy's
            // parameter set: approval replay re-sends the same call, so the
            // approved-context hash matches, and retries dedupe.
            return $this->encodeResult($this->vfs->remove($this->agent, (string) ($request['path'] ?? ''), $request->toArray()));
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
}
