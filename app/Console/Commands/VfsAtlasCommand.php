<?php

namespace App\Console\Commands;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\ToolRegistry;
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
 * tool. It runs a VFS shell command (through the vfs_exec tool) or a direct VFS
 * primitive as one agent so developers can inspect permissions, paths, command
 * coverage, and structured output quickly.
 *
 * Boundaries (do not relax):
 * - Only runs in the local and testing environments.
 * - The agent is resolved only inside one workspace: --workspace (id or
 *   slug), or the already-bound currentWorkspace. It never guesses
 *   Workspace::first() and never rewrites the agent's workspace_id.
 * - Tools are dispatched with the same permission decision and
 *   ApprovalWrappedTool wrapping the agent runtime applies, so an
 *   approval-gated primitive creates a normal approval instead of running.
 *
 * Use --primitive when the smoke test needs to hit the same direct VFS tool
 * primitive an agent or Code Mode helper would use, such as vfs_patch or vfs_read.
 */
class VfsAtlasCommand extends Command
{
    protected $signature = 'vfs:atlas
        {vfsCommand?* : VFS command tokens, or one quoted VFS command string}
        {--agent=Atlas : Agent name, email, or ID to execute as}
        {--workspace= : Workspace ID or slug to resolve the agent in (defaults to the bound workspace)}
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

    public function handle(ToolRegistry $registry): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('vfs:atlas is a local smoke-test helper and only runs in the local or testing environment.');

            return self::FAILURE;
        }

        $command = $this->commandString();
        $primitive = trim((string) $this->option('primitive'));
        if ($command === '' && $primitive === '') {
            $this->error('Provide a VFS command, for example: php artisan vfs:atlas "ls /tools/vfs"');

            return self::FAILURE;
        }

        $workspace = $this->resolveWorkspace();
        if (! $workspace) {
            $this->error('No workspace selected. Pass --workspace=<id or slug>.');

            return self::FAILURE;
        }

        $agent = $this->resolveAgent((string) $this->option('agent'), $workspace);
        if (! $agent) {
            $this->error("Agent not found in workspace {$workspace->slug}: {$this->option('agent')}");

            return self::FAILURE;
        }

        app()->instance('currentWorkspace', $workspace);

        if ($primitive !== '') {
            return $this->runPrimitive($primitive, $command, $agent, $registry);
        }

        try {
            $tool = $this->dispatchableTool('vfs_exec', $agent, $registry);
            $response = $tool->handle(new ToolRequest([
                'command' => $command,
                'cwd' => (string) $this->option('cwd'),
                'maxEntries' => $this->integerOption('max-entries', 100, 1),
                'maxDepth' => $this->integerOption('max-depth', 3, 0),
                'maxBytes' => $this->integerOption('max-bytes', 100000, 1),
                'maxFiles' => $this->integerOption('max-files', 100, 1),
                'maxMatches' => $this->integerOption('max-matches', 100, 1),
                'source' => 'artisan:vfs:atlas',
            ]));
        } catch (VfsError $e) {
            $this->renderError($e);

            return self::FAILURE;
        }

        $result = json_decode($response, true);
        if (! is_array($result)) {
            // ApprovalWrappedTool answers in prose when the primitive itself
            // is approval-gated; nothing ran.
            $this->line($response);

            return self::FAILURE;
        }
        if (($result['ok'] ?? true) === false && is_array($result['error'] ?? null)) {
            $this->renderError(new VfsError(
                (string) ($result['error']['code'] ?? 'internal_error'),
                (string) ($result['error']['message'] ?? 'VFS command failed.'),
                is_array($result['error']['context'] ?? null) ? $result['error']['context'] : [],
            ));

            return self::FAILURE;
        }

        $this->renderResult($command, $agent, $result);

        return ($result['ok'] ?? true) === true ? self::SUCCESS : self::FAILURE;
    }

    private function runPrimitive(string $primitive, string $command, User $agent, ToolRegistry $registry): int
    {
        try {
            $tool = $this->makePrimitiveTool($primitive, $agent, $registry);
            $payload = $this->primitivePayload();
            if ($this->canonicalPrimitiveName($primitive) === 'exec' && ! isset($payload['command']) && $command !== '') {
                $payload['command'] = $command;
            }

            $response = $tool->handle(new ToolRequest($payload));
            $this->renderPrimitiveResult($this->canonicalPrimitiveName($primitive), $agent, $payload, $response);
            $decoded = json_decode($response, true);

            return ! is_array($decoded) || ($decoded['ok'] ?? true) === false
                ? self::FAILURE
                : self::SUCCESS;
        } catch (VfsError $e) {
            $this->renderError($e);

            return self::FAILURE;
        }
    }

    /**
     * Resolve the tool exactly as the runtime would hand it to this agent:
     * re-evaluate permission now, refuse denied tools, and wrap
     * approval-gated ones in ApprovalWrappedTool.
     */
    private function dispatchableTool(string $slug, User $agent, ToolRegistry $registry): Tool
    {
        $dispatch = $registry->resolveScriptToolForDispatch($slug, $agent);
        if ($dispatch['decision'] === 'deny') {
            throw VfsError::permissionDenied($slug, $dispatch['reason']);
        }

        $tool = $dispatch['decision'] === 'allow'
            ? ($dispatch['tool'] ?? null)
            : $registry->instantiateToolBySlug($slug, $agent);
        if (! $tool instanceof Tool) {
            throw VfsError::invalid("Tool not available: {$slug}", ['tool' => $slug]);
        }

        return $dispatch['decision'] === 'approval_required'
            ? new ApprovalWrappedTool($tool, $agent, $slug, $registry->getToolDefinitionBySlug($slug) ?? [])
            : $tool;
    }

    private function commandString(): string
    {
        $parts = $this->argument('vfsCommand');

        return trim(implode(' ', array_map('strval', $parts)));
    }

    /**
     * The explicit --workspace (id or slug), else the bound workspace. Never
     * falls back to an arbitrary workspace.
     */
    private function resolveWorkspace(): ?Workspace
    {
        $identifier = trim((string) $this->option('workspace'));
        if ($identifier !== '') {
            return Workspace::query()
                ->whereKey($identifier)
                ->orWhere('slug', $identifier)
                ->first();
        }

        $bound = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        return $bound instanceof Workspace ? $bound : null;
    }

    /** Resolve an agent by id, name, or email strictly inside $workspace. */
    private function resolveAgent(string $identifier, Workspace $workspace): ?User
    {
        return User::query()
            ->where('type', 'agent')
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($identifier): void {
                $query->whereKey($identifier)
                    ->orWhere('name', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();
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

    private function makePrimitiveTool(string $primitive, User $agent, ToolRegistry $registry): Tool
    {
        $supported = ['exec', 'ls', 'stat', 'read', 'write', 'patch', 'find', 'rg', 'search', 'count', 'exists', 'mkdir', 'cp', 'mv', 'rm'];
        $name = $this->canonicalPrimitiveName($primitive);
        if (! in_array($name, $supported, true)) {
            throw VfsError::invalid('Unknown VFS primitive.', [
                'primitive' => $primitive,
                'supported' => $supported,
            ]);
        }

        return $this->dispatchableTool('vfs_'.$name, $agent, $registry);
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
