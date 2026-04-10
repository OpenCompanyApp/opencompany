<?php

namespace App\Services\Memory;

class PromptFrameBuilder
{
    /**
     * @var string[]
     */
    public const DEFAULT_VOLATILE_SECTION_LABELS = [
        'Current Time',
        'Current Context',
        'Current Task',
    ];

    /**
     * @param  array<int, array{label: string, content: string}>  $sections
     * @param  string[]|null  $volatileLabels
     * @return array{
     *     stable_prompt: string,
     *     volatile_prompt: string,
     *     full_prompt: string,
     *     stable_sections: array<int, array{label: string, content: string}>,
     *     volatile_sections: array<int, array{label: string, content: string}>,
     *     stable_breakdown: array<int, array{label: string, chars: int}>,
     *     volatile_breakdown: array<int, array{label: string, chars: int}>,
     *     full_breakdown: array<int, array{label: string, chars: int}>
     * }
     */
    public function splitSections(array $sections, ?array $volatileLabels = null): array
    {
        $volatileLabels ??= self::DEFAULT_VOLATILE_SECTION_LABELS;
        $stable = [];
        $volatile = [];

        foreach ($sections as $section) {
            if (in_array($section['label'], $volatileLabels, true)) {
                $volatile[] = $section;
            } else {
                $stable[] = $section;
            }
        }

        return [
            'stable_prompt' => $this->join($stable),
            'volatile_prompt' => $this->join($volatile),
            'full_prompt' => $this->join($sections),
            'stable_sections' => $stable,
            'volatile_sections' => $volatile,
            'stable_breakdown' => $this->breakdown($stable),
            'volatile_breakdown' => $this->breakdown($volatile),
            'full_breakdown' => $this->breakdown($sections),
        ];
    }

    /**
     * @param  array<int, array{label: string, content: string}>  $sections
     */
    private function join(array $sections): string
    {
        return implode('', array_column($sections, 'content'));
    }

    /**
     * @param  array<int, array{label: string, content: string}>  $sections
     * @return array<int, array{label: string, chars: int}>
     */
    private function breakdown(array $sections): array
    {
        return array_values(array_map(
            fn (array $section) => [
                'label' => $section['label'],
                'chars' => mb_strlen($section['content']),
            ],
            $sections,
        ));
    }
}
