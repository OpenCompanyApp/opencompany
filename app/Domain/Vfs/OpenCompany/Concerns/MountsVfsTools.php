<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Runtime tool-documentation mount.
 *
 * These files describe callable VFS and mruby Code Mode surfaces; they do not execute tools
 * and must stay permission-neutral documentation.
 */
trait MountsVfsTools
{
    /**
     * @return list<VfsEntry>
     */
    private function listTools(string $path): array
    {
        if (in_array($path, ['/tools/apps', '/tools/integrations', '/tools/mcp'], true)) {
            return [
                new VfsEntry('README.md', $path.'/README.md', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
                new VfsEntry('index.json', $path.'/index.json', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
            ];
        }

        if ($path === '/tools/vfs') {
            return [
                new VfsEntry('commands.json', '/tools/vfs/commands.json', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
                new VfsEntry('commands.md', '/tools/vfs/commands.md', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
                new VfsEntry('commands', '/tools/vfs/commands', 'directory', capabilities: ['browse', 'read', 'search']),
                new VfsEntry('code.json', '/tools/vfs/code.json', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
                new VfsEntry('examples.md', '/tools/vfs/examples.md', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
                new VfsEntry('README.md', '/tools/vfs/README.md', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
            ];
        }

        if ($path === '/tools/vfs/commands') {
            return array_map(
                fn (string $command): VfsEntry => new VfsEntry($command, "/tools/vfs/commands/{$command}", 'directory', capabilities: ['browse', 'read', 'search']),
                array_keys(VfsCommandExecutor::commandCatalog()),
            );
        }

        if (preg_match('#^/tools/vfs/commands/([^/]+)$#', $path, $matches) === 1) {
            VfsCommandHelp::commandEntry($matches[1]);

            return [
                new VfsEntry("{$matches[1]}.json", "{$path}/{$matches[1]}.json", 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
                new VfsEntry("{$matches[1]}.md", "{$path}/{$matches[1]}.md", 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
            ];
        }

        if ($path !== '/tools') {
            throw VfsError::notReadable($path);
        }

        return [
            new VfsEntry('catalog.md', '/tools/catalog.md', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
            new VfsEntry('vfs.md', '/tools/vfs.md', 'file', capabilities: ['read', 'search'], metadata: $this->toolDocumentMetadata()),
            new VfsEntry('vfs', '/tools/vfs', 'directory', capabilities: ['browse', 'read', 'search']),
            new VfsEntry('apps', '/tools/apps', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('integrations', '/tools/integrations', 'directory', capabilities: ['browse', 'read']),
            new VfsEntry('mcp', '/tools/mcp', 'directory', capabilities: ['browse', 'read']),
        ];
    }

    private function readTools(User $agent, string $path, VfsBudget $budget): string
    {
        $content = null;

        if ($path === '/tools') {
            $content = $this->entriesToText($this->listTools($path));
        }

        if ($content === null && $path === '/tools/vfs') {
            $content = $this->entriesToText($this->listTools($path));
        }

        if ($content === null && ($path === '/tools/apps' || $path === '/tools/apps/README.md')) {
            $content = "OpenCompany app tools are exposed through the agent tool registry and Code Mode docs.\n\nRead `/tools/apps/index.json` for the permission-filtered built-in app/tool groups visible to this agent. Use `/tools/catalog.md`, `/tools/vfs/commands.json`, and `code_read_doc(\"overview\")` for callable surfaces.";
        }

        if ($content === null && $path === '/tools/apps/index.json') {
            $content = json_encode($this->toolCatalogIndex($agent, false), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($content === null && ($path === '/tools/integrations' || $path === '/tools/integrations/README.md')) {
            $content = 'Integration tools are permission-aware app tools. Read `/tools/integrations/index.json` for enabled integration groups and callable tool metadata visible to this agent.';
        }

        if ($content === null && $path === '/tools/integrations/index.json') {
            $content = json_encode($this->toolCatalogIndex($agent, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($content === null && ($path === '/tools/mcp' || $path === '/tools/mcp/README.md')) {
            $content = 'MCP tools are external connector tools. Use the app tool catalog and MCP CLI documentation for enabled server-specific schemas; the VFS exposes this node for discovery only.';
        }

        if ($content === null && $path === '/tools/mcp/index.json') {
            $content = json_encode([
                'kind' => 'mcp_tools',
                'note' => 'MCP tools are included in /tools/integrations/index.json when enabled for this workspace and agent.',
                'groups' => array_values(array_filter(
                    $this->toolCatalogIndex($agent, true)['groups'],
                    fn (array $group): bool => str_starts_with((string) ($group['name'] ?? ''), 'mcp')
                )),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($content === null && $path === '/tools/vfs/commands.json') {
            $content = json_encode(VfsCommandExecutor::commandCatalog(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($content === null && $path === '/tools/vfs/commands.md') {
            $content = VfsCommandHelp::listMarkdown();
        }

        if ($content === null && preg_match('#^/tools/vfs/commands/([^/]+)$#', $path, $matches) === 1) {
            $content = $this->entriesToText($this->listTools($path));
        }

        if ($content === null && preg_match('#^/tools/vfs/commands/([^/]+)/\1\.json$#', $path, $matches) === 1) {
            $content = json_encode(VfsCommandHelp::commandEntry($matches[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($content === null && preg_match('#^/tools/vfs/commands/([^/]+)/\1\.md$#', $path, $matches) === 1) {
            $content = VfsCommandHelp::commandMarkdown($matches[1]);
        }

        if ($content === null && $path === '/tools/vfs/code.json') {
            $content = json_encode([
                'namespace' => 'app.vfs',
                'runtime' => 'mruby Code Mode (code_exec)',
                'calling_convention' => 'Keyword arguments; snake_case keys map to the tool schema (max_matches -> maxMatches).',
                'primitive_tools' => ['exec', 'patch', 'write'],
                'return_envelope' => 'Hash with ok (boolean), result (hash) and error (hash, optional).',
                'exec_options' => ['response_format' => 'default|structured', 'include_command_spec' => 'boolean'],
                'helpers' => [
                    'shell(command:, response_format:)  # vfs_exec; `exec` is reserved in Ruby',
                    'stat(path:)',
                    'ls(path:, limit:, cursor:)',
                    'exists(path:)',
                    'read(path:, max_bytes:, cursor:)',
                    'rg(pattern:, paths:, max_matches:)',
                    'find(paths:, name:, type:)',
                    'search(query:, paths:)',
                    'write(path:, content:, mode:)',
                    'patch(path:, patch:)',
                    'mkdir(path:)',
                    'cp(source:, destination:)',
                    'mv(source:, destination:)',
                    'rm(path:)',
                    'count(path:)',
                ],
                'permission_model' => 'Helpers execute through the same primitive VFS permissions (vfs_exec for reads, vfs_write for writes) and per-path resource checks.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($content === null && $path === '/tools/vfs/examples.md') {
            $content = implode("\n", [
                '# VFS Examples',
                '',
                '```sh',
                'ls /',
                'tree /docs -L 2',
                'rg "refund" /docs /files',
                'grep -o refund /docs --max-depth 2',
                'cat /tasks/counts.json | jq .open',
                'jq ".items[0]" /files/generated/data.json',
                'stat /docs/by-id/{id}',
                'find /docs -name "*.md" | xargs grep -n refund',
                'xxd -p /files/generated/report.md | head -c 32',
                'cat /files/logs.gz | zgrep error',
                'strings -n 5 /files/generated/upload.bin',
                'head -c4 /files/generated/report.md',
                'mktemp -p /files/tmp scratch.XXXXXX',
                'truncate -s 0 /files/generated/scratch.txt',
                'printf "alpha\nbeta\n" | grep -n alpha && date',
                '```',
                '',
                'Direct patch tools can update explicitly scoped docs, writable files, limited task/list fields, table row data, and relationship-scoped agent identity/memory files:',
                '',
                '`vfs_patch(path: "/tasks/by-id/{id}", patch: \'{"status":"completed","priority":"high"}\')`',
                '`vfs_patch(path: "/tables/customers/rows/by-id/{row}.json", patch: \'{"data":{"status":"active"}}\')`',
                '`vfs_patch(path: "/agents/by-id/{agent}/identity/IDENTITY.md", patch: \'{"search":"old","replace":"new"}\')`',
                '',
                'In mruby Code Mode (`code_exec`), the same primitives are `app.vfs.*` with keyword arguments:',
                '',
                '```ruby',
                'docs = app.vfs.ls(path: "/docs", limit: 20)',
                'docs_count = docs[:result][:items].length',
                'hits = app.vfs.rg(pattern: "refund", paths: ["/docs", "/files"], max_matches: 20)',
                'first = hits[:result][:matches].first',
                'doc = app.vfs.read(path: first[:path], max_bytes: 4000) if first',
                'app.vfs.write(path: "/files/generated/note.md", content: "hello", mode: "overwrite")',
                '{docs: docs_count, hits: hits[:result][:matches].length}',
                '```',
            ]);
        }

        if ($content === null && ($path === '/tools/vfs.md' || $path === '/tools/vfs/README.md')) {
            $content = implode("\n\n", [
                'The VFS is a permission-aware unix-like filesystem over OpenCompany workspace data.',
                'Use `vfs_exec` for command-shaped inspection, `vfs_patch` for targeted edits, `vfs_write` for raw `/files` writes, and `app.vfs.*` helpers inside mruby Code Mode (`code_exec`) for structured multi-step workflows. VFS composes primitive permissions with existing domain permissions instead of creating duplicate VFS-only controls: table row patches require `vfs_patch` plus `update_table_row`; agent identity patches require `vfs_patch` plus `update_agent_identity_file`; memory patches are relationship-scoped. If any required permission needs approval, VFS creates a normal approval request and approved replay revalidates permissions before mutating.',
                'Discover command support with `help`, `help <command>`, `man <command>`, `<command> --help`, `which <command>`, `type <command>`, `command -v <command>`, `/tools/vfs/commands.json`, and `/tools/vfs/commands/<command>/<command>.json`.',
                '`grep` is literal; `rg` is regex. Path globs expand only when unquoted and currently cover normal `*`, `?`, and character-class patterns against one parent directory. No host `$VAR`, command substitution, process substitution, brace expansion, or `**` globstar is supported.',
                'Redirection support is explicit: `<` reads bounded stdin from a VFS path; `>`, `>>`, `1>`, and `1>>` write stdout to `/files`; `/dev/null` discards stdout; `2>/dev/null` suppresses stderr; `2>&1` merges stderr into stdout with shell-like order sensitivity. Stderr file targets such as `2>/files/error.txt`, heredocs, and process substitution are rejected.',
                'Budgets are hard limits for returned/scanned work. `vfs_exec` accepts `maxEntries`, `maxDepth`, `maxBytes`, `maxFiles`, and `maxMatches`; structured helpers accept camelCase or snake_case aliases. Directory counts are sampled unless `count_is_exact` is true; inspect `returned`, `limit`, `truncated`, `next_cursor`, `skipped`, `count_mode`, `sampled_count`, and `total_count`. Pass `next_cursor` back as `cursor` to continue `vfs_ls`, `vfs_find`, and `vfs_count`; `vfs_read` also pages channel transcripts, table-row NDJSON, and the workspace roster. Those generated reads emit only complete records within `maxBytes`; an oversized leading record reports `context.minimum_bytes` for a safe retry.',
                '`jq` is a safe subset for JSON projections: `.`, `.field`, `.[0]`, `.items[]`, `.items[].name`, `.["key"]`, `length`, sorted `keys`, `has("key")`, `map(.field)`, `select(.field == "value")`, simple numeric arithmetic, stdin, NDJSON, and one or more file arguments. `-r` is accepted; scalar output is raw by default. `-c` emits compact JSON.',
                'Safe binary-adjacent inspection is available through bounded text APIs: `xxd`, `strings`, `iconv`, and gzip text pipelines through `gzip`, `gunzip`, `zcat`, and `zgrep`. `tar`, `zip`, and `unzip` support bounded archive create/list/print/extract flows; extraction writes only under `/files` and requires `vfs_write`. Mutating shell commands through `vfs_exec` compose `vfs_exec` plus `vfs_write`, so approval on either permission is respected. `split`, `csplit`, `mktemp`, and non-symlink `ln` use the same write permission model. `ln -s` is recognized but rejected until VFS has first-class symlink entries.',
                'Code Mode helpers return the same `{ok, result, error}` hash as the direct tools; list-like results live under `result[:items]` or `result[:matches]`. `app.vfs.shell(command: "...", response_format: "structured", include_command_spec: true)` keeps stdout text but enriches `data` and diagnostics for programmatic workflows.',
                '`vfs_write` defaults to create. Scripts should pass `mode: "create"` or `mode: "overwrite"` explicitly.',
            ]);
        }

        if ($content === null && $path !== '/tools/catalog.md') {
            throw VfsError::notFound($path);
        }

        $content ??= "Tool catalog is exposed in the agent system prompt and Code Mode docs.\n\nUse code_read_doc(\"overview\") and code_read_doc(\"vfs\") for programmable tool access.";

        return VfsText::fit($content, $budget, $path);
    }
}
