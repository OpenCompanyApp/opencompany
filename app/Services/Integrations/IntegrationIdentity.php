<?php

namespace App\Services\Integrations;

/**
 * Normalizes IDs that travel between the integrations directory, browser UI,
 * and persistence layer.
 *
 * Directory cards need globally unique IDs because OpenCompany can expose the
 * same vendor through different ownership layers: an AI model provider, a
 * package-backed API integration, a chat adapter, or a workspace MCP server.
 * Database rows and package registries still use the raw provider slug, so this
 * class is the single place that strips or applies the UI-facing prefix.
 */
class IntegrationIdentity
{
    public const AI_PROVIDER = 'ai_provider';

    public const PACKAGE = 'integration';

    public const CHAT = 'chat';

    public const STATIC = 'static';

    public const MCP = 'mcp';

    public static function forAiProvider(string $id): string
    {
        return self::prefix(self::AI_PROVIDER, $id);
    }

    public static function forPackage(string $id): string
    {
        return self::prefix(self::PACKAGE, $id);
    }

    public static function forChat(string $id): string
    {
        return self::prefix(self::CHAT, $id);
    }

    public static function forStatic(string $id): string
    {
        return self::prefix(self::STATIC, $id);
    }

    public static function forMcp(string $slug): string
    {
        return self::prefix(self::MCP, $slug);
    }

    public static function rawId(string $id): string
    {
        $parts = self::parse($id);

        return $parts['raw'];
    }

    public static function entryType(string $id, ?string $fallback = null): string
    {
        $parts = self::parse($id);

        return $parts['type'] ?? $fallback ?? self::STATIC;
    }

    /**
     * @return array{type: ?string, raw: string}
     */
    public static function parse(string $id): array
    {
        if (! str_contains($id, ':')) {
            return ['type' => null, 'raw' => $id];
        }

        [$type, $raw] = explode(':', $id, 2);
        if (! in_array($type, [self::AI_PROVIDER, self::PACKAGE, self::CHAT, self::STATIC, self::MCP], true) || $raw === '') {
            return ['type' => null, 'raw' => $id];
        }

        return ['type' => $type, 'raw' => $raw];
    }

    private static function prefix(string $type, string $id): string
    {
        return "{$type}:{$id}";
    }
}
