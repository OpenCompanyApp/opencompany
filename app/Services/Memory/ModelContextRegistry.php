<?php

namespace App\Services\Memory;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;

class ModelContextRegistry
{
    /**
     * Maximum Levenshtein distance for fuzzy model matching.
     */
    private const LEVENSHTEIN_MAX_DISTANCE = 5;

    /**
     * Get the context window size (in tokens) for a given model.
     *
     * Lookup order:
     * 1. User overrides from AppSetting (admin-configurable)
     * 2. Built-in registry: exact match, then longest prefix match
     * 3. Levenshtein fuzzy match (closest known model within distance threshold)
     * 4. Default (conservative 32K)
     */
    public function getContextWindow(string $model): int
    {
        $overrides = $this->getUserOverrides();
        $builtIn = config('memory.context_windows.models', []);

        // 1. User overrides — exact match takes highest priority
        if (isset($overrides[$model])) {
            return (int) $overrides[$model];
        }

        // 2a. Built-in exact match
        if (isset($builtIn[$model])) {
            return $builtIn[$model];
        }

        // 2b. Longest prefix match across both built-in and user overrides
        $allModels = array_merge($builtIn, $overrides);
        $prefixResult = $this->longestPrefixMatch($model, $allModels);
        if ($prefixResult !== null) {
            return $prefixResult;
        }

        // 3. Levenshtein fuzzy match — find closest known model
        $fuzzyResult = $this->levenshteinMatch($model, $allModels);
        if ($fuzzyResult !== null) {
            return $fuzzyResult;
        }

        // 4. Default
        return config('memory.context_windows.default', 32_000);
    }

    /**
     * Get the hard minimum context window size.
     * Models below this threshold are too small for meaningful agent operation.
     */
    public function getHardMinimum(): int
    {
        return config('memory.context_windows.hard_minimum', 16_000);
    }

    /**
     * Find the longest prefix match in the model registry.
     *
     * @param  array<string, int>  $registry
     */
    private function longestPrefixMatch(string $model, array $registry): ?int
    {
        $best = null;
        $bestLen = 0;

        foreach ($registry as $prefix => $window) {
            $prefix = (string) $prefix;
            if (str_starts_with($model, $prefix) && strlen($prefix) > $bestLen) {
                $best = (int) $window;
                $bestLen = strlen($prefix);
            }
        }

        return $best;
    }

    /**
     * Find the closest model by Levenshtein distance within the threshold.
     *
     * @param  array<string, int>  $registry
     */
    private function levenshteinMatch(string $model, array $registry): ?int
    {
        $closestWindow = null;
        $closestDist = self::LEVENSHTEIN_MAX_DISTANCE + 1;

        foreach ($registry as $known => $window) {
            $dist = levenshtein($model, (string) $known);
            if ($dist < $closestDist) {
                $closestDist = $dist;
                $closestWindow = (int) $window;
            }
        }

        if ($closestWindow !== null && $closestDist <= self::LEVENSHTEIN_MAX_DISTANCE) {
            Log::info("ModelContextRegistry: fuzzy match for '{$model}' (distance {$closestDist})", [
                'context_window' => $closestWindow,
            ]);
            return $closestWindow;
        }

        return null;
    }

    /**
     * Get user-configured context window overrides from AppSetting.
     *
     * @return array<string, int>
     */
    private function getUserOverrides(): array
    {
        try {
            $value = AppSetting::getValue('model_context_windows');
        } catch (\Throwable) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
