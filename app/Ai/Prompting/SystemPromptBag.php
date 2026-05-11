<?php

namespace App\Ai\Prompting;

final readonly class SystemPromptBag
{
    /**
     * @param  string[]  $prompts
     */
    public function __construct(
        public array $prompts,
    ) {}

    public function hasPrompts(): bool
    {
        return $this->prompts !== [];
    }

    /**
     * @return string[]
     */
    public function prompts(): array
    {
        return array_values(array_filter(
            array_map(static fn (string $prompt): string => trim($prompt), $this->prompts),
            static fn (string $prompt): bool => $prompt !== '',
        ));
    }

    public function toInstructions(?string $fallback = null): ?string
    {
        $prompts = $this->prompts();

        if ($prompts === []) {
            return $fallback;
        }

        return implode("\n\n", $prompts);
    }
}
