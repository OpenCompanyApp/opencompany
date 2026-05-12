<?php

namespace App\Services\Ai;

use App\Services\Integrations\Data\IntegrationConnectionTestResult;
use Illuminate\Support\Facades\Http;

class ModelConnectionTester
{
    public function test(string $provider, ?string $apiKey, string $url, ?string $model, ?string $format = null): IntegrationConnectionTestResult
    {
        if (! $apiKey && $format !== 'ollama') {
            return new IntegrationConnectionTestResult(false, error: 'No API key provided', status: 400, meta: [
                'provider' => $provider,
                'model' => $model,
                'statusCode' => 'missing_credentials',
            ]);
        }

        try {
            return match ($format) {
                'anthropic' => $this->testAnthropic($provider, (string) $apiKey, $url, $model),
                'gemini' => $this->testGemini($provider, (string) $apiKey, $url, $model),
                'ollama' => $this->testOpenAiCompat($provider, null, $url, $model, 'ollama'),
                'openai', 'openai_compat' => $this->testOpenAiCompat($provider, $apiKey, $url, $model, $format),
                default => new IntegrationConnectionTestResult(false, error: 'Test not implemented for this integration', status: 501, meta: [
                    'provider' => $provider,
                    'model' => $model,
                    'statusCode' => 'not_implemented',
                ]),
            };
        } catch (\Throwable $e) {
            return new IntegrationConnectionTestResult(false, error: $e->getMessage(), status: 500, meta: [
                'provider' => $provider,
                'model' => $model,
                'statusCode' => 'network_error',
            ]);
        }
    }

    private function testOpenAiCompat(string $provider, ?string $apiKey, string $url, ?string $model, ?string $format): IntegrationConnectionTestResult
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post($url.'/chat/completions', [
                'model' => $model ?? 'default',
                'messages' => [['role' => 'user', 'content' => 'Respond with "ok".']],
                'max_tokens' => 10,
            ]);

        return $this->httpResult($response->successful(), $response->status(), $response->json('error.message') ?? $response->body(), $provider, $model, $format);
    }

    private function testAnthropic(string $provider, string $apiKey, string $url, ?string $model): IntegrationConnectionTestResult
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url.'/messages', [
            'model' => $model ?? 'claude-sonnet-4-5-20250929',
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'Respond with "ok".']],
        ]);

        return $this->httpResult($response->successful(), $response->status(), $response->json('error.message') ?? $response->body(), $provider, $model, 'anthropic');
    }

    private function testGemini(string $provider, string $apiKey, string $url, ?string $model): IntegrationConnectionTestResult
    {
        $model ??= 'gemini-2.0-flash';
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url.'/models/'.$model.':generateContent?key='.$apiKey, [
            'contents' => [['parts' => [['text' => 'Respond with "ok".']]]],
            'generationConfig' => ['maxOutputTokens' => 10],
        ]);

        return $this->httpResult($response->successful(), $response->status(), $response->json('error.message') ?? $response->body(), $provider, $model, 'gemini');
    }

    private function httpResult(bool $ok, int $httpStatus, string $error, string $provider, ?string $model, ?string $format): IntegrationConnectionTestResult
    {
        if ($ok) {
            return new IntegrationConnectionTestResult(true, 'Connection successful', status: 200, meta: [
                'provider' => $provider,
                'model' => $model,
                'format' => $format,
                'statusCode' => 'ok',
            ]);
        }

        return new IntegrationConnectionTestResult(false, error: 'API returned error: '.$error, status: 400, meta: [
            'provider' => $provider,
            'model' => $model,
            'format' => $format,
            'httpStatus' => $httpStatus,
            'statusCode' => $httpStatus === 404 ? 'model_not_found' : 'provider_error',
        ]);
    }
}
