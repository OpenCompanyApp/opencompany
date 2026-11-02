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
 * `vfs_write`: whole-content create / overwrite / append.
 *
 * Owns the mode contract; the write itself (path eligibility, vfs_write
 * policy and approvals, atomic version-checked write, audit) is
 * OpenCompanyVfs::write().
 *
 * - create: fails if the object exists (facade rule).
 * - overwrite: an existing object requires `version` (from vfs_read), so an
 *   agent can never blindly replace content it has not read ("human edits
 *   win" is enforced by the facade's atomic version check, not by prompts).
 *   A missing target is simply created.
 * - append: content read + facade overwrite anchored on the version of the
 *   bytes it read, so a concurrent writer causes `conflict`, never a lost
 *   update. A missing target is created.
 *
 * The raw request array is forwarded as the tool parameters, so an approval
 * created by policy replays this exact call (mode included) through vfs_write,
 * and an `idempotency_key` reaches the write path unchanged.
 */
class VfsWrite implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_write';

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
            if ($path === '') {
                throw VfsError::invalid('path is required.', ['field' => 'path']);
            }
            if (! is_string($request['content'] ?? null)) {
                throw VfsError::invalid('content is required text.', ['field' => 'content', 'path' => $path]);
            }
            $content = (string) $request['content'];
            $mode = $input->choice('mode');
            $version = $input->optionalString('version');
            $parameters = $request->toArray();

            $result = match ($mode) {
                'append' => $this->append($path, $content, $version, $parameters),
                'overwrite' => $this->overwrite($path, $content, $version, $parameters),
                default => $this->vfs->write($this->agent, $path, $content, 'create', $version, $parameters),
            };
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
                ['mode' => (string) ($request['mode'] ?? 'create')],
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function overwrite(string $path, string $content, ?string $version, array $parameters): array
    {
        if ($version === null && $this->exists($path)) {
            throw VfsError::invalid("{$this->vfs->normalizePath($path)} exists; overwriting it requires its version.", [
                'field' => 'version',
                'path' => $this->vfs->normalizePath($path),
                'hint' => 'Read it first with vfs_read (limit: 1 is enough) and pass the returned version, or use vfs_edit for targeted changes.',
            ]);
        }

        return $this->vfs->write($this->agent, $path, $content, 'overwrite', $version, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function append(string $path, string $content, ?string $version, array $parameters): array
    {
        try {
            $current = $this->vfs->readContentWithVersion($this->agent, $path);
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }

            return $this->vfs->write($this->agent, $path, $content, 'create', null, $parameters);
        }

        // A caller version is checked strictly; otherwise anchor on the
        // version of the bytes just read so concurrent appends conflict
        // instead of silently dropping one writer's data.
        return $this->vfs->write($this->agent, $path, $current['content'].$content, 'overwrite', $version ?? $current['version'], $parameters);
    }

    private function exists(string $path): bool
    {
        try {
            $this->vfs->stat($this->agent, $path);

            return true;
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return false;
            }

            throw $e;
        }
    }
}
