<?php

namespace App\Ai\Providers;

use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\DeepSeek\DeepSeekGateway;
use Laravel\Ai\Providers\CohereProvider;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;

/**
 * Relay-backed Cohere-compatible provider for Laravel AI.
 *
 * The relay exposes an OpenAI/DeepSeek-style text endpoint for this provider, so
 * this adapter reuses DeepSeekGateway while preserving CohereProvider identity.
 */
class CohereRelayProvider extends CohereProvider implements TextProvider
{
    use GeneratesText;
    use HasTextGateway;
    use StreamsText;

    public function textGateway(): TextGateway
    {
        // Prism Relay routes this provider through a DeepSeek-compatible API
        // shape; swapping only the gateway avoids a custom provider fork.
        return $this->textGateway ??= new DeepSeekGateway($this->events);
    }

    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default']
            ?? $this->config['default_model']
            ?? array_key_first($this->config['models'] ?? [])
            ?? 'default';
    }

    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? $this->defaultTextModel();
    }

    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? $this->defaultTextModel();
    }
}
