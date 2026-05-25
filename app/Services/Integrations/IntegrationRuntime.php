<?php

namespace App\Services\Integrations;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\Tool as IntegrationTool;

class IntegrationRuntime
{
    public function __construct(private ToolRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $args
     */
    public function call(User $agent, string $toolSlug, array $args, ?string $account = null): mixed
    {
        $tool = $this->registry->instantiateToolBySlug($toolSlug, $agent, $account);

        if ($tool === null) {
            throw new \RuntimeException("Tool not available: {$toolSlug}");
        }

        if ($tool instanceof IntegrationTool) {
            $result = $tool->execute($args);
            if (! $result->succeeded()) {
                throw new \RuntimeException($result->error ?? "Tool failed: {$toolSlug}");
            }

            return $this->normalize($result->data);
        }

        $raw = $tool->handle(new Request($this->snakeToCamel($args)));

        if (! is_string($raw)) {
            return $this->normalize($raw);
        }

        if (str_contains($raw, "\nStructured data:\n")) {
            $json = trim((string) str($raw)->afterLast("\nStructured data:\n"));
            $decoded = json_decode($json, true);

            if ($decoded !== null) {
                return $this->normalize($decoded);
            }
        }

        $trimmed = ltrim($raw);
        if (($trimmed[0] ?? '') !== '{' && ($trimmed[0] ?? '') !== '[') {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        return $decoded !== null ? $this->normalize($decoded) : $raw;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function snakeToCamel(array $params): array
    {
        $converted = [];
        foreach ($params as $key => $value) {
            $converted[lcfirst(str_replace('_', '', ucwords((string) $key, '_')))] = $value;
        }

        return $converted;
    }

    private function normalize(mixed $value): mixed
    {
        if (is_int($value)) {
            return ($value > 2147483647 || $value < -2147483648) ? (float) $value : $value;
        }

        if ($value === null || is_bool($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalize($value->jsonSerialize());
        }

        if ($value instanceof Arrayable) {
            return $this->normalize($value->toArray());
        }

        if ($value instanceof \Traversable) {
            return $this->normalize(iterator_to_array($value));
        }

        if (is_object($value)) {
            return $this->normalize(get_object_vars($value));
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalize($item);
        }

        return $normalized;
    }
}
