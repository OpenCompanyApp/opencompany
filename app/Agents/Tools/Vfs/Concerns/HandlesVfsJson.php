<?php

namespace App\Agents\Tools\Vfs\Concerns;

use App\Agents\Tools\Vfs\Support\VfsToolEnvelope;
use App\Agents\Tools\Vfs\Support\VfsToolInput;
use App\Domain\Vfs\Spec\VfsOperationSpec;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Domain\Vfs\Spec\VfsToolSchemaFactory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;

/**
 * Shared surface of every `vfs_*` tool class.
 *
 * A using class declares `public const TOOL_SLUG`. Its description() and
 * schema() then come from VfsOperationSpecRegistry (single source: the
 * provider meta, Code Mode catalog, OpenAPI export and docs use the same
 * spec), and its results go through VfsToolEnvelope (compact JSON, one
 * error location, sanitized internal errors).
 *
 * Agent breadcrumb: do not add a hand-written description() or schema() to a
 * VFS tool; change the spec instead and run `php artisan vfs:spec --write-docs`.
 */
trait HandlesVfsJson
{
    public function description(): string
    {
        return $this->spec()->description;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return VfsToolSchemaFactory::build($schema, $this->spec());
    }

    private function spec(): VfsOperationSpec
    {
        return VfsOperationSpecRegistry::get(static::TOOL_SLUG);
    }

    private function input(Request $request): VfsToolInput
    {
        return new VfsToolInput($this->spec(), $request->toArray());
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function encodeResult(array $result): string
    {
        return VfsToolEnvelope::encode(VfsToolEnvelope::success($result));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function encodeError(string $code, string $message, array $context = []): string
    {
        return VfsToolEnvelope::encode(VfsToolEnvelope::error($code, $message, $context));
    }

    private function errorResponse(\Throwable $e): string
    {
        return VfsToolEnvelope::encode(VfsToolEnvelope::fromThrowable($e, static::TOOL_SLUG));
    }
}
