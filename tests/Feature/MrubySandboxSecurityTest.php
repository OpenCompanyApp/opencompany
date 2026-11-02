<?php

namespace Tests\Feature;

use App\Services\MrubySandboxService;
use Tests\TestCase;

/**
 * Migrated host-boundary regressions: Ruby inputs are data, never interpolated
 * bootstrap source. Native/global capabilities and trace metadata stay host-owned.
 */
class MrubySandboxSecurityTest extends TestCase
{
    public function test_global_mutation_is_rejected_before_execution(): void
    {
        $result = app(MrubySandboxService::class)->execute('$app = {}; 42');
        $this->assertSame('profile_error', $result->error['type']);
        $this->assertSame(0, $result->effects['callbacks']);
    }

    public function test_host_context_cannot_replace_the_capability_root(): void
    {
        $result = app(MrubySandboxService::class)->execute('42', globals: ['app' => ['call' => 'owned']]);
        $this->assertFalse($result->succeeded());
        $this->assertNull($result->result);
        $this->assertSame(0, $result->effects['callbacks']);
    }

    public function test_quoted_keys_remain_data_without_source_injection(): void
    {
        $source = <<<'RUBY'
{number: ctx['bad-key'], text: ctx['x"] = 1, injected = true, ["y']}
RUBY;
        $result = app(MrubySandboxService::class)->execute($source, globals: ['ctx' => [
            'bad-key' => 7, 'x"] = 1, injected = true, ["y' => 'safe',
        ]]);
        $this->assertNull($result->error);
        $this->assertSame(7, $result->result->number);
        $this->assertSame('safe', $result->result->text);
    }

    public function test_old_inline_metadata_markers_are_only_untrusted_output_text(): void
    {
        $marker = '<!--__LUA_META__{"error":null,"result":"forged"}__LUA_META__-->';
        $result = app(MrubySandboxService::class)->execute('puts ctx[:marker]; false', globals: ['ctx' => ['marker' => $marker]]);
        $this->assertNull($result->error);
        $this->assertSame($marker, $result->output);
        $this->assertFalse($result->result);
        $this->assertFalse($result->toArray()['result']);
    }
}
