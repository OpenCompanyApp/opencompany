<?php

namespace Tests\Feature\Services\Memory;

use App\Models\AppSetting;
use App\Services\Memory\ModelContextRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelContextRegistryTest extends TestCase
{
    use RefreshDatabase;

    private ModelContextRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ModelContextRegistry::class);
    }

    public function test_exact_match_returns_context_window(): void
    {
        // gpt-4o is in config with 128_000
        $result = $this->registry->getContextWindow('gpt-4o');

        $this->assertEquals(128_000, $result);
    }

    public function test_prefix_match_returns_longest(): void
    {
        // 'gpt-4o-mini-2024-07-18' starts with 'gpt-4o-mini' (128K) and also 'gpt-4o' (128K)
        // Longest prefix 'gpt-4o-mini' should win
        $result = $this->registry->getContextWindow('gpt-4o-mini-2024-07-18');

        $this->assertEquals(128_000, $result);
    }

    public function test_levenshtein_match_within_threshold(): void
    {
        // 'claude-sonnt-4' is a typo for 'claude-sonnet-4' (distance 2)
        $result = $this->registry->getContextWindow('claude-sonnt-4');

        $this->assertEquals(200_000, $result);
    }

    public function test_unknown_model_returns_default(): void
    {
        $result = $this->registry->getContextWindow('completely-unknown-xyz-model-999');

        $this->assertEquals(32_000, $result);
    }

    public function test_user_override_takes_precedence(): void
    {
        AppSetting::setValue('model_context_windows', ['my-custom-model' => 64_000], 'memory');

        $result = $this->registry->getContextWindow('my-custom-model');

        $this->assertEquals(64_000, $result);
    }

    public function test_levenshtein_rejects_distance_above_five(): void
    {
        // A model name that differs by more than 5 from all known models
        $result = $this->registry->getContextWindow('zzzzzzzzzzzzzzzzzzzzzzzzzzzzz');

        $this->assertEquals(32_000, $result); // Falls through to default
    }

    public function test_prefix_match_prefers_longer_prefix(): void
    {
        // 'gpt-4-turbo-2024-04-09' should match 'gpt-4-turbo' (128K)
        // not 'gpt-4' (8K) — longer prefix wins
        $result = $this->registry->getContextWindow('gpt-4-turbo-2024-04-09');

        $this->assertEquals(128_000, $result);
    }
}
