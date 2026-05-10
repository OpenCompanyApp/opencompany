<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Contracts\LuaToolInvoker;
use OpenCompany\IntegrationCore\Contracts\Tool as IntegrationTool;

class OpenCompanyLuaToolInvoker implements LuaToolInvoker
{
    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
    ) {}

    public function invoke(string $toolSlug, array $args, ?string $account = null): mixed
    {
        $tool = $this->registry->instantiateToolBySlug($toolSlug, $this->agent, $account);

        if ($tool === null) {
            throw new \RuntimeException("Tool not available: {$toolSlug}");
        }

        if ($tool instanceof IntegrationTool) {
            $toolResult = $tool->execute($args);

            if (! $toolResult->succeeded()) {
                throw new \RuntimeException($toolResult->error ?? "Tool failed: {$toolSlug}");
            }

            return $this->normalizeForLua($toolResult->data);
        }

        $request = new Request($this->snakeToCamel($args));
        $rawResult = $tool->handle($request);

        if (! is_string($rawResult)) {
            return $this->normalizeForLua($rawResult);
        }

        $trimmed = ltrim($rawResult);
        if (($trimmed[0] ?? '') !== '{' && ($trimmed[0] ?? '') !== '[') {
            return $rawResult;
        }

        $decoded = json_decode($rawResult, true);

        return $decoded !== null ? $this->normalizeForLua($decoded) : $rawResult;
    }

    public function getToolMeta(string $toolSlug): array
    {
        return $this->registry->getToolMetaBySlug($toolSlug);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function snakeToCamel(array $params): array
    {
        $converted = [];

        foreach ($params as $key => $value) {
            $camelKey = lcfirst(str_replace('_', '', ucwords((string) $key, '_')));
            $converted[$camelKey] = $value;
        }

        return $converted;
    }

    private function normalizeForLua(mixed $value): mixed
    {
        if (is_int($value)) {
            return ($value > 2147483647 || $value < -2147483648) ? (float) $value : $value;
        }

        if ($value === null || is_bool($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeForLua($value->jsonSerialize());
        }

        if ($value instanceof Arrayable) {
            return $this->normalizeForLua($value->toArray());
        }

        if ($value instanceof \Traversable) {
            return $this->normalizeForLua(iterator_to_array($value));
        }

        if (is_object($value)) {
            return $this->normalizeForLua(get_object_vars($value));
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeForLua($item);
        }

        return array_is_list($normalized) ? array_values($normalized) : $normalized;
    }
}
