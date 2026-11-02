<?php

namespace Tests\Feature\Code;

use App\Services\MrubySandboxService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Profile errors that GLM hit repeatedly in the live Code Mode evals must come
 * back with a specific, actionable repair hint (the model's next attempt is
 * driven by this text), not the generic "check the source location".
 */
class MrubyRepairHintTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function cases(): array
    {
        return [
            'record each' => ['r = JSON.parse(%q({"a":1})); r.each { |k, v| k }', 'record.keys.each'],
            'symbol to proc' => ['[1, 2].map(&:to_s)', 'explicit block'],
            'blockless enumerator' => ['[1, 2, 3].each_slice(2).to_a', 'pass one directly'],
            'regexp' => ['"a1".scan(/\d/)', 'include?'],
            'non json puts' => ['puts(Integer); 1', '.to_s'],
        ];
    }

    #[DataProvider('cases')]
    public function test_profile_errors_get_targeted_repair_hints(string $code, string $hint): void
    {
        if (! is_executable((string) config('code.engine_binary'))) {
            $this->markTestSkipped('Requires the pinned Ruby engine (RUBY_ENGINE_BINARY).');
        }

        $result = app(MrubySandboxService::class)->execute($code, 'console');

        $this->assertNotNull($result->error);
        $this->assertStringContainsString($hint, (string) $result->error['suggestion']);
    }
}
