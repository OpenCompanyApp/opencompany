<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Code Mode helper `app.vfs.rg` (`vfs_rg`, script-only, read).
 *
 * Permission-filtered text search; regex by default, `app.vfs.grep` passes regex: false (this replaced vfs_search). Governed by the `vfs_exec` permission
 * (ToolRegistry `permissionAs`); authorization and audit are the
 * OpenCompanyVfs facade's. Description and schema come from
 * VfsOperationSpecRegistry.
 */
class VfsRg implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_rg';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        try {
            $input = $this->input($request);
            $pattern = $input->string('pattern');
            $paths = $input->raw('paths') ?? '/';
            $paths = is_array($paths) ? array_values($paths) : [(string) $paths];
            $budget = $input->budget();
            $search = $this->vfs->searchWithMetadata($this->agent, $pattern, $paths, $budget, $input->bool('regex'), $input->bool('ignoreCase'));
            $matches = $search['matches'];

            return $this->encodeResult([
                'pattern' => $pattern,
                'paths' => $paths,
                'matches' => $matches,
                'count' => count($matches),
                'returned' => count($matches),
                'limit' => $budget->maxMatches,
                'scanned' => $search['scanned'],
                'max_files' => $search['max_files'],
                'skipped' => $search['skipped'],
                'backend' => $search['backend'],
                'truncated' => $search['truncated'],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
}
