<?php

namespace Tests\Evals\Vfs\Support;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Spec\VfsGuide;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

/**
 * Shared runner for VFS eval cases: task loading, the database guard, world
 * building, system prompts, the two drivers (GLM and the scripted oracle),
 * scoring and the JSONL/transcript writers.
 *
 * Database invariant: evals only ever run on sqlite `:memory:` (phpunit.xml).
 * `assertInMemoryDatabase()` runs BEFORE parent::setUp() so RefreshDatabase
 * can never migrate a real database if the environment is misconfigured.
 */
trait RunsVfsEvalCases
{
    /** @return array<string, mixed> */
    protected static function suite(): array
    {
        return Yaml::parseFile(dirname(__DIR__).'/tasks.yaml');
    }

    /**
     * Expand tasks into (task, arm) cases: `modes` default to vfs_only, the
     * `computer_subset` adds the computer arm.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    protected static function cases(): array
    {
        $suite = self::suite();
        $cases = [];
        foreach ($suite['tasks'] as $task) {
            $modes = $task['modes'] ?? $suite['defaults']['modes'];
            if (in_array($task['id'], $suite['computer_subset'] ?? [], true) && ! in_array('computer_with_bridge', $modes, true)) {
                $modes[] = 'computer_with_bridge';
            }
            foreach ($modes as $mode) {
                $cases["{$task['id']}:{$mode}"] = [$task, $mode];
            }
        }

        return $cases;
    }

    protected static function assertInMemoryDatabase(): void
    {
        $connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException('VFS evals must run on sqlite :memory: (phpunit.xml); refusing to touch another database.');
        }
    }

    /**
     * Run one case with either driver and return the scored record.
     *
     * @param  array<string, mixed>  $task
     * @param  'llm'|'oracle'  $driver
     * @return array<string, mixed>
     */
    protected function runCase(array $task, string $mode, string $driver, ?GlmChatClient $client = null): array
    {
        $world = EvalWorld::build($this->workspace, $task['extras'] ?? []);
        $scenarios = new EvalScenarios($world);
        $agentMode = $mode === 'mixed' ? ($task['agent_mode'] ?? 'vfs_only') : $mode;
        $agent = ($task['agent'] ?? 'atlas') === 'forge' ? $world->forge : $world->atlas;
        app(ToolRegistry::class)->setChannelContext($world->channel->id);

        if (isset($task['scripted'])) {
            $before = EvalCheckers::snapshot();
            $run = ['requests' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'stopped' => 'scripted', 'error' => null, 'wall_s' => 0.0] + $scenarios->mix02StalePush();

            return $this->score($task, $mode, $driver, $world, $run, $before, $client);
        }

        $shim = $agentMode === 'computer_with_bridge' ? new BridgeShim($agent) : null;
        $toolbox = $shim === null ? new EvalToolbox($agent, 'tool') : null;
        if (isset($task['pre'])) {
            $scenarios->pre((string) $task['pre'], $shim);
        }
        $before = EvalCheckers::snapshot();

        $execute = $shim !== null
            ? fn (string $name, array $args): string => $name === BridgeShim::TOOL_NAME
                ? $shim->run((string) ($args['command'] ?? ''))
                : json_encode(['ok' => false, 'error' => ['code' => 'unknown_tool', 'message' => "Tool {$name} does not exist. Available tools: ".BridgeShim::TOOL_NAME.'.']])
            : fn (string $name, array $args): string => $toolbox->call($name, $args);
        $afterTool = isset($task['hook']) ? fn (array $step) => $scenarios->afterTool($task['hook'], $step) : null;
        $prompt = $world->interpolate((string) $task['prompt']);

        if ($driver === 'llm') {
            $loop = new EvalAgentLoop(
                $client,
                $shim !== null ? $shim->schemas() : $toolbox->schemas(),
                $execute,
                (int) ($task['max_turns'] ?? self::suite()['defaults']['max_turns']),
                $afterTool,
            );
            $run = $loop->run($this->systemPrompt($agent, $agentMode), $prompt);
        } else {
            $run = $this->runOracle($task, $agentMode, $world, $toolbox, $execute, $afterTool);
        }
        $run['hook_fired'] = isset($task['hook']) ? $scenarios->fired() : null;

        return $this->score($task, $mode, $driver, $world, $run, $before, $client);
    }

    /**
     * The scripted known-good trajectory from tasks.yaml.
     *
     * @return array<string, mixed>
     */
    private function runOracle(array $task, string $agentMode, EvalWorld $world, ?EvalToolbox $toolbox, \Closure $execute, ?\Closure $afterTool): array
    {
        $steps = [];
        $reply = '';
        $lastVersion = '';
        $script = $agentMode === 'computer_with_bridge' ? ($task['oracle_computer'] ?? []) : ($task['oracle'] ?? []);
        foreach ($script as $entry) {
            if (isset($entry['answer'])) {
                $reply = $world->interpolate((string) $entry['answer']);

                continue;
            }
            $slug = (string) array_key_first($entry);
            $args = $this->interpolateArgs($entry[$slug], $world, $lastVersion);
            $name = $slug === 'shell' ? BridgeShim::TOOL_NAME : (string) $toolbox?->nameForSlug($slug);
            $result = $slug === 'shell' ? $execute($name, ['command' => $args]) : $execute($name, $args);
            $step = ['tool' => $name, 'arguments' => $slug === 'shell' ? ['command' => $args] : $args, 'result' => $result] + EvalAgentLoop::outcome($result);
            $steps[] = $step;
            $decoded = json_decode($result, true);
            $lastVersion = (string) ($decoded['result']['version'] ?? $lastVersion);
            if ($afterTool !== null) {
                $afterTool($step);
            }
        }

        return ['reply' => $reply, 'steps' => $steps, 'requests' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'stopped' => 'oracle', 'error' => null, 'wall_s' => 0.0];
    }

    private function interpolateArgs(mixed $value, EvalWorld $world, string $lastVersion): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->interpolateArgs($v, $world, $lastVersion), $value);
        }

        return is_string($value) ? str_replace('{{last_version}}', $lastVersion, $world->interpolate($value)) : $value;
    }

    /**
     * System prompt: a short identity plus the real VFS orientation block
     * OpenCompanyAgent adds whenever an agent has VFS tools
     * (VfsGuide::promptBlock()). The computer arm adds how the same
     * operations are reached from the sandbox.
     */
    protected function systemPrompt(User $agent, string $mode): string
    {
        $identity = "You are {$agent->name}, an AI agent in the OpenCompany workspace \"{$this->workspace->name}\" working for Rutger (a human admin). "
            ."Do the user's task with your tools, then reply with a short summary of what you did and the answer.\n\n";
        $prompt = $identity.VfsGuide::promptBlock();
        if ($mode === 'computer_with_bridge') {
            $prompt .= "\n\n## Computer\n\nYou work from a sandbox computer (tool computer_shell). Company data is not stored on it and no network route or credential reaches company systems. "
                .'The VFS operations above are the `oc vfs exec|read|edit|write|patch` commands on that computer, with the same JSON parameters and results (`oc vfs help`). '
                .'/mnt/oc is a read-only snapshot of /docs, /tables and /files pulled at session start; `oc vfs push` writes a local copy back through the VFS.';
        }

        return $prompt;
    }

    /**
     * @return array<string, mixed>
     */
    private function score(array $task, string $mode, string $driver, EvalWorld $world, array $run, array $before, ?GlmChatClient $client): array
    {
        $verdict = EvalCheckers::check($task, $mode, $world, $run, $before);

        return [
            'task' => $task['id'],
            'category' => $task['category'],
            'mode' => $mode,
            'driver' => $driver,
            'model' => $client?->model(),
            'success' => $verdict['success'],
            'why' => $verdict['why'],
            'violations' => $verdict['violations'],
            'stopped' => $run['stopped'],
            'error' => $run['error'],
            'requests' => $run['requests'],
            'prompt_tokens' => $run['prompt_tokens'],
            'completion_tokens' => $run['completion_tokens'],
            'wall_s' => $run['wall_s'],
            'hook_fired' => $run['hook_fired'] ?? null,
            ...$verdict['metrics'],
            'reply' => $run['reply'],
            'steps' => array_map(fn (array $s) => [
                'tool' => $s['tool'],
                'arguments' => $s['arguments'],
                'ok' => $s['ok'],
                'error_code' => $s['error_code'],
                'pending' => $s['pending'],
                'result' => mb_strimwidth((string) $s['result'], 0, 3000, '…'),
            ], $run['steps']),
        ];
    }

    /**
     * Append the summary line to storage/app/evals/vfs/<run>.jsonl and the
     * full transcript to storage/app/evals/vfs/<run>/<task>__<mode>.json
     * (storage/app is gitignored).
     *
     * @param  array<string, mixed>  $record
     */
    protected function writeRecord(string $runId, array $record): string
    {
        $dir = storage_path('app/evals/vfs');
        @mkdir("{$dir}/{$runId}", 0775, true);
        $summary = array_diff_key($record, array_flip(['steps']));
        file_put_contents("{$dir}/{$runId}.jsonl", json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        $path = "{$dir}/{$runId}/".str_replace(':', '__', $record['task'].':'.$record['mode']).'.json';
        file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
