<?php

namespace App\Ai\Providers;

use App\Ai\Gateways\CachingTextGateway;
use App\Ai\Gateways\CodexTextGateway;
use App\Ai\Gateways\ModelRouterTextGateway;
use App\Ai\Gateways\UnsupportedTextGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\Anthropic\AnthropicGateway;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Providers\AnthropicProvider;
use Laravel\Ai\Providers\DeepSeekProvider;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\GroqProvider;
use Laravel\Ai\Providers\MistralProvider;
use Laravel\Ai\Providers\OllamaProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\OpenRouterProvider;
use Laravel\Ai\Providers\XaiProvider;
use OpenCompany\PrismCodex\CodexOAuthService;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class RelayAiProviderFactory
{
    public function __construct(
        private readonly RelayRegistry $registry,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function create(string $name, array $config): TextProvider
    {
        $canonical = $this->registry->canonicalProvider($name) ?? $name;
        $providerDefinition = $this->registry->provider($canonical) ?? [];
        $url = $this->filledString($config['url'] ?? null)
            ?? $this->filledString($providerDefinition['url'] ?? null);
        $driver = $this->registry->laravelAiDriver($canonical, $url);

        return $this->createAnonymous($canonical, $driver, $providerDefinition, array_merge($config, [
            'name' => $config['name'] ?? $name,
        ]));
    }

    /**
     * @param  array<string, mixed>  $providerDefinition
     * @param  array<string, mixed>  $config
     */
    public function createAnonymous(
        string $providerName,
        ?string $driver,
        array $providerDefinition,
        array $config,
    ): TextProvider {
        $config = $this->normalizeConfig($providerName, $driver, $providerDefinition, $config);

        $provider = match ($driver) {
            'anthropic' => new AnthropicProvider(
                new AnthropicGateway($this->events),
                $config,
                $this->events,
            ),
            'codex' => (new OpenAiProvider(
                new OpenAiGateway($this->events),
                $config,
                $this->events,
            ))->useTextGateway(new CodexTextGateway($this->events, app(CodexOAuthService::class))),
            'cohere' => new CohereRelayProvider($config, $this->events),
            'gemini' => new GeminiProvider(
                new GeminiGateway($this->events),
                $config,
                $this->events,
            ),
            'groq' => new GroqProvider($config, $this->events),
            'mistral' => new MistralProvider($config, $this->events),
            'ollama' => new OllamaProvider($config, $this->events),
            'openai' => new OpenAiProvider(
                new OpenAiGateway($this->events),
                $config,
                $this->events,
            ),
            'openrouter' => new OpenRouterProvider($config, $this->events),
            'xai' => new XaiProvider($config, $this->events),
            'model-router' => (new OpenRouterProvider($config, $this->events))->useTextGateway(
                new ModelRouterTextGateway($this, $providerName, $providerDefinition, $config),
            ),
            'deepseek' => new DeepSeekProvider($config, $this->events),
            default => (new OpenRouterProvider($config, $this->events))->useTextGateway(
                new UnsupportedTextGateway($providerName, $this->registry->driver($providerName)),
            ),
        };

        return $this->wrap($provider);
    }

    private function wrap(TextProvider $provider): TextProvider
    {
        $gateway = $provider->textGateway();

        return $gateway instanceof CachingTextGateway
            ? $provider
            : $provider->useTextGateway(new CachingTextGateway($gateway));
    }

    /**
     * @param  array<string, mixed>  $providerDefinition
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(
        string $providerName,
        ?string $driver,
        array $providerDefinition,
        array $config,
    ): array {
        $url = $this->filledString($config['url'] ?? null)
            ?? $this->filledString($providerDefinition['url'] ?? null);

        $normalized = array_merge($providerDefinition, $config, [
            'name' => $config['name'] ?? $providerName,
            'driver' => $providerName,
            'key' => $config['key'] ?? $config['api_key'] ?? '',
            'url' => $url,
            'relay_driver' => $this->registry->driver($providerName),
        ]);

        if (isset($providerDefinition['version']) && ! isset($normalized['version'])) {
            $normalized['version'] = $providerDefinition['version'];
        }

        if (isset($providerDefinition['anthropic_beta']) && ! isset($normalized['anthropic_beta'])) {
            $normalized['anthropic_beta'] = $providerDefinition['anthropic_beta'];
        }

        return array_filter($normalized, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function filledString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
