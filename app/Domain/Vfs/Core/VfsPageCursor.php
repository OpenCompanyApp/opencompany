<?php

namespace App\Domain\Vfs\Core;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encodes adapter-owned VFS pagination state into an opaque, bound cursor.
 *
 * Cursor payloads are encrypted and authenticated by Laravel's application
 * encrypter. Binding state to the normalized path, adapter, workspace, and
 * acting agent prevents a cursor from being replayed against another listing
 * scope. Adapters still validate their own state shape and always re-run
 * authorization; possession of a cursor never grants access.
 */
final class VfsPageCursor
{
    private const VERSION = 1;

    /**
     * @param  array<string, mixed>  $state
     */
    public static function encode(
        string $path,
        string $adapter,
        string $scope,
        array $state,
        int $seen,
    ): string {
        return Crypt::encryptString(json_encode([
            'version' => self::VERSION,
            'path' => $path,
            'adapter' => $adapter,
            'scope' => $scope,
            'state' => $state,
            'seen' => $seen,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{state: array<string, mixed>, seen: int}
     */
    public static function decode(?string $cursor, string $path, string $adapter, string $scope): array
    {
        if ($cursor === null || $cursor === '') {
            return ['state' => [], 'seen' => 0];
        }

        try {
            $payload = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw self::invalid();
        }

        if (
            ! is_array($payload)
            || ($payload['version'] ?? null) !== self::VERSION
            || ($payload['path'] ?? null) !== $path
            || ($payload['adapter'] ?? null) !== $adapter
            || ($payload['scope'] ?? null) !== $scope
            || ! is_array($payload['state'] ?? null)
            || ! is_int($payload['seen'] ?? null)
            || $payload['seen'] < 0
        ) {
            throw self::invalid();
        }

        /** @var array<string, mixed> $state */
        $state = $payload['state'];

        return ['state' => $state, 'seen' => $payload['seen']];
    }

    /**
     * Scope binding a cursor to one workspace and acting agent. Must stay
     * identical to OpenCompanyVfs's pagination scope so shell-owned cursors
     * (find) and mount cursors share the same replay protection.
     */
    public static function agentScope(\App\Models\User $agent): string
    {
        return hash('sha256', (string) $agent->workspace_id.'|'.(string) $agent->id);
    }

    private static function invalid(): VfsError
    {
        return VfsError::invalid('cursor is invalid, expired, or belongs to another listing.', [
            'field' => 'cursor',
        ]);
    }
}
