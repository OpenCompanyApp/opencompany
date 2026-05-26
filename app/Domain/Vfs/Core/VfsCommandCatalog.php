<?php

namespace App\Domain\Vfs\Core;

/**
 * Discoverable catalog for unix-like commands implemented by the VFS engine.
 *
 * Runtime tools expose this through `/tools/vfs/commands.json`, and command
 * discovery helpers such as `which`, `type`, and `command -v` intentionally use
 * the same source so agent-visible support cannot drift from implementation.
 */
final class VfsCommandCatalog
{
    /**
     * @return array<string, array{class: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'pwd' => ['class' => 'browse', 'description' => 'Print the current virtual working directory.'],
            'cd' => ['class' => 'browse', 'description' => 'Change the command-local virtual working directory.'],
            'ls' => ['class' => 'browse', 'description' => 'List virtual directory entries. Supports -1, -a, -l, -R. Aliases: dir, ll.'],
            'tree' => ['class' => 'browse', 'description' => 'Render a bounded directory tree. Supports -L/--level and returns truncation/skipped metadata.'],
            'find' => ['class' => 'browse', 'description' => 'Find entries by name, type, and max depth in bounded virtual directory trees. Supports -name, -type, -maxdepth/-maxDepth/--max-depth, -print.'],
            'cat' => ['class' => 'read', 'description' => 'Read virtual text content. Aliases: less, more.'],
            'head' => ['class' => 'read', 'description' => 'Read the first lines or bytes of text content. Supports -n/--lines, -c/--bytes, -1, and attached byte forms such as -c4.'],
            'tail' => ['class' => 'read', 'description' => 'Read the last lines or bytes of text content. Supports -n/--lines, -c/--bytes, -1, -c4, zero counts, and +N forms.'],
            'wc' => ['class' => 'read', 'description' => 'Count lines, words, and bytes. Supports -l, -w, -c.'],
            'stat' => ['class' => 'browse', 'description' => 'Return structured metadata, capabilities, and version tokens.'],
            'file' => ['class' => 'browse', 'description' => 'Show the virtual file type or mime type.'],
            'du' => ['class' => 'read', 'description' => 'Show byte size for readable virtual text.'],
            'realpath' => ['class' => 'browse', 'description' => 'Normalize a virtual path.'],
            'dirname' => ['class' => 'browse', 'description' => 'Return the parent virtual path.'],
            'basename' => ['class' => 'browse', 'description' => 'Return the leaf virtual path name.'],
            'grep' => ['class' => 'read', 'description' => 'Search literal text or stdin. Supports -c, -l, -n, -o, -r, -R, -i, --max-count, and --max-depth. Aliases: egrep, fgrep.'],
            'rg' => ['class' => 'read', 'description' => 'Search VFS text using regex-style matching. Supports -c, -l, -n, -o, -i, --max-count, and --max-depth.'],
            'search' => ['class' => 'read', 'description' => 'Search VFS text using deterministic lexical matching.'],
            'echo' => ['class' => 'read-transform', 'description' => 'Print arguments to stdout. Supports -n.'],
            'true' => ['class' => 'read-transform', 'description' => 'Return success with no output.'],
            'false' => ['class' => 'read-transform', 'description' => 'Return a VFS command failure for conditional chains.'],
            'printf' => ['class' => 'read-transform', 'description' => 'Format arguments with a printf-style format string.'],
            'sort' => ['class' => 'read-transform', 'description' => 'Sort piped text lines. Supports -r, -n, -u, -k.'],
            'uniq' => ['class' => 'read-transform', 'description' => 'Filter adjacent duplicate piped text lines. Supports -c, -d, -u.'],
            'cut' => ['class' => 'read-transform', 'description' => 'Select delimited fields from piped text.'],
            'awk' => ['class' => 'read-transform', 'description' => 'Small awk subset for field extraction, e.g. awk -F , \'{print $2}\'.'],
            'xargs' => ['class' => 'read-transform', 'description' => 'Run a bounded VFS command once for each non-empty stdin line.'],
            'tr' => ['class' => 'read-transform', 'description' => 'Translate characters in piped text.'],
            'nl' => ['class' => 'read-transform', 'description' => 'Number piped text lines.'],
            'rev' => ['class' => 'read-transform', 'description' => 'Reverse each piped text line.'],
            'paste' => ['class' => 'read-transform', 'description' => 'Merge input lines or readable files with tab separators.'],
            'jq' => ['class' => 'read-transform', 'description' => 'Safe jq subset for JSON projections: ., fields, array indexes, length, keys, has(), arithmetic, stdin, and file args.'],
            'sed' => ['class' => 'read-transform', 'description' => 'Run safe substitution on piped text.'],
            'diff' => ['class' => 'read-transform', 'description' => 'Compare two readable virtual files.'],
            'cmp' => ['class' => 'read-transform', 'description' => 'Report whether two readable virtual files differ.'],
            'comm' => ['class' => 'read-transform', 'description' => 'Return common lines between two readable virtual files.'],
            'sha256sum' => ['class' => 'read-transform', 'description' => 'Compute SHA-256 over readable virtual text.'],
            'sha1sum' => ['class' => 'read-transform', 'description' => 'Compute SHA-1 over readable virtual text.'],
            'md5sum' => ['class' => 'read-transform', 'description' => 'Compute MD5 over readable virtual text.'],
            'checksum' => ['class' => 'read-transform', 'description' => 'Compute SHA-256 over readable virtual text. Alias for sha256sum.'],
            'which' => ['class' => 'browse', 'description' => 'Show whether a command is supported by the VFS command engine.'],
            'type' => ['class' => 'browse', 'description' => 'Describe a supported VFS command or alias.'],
            'command' => ['class' => 'browse', 'description' => 'Supports command -v for VFS command discovery.'],
            'env' => ['class' => 'browse', 'description' => 'Print safe VFS execution environment metadata.'],
            'printenv' => ['class' => 'browse', 'description' => 'Print safe VFS execution environment metadata.'],
            'date' => ['class' => 'browse', 'description' => 'Print the current application time as ISO-8601.'],
            'mkdir' => ['class' => 'write', 'description' => 'Create a workspace-file folder after write permission preflight.'],
            'touch' => ['class' => 'write', 'description' => 'Create or touch a workspace file after write permission preflight.'],
            'cp' => ['class' => 'write', 'description' => 'Copy a workspace file after write permission preflight.'],
            'mv' => ['class' => 'write', 'description' => 'Move a workspace file after write permission preflight.'],
            'rm' => ['class' => 'destructive', 'description' => 'Delete a workspace file after write permission preflight. Alias: rmdir.'],
            'truncate' => ['class' => 'write', 'description' => 'Overwrite a writable virtual file with empty content. Requires -s 0 or --size=0.'],
            'tee' => ['class' => 'write', 'description' => 'Write or append piped text to a writable VFS path. Supports -a.'],
        ];
    }
}
