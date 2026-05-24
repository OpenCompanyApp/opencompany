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
 * Cohere text adapter for Laravel AI.
 *
 * Laravel's Cohere provider does not expose text generation in this SDK version.
 * OpenCompany keeps the provider identity but uses the OpenAI-compatible gateway
 * path for text calls when a Cohere-compatible endpoint is configured.
 */
class CohereTextProvider extends CohereProvider implements TextProvider
{
    use GeneratesText;
    use HasTextGateway;
    use StreamsText;

    public function textGateway(): TextGateway
    {
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
