<?php

namespace App\Domain\Vfs\Core;

/**
 * Lightweight virtual directory entry returned by adapters.
 *
 * It intentionally carries only display-safe metadata. Callers that need the
 * full model must go through path resolution again so permission checks cannot
 * be bypassed by stashing backend IDs from a broad listing.
 */
class VfsEntry implements \JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $type,
        public readonly ?string $canonicalPath = null,
        public readonly ?string $backendType = null,
        public readonly ?string $backendId = null,
        public readonly ?string $version = null,
        public readonly array $capabilities = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * True for an alias entry (metadata `alias: true`) whose contents are
     * also reachable under their friendly paths: the `/me` mount in `/`, and
     * the flat `by-id` directories of `/docs`, `/files` and `/agents`.
     *
     * Contract for every recursive walker (find, grep -r / rg, du, diff -r,
     * vfs_rg/search, Code Mode find): never descend into an alias entry met
     * while walking, so walks do not visit objects twice (or print by-id
     * forms first). Walks that START at an alias path (`find /me`,
     * `ls -R /docs/by-id`) are unaffected: only the parent listing marks the
     * alias. Display walkers (tree, ls -R) still print the alias name but do
     * not descend into it.
     */
    public function isAlias(): bool
    {
        return ($this->metadata['alias'] ?? false) === true;
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'name' => $this->name,
            'path' => $this->path,
            'type' => $this->type,
            'canonical_path' => $this->canonicalPath,
            'backend_type' => $this->backendType,
            'backend_id' => $this->backendId,
            'version' => $this->version,
            'capabilities' => $this->capabilities,
            'metadata' => $this->metadata,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
