<?php

namespace App\Domain\Web\Registry;

use App\Domain\Web\Contracts\WebFetchProvider;
use App\Domain\Web\Contracts\WebProvider;
use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\Enums\WebCapability;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Providers\AnthropicNativeSearchProvider;
use App\Domain\Web\Providers\BraveProvider;
use App\Domain\Web\Providers\ExaProvider;
use App\Domain\Web\Providers\Fetch\DirectFetchProvider;
use App\Domain\Web\Providers\Fetch\ZaiReaderFetchProvider;
use App\Domain\Web\Providers\FirecrawlProvider;
use App\Domain\Web\Providers\JinaProvider;
use App\Domain\Web\Providers\OpenAiNativeSearchProvider;
use App\Domain\Web\Providers\ParallelProvider;
use App\Domain\Web\Providers\PerplexityProvider;
use App\Domain\Web\Providers\Search\ZaiMcpSearchProvider;
use App\Domain\Web\Providers\SearxngProvider;
use App\Domain\Web\Providers\TavilyProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Factory and status catalog for all app-owned web providers.
 */
class WebProviderRegistry
{
    /** @var array<string, WebProvider> */
    private array $providers = [];

    public function __construct(private Container $container) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys(config('web.providers', []));
    }

    public function searchProvider(string $name): WebSearchProvider
    {
        $provider = $this->provider($name, WebCapability::Search);
        if (! $provider instanceof WebSearchProvider) {
            throw new WebProviderException($provider->label().' does not support search.');
        }

        return $provider;
    }

    public function fetchProvider(string $name): WebFetchProvider
    {
        $provider = $this->provider($name, WebCapability::Fetch);
        if (! $provider instanceof WebFetchProvider) {
            throw new WebProviderException($provider->label().' does not support fetch.');
        }

        return $provider;
    }

    public function provider(string $name, ?WebCapability $capability = null): WebProvider
    {
        $name = $this->normalize($name);
        if (! in_array($name, $this->names(), true)) {
            throw new WebProviderException("Unknown web provider [{$name}].");
        }

        $provider = $this->providers[$name.':'.($capability !== null ? $capability->value : 'any')] ??= $this->make($name, $capability);
        if ($capability !== null && ! $provider->supports($capability)) {
            throw new WebProviderException($provider->label().' does not support '.$capability->value.'.');
        }

        return $provider;
    }

    /**
     * @return list<string>
     */
    public function availableSearchProviderIds(): array
    {
        return $this->availableProviderIds(WebCapability::Search);
    }

    /**
     * @return list<string>
     */
    public function availableFetchProviderIds(): array
    {
        return $this->availableProviderIds(WebCapability::Fetch);
    }

    /**
     * @return list<array{id: string, label: mixed, enabled: bool, configured: bool, capabilities: mixed, integration_id: mixed, api_key_url: mixed}>
     */
    public function statuses(): array
    {
        $rows = [];
        foreach ($this->names() as $name) {
            $config = config("web.providers.{$name}", []);
            $provider = $this->provider($name, $name === 'zai' ? WebCapability::Search : null);
            $rows[] = [
                'id' => $name,
                'label' => $config['label'] ?? $provider->label(),
                'enabled' => (bool) ($config['enabled'] ?? false),
                'configured' => $provider->isAvailable(),
                'capabilities' => $config['capabilities'] ?? [],
                'integration_id' => $config['integration_id'] ?? null,
                'api_key_url' => $config['api_key_url'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function availableProviderIds(WebCapability $capability): array
    {
        $ids = [];
        foreach ($this->names() as $name) {
            $config = config("web.providers.{$name}", []);
            if (! (bool) ($config['enabled'] ?? false) || ! in_array($capability->value, $config['capabilities'] ?? [], true)) {
                continue;
            }
            $provider = $this->provider($name, $capability);
            if ($provider->isAvailable()) {
                $ids[] = $name;
            }
        }

        return $ids;
    }

    private function make(string $name, ?WebCapability $capability): WebProvider
    {
        return match ($name) {
            'direct' => $this->container->make(DirectFetchProvider::class),
            'tavily' => $this->container->make(TavilyProvider::class),
            'zai' => $capability === WebCapability::Fetch
                ? $this->container->make(ZaiReaderFetchProvider::class)
                : $this->container->make(ZaiMcpSearchProvider::class),
            'firecrawl' => $this->container->make(FirecrawlProvider::class),
            'exa' => $this->container->make(ExaProvider::class),
            'brave' => $this->container->make(BraveProvider::class),
            'parallel' => $this->container->make(ParallelProvider::class),
            'jina' => $this->container->make(JinaProvider::class),
            'searxng' => $this->container->make(SearxngProvider::class),
            'perplexity' => $this->container->make(PerplexityProvider::class),
            'openai_native' => $this->container->make(OpenAiNativeSearchProvider::class),
            'anthropic_native' => $this->container->make(AnthropicNativeSearchProvider::class),
            default => throw new WebProviderException("Unknown web provider [{$name}]."),
        };
    }

    private function normalize(string $name): string
    {
        return str_replace('-', '_', strtolower(trim($name)));
    }
}
