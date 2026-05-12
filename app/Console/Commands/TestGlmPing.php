<?php

namespace App\Console\Commands;

use App\Agents\Providers\DynamicProviderResolver;
use App\Models\IntegrationSetting;
use Illuminate\Console\Command;
use Laravel\Ai\AnonymousAgent;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

/**
 * Operator smoke test for the configured Z.AI/GLM relay provider.
 *
 * This performs a real model call after resolving the workspace integration
 * overlay, so it validates both stored credentials and Laravel AI provider wiring.
 */
class TestGlmPing extends Command
{
    protected $signature = 'z:ping {--prompt= : Custom prompt to send}';

    protected $description = 'Test Z.AI API connection';

    public function handle(): int
    {
        $this->info('Testing Z.AI API connection...');
        $this->newLine();

        $setting = IntegrationSetting::query()
            ->where('integration_id', 'z')
            ->where('enabled', true)
            ->first();

        $registry = app(RelayRegistry::class);
        $provider = 'z';
        // Use the relay registry default so the command follows provider
        // upgrades such as GLM 5.1 without hardcoded model drift.
        $model = $registry->provider($provider)['default_model'] ?? null;

        if (! is_string($model) || $model === '') {
            $this->error('Z.AI default model is not available in the relay registry.');

            return Command::FAILURE;
        }
        $url = $setting?->getConfigValue('url')
            ?? config("ai.providers.{$provider}.url")
            ?? config("prism.providers.{$provider}.url")
            ?? $registry->url($provider);
        $apiKey = $setting?->getConfigValue('api_key')
            ?? config("ai.providers.{$provider}.key")
            ?? config("prism.providers.{$provider}.api_key");

        if (! $apiKey) {
            $this->error('No enabled Z.AI integration or configured provider key is available.');

            return Command::FAILURE;
        }

        // Push the workspace-specific provider config into Laravel AI before
        // the AnonymousAgent call resolves its provider instance.
        app(DynamicProviderResolver::class)
            ->setWorkspaceId($setting?->workspace_id)
            ->resolveFromParts($provider, (string) $model);

        $this->line('Endpoint: '.$url);
        $this->line('API Key: configured');
        $this->newLine();

        $prompt = $this->option('prompt') ?? 'What is your model name? Reply with just the model identifier.';

        try {
            $response = (new AnonymousAgent(
                instructions: 'You are a concise API healthcheck assistant.',
                messages: [],
                tools: [],
            ))->prompt($prompt, provider: $provider, model: (string) $model, timeout: 60);

            $this->info('Response:');
            $this->newLine();
            $this->line($response->text);
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Prompt Tokens', $response->usage->promptTokens],
                    ['Completion Tokens', $response->usage->completionTokens],
                    ['Total Tokens', $response->usage->promptTokens + $response->usage->completionTokens],
                ]
            );

            $this->newLine();
            $this->info('Z.AI connection successful!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to connect to Z.AI:');
            $this->error($e->getMessage());

            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->warn('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
