<?php

namespace App\Services\Memory;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores oversized tool outputs and returns a bounded preview.
 *
 * This service protects model context and database records from very large
 * strings while preserving the full result in storage for later inspection.
 */
class OutputTruncator
{
    public function __construct(
        private ?int $maxLines = null,
        private ?int $maxBytes = null,
        private ?string $disk = null,
        private ?string $pathPrefix = null,
    ) {
        $this->maxLines ??= (int) config('memory.tool_results.max_lines', 2000);
        $this->maxBytes ??= (int) config('memory.tool_results.max_bytes', 50_000);
        $this->disk ??= (string) config('memory.tool_results.disk', 'local');
        $this->pathPrefix ??= trim((string) config('memory.tool_results.path', 'agent-tool-results'), '/');
    }

    public function truncate(mixed $result, string $toolCallId): mixed
    {
        if (! is_string($result)) {
            return $result;
        }

        $lines = substr_count($result, "\n") + 1;
        $bytes = strlen($result);

        if ($lines <= $this->maxLines && $bytes <= $this->maxBytes) {
            return $result;
        }

        // Store first so even byte-trimmed UTF-8 output has a complete original
        // artifact available to humans or debugging tools.
        $storagePath = $this->storeFullOutput($result, $toolCallId);
        $truncated = $result;

        if ($lines > $this->maxLines) {
            $truncated = implode("\n", array_slice(explode("\n", $truncated), 0, $this->maxLines));
        }

        if (strlen($truncated) > $this->maxBytes) {
            $truncated = mb_strcut($truncated, 0, $this->maxBytes, 'UTF-8');
        }

        return $truncated."\n\n[truncated - full output stored at storage:{$storagePath}]";
    }

    private function storeFullOutput(string $result, string $toolCallId): string
    {
        $datePath = now()->format('Y/m/d');
        // Tool call IDs may come from providers and are not guaranteed to be
        // filesystem-safe. Keep enough entropy while removing path characters.
        $safeId = trim(preg_replace('/[^a-zA-Z0-9_-]/', '_', $toolCallId) ?? '', '_');
        $safeId = $safeId !== '' ? $safeId : Str::random(12);
        $path = "{$this->pathPrefix}/{$datePath}/tool_{$safeId}.txt";

        Storage::disk($this->disk)->put($path, $result);

        return $path;
    }
}
