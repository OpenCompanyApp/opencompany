<?php

namespace App\Domain\Ai\Runtime;

use App\Ai\Gateways\CachingTextGateway;
use App\Ai\Gateways\CodexTextGateway;
use App\Ai\Gateways\UnsupportedTextGateway;
use App\Ai\Providers\CohereTextProvider;
use App\Domain\Ai\Catalog\AiCatalog;
use App\Domain\Ai\Catalog\ProviderInfo;
use App\Domain\Ai\Codex\CodexOAuthService;
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

/**
 * Builds Laravel AI text providers from OpenCompany's app-owned catalog.
 *
 * The catalog defines canonical provider IDs and transport drivers. The factory
 * maps those durable transport names to Laravel AI provider implementations and
 * keeps cache decoration centralized so call sites do not know about gateway
 * quirks.
 */
class OpenCompanyAiProviderFactory
{
    public function __construct(
        private readonly AiCatalog $catalog,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function create(string $name, array $config): TextProvider
    {
        $provider = $this->catalog->provider($name);
        $canonical = $provider?->id ?? $name;

        return $this->createAnonymous($canonical, $provider, array_merge($config, [
            'name' => $config['name'] ?? $canonical,
        ]));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function createAnonymous(
        string $providerName,
        ?ProviderInfo $providerInfo,
        array $config,
    ): TextProvider {
        $config = $this->normalizeConfig($providerName, $providerInfo, $config);
        $driver = $this->laravelDriver($providerInfo, $config);

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
            'cohere' => new CohereTextProvider($config, $this->events),
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
            'openrouter' => (new OpenRouterProvider($config, $this->events))->useTextGateway(
                new OpenRouterBillingGateway($this->events),
            ),
            'xai' => new XaiProvider($config, $this->events),
            'deepseek' => new DeepSeekProvider($config, $this->events),
            default => (new OpenRouterProvider($config, $this->events))->useTextGateway(
                new UnsupportedTextGateway($providerName, $providerInfo?->driver ?? 'unknown'),
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
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(string $providerName, ?ProviderInfo $providerInfo, array $config): array
    {
        $url = $this->filledString($config['url'] ?? null)
            ?? $this->filledString($providerInfo?->defaultUrl);

        return array_filter([
            ...$config,
            'name' => $config['name'] ?? $providerName,
            // The Laravel AI driver value doubles as the provider-option key.
            // Keep it canonical so cache and per-provider options resolve.
            'driver' => $providerName,
            'key' => $config['key'] ?? $config['api_key'] ?? '',
            'url' => $url,
            'api_format' => $providerInfo?->apiFormat,
            'default_model' => $providerInfo?->defaultModel,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function laravelDriver(?ProviderInfo $providerInfo, array $config): ?string
    {
        $transport = $providerInfo?->driver ?? (string) ($config['transport'] ?? $config['provider_driver'] ?? '');

        return match ($transport) {
            'anthropic' => 'anthropic',
            'codex' => 'codex',
            'cohere' => 'cohere',
            'gemini' => 'gemini',
            'groq' => 'groq',
            'mistral' => 'mistral',
            'ollama' => 'ollama',
            'openai' => 'openai',
            'openrouter' => 'openrouter',
            'xai' => 'xai',
            'deepseek', 'openai_compat', 'openai-compatible' => 'deepseek',
            default => null,
        };
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
