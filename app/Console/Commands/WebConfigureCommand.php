<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\IntegrationSetting;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scriptable setup helper for web provider routing and optional credentials.
 *
 * Normal users should prefer the Integrations and Web Access settings screens.
 * This command exists for local development, deploy scripts, and parity with
 * Kosmo-style operator workflows.
 */
class WebConfigureCommand extends Command
{
    protected $signature = 'web:configure
        {--workspace= : Workspace id for settings and credentials}
        {--search-provider= : Default search provider}
        {--search-fallbacks= : Comma-separated search fallback providers}
        {--fetch-provider= : Default fetch provider}
        {--fetch-fallbacks= : Comma-separated fetch fallback providers}
        {--allow-external-fetch= : true/false}
        {--allowed-private-hosts= : Comma-separated private/reserved hosts direct fetch may access}
        {--allowed-domains= : Comma-separated allowed domains}
        {--blocked-domains= : Comma-separated blocked domains}
        {--cache-ttl= : Cache TTL in seconds}
        {--provider= : Provider integration id or short provider id for credential update}
        {--api-key= : API key to store for --provider}
        {--base-url= : Base URL to store for --provider}';

    protected $description = 'Configure workspace web search/fetch routing and optional provider credentials';

    public function handle(): int
    {
        $workspace = $this->bindWorkspace();
        if ($workspace === null) {
            $this->error('No workspace found. Pass --workspace= or create a workspace first.');

            return Command::FAILURE;
        }

        $changes = $this->settingsFromOptions();
        if ($changes !== []) {
            AppSetting::setMany($changes, 'web');
        }

        if (is_string($this->option('provider'))) {
            $this->upsertCredential($workspace, $this->normalizeIntegrationId($this->option('provider')));
        }

        $this->info('Web configuration updated.');

        return Command::SUCCESS;
    }

    private function bindWorkspace(): ?Workspace
    {
        $workspace = is_string($this->option('workspace'))
            ? Workspace::query()->find($this->option('workspace'))
            : Workspace::query()->first();

        if ($workspace !== null) {
            app()->instance('currentWorkspace', $workspace);
        }

        return $workspace;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFromOptions(): array
    {
        $settings = [];
        $this->stringOption($settings, 'search-provider', 'web_search_default_provider');
        $this->stringOption($settings, 'fetch-provider', 'web_fetch_default_provider');
        $this->intOption($settings, 'cache-ttl', 'web_cache_ttl_seconds');
        $this->listOption($settings, 'search-fallbacks', 'web_search_fallback_providers');
        $this->listOption($settings, 'fetch-fallbacks', 'web_fetch_fallback_providers');
        $this->listOption($settings, 'allowed-private-hosts', 'web_fetch_allowed_private_hosts');
        $this->listOption($settings, 'allowed-domains', 'web_allowed_domains');
        $this->listOption($settings, 'blocked-domains', 'web_blocked_domains');

        $allowExternal = $this->option('allow-external-fetch');
        if (is_string($allowExternal)) {
            $settings['web_fetch_allow_external'] = filter_var($allowExternal, FILTER_VALIDATE_BOOL);
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function stringOption(array &$settings, string $option, string $key): void
    {
        $value = $this->option($option);
        if (is_string($value) && trim($value) !== '') {
            $settings[$key] = trim($value);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function intOption(array &$settings, string $option, string $key): void
    {
        $value = $this->option($option);
        if (is_string($value) && trim($value) !== '') {
            $settings[$key] = max(0, (int) $value);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function listOption(array &$settings, string $option, string $key): void
    {
        $value = $this->option($option);
        if (is_string($value)) {
            $settings[$key] = array_values(array_filter(array_map('trim', explode(',', $value))));
        }
    }

    private function upsertCredential(Workspace $workspace, string $integrationId): void
    {
        $config = [];
        if (is_string($this->option('api-key')) && trim($this->option('api-key')) !== '') {
            $config['api_key'] = trim($this->option('api-key'));
        }
        if (is_string($this->option('base-url')) && trim($this->option('base-url')) !== '') {
            $config['base_url'] = rtrim(trim($this->option('base-url')), '/');
        }

        if ($config === []) {
            return;
        }

        IntegrationSetting::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'integration_id' => $integrationId, 'account_alias' => ''],
            [
                'id' => IntegrationSetting::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('integration_id', $integrationId)
                    ->where('account_alias', '')
                    ->value('id') ?? Str::uuid()->toString(),
                'config' => $config,
                'enabled' => true,
                'is_default' => true,
            ],
        );
    }

    private function normalizeIntegrationId(string $provider): string
    {
        $provider = str_replace('-', '_', strtolower(trim($provider)));

        return str_starts_with($provider, 'web.') ? $provider : "web.{$provider}";
    }
}
