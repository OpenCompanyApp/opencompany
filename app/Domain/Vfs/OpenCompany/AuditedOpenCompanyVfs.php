<?php

namespace App\Domain\Vfs\OpenCompany;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsPage;
use App\Domain\Vfs\Core\VfsPath;
use App\Domain\Vfs\Core\VfsReadPage;
use App\Domain\Vfs\Events\VfsAuditor;
use App\Domain\Vfs\Events\VfsOperationContext;
use App\Models\User;

/**
 * The OpenCompanyVfs the container hands out: the same facade with every
 * public read and mutation routed through VfsAuditor.
 *
 * Why a subclass bound in AppServiceProvider instead of calls inside the
 * mounts: the audit pipeline must sit outside the mounts so a new mount or
 * mutation path cannot forget it (audit recommendation 25), and every
 * surface — vfs_* tools, Code Mode helpers (cp/mv/rm/mkdir), the shell,
 * approval replay, future bridges — reaches the VFS through this class.
 *
 * Contract for other streams:
 * - Add a new public facade method that reads or mutates company data?
 *   Override it here (read() or write() wrapper). Signatures forward the
 *   trailing arguments variadically so additions to the parent's optional
 *   parameters (including named arguments such as permissionAlreadyChecked)
 *   do not need changes here.
 * - stat() is intentionally not audited (metadata only, and it is called
 *   internally by every write).
 * - Never instantiate OpenCompanyVfs directly in application code; resolve
 *   it from the container so audit applies.
 */
class AuditedOpenCompanyVfs extends OpenCompanyVfs
{
    private ?VfsAuditor $auditor = null;

    public function list(User $agent, string $path, ?VfsBudget $budget = null): array
    {
        return $this->audit()->read($agent, 'list', $path, fn (): array => parent::list($agent, $path, $budget), VfsPath::normalize($path));
    }

    public function listPage(User $agent, string $path, ?VfsBudget $budget = null, ?string $cursor = null): VfsPage
    {
        return $this->audit()->read($agent, 'list', $path, fn (): VfsPage => parent::listPage($agent, $path, $budget, $cursor), VfsPath::normalize($path));
    }

    public function listBounded(User $agent, string $path, VfsBudget $budget): array
    {
        return $this->audit()->read($agent, 'list', $path, fn (): array => parent::listBounded($agent, $path, $budget), VfsPath::normalize($path));
    }

    public function readPage(User $agent, string $path, ?VfsBudget $budget = null, ?string $cursor = null): VfsReadPage
    {
        return $this->audit()->read($agent, 'read', $path, fn (): VfsReadPage => parent::readPage($agent, $path, $budget, $cursor), VfsPath::normalize($path));
    }

    public function read(User $agent, string $path, ?VfsBudget $budget = null): string
    {
        return $this->audit()->read($agent, 'read', $path, fn (): string => parent::read($agent, $path, $budget), VfsPath::normalize($path));
    }

    public function readContentWithVersion(User $agent, string $path): array
    {
        return $this->audit()->read($agent, 'read', $path, fn (): array => parent::readContentWithVersion($agent, $path), VfsPath::normalize($path));
    }

    public function searchWithMetadata(User $agent, string $pattern, array $paths, ?VfsBudget $budget = null, bool $regex = false, bool $caseInsensitive = false): array
    {
        $subject = implode(',', array_map(fn (string $path): string => VfsPath::normalize($path), $paths === [] ? ['/'] : $paths));

        return $this->audit()->read($agent, 'search', $subject, fn (): array => parent::searchWithMetadata($agent, $pattern, $paths, $budget, $regex, $caseInsensitive));
    }

    public function write(User $agent, string $path, mixed ...$arguments): array
    {
        $content = $arguments[0] ?? $arguments['content'] ?? null;
        $toolParameters = $arguments[3] ?? $arguments['toolParameters'] ?? null;

        return $this->audit()->write(
            $agent,
            'write',
            VfsPath::normalize($path),
            null,
            fn (): array => parent::write($agent, $path, ...$arguments),
            $this->statQuietly($agent),
            is_array($toolParameters) ? $toolParameters : null,
            is_string($content) ? strlen($content) : null,
        );
    }

    public function patch(User $agent, string $path, mixed ...$arguments): array
    {
        $toolParameters = $arguments[2] ?? $arguments['toolParameters'] ?? null;

        return $this->audit()->write(
            $agent,
            'patch',
            VfsPath::normalize($path),
            null,
            fn (): array => parent::patch($agent, $path, ...$arguments),
            $this->statQuietly($agent),
            is_array($toolParameters) ? $toolParameters : null,
        );
    }

    public function makeDirectory(User $agent, string $path, mixed ...$arguments): array
    {
        return $this->audit()->write($agent, 'mkdir', VfsPath::normalize($path), null, fn (): array => parent::makeDirectory($agent, $path, ...$arguments), $this->statQuietly($agent));
    }

    public function touch(User $agent, string $path, mixed ...$arguments): array
    {
        return $this->audit()->write($agent, 'touch', VfsPath::normalize($path), null, fn (): array => parent::touch($agent, $path, ...$arguments), $this->statQuietly($agent));
    }

    public function remove(User $agent, string $path, mixed ...$arguments): array
    {
        return $this->audit()->write($agent, 'remove', VfsPath::normalize($path), null, fn (): array => parent::remove($agent, $path, ...$arguments), $this->statQuietly($agent));
    }

    public function copy(User $agent, string $source, string $destination, mixed ...$arguments): array
    {
        return $this->audit()->write($agent, 'copy', VfsPath::normalize($source), VfsPath::normalize($destination), fn (): array => parent::copy($agent, $source, $destination, ...$arguments), $this->statQuietly($agent));
    }

    public function move(User $agent, string $source, string $destination, mixed ...$arguments): array
    {
        return $this->audit()->write($agent, 'move', VfsPath::normalize($source), VfsPath::normalize($destination), fn (): array => parent::move($agent, $source, $destination, ...$arguments), $this->statQuietly($agent));
    }

    public function truncate(User $agent, string $path, mixed ...$arguments): array
    {
        return $this->audit()->write($agent, 'truncate', VfsPath::normalize($path), null, fn (): array => parent::truncate($agent, $path, ...$arguments), $this->statQuietly($agent));
    }

    /**
     * Stat used for before/after versions. Never audited, never throws.
     *
     * @return callable(string): (array<string, mixed>|null)
     */
    private function statQuietly(User $agent): callable
    {
        return function (string $path) use ($agent): ?array {
            try {
                return app(VfsOperationContext::class)->unaudited(fn (): array => $this->stat($agent, $path));
            } catch (\Throwable) {
                return null;
            }
        };
    }

    private function audit(): VfsAuditor
    {
        return $this->auditor ??= app(VfsAuditor::class);
    }
}
