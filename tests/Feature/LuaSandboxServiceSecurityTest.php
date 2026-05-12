<?php

namespace Tests\Feature;

use App\Services\LuaSandboxService;
use Tests\TestCase;

/**
 * Guards Lua sandbox global injection and protected bridge names.
 */
class LuaSandboxServiceSecurityTest extends TestCase
{
    public function test_rejects_user_assignment_to_bridge_globals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lua code may not assign __-prefixed bridge globals.');

        app(LuaSandboxService::class)->execute('__json = { decode = function() return "owned" end }');
    }

    public function test_rejects_protected_injected_global_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Lua global name: __app');

        app(LuaSandboxService::class)->execute('return true', globals: ['__app' => ['call' => 'owned']]);
    }

    public function test_quotes_associative_array_keys_when_injecting_globals(): void
    {
        $result = app(LuaSandboxService::class)->execute(
            'print(ctx["bad-key"]); print(ctx["x\"] = 1, injected = true, [\"y"]); print(injected == nil)',
            globals: [
                'ctx' => [
                    'bad-key' => 7,
                    'x"] = 1, injected = true, ["y' => 'safe',
                ],
            ],
        );

        $this->assertNull($result->error);
        $this->assertSame("7\nsafe\ntrue", $result->output);
    }
}
