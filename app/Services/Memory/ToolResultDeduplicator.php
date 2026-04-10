<?php

namespace App\Services\Memory;

use App\Agents\Tools\ToolRegistry;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolResult;

class ToolResultDeduplicator
{
    private const EXACT_SUPERSEDE = '[Superseded - identical result returned by later call]';

    public function __construct(
        private ToolRegistry $toolRegistry,
    ) {}

    /**
     * @param  array<int, mixed>  $messages
     * @return array{messages: array<int, mixed>, deduplicated: int}
     */
    public function deduplicate(array $messages): array
    {
        $count = count($messages);
        if ($count < 2) {
            return ['messages' => $messages, 'deduplicated' => 0];
        }

        $latestBySig = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            if (! $messages[$i] instanceof ToolResultMessage) {
                continue;
            }

            $results = $messages[$i]->toolResults->values();
            for ($rIdx = $results->count() - 1; $rIdx >= 0; $rIdx--) {
                /** @var ToolResult $result */
                $result = $results[$rIdx];

                if ($this->isSuperseded($result->result)) {
                    continue;
                }

                if (! $this->shouldDeduplicate($result)) {
                    continue;
                }

                $sig = $this->signature($result);
                if (! isset($latestBySig[$sig])) {
                    $latestBySig[$sig] = [$i, $rIdx];
                }
            }
        }

        $deduplicated = 0;

        for ($i = 0; $i < $count; $i++) {
            if (! $messages[$i] instanceof ToolResultMessage) {
                continue;
            }

            $toolResults = $messages[$i]->toolResults->values();

            foreach ($toolResults as $rIdx => $result) {
                if (! $result instanceof ToolResult) {
                    continue;
                }

                if ($this->isSuperseded($result->result)) {
                    continue;
                }

                if (! $this->shouldDeduplicate($result)) {
                    continue;
                }

                $sig = $this->signature($result);
                if (isset($latestBySig[$sig]) && $latestBySig[$sig] !== [$i, $rIdx]) {
                    $toolResults[$rIdx] = $this->supersede($result, self::EXACT_SUPERSEDE);
                    $deduplicated++;
                }
            }

            $messages[$i]->toolResults = $toolResults;
        }

        return ['messages' => $messages, 'deduplicated' => $deduplicated];
    }

    private function supersede(ToolResult $result, string $placeholder): ToolResult
    {
        return new ToolResult(
            id: $result->id,
            name: $result->name,
            arguments: $result->arguments,
            result: $placeholder,
            resultId: $result->resultId,
        );
    }

    private function signature(ToolResult $result): string
    {
        $args = $result->arguments;
        ksort($args);
        $resultString = is_string($result->result)
            ? $result->result
            : json_encode($result->result, JSON_INVALID_UTF8_SUBSTITUTE);

        return $result->name.':'.json_encode($args, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE).':'.md5((string) $resultString);
    }

    private function isSuperseded(mixed $result): bool
    {
        if (! is_string($result)) {
            return false;
        }

        return str_starts_with($result, '[Superseded');
    }

    private function shouldDeduplicate(ToolResult $result): bool
    {
        return $this->toolRegistry->getToolTypeBySlug($result->name) === 'read';
    }
}
