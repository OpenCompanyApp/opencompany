<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Code Mode helper `app.vfs.ls` (`vfs_ls`, script-only, read).
 *
 * Cursor-paged directory listing; the final page carries the exact total_count (this replaced vfs_count). Governed by the `vfs_exec` permission
 * (ToolRegistry `permissionAs`); authorization and audit are the
 * OpenCompanyVfs facade's. Description and schema come from
 * VfsOperationSpecRegistry.
 */
class VfsLs implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_ls';

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        try {
            $input = $this->input($request);
            $path = $input->string('path');
            $budget = $input->budget();
            $cursor = $input->cursor();
            $page = $this->vfs->listPage($this->agent, $path, $budget, $cursor);
            $entries = array_map(
                fn ($entry): array => $entry->jsonSerialize(),
                $page->entries,
            );

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'items' => $entries,
                'count' => count($entries),
                'returned' => count($entries),
                'limit' => $budget->maxEntries,
                'cursor' => $cursor,
                'next_cursor' => $page->nextCursor,
                'truncated' => $page->truncated,
                // Replaces the removed vfs_count helper: the final page of a
                // traversal knows the exact cumulative visible count.
                'total_count' => $page->truncated ? null : $page->seenBefore + count($entries),
                'count_is_exact' => ! $page->truncated,
                'seen_before' => $page->seenBefore,
                'scanned' => $page->scanned,
                'skipped' => $page->skipped,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }
}
