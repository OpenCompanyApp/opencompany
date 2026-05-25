<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSetting;
use App\Services\Ai\ModelConnectionTester;
use App\Services\Chat\ChatAdapterFactory;
use App\Services\Integrations\Data\IntegrationConnectionTestResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Runs connection checks for both legacy app integrations and package providers.
 *
 * The controller layer collects request data; this service owns the safety
 * contract for testing it. In particular, masked secrets must be resolved from
 * stored settings, no-key integrations should use safe read-only probes, and
 * credentialed package integrations must not pretend to be fully live-tested
 * without usable credentials.
 */
class IntegrationConnectionTester
{
    public function __construct(
        private IntegrationConfigResolver $configResolver,
        private IntegrationAccountResolver $accounts,
        private ModelConnectionTester $models,
    ) {}

    public function test(Request $request, string $id): IntegrationConnectionTestResult
    {
        $id = IntegrationIdentity::rawId($id);

        if ($id === 'telegram') {
            return $this->testAppOwnedTelegramChat($request);
        }

        $provider = $this->configResolver->findConfigurableProvider($id);
        if ($provider) {
            $config = $request->all();
            $setting = $this->accounts->findSetting($id, $this->accounts->accountFromRequest($request));

            // The UI sends masked values for existing secrets. Replace only
            // masked/blank secret fields from persisted config so connection
            // tests can run without exposing raw tokens back to the browser.
            foreach (ConfigSchemaNormalizer::normalize($provider->configSchema()) as $field) {
                if ($field['type'] === 'secret' || $field['type'] === 'oauth_connect') {
                    $key = $field['key'];
                    $value = $config[$key] ?? '';
                    if (! $value || str_contains($value, '*')) {
                        $config[$key] = $setting?->getConfigValue($key);
                    }
                }
            }

            try {
                $result = $provider->testConnection($config);

                return new IntegrationConnectionTestResult(
                    success: $result['success'],
                    message: $result['message'] ?? null,
                    error: $result['error'] ?? null,
                    status: $result['success'] ? 200 : 400,
                    meta: array_diff_key($result, array_flip(['success', 'message', 'error'])),
                );
            } catch (\Throwable $e) {
                return new IntegrationConnectionTestResult(false, error: $e->getMessage(), status: 500);
            }
        }

        // Package integrations are owned by ../integrations. OpenCompany only
        // decides how to prove they are registered/configured for this runtime.
        $packageProvider = app(ToolProviderRegistry::class)->get($id);
        if ($packageProvider?->isIntegration()) {
            return $this->testPackageIntegration($packageProvider, $id);
        }

        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id])) {
            return new IntegrationConnectionTestResult(false, error: 'Integration not found', status: 404);
        }

        if (($available[$id]['category'] ?? null) === 'web-providers') {
            return $this->testWebProviderConfig($id, $request, $available[$id]);
        }

        if ($available[$id]['config_fields'] ?? null) {
            return $this->testChatIntegration($id, $request);
        }

        $apiKey = $request->input('apiKey');
        if (! $apiKey || str_contains((string) $apiKey, '*')) {
            // Preserve the same masked-secret behavior for older static entries
            // until every provider has moved behind package metadata.
            $setting = $this->accounts->findSetting($id, $this->accounts->accountFromRequest($request));
            $apiKey = $setting?->getConfigValue('api_key');
        }

        $format = $available[$id]['api_format'] ?? null;
        $url = $request->input('url') ?: ($available[$id]['default_url'] ?? '');
        $model = $request->input('defaultModel') ?: array_key_first($available[$id]['models'] ?? []);

        return $this->models->test($id, $apiKey, $url, $model, $format);
    }

    public function testAiProvider(Request $request, string $id): IntegrationConnectionTestResult
    {
        $id = IntegrationIdentity::rawId($id);
        $available = IntegrationSetting::getAvailableIntegrations();
        if (! isset($available[$id]) || ($available[$id]['category'] ?? null) !== 'ai-models') {
            return new IntegrationConnectionTestResult(false, error: 'AI provider not found', status: 404);
        }

        $apiKey = $request->input('apiKey');
        if (! $apiKey || str_contains((string) $apiKey, '*')) {
            $setting = $this->accounts->findSetting($id, $this->accounts->accountFromRequest($request));
            $apiKey = $setting?->getConfigValue('api_key');
        }

        $format = $available[$id]['api_format'] ?? null;
        $url = $request->input('url') ?: ($available[$id]['default_url'] ?? '');
        $model = $request->input('defaultModel') ?: array_key_first($available[$id]['models'] ?? []);

        return $this->models->test($id, $apiKey, $url, $model, $format);
    }

    private function testChatIntegration(string $id, Request $request): IntegrationConnectionTestResult
    {
        try {
            if ($id === 'telegram') {
                return $this->testAppOwnedTelegramChat($request);
            }

            $available = IntegrationSetting::getAvailableIntegrations();
            $configFields = $available[$id]['config_fields'] ?? [];
            $setting = $this->accounts->findSetting($id, $this->accounts->accountFromRequest($request));

            $config = [];
            foreach ($configFields as $key => $field) {
                $value = $request->input($key);
                if ($field['type'] === 'secret' && (! $value || str_contains((string) $value, '*'))) {
                    $config[$key] = $setting?->getConfigValue($key);
                } else {
                    $config[$key] = $value;
                }
            }

            $testSetting = new IntegrationSetting;
            $testSetting->integration_id = $id;
            $testSetting->config = $config;

            $adapter = ChatAdapterFactory::create($testSetting);
            if (! $adapter) {
                return new IntegrationConnectionTestResult(false, error: 'Adapter not supported', status: 400);
            }

            return new IntegrationConnectionTestResult(true, 'Connection configured successfully');
        } catch (\Throwable $e) {
            return new IntegrationConnectionTestResult(false, error: $e->getMessage(), status: 500);
        }
    }

    /**
     * Validate web provider configuration without making a live vendor request.
     *
     * Live search/fetch smoke tests belong in the web tool test path where URL
     * policy, provider routing, and output normalization are exercised together.
     */
    /**
     * @param  array<string, mixed>  $info
     */
    private function testWebProviderConfig(string $id, Request $request, array $info): IntegrationConnectionTestResult
    {
        $configFields = $info['config_fields'] ?? [];
        $setting = $this->accounts->findSetting($id, $this->accounts->accountFromRequest($request));

        foreach ($configFields as $key => $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            $value = $request->input($key);
            if (($field['type'] ?? null) === 'secret' && (! $value || str_contains((string) $value, '*'))) {
                $value = $setting?->getConfigValue($key);
            }

            if (! is_string($value) || trim($value) === '') {
                return new IntegrationConnectionTestResult(false, error: "{$field['label']} is required.", status: 400);
            }
        }

        return new IntegrationConnectionTestResult(true, 'Web provider configuration is present. Run web_search or web_fetch to perform a live provider call.');
    }

    private function testPackageIntegration(ToolProvider $provider, string $id): IntegrationConnectionTestResult
    {
        try {
            if ($provider->credentialFields() !== []) {
                // Without credentials we can only verify package registration.
                // Returning success keeps setup flows unblocked while the
                // message stays explicit that no live vendor call happened.
                return new IntegrationConnectionTestResult(
                    success: true,
                    message: "Integration {$id} is registered. Configure its credentials before testing a live connection.",
                );
            }

            foreach ($provider->tools() as $slug => $meta) {
                if ($meta['type'] !== 'read') {
                    continue;
                }

                // For no-key packages such as WorldBank, prefer a harmless
                // read-only tool with no required input. That exercises the real
                // package code and remote API without needing stored secrets or
                // inventing synthetic parameters.
                $tool = $provider->createTool($meta['class'], [
                    'tool_slug' => (string) $slug,
                ]);

                if ($this->hasRequiredParameters($tool->parameters())) {
                    continue;
                }

                $result = $tool->execute([]);
                $name = $meta['name'];

                return $result->succeeded()
                    ? new IntegrationConnectionTestResult(
                        success: true,
                        message: "Connection OK. {$id} requires no API key; {$name} responded.",
                        meta: ['tool' => $slug],
                    )
                    : new IntegrationConnectionTestResult(
                        success: false,
                        error: $result->error ?? 'unknown error',
                        status: 400,
                        meta: ['tool' => $slug],
                    );
            }

            return new IntegrationConnectionTestResult(
                success: true,
                message: "Connection OK. {$id} is registered and requires no API key.",
            );
        } catch (\Throwable $e) {
            return new IntegrationConnectionTestResult(false, error: $e->getMessage(), status: 500);
        }
    }

    /** @param  array<string, mixed>  $parameters */
    private function hasRequiredParameters(array $parameters): bool
    {
        foreach ($parameters as $parameter) {
            if (is_array($parameter) && ($parameter['required'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function testAppOwnedTelegramChat(Request $request): IntegrationConnectionTestResult
    {
        $apiKey = $request->input('apiKey')
            ?: $request->input('api_key')
            ?: $request->input('bot_token')
            ?: $request->input('accessToken')
            ?: $request->input('access_token');

        if (! $apiKey || str_contains((string) $apiKey, '*')) {
            $setting = $this->accounts->findSetting('telegram', $this->accounts->accountFromRequest($request));
            $apiKey = $setting?->getConfigValue('api_key')
                ?: $setting?->getConfigValue('access_token');
        }

        if (! $apiKey) {
            return new IntegrationConnectionTestResult(false, error: 'Bot token is required', status: 400);
        }

        $baseUrl = $request->input('url') ?: config('telegram.bot_api_base_url', 'https://api.telegram.org');

        // Telegram no longer asks Chatogrator for an adapter. The app-owned chat
        // domain validates the Bot API token here, while webhook health and setup
        // diagnostics live in Domain\Chat\Telegram.
        return $this->testTelegram((string) $apiKey, (string) $baseUrl);
    }

    private function testTelegram(string $apiKey, string $baseUrl = 'https://api.telegram.org'): IntegrationConnectionTestResult
    {
        $response = Http::timeout(10)->post(rtrim($baseUrl, '/')."/bot{$apiKey}/getMe");
        $data = $response->json();

        if ($response->successful() && ($data['ok'] ?? false)) {
            $result = $data['result'] ?? [];
            $setting = app()->bound('currentWorkspace')
                ? $this->accounts->findSetting('telegram')
                : null;
            if ($setting) {
                $setting->setConfigValue('bot_username', $result['username'] ?? '');
                $setting->save();
            }

            return new IntegrationConnectionTestResult(true, meta: [
                'botName' => $result['first_name'] ?? 'Unknown',
                'username' => $result['username'] ?? 'Unknown',
            ]);
        }

        return new IntegrationConnectionTestResult(false, error: $data['description'] ?? 'Failed to connect to Telegram', status: 400);
    }
}
