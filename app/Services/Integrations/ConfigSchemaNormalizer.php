<?php

namespace App\Services\Integrations;

class ConfigSchemaNormalizer
{
    /**
     * @param  array<int|string, array<string, mixed>>  $schema
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? $field['name'] ?? (is_string($index) ? $index : null);
            if (! is_string($key) || $key === '') {
                continue;
            }

            $label = $field['label'] ?? str($key)->replace('_', ' ')->headline()->toString();

            $normalized[] = array_filter([
                ...$field,
                'key' => $key,
                'type' => self::normalizeType((string) ($field['type'] ?? 'text')),
                'label' => $label,
                'options' => isset($field['options']) && is_array($field['options'])
                    ? self::normalizeOptions($field['options'])
                    : null,
                'hint' => $field['hint'] ?? $field['description'] ?? null,
            ], fn ($value) => $value !== null);
        }

        return $normalized;
    }

    private static function normalizeType(string $type): string
    {
        return match ($type) {
            'string', 'email' => 'text',
            'password' => 'secret',
            default => $type,
        };
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return array<string, string>
     */
    private static function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $optionValue = $label['value'] ?? null;
                if (! is_string($optionValue) && ! is_numeric($optionValue)) {
                    continue;
                }

                $normalized[(string) $optionValue] = (string) ($label['label'] ?? $optionValue);
                continue;
            }

            if (is_int($value)) {
                $normalized[(string) $label] = (string) $label;
                continue;
            }

            $normalized[(string) $value] = (string) $label;
        }

        return $normalized;
    }
}
