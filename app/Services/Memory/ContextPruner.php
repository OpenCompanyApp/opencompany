<?php

namespace App\Services\Memory;

use App\Agents\Tools\ToolRegistry;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * Removes bulky read-only tool results from retry prompts.
 *
 * This runs only on checkpoint/resume context. It must never prune write-tool
 * results because those are the durable evidence that a side effect already
 * happened and should not be repeated by the model on retry.
 */
class ContextPruner
{
    public function __construct(
        private ToolRegistry $toolRegistry,
    ) {}

    /**
     * Prune old checkpoint-injected read results from retry context.
     *
     * @param  array<int, mixed>  $messages
     * @return array{messages: array<int, mixed>, pruned_results: int, estimated_tokens_saved: int}
     */
    public function prune(array $messages): array
    {
        if (! config('memory.pruning.enabled', true)) {
            return [
                'messages' => $messages,
                'pruned_results' => 0,
                'estimated_tokens_saved' => 0,
            ];
        }

        $candidates = [];

        foreach ($messages as $index => $message) {
            if (! $message instanceof ToolResultMessage) {
                continue;
            }

            $savings = 0;
            $eligible = true;

            foreach ($message->toolResults as $toolResult) {
                // Only read tools are replaceable. A write result may contain
                // the only record that the model has already mutated data.
                $toolType = $this->toolRegistry->getToolTypeBySlug($toolResult->name);

                if ($toolType !== 'read') {
                    $eligible = false;
                    break;
                }

                $serialized = $this->serializeResult($toolResult->result);
                if ($serialized === null) {
                    $eligible = false;
                    break;
                }

                $tokens = TokenEstimator::estimate($serialized);
                if ($tokens < (int) config('memory.pruning.min_result_tokens', 400)) {
                    $eligible = false;
                    break;
                }

                $placeholderTokens = TokenEstimator::estimate($this->placeholder($toolResult->name));
                $savings += max(0, $tokens - $placeholderTokens);
            }

            if (! $eligible || $savings <= 0) {
                continue;
            }

            $candidates[] = [
                'index' => $index,
                'tokens_saved' => $savings,
                'message' => $message,
            ];
        }

        $keepRecent = (int) config('memory.pruning.keep_recent_read_results', 2);
        if (count($candidates) <= $keepRecent) {
            return [
                'messages' => $messages,
                'pruned_results' => 0,
                'estimated_tokens_saved' => 0,
            ];
        }

        $prunable = array_slice($candidates, 0, max(0, count($candidates) - $keepRecent));
        $tokensSaved = array_sum(array_column($prunable, 'tokens_saved'));

        if ($tokensSaved < (int) config('memory.pruning.min_total_saved_tokens', 1_000)) {
            return [
                'messages' => $messages,
                'pruned_results' => 0,
                'estimated_tokens_saved' => 0,
            ];
        }

        $prunedResults = 0;

        foreach ($prunable as $candidate) {
            /** @var ToolResultMessage $toolResultMessage */
            $toolResultMessage = $candidate['message'];

            // Preserve tool call IDs, arguments, and result IDs so the retry
            // transcript remains structurally valid for the provider. Only the
            // bulky textual result is replaced with a model-readable pointer.
            $messages[$candidate['index']] = new ToolResultMessage(
                $toolResultMessage->toolResults->map(function (ToolResult $toolResult) use (&$prunedResults) {
                    $prunedResults++;

                    return new ToolResult(
                        id: $toolResult->id,
                        name: $toolResult->name,
                        arguments: $toolResult->arguments,
                        result: $this->placeholder($toolResult->name),
                        resultId: $toolResult->resultId,
                    );
                })
            );
        }

        return [
            'messages' => $messages,
            'pruned_results' => $prunedResults,
            'estimated_tokens_saved' => $tokensSaved,
        ];
    }

    private function placeholder(string $toolName): string
    {
        return "[Earlier {$toolName} read result omitted from retry context. Re-run the tool if you still need the full output.]";
    }

    private function serializeResult(mixed $result): ?string
    {
        if (is_string($result)) {
            return trim($result) !== '' ? $result : null;
        }

        if (is_array($result)) {
            $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded !== false && $encoded !== '[]' ? $encoded : null;
        }

        return null;
    }
}
