<?php

namespace App\Console\Commands;

use App\Models\IntegrationSetting;
use Illuminate\Console\Command;
use Laravel\Ai\AnonymousAgent;

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

        $url = $setting?->getConfigValue('url') ?? config('ai.providers.z.url') ?? 'https://api.z.ai/api/coding/paas/v4';
        $apiKey = $setting?->getConfigValue('api_key') ?? config('ai.providers.z.key');

        if (! $apiKey) {
            $this->error('No enabled Z.AI integration or ai.providers.z key is configured.');

            return Command::FAILURE;
        }

        config(['ai.providers.z' => array_merge(config('ai.providers.z', []), [
            'driver' => 'z',
            'key' => $apiKey,
            'url' => $url,
        ])]);

        $this->line('Endpoint: ' . $url);
        $this->line('API Key: configured');
        $this->newLine();

        $prompt = $this->option('prompt') ?? 'What is your model name? Reply with just the model identifier.';

        try {
            $response = (new AnonymousAgent(
                instructions: 'You are a concise API healthcheck assistant.',
                messages: [],
                tools: [],
            ))->prompt($prompt, provider: 'z', model: 'glm-5.1', timeout: 60);

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
