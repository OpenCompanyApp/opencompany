<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Facades\Prism;

class TestGlmPing extends Command
{
    protected $signature = 'z:ping {--prompt= : Custom prompt to send}';
    protected $description = 'Test Z.AI API connection';

    public function handle(): int
    {
        $this->info('Testing Z.AI API connection...');
        $this->newLine();

        $url = config('prism.providers.z.url');
        $apiKey = config('prism.providers.z.api_key');

        $this->line('Endpoint: ' . $url);
        $this->line('API Key: ' . substr($apiKey, 0, 10) . '...');
        $this->newLine();

        $prompt = $this->option('prompt') ?? 'What is your model name? Reply with just the model identifier.';

        try {
            $response = Prism::text()
                ->using('z', 'glm-5.1')
                ->withPrompt($prompt)
                ->asText();

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
