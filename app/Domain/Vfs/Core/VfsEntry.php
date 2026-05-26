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
