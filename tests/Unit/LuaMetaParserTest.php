<?php

namespace Tests\Unit;

use App\Support\LuaMetaParser;
use PHPUnit\Framework\TestCase;

class LuaMetaParserTest extends TestCase
{
    public function test_extracts_valid_meta_and_strips_marker(): void
    {
        $meta = json_encode(['output' => 'hello', 'error' => null, 'executionTime' => 5.0]);
        $input = "<!--__LUA_META__{$meta}__LUA_META__-->\nOutput:\nhello\nExecution time: 5.0ms";

        $result = LuaMetaParser::extract($input);

        $this->assertNotNull($result['meta']);
        $this->assertEquals('hello', $result['meta']['output']);
        $this->assertNull($result['meta']['error']);
        $this->assertEquals(5.0, $result['meta']['executionTime']);
        $this->assertStringNotContainsString('__LUA_META__', $result['result']);
        $this->assertStringContainsString('hello', $result['result']);
    }

    public function test_returns_null_meta_for_non_lua_result(): void
    {
        $result = LuaMetaParser::extract('Query returned 5 rows');

        $this->assertNull($result['meta']);
        $this->assertEquals('Query returned 5 rows', $result['result']);
    }

    public function test_returns_null_meta_for_non_string_result(): void
    {
        $this->assertNull(LuaMetaParser::extract(null)['meta']);
        $this->assertNull(LuaMetaParser::extract(42)['meta']);
        $this->assertNull(LuaMetaParser::extract(['array'])['meta']);

        // Values are preserved unchanged
        $this->assertNull(LuaMetaParser::extract(null)['result']);
        $this->assertEquals(42, LuaMetaParser::extract(42)['result']);
        $this->assertEquals(['array'], LuaMetaParser::extract(['array'])['result']);
    }

    public function test_returns_null_meta_for_empty_string(): void
    {
        $result = LuaMetaParser::extract('');

        $this->assertNull($result['meta']);
        $this->assertEquals('', $result['result']);
    }

    public function test_handles_multiline_json_in_marker(): void
    {
        $meta = json_encode([
            'output' => "line1\nline2\nline3",
            'bridgeCalls' => [
                ['path' => 'chat.send', 'durationMs' => 10, 'status' => 'ok'],
            ],
        ], JSON_PRETTY_PRINT);

        $input = "<!--__LUA_META__{$meta}__LUA_META__-->\nHuman text";

        $result = LuaMetaParser::extract($input);

        $this->assertNotNull($result['meta']);
        $this->assertEquals("line1\nline2\nline3", $result['meta']['output']);
        $this->assertCount(1, $result['meta']['bridgeCalls']);
        $this->assertEquals('Human text', $result['result']);
    }

    public function test_handles_malformed_json_in_marker(): void
    {
        $input = "<!--__LUA_META__not-valid-json__LUA_META__-->\nHuman text";

        $result = LuaMetaParser::extract($input);

        // json_decode returns null for invalid JSON
        $this->assertNull($result['meta']);
        // Marker is still stripped
        $this->assertEquals('Human text', $result['result']);
    }

    public function test_handles_truncated_marker(): void
    {
        // Simulates what happens when mb_strcut truncates the closing tag
        $meta = json_encode(['output' => 'hello']);
        $input = "<!--__LUA_META__{$meta}__LUA_ME... [truncated]";

        $result = LuaMetaParser::extract($input);

        // The regex won't match since the closing tag is incomplete
        $this->assertNull($result['meta']);
        // Original string is preserved
        $this->assertEquals($input, $result['result']);
    }

    public function test_trims_whitespace_around_human_text(): void
    {
        $meta = json_encode(['output' => 'test']);
        $input = "<!--__LUA_META__{$meta}__LUA_META__-->\n\n  Output:\ntest  \n";

        $result = LuaMetaParser::extract($input);

        // Result is trimmed
        $this->assertStringNotContainsString('__LUA_META__', $result['result']);
        $this->assertFalse(str_starts_with($result['result'], "\n"));
        $this->assertFalse(str_ends_with($result['result'], "\n"));
    }
}
