<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Agents\Tools\Vfs\Support\VfsToolInput;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * `vfs_read`: the direct, line-oriented read of one text file.
 *
 * Two modes, chosen by the path (stat metadata `paged_collection`):
 *
 * - Ordinary files: one content read (OpenCompanyVfs::readContentWithVersion,
 *   never the truncated display read), so `version` describes exactly the
 *   bytes served (Phase 1 contract) and `total_lines` is exact. The page is
 *   lines [offset, offset+limit) capped at maxBytes on whole lines, numbered
 *   `cat -n` style unless raw. Continuation is explicit: truncated +
 *   next_offset + hint. raw pages are byte-exact slices of the file.
 *   A line longer than the byte budget is cut on a UTF-8 boundary and the
 *   page says so (truncated + line_cut); the continuation stays ON that
 *   line (next_offset = the cut line, next_line_byte_offset = where the cut
 *   happened), so a paging loop that follows next_offset /
 *   next_line_byte_offset reads every byte exactly once.
 * - Paged collection files (rows.ndjson, channel day transcripts,
 *   members.json): OpenCompanyVfs::readPage() with the cursor; records are
 *   returned raw and never split; `limit` is the record count.
 *
 * Read authorization is the mounts' (same as cat). Directories and binary
 * content fail with an actionable hint instead of an empty or garbled page.
 */
class VfsRead implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_read';

    /** Upper bound on lines per page, whatever the caller asks for. */
    private const MAX_LINES = 20_000;

    public function __construct(
        private readonly User $agent,
        private readonly OpenCompanyVfs $vfs,
    ) {}

    public function handle(Request $request): string
    {
        try {
            $input = $this->input($request);
            $path = trim($input->string('path'));
            if ($path === '') {
                throw VfsError::invalid('path is required.', ['field' => 'path']);
            }

            $stat = $this->vfs->stat($this->agent, $path);
            if (($stat['type'] ?? null) === 'directory') {
                throw new VfsError('not_readable', "{$this->vfs->normalizePath($path)} is a directory.", [
                    'path' => $this->vfs->normalizePath($path),
                    'hint' => 'List it with vfs_exec "ls '.$this->vfs->normalizePath($path).'".',
                ]);
            }

            $result = ($stat['metadata']['paged_collection'] ?? false) === true
                ? $this->readCollectionPage($path, $input)
                : $this->readLines($path, $input);

            return $this->encodeResult([
                'path' => $this->vfs->normalizePath($path),
                'canonical_path' => $stat['canonical_path'] ?? $stat['path'] ?? $this->vfs->normalizePath($path),
                ...$result,
                'capabilities' => array_values($stat['capabilities'] ?? []),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readLines(string $path, VfsToolInput $input): array
    {
        if ($input->cursor() !== null) {
            throw VfsError::invalid('cursor only pages collection files (rows.ndjson, channel day transcripts, members.json); use offset for this file.', [
                'field' => 'cursor',
                'path' => $this->vfs->normalizePath($path),
            ]);
        }

        $offset = $input->int('offset', 1);
        $lineByteOffset = $input->int('line_byte_offset', 0);
        $limit = $input->int('limit', 1, self::MAX_LINES);
        $maxBytes = $input->int('maxBytes', 1, 2_000_000);
        $raw = $input->bool('raw');

        ['content' => $content, 'version' => $version] = $this->vfs->readContentWithVersion($this->agent, $path);
        $this->assertText($path, $content);

        // Byte offset of each line start; a trailing newline does not open
        // an extra (empty) line, matching wc -l / cat -n.
        $starts = $content === '' ? [] : [0];
        $search = 0;
        while (($newline = strpos($content, "\n", $search)) !== false && $newline + 1 < strlen($content)) {
            $starts[] = $newline + 1;
            $search = $newline + 1;
        }
        $total = count($starts);

        $base = [
            'version' => $version,
            'offset' => $offset,
            'total_lines' => $total,
        ];

        if ($offset > max(1, $total)) {
            return $base + [
                'content' => '',
                'returned_lines' => 0,
                'truncated' => false,
                'hint' => "offset {$offset} is past the end of the file ({$total} lines).",
            ];
        }
        if ($lineByteOffset > 0) {
            $lineLength = $offset <= $total ? ($starts[$offset] ?? strlen($content)) - $starts[$offset - 1] : 0;
            if ($lineByteOffset >= $lineLength) {
                throw VfsError::invalid("line_byte_offset {$lineByteOffset} is not inside line {$offset} ({$lineLength} bytes).", [
                    'field' => 'line_byte_offset',
                    'path' => $this->vfs->normalizePath($path),
                ]);
            }
            $base['line_byte_offset'] = $lineByteOffset;
        }

        $lines = [];
        $bytes = 0;
        $last = $offset - 1;
        $cutAt = null;
        for ($index = $offset - 1; $index < $total && $index < $offset - 1 + $limit; $index++) {
            $end = $starts[$index + 1] ?? strlen($content);
            // Only the first line of the page can start mid-line.
            $start = $starts[$index] + ($index === $offset - 1 ? $lineByteOffset : 0);
            $line = substr($content, $start, $end - $start);
            $prefix = $raw ? '' : sprintf('%6d', $index + 1)."\t";
            $rendered = $raw ? $line : $prefix.rtrim($line, "\n")."\n";

            if ($bytes + strlen($rendered) > $maxBytes) {
                if ($lines === []) {
                    // A single line longer than the whole budget: return a
                    // UTF-8-safe prefix of it (at least one character, so
                    // paging always advances) and continue on this line.
                    $room = max(1, $maxBytes - strlen($prefix) - ($raw ? 0 : 1));
                    $piece = mb_strcut($line, 0, $room, 'UTF-8');
                    if ($piece === '') {
                        $piece = mb_substr($line, 0, 1, 'UTF-8');
                    }
                    $lines[] = $raw ? $piece : $prefix.$piece."\n";
                    $cutAt = $start - $starts[$index] + strlen($piece);
                    $last = $index;
                }
                break;
            }

            $lines[] = $rendered;
            $bytes += strlen($rendered);
            $last = $index + 1;
        }

        $text = implode('', $lines);
        $truncated = $cutAt !== null || $last < $total;
        $page = $base + [
            'content' => $text,
            'bytes' => strlen($text),
            // Lines whose bytes appear in content; a cut line counts once.
            'returned_lines' => max(0, $last - $offset + 1) + ($cutAt !== null ? 1 : 0),
            'truncated' => $truncated,
        ];
        if ($cutAt !== null) {
            $lineNumber = $last + 1;
            $lineLength = ($starts[$last + 1] ?? strlen($content)) - $starts[$last];
            $page['line_cut'] = true;
            $page['next_offset'] = $lineNumber;
            $page['next_line_byte_offset'] = $cutAt;
            $page['hint'] = "Line {$lineNumber} is longer than maxBytes; showed its bytes up to {$cutAt} of {$lineLength}. "
                ."Continue with offset={$lineNumber}, line_byte_offset={$cutAt} (or raise maxBytes).";
        } elseif ($truncated) {
            $page['next_offset'] = $last + 1;
            $page['hint'] = "Showing lines {$offset}-{$last} of {$total}. Continue with offset=".($last + 1).'.';
        }

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCollectionPage(string $path, VfsToolInput $input): array
    {
        if ($input->int('offset', 1) !== 1) {
            throw VfsError::invalid('This is a paged collection file; continue with cursor (next_cursor), not offset.', [
                'field' => 'offset',
                'path' => $this->vfs->normalizePath($path),
            ]);
        }

        $budget = $input->budget();
        $cursor = $input->cursor();
        $page = $this->vfs->readPage($this->agent, $path, $budget, $cursor);

        $result = [
            'content' => $page->content,
            'bytes' => strlen($page->content),
            'version' => $budget->lastReadVersion(),
            'paged_collection' => true,
            'returned' => $page->returned,
            'seen_before' => $page->seenBefore,
            'truncated' => $page->truncated,
            // Always present (null on the last page) so loops can test it.
            'next_cursor' => $page->nextCursor,
        ];
        if ($page->nextCursor !== null) {
            $result['hint'] = 'More records follow. Continue with cursor=next_cursor (same path).';
        }

        return $result;
    }

    private function assertText(string $path, string $content): void
    {
        if (! str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8')) {
            return;
        }

        $normalized = $this->vfs->normalizePath($path);

        throw VfsError::invalid("{$normalized} is binary or not UTF-8 text.", [
            'path' => $normalized,
            'bytes' => strlen($content),
            'hint' => "Inspect it with vfs_exec \"xxd {$normalized} | head\" or \"base64 {$normalized}\"; text in another encoding can go through iconv.",
        ]);
    }
}
