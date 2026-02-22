<?php

namespace App\Support;

class LuaMetaParser
{
    /**
     * Extract structured LuaExec metadata from a tool result string.
     *
     * The LuaExec tool prepends a `<!--__LUA_META__...-->` marker containing
     * JSON metadata (output, error, timing, bridge calls). This method extracts
     * that metadata and returns the clean human-readable result separately.
     *
     * @return array{meta: ?array<string, mixed>, result: mixed}
     */
    public static function extract(mixed $result): array
    {
        if (! is_string($result) || ! str_contains($result, '__LUA_META__')) {
            return ['meta' => null, 'result' => $result];
        }

        if (preg_match('/<!--__LUA_META__(.*?)__LUA_META__-->/s', $result, $m)) {
            $meta = json_decode($m[1], true);
            $result = trim(preg_replace('/<!--__LUA_META__.*?__LUA_META__-->/s', '', $result));

            return ['meta' => $meta, 'result' => $result];
        }

        return ['meta' => null, 'result' => $result];
    }
}
