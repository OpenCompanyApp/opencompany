<?php

namespace App\Console\Commands;

use App\Agents\Tools\Vfs\VfsCount;
use App\Agents\Tools\Vfs\VfsCp;
use App\Agents\Tools\Vfs\VfsExec;
use App\Agents\Tools\Vfs\VfsExists;
use App\Agents\Tools\Vfs\VfsFind;
use App\Agents\Tools\Vfs\VfsLs;
use App\Agents\Tools\Vfs\VfsMkdir;
use App\Agents\Tools\Vfs\VfsMv;
use App\Agents\Tools\Vfs\VfsPatch;
use App\Agents\Tools\Vfs\VfsRead;
use App\Agents\Tools\Vfs\VfsRg;
use App\Agents\Tools\Vfs\VfsRm;
use App\Agents\Tools\Vfs\VfsSearch;
use App\Agents\Tools\Vfs\VfsStat;
use App\Agents\Tools\Vfs\VfsWrite;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * Local VFS smoke-test helper for running VFS shell commands as Atlas.
 *
 * This command is intentionally app-local test ergonomics, not an agent-facing
 * tool. It binds the selected agent's workspace and then executes a VFS shell
 * command through the real VFS command executor so developers can inspect
 * permissions, paths, command coverage, and structured output quickly.
 *
 * Use --primitive when the smoke test needs to hit the same direct VFS tool
 * primitive an agent or Lua helper would use, such as vfs_patch or vfs_read.
 */
class VfsAtlasCommand extends Command
{
    protected $signature = 'vfs:atlas
        {vfsCommand?* : VFS command tokens, or one quoted VFS command string}
        {--agent=Atlas : Agent name, email, or ID to execute as}
        {--primitive= : Direct VFS primitive to run instead of shell command: exec, ls, stat, read, write, patch, find, rg, search, count, exists, mkdir, cp, mv, rm}
        {--input= : JSON object payload for --primitive}
        {--arg=* : Primitive payload key=value pair; repeat as needed}
        {--cwd=/ : Virtual working directory}
        {--max-entries=100 : Maximum list entries}
        {--max-depth=3 : Maximum tree/find/search depth}
        {--max-bytes=100000 : Maximum bytes read from one file}
        {--max-files=100 : Maximum files scanned}
        {--max-matches=100 : Maximum search matches}
        {--json : Print the full executor envelope as JSON}
        {--structured : Include command data in a stable JSON envelope}
        {--spec : Include the primary command spec in diagnostics}';

    protected $description = 'Execute a VFS shell command as the Atlas agent for local testing';

    public function handle(VfsCommandExecutor $executor): int
    {
        $command = $this->commandString();
        $primitive = trim((string) $this->option('primitive'));
        if ($command === '' && $primitive === '') {
            $this->error('Provide a VFS command, for example: php artisan vfs:atlas "ls /tools/vfs"');

            return self::FAILURE;
        }

        $agent = $this->resolveAgent((string) $this->option('agent'));
        if (! $agent) {
            $this->error("Agent not found: {$this->option('agent')}");

            return self::FAILURE;
        }

        $this->bindWorkspace($agent);

        if ($primitive !== '') {
            return $this->runPrimitive($primitive, $command, $agent);
        }

        try {
            $result = $executor->execute(
                $agent,
                $command,
                (string) $this->option('cwd'),
                new VfsBudget(
                    maxEntries: $this->integerOption('max-entries', 100, 1),
                    maxDepth: $this->integerOption('max-depth', 3, 0),
                    maxBytes: $this->integerOption('max-bytes', 100000, 1),
                    maxFiles: $this->integerOption('max-files', 100, 1),
                    maxMatches: $this->integerOption('max-matches', 100, 1),
                ),
                [
                    'command' => $command,
                    'cwd' => (string) $this->option('cwd'),
                    'source' => 'artisan:vfs:atlas',
                ],
            );

            $this->renderResult($command, $agent, $result);

            return ($result['ok'] ?? true) === true ? self::SUCCESS : self::FAILURE;
        } catch (VfsError $e) {
            $this->renderError($e);

            return self::FAILURE;
        }
    }

    private function runPrimitive(string $primitive, string $command, User $agent): int
    {
        try {
            $tool = $this->makePrimitiveTool($primitive, $agent);
            $payload = $this->primitivePayload();
            if ($this->canonicalPrimitiveName($primitive) === 'exec' && ! isset($payload['command']) && $command !== '') {
                $payload['command'] = $command;
            }

            $response = $tool->handle(new ToolRequest($payload));
            $this->renderPrimitiveResult($this->canonicalPrimitiveName($primitive), $agent, $payload, $response);
            $decoded = json_decode($response, true);

            return is_array($decoded) && ($decoded['ok'] ?? true) === false
                ? self::FAILURE
                : self::SUCCESS;
        } catch (VfsError $e) {
            $this->renderError($e);

            return self::FAILURE;
        }
    }

    private function commandString(): string
    {
        $parts = $this->argument('vfsCommand');

        return trim(implode(' ', array_map('strval', $parts)));
    }

    private function resolveAgent(string $identifier): ?User
    {
        $currentWorkspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
        $currentWorkspaceId = $currentWorkspace instanceof Workspace ? $currentWorkspace->id : null;

        return User::query()
            ->where('type', 'agent')
            ->where(function ($query) use ($identifier): void {
                $query->whereKey($identifier)
                    ->orWhere('name', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->when(
                $currentWorkspaceId !== null,
                fn ($query) => $query->orderByRaw('CASE WHEN workspace_id = ? THEN 0 WHEN workspace_id IS NULL THEN 2 ELSE 1 END', [$currentWorkspaceId]),
                fn ($query) => $query->orderByRaw('CASE WHEN workspace_id IS NULL THEN 1 ELSE 0 END'),
            )
            ->first();
    }

    private function bindWorkspace(User $agent): void
    {
        $workspace = $agent->workspace_id
            ? Workspace::find($agent->workspace_id)
            : Workspace::first();
        if ($workspace) {
            $agent->workspace_id ??= $workspace->id;
            app()->instance('currentWorkspace', $workspace);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function primitivePayload(): array
    {
        $payload = [];
        $input = trim((string) $this->option('input'));
        if ($input !== '') {
            $decoded = json_decode($input, true);
            if (! is_array($decoded)) {
                throw VfsError::invalid('--input must be a JSON object.', ['input' => $input]);
            }
            $payload = $decoded;
        }

        foreach ((array) $this->option('arg') as $pair) {
            $parts = explode('=', (string) $pair, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '') {
                throw VfsError::invalid('--arg must be formatted as key=value.', ['arg' => $pair]);
            }

            $payload[trim($parts[0])] = $this->decodePrimitiveArgument($parts[1]);
        }

        return $payload;
    }

    private function decodePrimitiveArgument(string $value): mixed
    {
        $trimmed = trim($value);
        $decoded = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function makePrimitiveTool(string $primitive, User $agent): Tool
    {
        $class = match ($this->canonicalPrimitiveName($primitive)) {
            'count' => VfsCount::class,
            'cp' => VfsCp::class,
            'exec' => VfsExec::class,
            'exists' => VfsExists::class,
            'find' => VfsFind::class,
            'ls' => VfsLs::class,
            'mkdir' => VfsMkdir::class,
            'mv' => VfsMv::class,
            'patch' => VfsPatch::class,
            'read' => VfsRead::class,
            'rg' => VfsRg::class,
            'rm' => VfsRm::class,
            'search' => VfsSearch::class,
            'stat' => VfsStat::class,
            'write' => VfsWrite::class,
            default => throw VfsError::invalid('Unknown VFS primitive.', [
                'primitive' => $primitive,
                'supported' => ['exec', 'ls', 'stat', 'read', 'write', 'patch', 'find', 'rg', 'search', 'count', 'exists', 'mkdir', 'cp', 'mv', 'rm'],
            ]),
        };

        return app()->makeWith($class, ['agent' => $agent]);
    }

    private function canonicalPrimitiveName(string $primitive): string
    {
        $primitive = strtolower(trim($primitive));

        return str_starts_with($primitive, 'vfs_') ? substr($primitive, 4) : $primitive;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function renderResult(string $command, User $agent, array $result): void
    {
        if ($this->option('json') || $this->option('structured')) {
            $payload = [
                'ok' => (bool) ($result['ok'] ?? true),
                'agent' => ['id' => $agent->id, 'name' => $agent->name],
                'command' => $command,
                'cwd' => $result['cwd'] ?? (string) $this->option('cwd'),
                'stdout' => (string) ($result['stdout'] ?? ''),
                'stderr' => (string) ($result['stderr'] ?? ''),
                'data' => $result['result'] ?? $result,
                'diagnostics' => $this->diagnostics($command),
            ];

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return;
        }

        $stdout = (string) ($result['stdout'] ?? '');
        $stderr = (string) ($result['stderr'] ?? '');

        if ($stdout !== '') {
            $this->line($stdout);
        }
        if ($stderr !== '') {
            $this->error($stderr);
        }
    }

    private function renderError(VfsError $e): void
    {
        $payload = [
            'ok' => false,
            'error' => [
                'code' => $e->errorCode,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ],
        ];

        if ($this->option('json') || $this->option('structured')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return;
        }

        $this->error($e->errorCode.': '.$e->getMessage());
        if ($e->context !== []) {
            $this->line(json_encode($e->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderPrimitiveResult(string $primitive, User $agent, array $payload, string $response): void
    {
        if (! $this->option('json') && ! $this->option('structured')) {
            $this->line($response);

            return;
        }

        $decoded = json_decode($response, true);
        $envelope = [
            'ok' => is_array($decoded) ? (bool) ($decoded['ok'] ?? true) : false,
            'agent' => ['id' => $agent->id, 'name' => $agent->name],
            'primitive' => 'vfs_'.$primitive,
            'input' => $payload,
            'response' => is_array($decoded) ? $decoded : $response,
        ];

        $this->line(json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostics(string $command): array
    {
        if (! $this->option('spec')) {
            return [];
        }

        $primary = strtok($command, " \t\r\n") ?: '';
        $spec = $primary !== '' ? VfsCommandSpecRegistry::get($primary) : null;

        return $spec ? ['command_spec' => $spec->catalogEntry()] : [];
    }

    private function integerOption(string $name, int $default, int $min): int
    {
        $raw = $this->option($name);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if (! is_int($value) || $value < $min) {
            throw VfsError::invalid("Option --{$name} must be an integer >= {$min}.", [
                'option' => $name,
                'value' => $raw,
            ]);
        }

        return $value;
    }
}
