<?php

namespace App\Domain\Vfs\OpenCompany\Mounts;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Models\User;

/**
 * Adapter that exposes one of OpenCompanyVfs's built-in mount traits
 * (MountsVfsDocuments, MountsVfsFiles, …) as a VfsMount.
 *
 * Why closures instead of one class per mount: the built-in mounts are
 * traits over private facade state (permissions, file service, friendly-name
 * resolution, shared record helpers). Wrapping their existing methods keeps
 * behaviour byte-identical while routing moves to the registry; a later
 * extraction can replace one adapter at a time with a standalone class.
 * OpenCompanyVfs::builtinMounts() is the only place these are built.
 *
 * Built-in mounts govern their own mutations (governsMutations() true): the
 * record paths behind the closures call VfsMutationPolicy at their ordering
 * point. Workspace assertion, routing, idempotency and audit still run in the
 * facade around them. A missing closure means "operation not supported" and
 * throws the AbstractVfsMount default (not_writable), which is what the
 * pre-registry `match` default arms threw.
 */
final class BuiltinVfsMount extends AbstractVfsMount
{
    /**
     * @param  \Closure(User, list<string>, string, VfsBudget): list<VfsEntry>  $list
     * @param  \Closure(User, list<string>, string, VfsBudget): string  $read
     * @param  (\Closure(User, list<string>, string): ?VfsEntry)|null  $entry
     * @param  (\Closure(User, list<string>, string, VfsBudget, array<string, mixed>): array{entries: list<VfsEntry>, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int})|null  $page
     * @param  (\Closure(list<string>): ?string)|null  $readPageAdapter
     * @param  (\Closure(User, list<string>, string, VfsBudget, array<string, mixed>, string): array{content: string, returned: int, has_more: bool, next_state: array<string, mixed>, scanned: int, skipped: int, resumable?: bool})|null  $readPage
     * @param  (\Closure(User, list<string>, string, string, string, ?string, array<string, mixed>, bool): array<string, mixed>)|null  $write
     * @param  (\Closure(User, list<string>, string, string, ?string, array<string, mixed>): array<string, mixed>)|null  $patch
     * @param  (\Closure(User, list<string>, string, array<string, mixed>): array<string, mixed>)|null  $remove
     * @param  (\Closure(User, list<string>, string, string, array<string, mixed>): array<string, mixed>)|null  $move
     * @param  (\Closure(User, list<string>, string, array<string, mixed>): array<string, mixed>)|null  $makeDirectory
     */
    public function __construct(
        private readonly string $prefix,
        private readonly string $description,
        private readonly \Closure $list,
        private readonly \Closure $read,
        private readonly ?\Closure $entry = null,
        private readonly ?string $pageAdapter = null,
        private readonly ?\Closure $page = null,
        private readonly ?\Closure $readPageAdapter = null,
        private readonly ?\Closure $readPage = null,
        private readonly ?\Closure $write = null,
        private readonly ?\Closure $patch = null,
        private readonly ?\Closure $remove = null,
        private readonly ?\Closure $move = null,
        private readonly ?\Closure $makeDirectory = null,
        private readonly bool $alias = false,
    ) {}

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function isAlias(): bool
    {
        return $this->alias;
    }

    public function governsMutations(): bool
    {
        return true;
    }

    public function list(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return ($this->list)($agent, $segments, $path, $budget);
    }

    public function read(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        return ($this->read)($agent, $segments, $path, $budget);
    }

    public function entry(User $agent, array $segments, string $path): ?VfsEntry
    {
        return $this->entry !== null ? ($this->entry)($agent, $segments, $path) : null;
    }

    public function pageAdapter(array $segments): ?string
    {
        return $this->page !== null ? $this->pageAdapter : null;
    }

    public function page(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        return $this->page !== null ? ($this->page)($agent, $segments, $path, $budget, $state) : parent::page($agent, $segments, $path, $budget, $state);
    }

    public function readPageAdapter(array $segments): ?string
    {
        return $this->readPageAdapter !== null ? ($this->readPageAdapter)($segments) : null;
    }

    public function readPage(User $agent, array $segments, string $path, VfsBudget $budget, array $state, string $adapter): array
    {
        return $this->readPage !== null
            ? ($this->readPage)($agent, $segments, $path, $budget, $state, $adapter)
            : parent::readPage($agent, $segments, $path, $budget, $state, $adapter);
    }

    public function write(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters, bool $permissionAlreadyChecked): array
    {
        return $this->write !== null
            ? ($this->write)($agent, $segments, $path, $content, $mode, $version, $toolParameters, $permissionAlreadyChecked)
            : parent::write($agent, $segments, $path, $content, $mode, $version, $toolParameters, $permissionAlreadyChecked);
    }

    public function patch(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        return $this->patch !== null
            ? ($this->patch)($agent, $segments, $path, $patch, $version, $toolParameters)
            : parent::patch($agent, $segments, $path, $patch, $version, $toolParameters);
    }

    public function remove(User $agent, array $segments, string $path, array $toolParameters): array
    {
        return $this->remove !== null
            ? ($this->remove)($agent, $segments, $path, $toolParameters)
            : parent::remove($agent, $segments, $path, $toolParameters);
    }

    public function move(User $agent, array $segments, string $source, string $destination, array $toolParameters): array
    {
        return $this->move !== null
            ? ($this->move)($agent, $segments, $source, $destination, $toolParameters)
            : parent::move($agent, $segments, $source, $destination, $toolParameters);
    }

    public function makeDirectory(User $agent, array $segments, string $path, array $toolParameters): array
    {
        return $this->makeDirectory !== null
            ? ($this->makeDirectory)($agent, $segments, $path, $toolParameters)
            : parent::makeDirectory($agent, $segments, $path, $toolParameters);
    }
}
