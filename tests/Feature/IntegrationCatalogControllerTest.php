<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Integrations\ConfigSchemaNormalizer;
use App\Services\Integrations\IntegrationIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenCompany\IntegrationCore\Contracts\ConfigurableIntegration;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;
use Tests\TestCase;

/**
 * Covers generated integration catalog normalization and runtime state metadata.
 */
class IntegrationCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_endpoint_returns_generated_catalog_with_runtime_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/integrations/catalog?perPage=1');

        $response->assertOk()
            ->assertJsonStructure([
                'generatedAt',
                'totalIntegrations',
                'totalTools',
                'categories',
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'description',
                        'category',
                        'toolCount',
                        'catalog',
                        'packageInstalled',
                        'configured',
                        'configurable',
                    ],
                ],
                'meta' => ['page', 'perPage', 'total', 'hasMore'],
            ]);

        $response->assertJsonPath('available', true);
        $this->assertGreaterThan(500, $response->json('totalIntegrations'));
        $this->assertSame(1, $response->json('meta.perPage'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_catalog_endpoint_reports_unavailable_when_no_catalog_file_exists(): void
    {
        config([
            'integration_catalog.path' => base_path('missing-catalog.json'),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/integrations/catalog?perPage=1');

        $response->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('pathSource', null)
            ->assertJsonPath('totalIntegrations', 0)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_dynamic_config_endpoint_normalizes_package_schema_variants(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/integrations/klaviyo/config');

        $response->assertOk()
            ->assertJsonPath('configSchema.0.key', 'api_key')
            ->assertJsonPath('configSchema.0.type', 'text')
            ->assertJsonPath('configSchema.0.hint', 'Your Klaviyo private API key.');
    }

    public function test_dynamic_config_endpoint_normalizes_select_options_for_the_modal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/integrations/datadog/config');

        $response->assertOk()
            ->assertJsonPath('configSchema.2.key', 'site')
            ->assertJsonPath('configSchema.2.options.us', 'US (datadoghq.com)')
            ->assertJsonPath('configSchema.2.options.eu', 'EU (datadoghq.eu)');
    }

    public function test_integration_directory_uses_unique_card_identities_for_colliding_providers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/integrations');

        $response->assertOk();
        $entries = collect($response->json());
        $ids = $entries->pluck('id')->all();

        $this->assertCount(count(array_unique($ids)), $ids);
        $this->assertNotNull($entries->firstWhere('id', IntegrationIdentity::forAiProvider('openai')));
        $this->assertNotNull($entries->firstWhere('id', IntegrationIdentity::forPackage('openai')));
        $this->assertNotNull($entries->firstWhere('id', IntegrationIdentity::forChat('slack')));
        $this->assertNotNull($entries->firstWhere('id', IntegrationIdentity::forPackage('slack')));
    }

    public function test_ai_provider_config_endpoint_does_not_return_same_slug_package_schema(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/ai/providers/openai/config')
            ->assertOk()
            ->assertJsonPath('category', null)
            ->assertJsonPath('defaultUrl', 'https://api.openai.com/v1')
            ->assertJsonPath('config.apiKey', null);

        $this->actingAs($user)
            ->getJson('/api/integrations/openai/config')
            ->assertOk()
            ->assertJsonPath('configSchema.0.key', 'api_key');
    }

    public function test_all_configurable_provider_schemas_normalize_to_supported_modal_fields(): void
    {
        $supportedTypes = ['secret', 'url', 'text', 'select', 'string_list', 'oauth_connect'];
        $failures = [];

        foreach (app(ToolProviderRegistry::class)->all() as $provider) {
            if (! $provider instanceof ConfigurableIntegration) {
                continue;
            }

            foreach (ConfigSchemaNormalizer::normalize($provider->configSchema()) as $field) {
                if (empty($field['key']) || empty($field['label']) || empty($field['type'])) {
                    $failures[] = "{$provider->appName()} has an incomplete field schema";

                    continue;
                }

                if (! in_array($field['type'], $supportedTypes, true)) {
                    $failures[] = "{$provider->appName()}.{$field['key']} uses unsupported type {$field['type']}";
                }

                if ($field['type'] === 'select') {
                    foreach (($field['options'] ?? []) as $value => $label) {
                        if (! is_string($value) || ! is_string($label) || $value === '' || $label === '') {
                            $failures[] = "{$provider->appName()}.{$field['key']} has unsupported select options";
                        }
                    }
                }
            }
        }

        $this->assertSame([], $failures);
    }
}
