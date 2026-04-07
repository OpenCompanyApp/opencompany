<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
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

            return $toolResult->data;
        }

        $request = new Request($this->snakeToCamel($args));
        $rawResult = $tool->handle($request);

        if (! is_string($rawResult)) {
            return $rawResult;
        }

        $trimmed = ltrim($rawResult);
        if (($trimmed[0] ?? '') !== '{' && ($trimmed[0] ?? '') !== '[') {
            return $rawResult;
        }

        $decoded = json_decode($rawResult, true);

        return $decoded ?? $rawResult;
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
}
