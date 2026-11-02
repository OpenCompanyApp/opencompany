<?php

namespace App\Domain\Vfs\Spec;

use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsPath;

/**
 * The one declaration of the agent-facing VFS operation surface.
 *
 * Owns, per `vfs_*` tool: names on every surface (tool slug, Ruby name, CLI
 * verb), effect, governance action class, governing and composed permissions,
 * parameter schema with aliases and alias defaults, result fields, error
 * codes, examples and notes. Everything below is generated from it and must
 * not be hand-maintained:
 *
 * - each tool's description() and schema() (VfsToolSchemaFactory),
 * - VfsToolProvider meta (name, description, type, icon, scriptOnly,
 *   permission borrowing),
 * - the Code Mode alias maps in CodeApiDocGenerator (RUBY_ALIASES below),
 * - `/tools/vfs/code.json`, `/tools/vfs/openapi.json`, the reference
 *   sections of `resources/code-docs/vfs.md` and `docs/tools/vfs.md`
 *   (VfsSpecDocumentation; `php artisan vfs:spec --write-docs`; a drift test
 *   fails when they differ).
 *
 * It does not own shell command semantics (VfsCommandSpecRegistry, Stream A),
 * authorization (OpenCompanyVfs + VfsMutationPolicy) or tool behavior.
 *
 * Agent breadcrumb: keep descriptions short and literal; they are paid for in
 * every prompt. Never reorder parameters casually: schema order is the
 * positional argument order ScriptBridge uses in Code Mode.
 */
final class VfsOperationSpecRegistry
{
    /**
     * Host-owned argument defaults per Code Mode alias path (caller values
     * win). CodeBridge applies them through
     * CodeApiDocGenerator::ALIAS_ARGUMENT_DEFAULTS, which is this constant, so
     * the defaults hold even when the generator is mocked.
     *
     * - `grep` is literal like the shell's grep; `rg` defaults to regex.
     * - `cat` returns raw text like the shell's cat; `read` numbers lines.
     *
     * @var array<string, array<string, mixed>>
     */
    public const RUBY_ALIAS_DEFAULTS = [
        'vfs.cat' => ['raw' => true],
        'vfs.grep' => ['regex' => false],
    ];

    /**
     * Code Mode alias path => target path. `shell` exists because `exec` is
     * rejected by the mruby profile (Kernel#exec); `app.call("vfs.exec", ...)`
     * still works.
     *
     * @var array<string, string>
     */
    public const RUBY_ALIASES = [
        'vfs.shell' => 'vfs.exec',
        'vfs.cat' => 'vfs.read',
        'vfs.grep' => 'vfs.rg',
    ];

    /**
     * Stable VFS error codes and when agents see them. `error.code` is the
     * only thing callers should branch on; messages are for humans.
     *
     * @var array<string, string>
     */
    public const ERROR_CODES = [
        'not_found' => 'The path does not exist or is not visible to you. Check the parent with ls; context may carry did_you_mean.',
        'not_readable' => 'The path exists but cannot be read as content (for example a directory); list it instead.',
        'not_writable' => 'The path is read-only or generated (snapshots, indexes, catalogs); write to a canonical writable path.',
        'permission_denied' => 'A required permission or scope is missing; context names it. Do not retry or work around it; tell the user what is needed.',
        'unsupported' => 'The command, flag, format or root is not supported; the message names an alternative.',
        'approval_required' => 'A gated permission created an approval request (context.approval_id). Not a failure: continue other work; the outcome is posted back when a human decides. Do not retry.',
        'invalid_request' => 'Malformed input: missing field, bad budget or cursor, text not found, binary content, or a missing version; context.hint says what to change.',
        'conflict' => 'The object changed since your read (context.current_version; rows add changed_fields). Read it again and re-apply your change.',
        'ambiguous_match' => 'old_text/search matched more than once (context.count, context.lines). Add surrounding text to make it unique or set replace_all.',
        'internal_error' => 'Unexpected server error; context.correlation_id identifies the log entry. Retrying the same call is unlikely to help.',
    ];

    /**
     * Roots whose text content vfs_edit can change (the text-backed roots
     * OpenCompanyVfs::patch() accepts search/replace for). Record roots go
     * through vfs_patch.
     */
    public const TEXT_EDIT_ROOTS = ['docs', 'files', 'agents', 'me'];

    /** @var array<string, VfsOperationSpec>|null */
    private static ?array $specs = null;

    /**
     * Every operation, keyed by tool slug, in declaration order (direct tools
     * first, in the order the model sees them).
     *
     * @return array<string, VfsOperationSpec>
     */
    public static function all(): array
    {
        return self::$specs ??= self::build();
    }

    /**
     * @return array<string, VfsOperationSpec>
     */
    public static function direct(): array
    {
        return array_filter(self::all(), fn (VfsOperationSpec $spec): bool => $spec->direct);
    }

    public static function get(string $toolSlug): VfsOperationSpec
    {
        return self::all()[$toolSlug] ?? throw new \InvalidArgumentException("Unknown VFS operation: {$toolSlug}");
    }

    public static function find(string $toolSlug): ?VfsOperationSpec
    {
        return self::all()[$toolSlug] ?? null;
    }

    /**
     * Resolve the operation by tool slug, operation name, Ruby name or CLI
     * verb (`vfs_read`, `read`, `cat` alias excluded).
     */
    public static function resolve(string $name): ?VfsOperationSpec
    {
        $name = strtolower(trim($name));
        foreach (self::all() as $spec) {
            if (in_array($name, [$spec->toolSlug, $spec->name, $spec->rubyName, $spec->cliVerb], true)) {
                return $spec;
            }
        }

        return null;
    }

    /**
     * Governance action class for one concrete call.
     *
     * Approximation rules (documented, conservative):
     * - exec: `read` when VfsCommandExecutor::classifyEffect() says read;
     *   `delete` when a pipeline names rm/rmdir/unlink; else reversible_edit.
     * - path-scoped writes: agent identity and memory files are `governance`
     *   (they change who the agent is); everything else reversible_edit.
     *   Deletes are always `delete` (VFS deletes move to trash, never purge).
     *
     * @return 'read'|'reversible_edit'|'delete'|'structural'|'governance'
     */
    public static function actionClassFor(string $toolSlug, ?string $path = null, ?string $command = null): string
    {
        $spec = self::get($toolSlug);

        if ($spec->actionClass === 'per_command') {
            $command = (string) $command;
            if (app(VfsCommandExecutor::class)->classifyEffect($command) === 'read') {
                return 'read';
            }

            return preg_match('/(^|[\s|;&(])(rm|rmdir|unlink)(\s|$)/', $command) === 1 ? 'delete' : 'reversible_edit';
        }

        if ($spec->actionClass === 'per_path') {
            $normalized = VfsPath::normalize((string) $path);

            return preg_match('#^/(agents/[^/]+(/[^/]+)?|me)/(identity|memory)(/|$)#', $normalized) === 1
                ? 'governance'
                : 'reversible_edit';
        }

        /** @var 'read'|'reversible_edit'|'delete'|'structural'|'governance' */
        return $spec->actionClass;
    }

    /**
     * @return array<string, VfsOperationSpec>
     */
    private static function build(): array
    {
        $specs = [
            self::execSpec(),
            self::readSpec(),
            self::editSpec(),
            self::writeSpec(),
            self::patchSpec(),
            self::statSpec(),
            self::lsSpec(),
            self::findSpec(),
            self::rgSpec(),
            self::existsSpec(),
            self::mkdirSpec(),
            self::cpSpec(),
            self::mvSpec(),
            self::rmSpec(),
        ];

        $keyed = [];
        foreach ($specs as $spec) {
            $keyed[$spec->toolSlug] = $spec;
        }

        return $keyed;
    }

    private static function execSpec(): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: 'exec',
            toolSlug: 'vfs_exec',
            rubyName: 'shell',
            cliVerb: 'exec',
            title: 'VFS Exec',
            icon: 'ph:terminal-window',
            direct: true,
            effect: 'per_command',
            registryType: 'read',
            actionClass: 'per_command',
            permission: 'vfs_exec',
            composedPermissions: [
                'read commands' => ['vfs_exec'],
                '/files mutations (mkdir, touch, tee, cp, mv, rm, >, >>)' => ['vfs_exec', 'vfs_write'],
            ],
            summary: 'Run unix-like commands over the virtual filesystem (ls, find, rg, cat, jq, ...).',
            description: 'Run a unix-like command in the OpenCompany virtual filesystem (not a host shell). '
                .'Navigate and inspect with ls, tree, find, cat, head, tail, grep (literal), rg (regex), wc, stat, jq, diff and more; '
                .'pipes, ;, &&, ||, globs and > or >> into /files work. '
                .'/files mutations (mkdir, touch, tee, cp, mv, rm) also need vfs_write. '
                .'Returns {ok, exit_code, stdout, cwd}; when truncated is true, hint says how to continue '
                .'(pass next_cursor as cursor, raise a budget, or narrow the path). '
                .'Start with `cat /README.md` or `help`. Use vfs_read and vfs_edit for file content.',
            parameters: [
                new VfsParameterSpec('command', 'string', 'Command line, e.g. `ls /docs`, `rg -n "refund" /docs`, `cat /tasks/counts.json | jq .open`.', required: true),
                new VfsParameterSpec('cwd', 'string', 'Virtual working directory.', default: '/'),
                new VfsParameterSpec('cursor', 'string', 'next_cursor from a previous truncated result (same command) to continue the listing or paged file.'),
                new VfsParameterSpec('responseFormat', 'string', 'structured adds parsed data (paths, matches, stat) next to stdout.', enum: ['default', 'structured'], default: 'default'),
                new VfsParameterSpec('maxEntries', 'integer', 'Entries per listing command.', default: 100, aliases: ['limit']),
                new VfsParameterSpec('maxDepth', 'integer', 'Recursion depth for tree/find/recursive search.', default: 3),
                new VfsParameterSpec('maxBytes', 'integer', 'Bytes read from one file.', default: 100000),
                new VfsParameterSpec('maxFiles', 'integer', 'Files scanned by search commands.', default: 100),
                new VfsParameterSpec('maxMatches', 'integer', 'Matches returned by search commands.', default: 100),
                new VfsParameterSpec('maxLineLength', 'integer', 'Characters per output line before it is shortened.', default: 2000),
                new VfsParameterSpec('includeCommandSpec', 'boolean', 'Add the primary command spec under diagnostics.command_spec.', default: false),
            ],
            resultFields: [
                'ok' => 'false when the command line failed (error holds the reason)',
                'exit_code' => 'status of the last command (0 success)',
                'stdout' => 'command output, once',
                'cwd' => 'working directory after the command',
                'truncated' => 'present and true when output was cut',
                'hint' => 'with truncated: how to continue',
                'next_cursor' => 'pass back as cursor to continue',
                'error' => '{code, message, context} when ok is false',
                'data' => 'responseFormat=structured only: parsed result',
                'offloaded_path' => 'set when the full output was saved to a file (see offloading)',
            ],
            errorCodes: ['not_found', 'not_readable', 'not_writable', 'permission_denied', 'unsupported', 'approval_required', 'invalid_request', 'conflict', 'internal_error'],
            examples: [
                '{"command": "ls /"}',
                '{"command": "rg -n \"refund\" /docs"}',
                '{"command": "find /docs -name \"*.md\" | head -20"}',
                '{"command": "ls /tables/customers/rows/by-id", "cursor": "<next_cursor>"}',
            ],
            rubyExamples: [
                'root = app.vfs.shell(command: "ls /")',
                'hits = app.vfs.shell(command: "rg -n refund /docs", max_matches: 20)',
            ],
            notes: [
                'grep is literal and rg is regex, in the shell and in Code Mode.',
                'No $VAR, command substitution, process substitution or host programs.',
                'A command that cannot run fails the whole line with error; a later group after ; or || still runs and exit_code reflects the last command.',
            ],
            canMutateWith: 'vfs_write',
        );
    }

    private static function readSpec(): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: 'read',
            toolSlug: 'vfs_read',
            rubyName: 'read',
            cliVerb: 'read',
            title: 'VFS Read',
            icon: 'ph:file-text',
            direct: true,
            effect: 'read',
            registryType: 'read',
            actionClass: 'read',
            permission: 'vfs_exec',
            composedPermissions: ['all paths' => ['vfs_exec']],
            summary: 'Read a text file with line numbers, paging and its version.',
            description: 'Read a text file. Lines come numbered like cat -n ("     N<TAB>text"); the prefix is not part of the file. '
                .'Page with offset (1-based line) and limit (lines, default 2000); paged collection files '
                .'(rows.ndjson, channel day transcripts, members.json) page with cursor instead. '
                .'Returns version (pass it to vfs_edit, vfs_patch or vfs_write), total_lines, and truncated with a next_offset hint. '
                .'raw: true returns the text without line numbers. Directories and binary files fail; use vfs_exec ls or xxd.',
            parameters: [
                new VfsParameterSpec('path', 'string', 'File path, e.g. /docs/handbook/onboarding or /files/report.md.', required: true),
                new VfsParameterSpec('offset', 'integer', 'First line to return (1-based).', default: 1),
                new VfsParameterSpec('limit', 'integer', 'Lines to return (records for paged collection files).', default: 2000),
                new VfsParameterSpec('cursor', 'string', 'next_cursor from the previous page of a paged collection file.'),
                new VfsParameterSpec('maxBytes', 'integer', 'Byte cap for the returned text.', default: 50000),
                new VfsParameterSpec('raw', 'boolean', 'Return text without line numbers.', default: false),
            ],
            resultFields: [
                'path' => 'normalized path',
                'canonical_path' => 'stable path to reuse (by-id where available)',
                'content' => 'the requested lines (numbered unless raw)',
                'bytes' => 'byte length of content',
                'version' => 'version of exactly these bytes; null for generated files',
                'offset' => 'first line returned',
                'returned_lines' => 'lines returned',
                'total_lines' => 'lines in the whole file (ordinary files)',
                'truncated' => 'more content exists',
                'next_offset' => 'offset to continue with (ordinary files)',
                'next_cursor' => 'cursor to continue with, null on the last page (paged collection files)',
                'returned' => 'records returned (paged collection files)',
                'hint' => 'how to continue when truncated',
                'capabilities' => 'what you may do with the path (read, patch, write, ...)',
            ],
            errorCodes: ['not_found', 'not_readable', 'permission_denied', 'invalid_request', 'internal_error'],
            examples: [
                '{"path": "/docs/handbook/onboarding"}',
                '{"path": "/files/logs/app.log", "offset": 2001, "limit": 500}',
                '{"path": "/tables/customers/rows.ndjson", "limit": 100, "cursor": "<next_cursor>"}',
            ],
            rubyExamples: [
                'page = app.vfs.read(path: "/docs/by-id/DOC_ID", limit: 200)',
                'text = app.vfs.cat(path: "/files/report.md")[:result][:content]  # raw: true',
            ],
            notes: [
                'version describes the bytes returned by this same read.',
                'Paged collection files return raw records; offset applies to ordinary files only.',
                'A line longer than maxBytes is cut at maxBytes (line_cut: true); use vfs_exec head -c or a larger maxBytes.',
            ],
        );
    }

    private static function editSpec(): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: 'edit',
            toolSlug: 'vfs_edit',
            rubyName: 'edit',
            cliVerb: 'edit',
            title: 'VFS Edit',
            icon: 'ph:pencil-simple-line',
            direct: true,
            effect: 'write',
            registryType: 'write',
            actionClass: 'per_path',
            permission: 'vfs_patch',
            composedPermissions: [
                '/docs, /files' => ['vfs_patch'],
                '/agents/{agent}/identity/*' => ['vfs_patch', 'update_agent_identity_file'],
                '/agents/{agent}/memory/*' => ['vfs_patch'],
            ],
            summary: 'Edit text files by exact replacement: several edits, all or nothing, with dry-run diff.',
            description: 'Edit a text file (docs, /files, agent identity and memory) by exact string replacement. '
                .'edits: [{old_text, new_text, replace_all?}] apply in order, all or nothing. '
                .'old_text must match exactly once (copy it from vfs_read without the line-number prefix) unless replace_all; '
                .'otherwise nothing is written and the error is not found or ambiguous_match with line numbers. '
                .'Pass version from vfs_read: if the file changed since, edits still apply when each old_text still matches exactly once '
                .'(result.stale_base), else conflict. dry_run: true returns a unified diff without writing. '
                .'Table rows and task/list fields use vfs_patch.',
            parameters: [
                new VfsParameterSpec('path', 'string', 'Text file to edit.', required: true),
                new VfsParameterSpec('edits', 'array', 'Edits applied in order to the result of the previous edit.', required: true, items: [
                    new VfsParameterSpec('old_text', 'string', 'Exact current text to replace (non-empty).', required: true),
                    new VfsParameterSpec('new_text', 'string', 'Replacement text (may be empty to delete).', required: true),
                    new VfsParameterSpec('replace_all', 'boolean', 'Replace every occurrence instead of requiring exactly one.', default: false),
                ]),
                new VfsParameterSpec('version', 'string', 'version from vfs_read; enables conflict detection.'),
                new VfsParameterSpec('dry_run', 'boolean', 'Return the unified diff without writing or requesting approval.', default: false),
            ],
            resultFields: [
                'status' => 'updated, unchanged or dry_run',
                'path' => 'normalized path',
                'canonical_path' => 'stable path',
                'version' => 'new version (current version for dry_run)',
                'edits_applied' => 'number of edits',
                'replacements' => 'total replacements across edits',
                'stale_base' => 'true when applied against content newer than your version',
                'diff' => 'dry_run only: unified diff',
            ],
            errorCodes: ['not_found', 'not_readable', 'not_writable', 'permission_denied', 'unsupported', 'approval_required', 'invalid_request', 'conflict', 'ambiguous_match', 'internal_error'],
            examples: [
                '{"path": "/docs/handbook/onboarding", "version": "<version>", "edits": [{"old_text": "within 14 days", "new_text": "within 30 days"}]}',
                '{"path": "/files/notes.md", "edits": [{"old_text": "TODO", "new_text": "DONE", "replace_all": true}], "dry_run": true}',
            ],
            rubyExamples: [
                'app.vfs.edit(path: "/docs/by-id/DOC_ID", version: v, edits: [{old_text: "draft", new_text: "final"}])',
            ],
            notes: [
                'Line numbers in ambiguous_match are 1-based start lines in the content as it was before that edit.',
                'A failing edit reports context.edit_index (0-based); no edit is written.',
                'Governed by the vfs_patch permission: vfs_edit has no separate capability toggle.',
            ],
        );
    }

    private static function writeSpec(): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: 'write',
            toolSlug: 'vfs_write',
            rubyName: 'write',
            cliVerb: 'write',
            title: 'VFS Write',
            icon: 'ph:file-plus',
            direct: true,
            effect: 'write',
            registryType: 'write',
            actionClass: 'per_path',
            permission: 'vfs_write',
            composedPermissions: ['/files' => ['vfs_write']],
            summary: 'Create, overwrite or append whole text content where a path allows writes.',
            description: 'Create, overwrite or append whole text content where the path allows writes (stat lists the write capability; today /files). '
                .'mode: create (default, fails if the file exists), overwrite (requires version from vfs_read when the file exists), '
                .'or append (adds to the end, creating the file if missing). '
                .'For changes inside an existing file use vfs_edit.',
            parameters: [
                new VfsParameterSpec('path', 'string', 'Path to write, e.g. /files/reports/q3.md.', required: true),
                new VfsParameterSpec('content', 'string', 'Text to write.', required: true),
                new VfsParameterSpec('mode', 'string', 'create, overwrite or append.', enum: ['create', 'overwrite', 'append'], default: 'create'),
                new VfsParameterSpec('version', 'string', 'version from vfs_read; required to overwrite an existing file, optional for append.'),
            ],
            resultFields: [
                'status' => 'created or updated',
                'path' => 'normalized path',
                'canonical_path' => 'stable path',
                'version' => 'new version',
                'bytes' => 'size after the write',
            ],
            errorCodes: ['not_found', 'not_writable', 'permission_denied', 'approval_required', 'invalid_request', 'conflict', 'internal_error'],
            examples: [
                '{"path": "/files/reports/q3.md", "content": "# Q3\n"}',
                '{"path": "/files/reports/q3.md", "mode": "overwrite", "version": "<version>", "content": "# Q3 (final)\n"}',
                '{"path": "/files/logs/run.log", "mode": "append", "content": "done\n"}',
            ],
            rubyExamples: [
                'app.vfs.write(path: "/files/generated/report.md", content: "# Report\n", mode: "create")',
            ],
            notes: [
                'append is version-checked against the content it read, so concurrent appends never lose data (one fails with conflict).',
                'An idempotency_key argument is forwarded to the write path unchanged.',
            ],
        );
    }

    private static function patchSpec(): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: 'patch',
            toolSlug: 'vfs_patch',
            rubyName: 'patch',
            cliVerb: 'patch',
            title: 'VFS Patch',
            icon: 'ph:file-dashed',
            direct: true,
            effect: 'write',
            registryType: 'write',
            actionClass: 'per_path',
            permission: 'vfs_patch',
            composedPermissions: [
                '/tasks, /lists, /docs, /files' => ['vfs_patch'],
                '/tables/{table}/rows/by-id/*' => ['vfs_patch', 'update_table_row'],
                '/agents/{agent}/identity/*' => ['vfs_patch', 'update_agent_identity_file'],
            ],
            summary: 'Patch structured records: task/list fields and table rows, version-checked.',
            description: 'Patch structured records. Task and list item fields: flat JSON, e.g. {"status":"completed"}. '
                .'Table rows (/tables/{table}/rows/by-id/{id}.json): {"data":{"Column":"value"},"base":{"Column":"value you read"}}; '
                .'send only changed columns; base fails the patch with conflict if those fields changed. '
                .'A single text {"search","replace","replace_all"?} also works, but prefer vfs_edit for text. '
                .'version (from vfs_read) is checked atomically; stale record patches fail with conflict. '
                .'Table rows also need update_table_row and agent identity files update_agent_identity_file.',
            parameters: [
                new VfsParameterSpec('path', 'string', 'Record path, preferably canonical (by-id).', required: true),
                new VfsParameterSpec('patch', 'string', 'JSON patch as a string: field map, {"data":...,"base":...} for rows, or {"search","replace"} for text.', required: true),
                new VfsParameterSpec('version', 'string', 'version from vfs_read; stale versions fail with conflict for records.'),
            ],
            resultFields: [
                'status' => 'updated',
                'path' => 'normalized path',
                'canonical_path' => 'stable path',
                'version' => 'new version',
                'stale_base' => 'text patches only: applied against newer content',
            ],
            errorCodes: ['not_found', 'not_writable', 'permission_denied', 'unsupported', 'approval_required', 'invalid_request', 'conflict', 'ambiguous_match', 'internal_error'],
            examples: [
                '{"path": "/tasks/by-id/TASK_ID", "patch": "{\"status\":\"completed\"}", "version": "<version>"}',
                '{"path": "/tables/customers/rows/by-id/ROW_ID.json", "patch": "{\"data\":{\"Status\":\"active\"},\"base\":{\"Status\":\"lead\"}}"}',
            ],
            rubyExamples: [
                'app.vfs.patch(path: "/tasks/by-id/TASK_ID", patch: \'{"status":"completed"}\')',
            ],
            notes: [
                'Row keys are column names or column ids; only supplied columns are written, merged into the row as it is at write time.',
                'SEARCH/REPLACE blocks (<<<<<<< SEARCH / ======= / >>>>>>> REPLACE) are accepted for text paths.',
            ],
        );
    }

    private static function statSpec(): VfsOperationSpec
    {
        return self::scriptRead(
            'stat',
            'VFS Stat',
            'ph:info',
            'Stat a path: type, version, capabilities, canonical path and metadata.',
            [new VfsParameterSpec('path', 'string', 'Path to inspect.', required: true)],
            [
                'type' => 'file or directory',
                'version' => 'version token',
                'canonical_path' => 'stable path',
                'capabilities' => 'allowed actions',
                'metadata' => 'mutation_actions, mime_type, read_only_reason, ...',
            ],
            ['app.vfs.stat(path: "/docs/by-id/DOC_ID")[:result][:version]'],
        );
    }

    private static function lsSpec(): VfsOperationSpec
    {
        return self::scriptRead(
            'ls',
            'VFS Ls',
            'ph:list-bullets',
            'List a directory as structured entries with cursor paging.',
            [
                new VfsParameterSpec('path', 'string', 'Directory to list.', default: '/'),
                new VfsParameterSpec('limit', 'integer', 'Entries per page.', default: 100, aliases: ['maxEntries']),
                new VfsParameterSpec('cursor', 'string', 'next_cursor from the previous page.'),
                new VfsParameterSpec('maxFiles', 'integer', 'Backend scan ceiling when permissions hide entries (never below limit).', default: 100),
            ],
            [
                'items' => 'entries (name, path, type, canonical_path, version, capabilities)',
                'returned' => 'entries on this page',
                'truncated' => 'more pages exist',
                'next_cursor' => 'cursor for the next page',
                'total_count' => 'exact visible count on the final page, else null',
                'count_is_exact' => 'true only when total_count is exact',
            ],
            ['page = app.vfs.ls(path: "/docs", limit: 50)', 'page = app.vfs.ls(path: "/docs", limit: 50, cursor: page[:result][:next_cursor])'],
            ['Counting: page until next_cursor is nil; total_count is exact on the final page.'],
        );
    }

    private static function findSpec(): VfsOperationSpec
    {
        return self::scriptRead(
            'find',
            'VFS Find',
            'ph:binoculars',
            'Find entries by name and type across bounded directory trees (resumable).',
            [
                new VfsParameterSpec('paths', 'array', 'Directories to search.', default: null, items: 'string'),
                new VfsParameterSpec('name', 'string', 'fnmatch name filter such as *.md.', default: '*', aliases: ['glob']),
                new VfsParameterSpec('type', 'string', 'file or directory.', enum: ['file', 'directory']),
                new VfsParameterSpec('limit', 'integer', 'Entries per page.', default: 100, aliases: ['maxEntries']),
                new VfsParameterSpec('cursor', 'string', 'next_cursor from the previous page.'),
                new VfsParameterSpec('maxDepth', 'integer', 'Recursion depth.', default: 3),
                new VfsParameterSpec('maxFiles', 'integer', 'Entries scanned per page.', default: 100),
            ],
            ['items' => 'matching entries', 'truncated' => 'more pages exist', 'next_cursor' => 'cursor for the next page'],
            ['found = app.vfs.find(paths: ["/docs"], name: "*.md", type: "file")'],
        );
    }

    private static function rgSpec(): VfsOperationSpec
    {
        return self::scriptRead(
            'rg',
            'VFS Rg',
            'ph:magnifying-glass',
            'Search text across VFS paths (regex by default; app.vfs.grep is literal). Deterministic and permission-filtered, not semantic.',
            [
                new VfsParameterSpec('pattern', 'string', 'Regex, or literal text with regex: false.', required: true, aliases: ['query']),
                new VfsParameterSpec('paths', 'array', 'Paths to search.', items: 'string', aliases: ['path']),
                new VfsParameterSpec('regex', 'boolean', 'Treat pattern as a regular expression.', default: true),
                new VfsParameterSpec('ignoreCase', 'boolean', 'Case-insensitive.', default: false, aliases: ['caseInsensitive']),
                new VfsParameterSpec('maxMatches', 'integer', 'Matches to return.', default: 100),
                new VfsParameterSpec('maxFiles', 'integer', 'Files to scan.', default: 100),
                new VfsParameterSpec('maxDepth', 'integer', 'Recursion depth.', default: 3),
            ],
            ['matches' => 'list of {path, line, text}', 'count' => 'matches returned', 'truncated' => 'budgets cut the search', 'scanned' => 'files scanned'],
            ['hits = app.vfs.rg(pattern: "refund", paths: ["/docs"], max_matches: 20)', 'hits = app.vfs.grep(pattern: "a.b", paths: ["/files"])  # literal'],
        );
    }

    private static function existsSpec(): VfsOperationSpec
    {
        return self::scriptRead(
            'exists',
            'VFS Exists',
            'ph:check-circle',
            'Check whether a path exists without raising not_found.',
            [new VfsParameterSpec('path', 'string', 'Path to check.', required: true)],
            ['exists' => 'boolean', 'stat' => 'stat result when it exists'],
            ['app.vfs.exists(path: "/files/report.md")[:result][:exists]'],
        );
    }

    private static function mkdirSpec(): VfsOperationSpec
    {
        return self::scriptWrite(
            'mkdir',
            'VFS Mkdir',
            'ph:folder-plus',
            'reversible_edit',
            'Create a writable directory (idempotent).',
            [new VfsParameterSpec('path', 'string', 'Directory path under /files.', required: true)],
            ['app.vfs.mkdir(path: "/files/generated")'],
        );
    }

    private static function cpSpec(): VfsOperationSpec
    {
        return self::scriptWrite(
            'cp',
            'VFS Cp',
            'ph:copy',
            'reversible_edit',
            'Copy a writable file.',
            [
                new VfsParameterSpec('source', 'string', 'Source path.', required: true, aliases: ['src']),
                new VfsParameterSpec('destination', 'string', 'Destination path or directory.', required: true, aliases: ['dst', 'dest']),
            ],
            ['app.vfs.cp(source: "/files/a.md", destination: "/files/b.md")'],
        );
    }

    private static function mvSpec(): VfsOperationSpec
    {
        return self::scriptWrite(
            'mv',
            'VFS Mv',
            'ph:arrows-out-line-horizontal',
            'reversible_edit',
            'Move or rename a writable file.',
            [
                new VfsParameterSpec('source', 'string', 'Source path.', required: true, aliases: ['src']),
                new VfsParameterSpec('destination', 'string', 'Destination path or directory.', required: true, aliases: ['dst', 'dest']),
            ],
            ['app.vfs.mv(source: "/files/a.md", destination: "/files/archive/a.md")'],
        );
    }

    private static function rmSpec(): VfsOperationSpec
    {
        return self::scriptWrite(
            'rm',
            'VFS Rm',
            'ph:trash',
            'delete',
            'Delete a writable file.',
            [new VfsParameterSpec('path', 'string', 'File path.', required: true)],
            ['app.vfs.rm(path: "/files/tmp/scratch.txt")'],
        );
    }

    /**
     * Script-only read helper: reachable as app.vfs.* in Code Mode, governed
     * by vfs_exec, never a model-visible tool.
     *
     * @param  list<VfsParameterSpec>  $parameters
     * @param  array<string, string>  $resultFields
     * @param  list<string>  $rubyExamples
     * @param  list<string>  $notes
     */
    private static function scriptRead(string $name, string $title, string $icon, string $summary, array $parameters, array $resultFields, array $rubyExamples, array $notes = []): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: $name,
            toolSlug: 'vfs_'.$name,
            rubyName: $name,
            cliVerb: $name,
            title: $title,
            icon: $icon,
            direct: false,
            effect: 'read',
            registryType: 'read',
            actionClass: 'read',
            permission: 'vfs_exec',
            composedPermissions: ['all paths' => ['vfs_exec']],
            summary: $summary,
            description: 'Code Mode helper. '.$summary,
            parameters: $parameters,
            resultFields: $resultFields,
            errorCodes: ['not_found', 'not_readable', 'permission_denied', 'invalid_request', 'internal_error'],
            rubyExamples: $rubyExamples,
            notes: $notes,
        );
    }

    /**
     * Script-only write helper, governed by vfs_write (which the mutation
     * path also composes internally).
     *
     * @param  'reversible_edit'|'delete'  $actionClass
     * @param  list<VfsParameterSpec>  $parameters
     * @param  list<string>  $rubyExamples
     */
    private static function scriptWrite(string $name, string $title, string $icon, string $actionClass, string $summary, array $parameters, array $rubyExamples): VfsOperationSpec
    {
        return new VfsOperationSpec(
            name: $name,
            toolSlug: 'vfs_'.$name,
            rubyName: $name,
            cliVerb: $name,
            title: $title,
            icon: $icon,
            direct: false,
            effect: 'write',
            registryType: 'write',
            actionClass: $actionClass,
            permission: 'vfs_write',
            composedPermissions: ['/files' => ['vfs_write']],
            summary: $summary,
            description: 'Code Mode helper. '.$summary,
            parameters: $parameters,
            resultFields: ['status' => 'operation result', 'path' => 'affected path'],
            errorCodes: ['not_found', 'not_writable', 'permission_denied', 'approval_required', 'invalid_request', 'internal_error'],
            rubyExamples: $rubyExamples,
        );
    }
}
