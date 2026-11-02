<?php

namespace App\Agents\Tools\Vfs\Support;

use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Support\Str;

/**
 * Keeps model-facing VFS tool results under a byte cap without losing data.
 *
 * When an encoded result exceeds config('vfs.tool_result_max_bytes'), the
 * full payload text (vfs_exec stdout, a read's content, or the whole JSON
 * result) is written to the agent's results folder (VfsAgentHome) and the
 * model gets a preview, `offloaded_path`, `offloaded_bytes` and a hint to
 * page it with vfs_read. This is restorable compression: nothing is dropped.
 *
 * Governance invariants:
 * - The file is written through OpenCompanyVfs::write(), the same governed
 *   and audited path an agent's own vfs_write uses. No side channel.
 * - Offloading must never create an approval request on the agent's behalf:
 *   if vfs_write is denied or approval-gated for this agent, or the write
 *   fails for any reason, the result is cut to the preview with a hint
 *   instead (truthful: `truncated: true`, no path).
 *
 * Not owned: the shape of the payload (VfsToolEnvelope / VfsExec), which
 * calls are model-facing (VfsOffloadingTool decides that).
 */
class VfsResultOffloader
{
    public function __construct(
        private readonly OpenCompanyVfs $vfs,
        private readonly AgentPermissionService $permissions,
    ) {}

    /**
     * Return $encoded unchanged when it fits; otherwise the offloaded (or
     * cut) envelope, re-encoded.
     */
    public function apply(User $agent, string $toolSlug, string $encoded): string
    {
        $cap = max(1_000, (int) config('vfs.tool_result_max_bytes', 100_000));
        if (strlen($encoded) <= $cap) {
            return $encoded;
        }

        $payload = json_decode($encoded, true);
        if (! is_array($payload)) {
            return $encoded;
        }

        [$field, $full] = $this->dominantText($payload, $encoded);
        $previewBytes = max(200, min((int) config('vfs.tool_result_preview_bytes', 8_000), intdiv($cap, 2)));
        $preview = $this->preview($full, $previewBytes);
        $path = $this->offload($agent, $toolSlug, $full, $field === null ? 'json' : 'txt');

        $note = $path !== null
            ? [
                'offloaded_path' => $path,
                'offloaded_bytes' => strlen($full),
                'truncated' => true,
                'hint' => 'Output was '.strlen($full)." bytes; the first {$previewBytes} are shown. The full output is saved at {$path}: read it with vfs_read (offset/limit) or search it with vfs_exec rg.",
            ]
            : [
                'truncated' => true,
                'output_bytes' => strlen($full),
                'hint' => 'Output was '.strlen($full)." bytes and was cut to the first {$previewBytes}. Narrow the path or command, or page with cursor/offset.",
            ];

        return VfsToolEnvelope::encode($this->replace($payload, $field, $preview, $note));
    }

    /**
     * The text to offload: stdout (exec), result.content (reads) or, when no
     * single field dominates, the whole JSON result.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: 'stdout'|'content'|null, 1: string}
     */
    private function dominantText(array $payload, string $encoded): array
    {
        if (is_string($payload['stdout'] ?? null) && strlen($payload['stdout']) * 2 > strlen($encoded)) {
            return ['stdout', $payload['stdout']];
        }
        if (is_string($payload['result']['content'] ?? null) && strlen($payload['result']['content']) * 2 > strlen($encoded)) {
            return ['content', $payload['result']['content']];
        }

        return [null, (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $note
     * @return array<string, mixed>
     */
    private function replace(array $payload, ?string $field, string $preview, array $note): array
    {
        if ($field === 'stdout') {
            return array_merge($payload, ['stdout' => $preview], $note);
        }
        if ($field === 'content') {
            $payload['result'] = array_merge($payload['result'], ['content' => $preview], $note);

            return $payload;
        }

        return array_filter([
            'ok' => $payload['ok'] ?? true,
            'error' => $payload['error'] ?? null,
            'preview' => $preview,
            ...$note,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Cut at the last newline inside the budget (falling back to a UTF-8 safe
     * byte cut) so the preview never ends mid-character.
     */
    private function preview(string $text, int $bytes): string
    {
        if (strlen($text) <= $bytes) {
            return $text;
        }

        $cut = mb_strcut($text, 0, $bytes, 'UTF-8');
        $newline = strrpos($cut, "\n");

        return $newline !== false && $newline > intdiv($bytes, 2) ? substr($cut, 0, $newline + 1) : $cut;
    }

    private function offload(User $agent, string $toolSlug, string $content, string $extension): ?string
    {
        // Invariant: same permission source VfsMutationPolicy consults, checked
        // first, so the write below can never create an approval request.
        $state = $this->permissions->resolveToolPermission($agent, 'vfs_write', 'write');
        if (! $state['allowed'] || $state['requires_approval']) {
            return null;
        }

        $path = VfsAgentHome::currentResultsFolder($agent).'/'.$toolSlug.'-'.Str::uuid().'.'.$extension;

        try {
            $result = $this->vfs->write($agent, $path, $content, 'create', null, [
                'path' => $path,
                'mode' => 'create',
                'purpose' => 'tool_result_offload',
            ]);
        } catch (\Throwable) {
            return null;
        }

        // The display path (not by-id) so the agent recognises its results
        // folder (`/me/results/...` aliases it).
        return (string) ($result['path'] ?? $path);
    }
}
