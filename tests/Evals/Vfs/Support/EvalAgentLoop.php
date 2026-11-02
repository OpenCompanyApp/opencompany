<?php

namespace Tests\Evals\Vfs\Support;

use Illuminate\Support\Str;

/**
 * The chat -> tool calls -> tool results loop for one eval case.
 *
 * Owns turn accounting, transcript capture and the post-tool hook used by
 * mixed tasks (a scripted second writer runs between two of the agent's tool
 * calls). It does not own tool semantics: `$execute` is either the real VFS
 * toolbox (vfs_only) or the BridgeShim (computer_with_bridge).
 *
 * Stops on: a final assistant message without tool calls (`final`), the
 * turn cap (`turn_cap`), the request budget (`budget`) or an HTTP error
 * (`http_error`). Each step records the tool, arguments, a result excerpt and
 * the parsed outcome (ok / error code / pending approval) for the checkers.
 */
final class EvalAgentLoop
{
    /**
     * @param  \Closure(string, array<string, mixed>): string  $execute
     * @param  list<array<string, mixed>>  $tools
     * @param  (\Closure(array<string, mixed>, int): void)|null  $afterTool  receives the recorded step and its index
     */
    public function __construct(
        private readonly GlmChatClient $client,
        private readonly array $tools,
        private readonly \Closure $execute,
        private readonly int $maxTurns,
        private readonly ?\Closure $afterTool = null,
    ) {}

    /**
     * @return array{reply: string, steps: list<array<string, mixed>>, requests: int, prompt_tokens: int, completion_tokens: int, stopped: string, error: ?string, wall_s: float}
     */
    public function run(string $system, string $prompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ];
        $steps = [];
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $requests = 0;
        $reply = '';
        $stopped = 'turn_cap';
        $error = null;
        $started = microtime(true);

        for ($turn = 0; $turn < $this->maxTurns; $turn++) {
            try {
                $response = $this->client->chat($messages, $this->tools);
            } catch (EvalBudgetExhausted $e) {
                $stopped = 'budget';
                $error = $e->getMessage();
                break;
            } catch (\Throwable $e) {
                $stopped = 'http_error';
                $error = $e->getMessage();
                break;
            }
            $requests++;
            $usage['prompt_tokens'] += $response['usage']['prompt_tokens'];
            $usage['completion_tokens'] += $response['usage']['completion_tokens'];
            $message = $response['message'];
            $calls = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];
            $reply = is_string($message['content'] ?? null) ? $message['content'] : '';

            if ($calls === []) {
                $stopped = 'final';
                break;
            }

            $messages[] = array_filter([
                'role' => 'assistant',
                'content' => $reply === '' ? null : $reply,
                'tool_calls' => $calls,
            ], fn ($v) => $v !== null);

            foreach ($calls as $call) {
                $name = (string) ($call['function']['name'] ?? '');
                $rawArguments = (string) ($call['function']['arguments'] ?? '{}');
                $arguments = json_decode($rawArguments, true);
                $result = is_array($arguments)
                    ? ($this->execute)($name, $arguments)
                    : json_encode(['ok' => false, 'error' => ['code' => 'invalid_arguments', 'message' => 'Tool arguments were not a JSON object.']]);
                $step = ['tool' => $name, 'arguments' => is_array($arguments) ? $arguments : $rawArguments, 'result' => $result] + self::outcome($result);
                $steps[] = $step;
                $messages[] = ['role' => 'tool', 'tool_call_id' => (string) ($call['id'] ?? Str::random(8)), 'content' => $result];
                if ($this->afterTool !== null) {
                    ($this->afterTool)($step, count($steps) - 1);
                }
            }
            $reply = '';
        }

        return [
            'reply' => $reply,
            'steps' => $steps,
            'requests' => $requests,
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'stopped' => $stopped,
            'error' => $error,
            'wall_s' => round(microtime(true) - $started, 1),
        ];
    }

    /**
     * Parse a tool result into {ok, error_code, pending}. Works for the VFS
     * envelope and for the shim's terminal JSON (whose stdout/stderr may
     * contain a forwarded envelope).
     *
     * @return array{ok: bool, error_code: ?string, pending: bool}
     */
    public static function outcome(string $result): array
    {
        $decoded = json_decode($result, true);
        if (is_array($decoded) && array_key_exists('exit_code', $decoded) && ! array_key_exists('ok', $decoded)) {
            // computer_shell: {exit_code, stdout, stderr}
            $text = (string) ($decoded['stdout'] ?? '').(string) ($decoded['stderr'] ?? '');
            $pending = str_contains($text, '"status":"pending_approval"');
            preg_match('/"code":"([a-z_]+)"/', $text, $m);
            $code = $m[1] ?? null;
            if ($code === null && (int) $decoded['exit_code'] !== 0) {
                $code = (int) $decoded['exit_code'] === 127 ? 'shell_unavailable' : 'shell_error';
            }

            return ['ok' => (int) $decoded['exit_code'] === 0 && $code === null, 'error_code' => $pending ? null : $code, 'pending' => $pending];
        }
        if (! is_array($decoded)) {
            return ['ok' => false, 'error_code' => 'unparseable', 'pending' => false];
        }
        $pending = ($decoded['status'] ?? null) === 'pending_approval' || ($decoded['error']['code'] ?? null) === 'approval_required';

        return [
            'ok' => ($decoded['ok'] ?? false) === true && ! $pending,
            'error_code' => $pending ? null : (is_string($decoded['error']['code'] ?? null) ? $decoded['error']['code'] : null),
            'pending' => $pending,
        ];
    }
}
