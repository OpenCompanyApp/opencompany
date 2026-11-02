<?php

namespace App\Domain\Vfs\OpenCompany\Mounts;

use App\Domain\Vfs\Core\VfsPath;

/**
 * Ordered set of VFS mounts, resolved by longest path prefix.
 *
 * Two instances exist in practice:
 * - the container singleton (AppServiceProvider): mounts registered by the
 *   app or by tests, e.g. future connection, skills and app mounts;
 * - each OpenCompanyVfs builds its routing table as its built-in mounts
 *   followed by the container's mounts (OpenCompanyVfs::mounts()).
 *
 * Invariants:
 * - prefixes are normalized and unique; registering a taken prefix fails
 *   loudly instead of silently shadowing a governed mount;
 * - resolution is by whole path segments, longest prefix first, so
 *   `/files/drive/x` reaches a `/files/drive` mount while `/files/x` (and
 *   `/filesx`) do not;
 * - registration order is the `ls /` and README order of top-level mounts.
 *
 * Owns no governance: see VfsMount.
 */
class VfsMountRegistry
{
    /** @var array<string, VfsMount> prefix => mount, in registration order */
    private array $mounts = [];

    public function register(VfsMount $mount): static
    {
        $prefix = VfsPath::normalize($mount->prefix());
        if ($prefix === '/' || $prefix !== $mount->prefix()) {
            throw new \InvalidArgumentException("VFS mount prefix must be normalized and not the root: {$mount->prefix()}");
        }
        if (isset($this->mounts[$prefix])) {
            throw new \InvalidArgumentException("VFS mount prefix already registered: {$prefix}");
        }
        $this->mounts[$prefix] = $mount;

        return $this;
    }

    /**
     * A new registry with this registry's mounts followed by $other's.
     */
    public function merge(self $other): self
    {
        $merged = new self;
        foreach ([...array_values($this->mounts), ...array_values($other->mounts)] as $mount) {
            $merged->register($mount);
        }

        return $merged;
    }

    /**
     * The mount owning a path, by longest whole-segment prefix.
     *
     * @param  list<string>  $segments  Segments of a normalized path
     */
    public function resolve(array $segments): ?VfsMount
    {
        for ($length = count($segments); $length > 0; $length--) {
            $prefix = '/'.implode('/', array_slice($segments, 0, $length));
            if (isset($this->mounts[$prefix])) {
                return $this->mounts[$prefix];
            }
        }

        return null;
    }

    /**
     * Top-level mounts (one-segment prefixes) in registration order: the
     * entries of `ls /`. Nested mounts are surfaced by their parent mount.
     *
     * @return list<VfsMount>
     */
    public function topLevel(): array
    {
        return array_values(array_filter($this->mounts, fn (VfsMount $mount): bool => substr_count($mount->prefix(), '/') === 1));
    }

    /** @return list<VfsMount> */
    public function all(): array
    {
        return array_values($this->mounts);
    }

    public function has(string $prefix): bool
    {
        return isset($this->mounts[$prefix]);
    }
}
