<?php

use App\Models\IntegrationSetting;
use App\Models\McpServer;
use App\Models\User;
use Illuminate\Support\Str;

it('shows installed integrations without duplicate suggested MCP servers', function () {
    $user = User::factory()->create();
    $workspace = app('currentWorkspace');

    $this->actingAs($user);

    foreach ([
        ['DeepWiki', 'deepwiki', 'https://mcp.deepwiki.com/mcp'],
        ['Context7', 'context7', 'https://context7.liam.sh/mcp'],
        ['Cloudflare Docs', 'cloudflare_docs', 'https://docs.mcp.cloudflare.com/mcp'],
        ['Exa Search', 'exa_search', 'https://mcp.exa.ai/mcp'],
    ] as [$name, $slug, $url]) {
        McpServer::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspace->id,
            'name' => $name,
            'slug' => $slug,
            'url' => $url,
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
            'discovered_tools' => [['name' => 'search', 'description' => 'Search']],
        ]);
    }

    $page = $this->visit('/w/test/integrations')
        ->wait(0.5)
        ->assertSee('Connected Services')
        ->assertSee('DeepWiki')
        ->assertSee('Context7')
        ->assertSee('Add MCP Server');

    $counts = $page->script(<<<'JS'
        () => {
            const cards = [...document.querySelectorAll('[data-test="integration-card"]')].map(card => card.innerText);
            return Object.fromEntries(['DeepWiki', 'Context7', 'Cloudflare Docs', 'Exa Search'].map(name => [
                name,
                cards.filter(text => text.includes(name)).length,
            ]));
        }
    JS);

    expect($counts)->toBe([
        'DeepWiki' => 1,
        'Context7' => 1,
        'Cloudflare Docs' => 1,
        'Exa Search' => 1,
    ]);
});

it('configures an integration, supports search links, and opens safe setup modals', function () {
    $user = User::factory()->create();
    $workspace = app('currentWorkspace');

    $this->actingAs($user);

    IntegrationSetting::create([
        'id' => Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'integration_id' => 'gmail',
        'account_alias' => '',
        'config' => [
            'client_id' => 'browser-client-id',
            'client_secret' => 'browser-client-secret',
            'access_token' => 'browser-access-token',
        ],
        'enabled' => true,
        'is_default' => true,
    ]);

    $page = $this->visit('/w/test/integrations')
        ->wait(0.5)
        ->assertSee('Connected Services')
        ->assertSee('Gmail');

    $page
        ->click('[data-test="integration-card"][data-integration-id="gmail"] [data-test="integration-configure"]')
        ->assertSee('Configure Gmail')
        ->assertSee('Integration Account')
        ->assertSee('OAuth app configuration')
        ->assertSee('Connected')
        ->assertSee('Disconnect')
        ->click('Close');

    $page
        ->click('Add MCP Server')
        ->assertSee('Server URL')
        ->assertSee('Test Connection')
        ->assertSee('Add Server')
        ->click('Close');

    $page
        ->click('AI Gateway')
        ->assertSee('Status')
        ->assertSee('Endpoint')
        ->assertSee('/api/ai-gateway/v1')
        ->click('Close');

    $page->script(<<<'JS'
        () => {
            const input = [...document.querySelectorAll('[data-test="integration-search"]')]
                .find(element => element.offsetParent !== null);
            input.value = 'aircall';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    JS);

    $page
        ->wait(0.25)
        ->assertSee('Search results for "aircall"')
        ->assertSee('Aircall');

    expect(str_contains((string) parse_url($page->url(), PHP_URL_QUERY), 'q=aircall'))->toBeTrue();

    $page
        ->click('[data-test="integration-card"][data-integration-id="aircall"] [data-test="integration-card-action"]')
        ->assertSee('Configure Aircall')
        ->assertSee('API ID');

    $page->script(<<<'JS'
        () => {
            for (const [selector, value] of [
                ['[data-test="integration-field-api_id"]', 'browser-api-id'],
                ['[data-test="integration-field-api_token"]', 'browser-api-token'],
            ]) {
                const input = document.querySelector(selector);
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    JS);

    $page
        ->click('[data-test="save-integration-config"]')
        ->wait(0.5)
        ->assertSee('Search results for "aircall"')
        ->assertSee('Installed')
        ->assertNoJavaScriptErrors();

    $setting = IntegrationSetting::forWorkspace()
        ->where('integration_id', 'aircall')
        ->where('account_alias', '')
        ->first();

    expect($setting)->not->toBeNull()
        ->and($setting->enabled)->toBeTrue()
        ->and($setting->getConfigValue('api_id'))->toBe('browser-api-id')
        ->and($setting->getConfigValue('api_token'))->toBe('browser-api-token');
});
