<?php

namespace App\Domain\Vfs\Core;

/**
 * One simple command (pipeline segment) produced by VfsShellLexer.
 *
 * The lexer has already removed quoting, so `words` are the final argv. The
 * parallel masks carry the only quoting facts the executor still needs:
 *
 * - `literal[i]`: the word's first character came from quotes or an escape.
 *   Such a word is never interpreted as an option (`"-n"` stays an operand).
 * - `glob[i]`: the word contains an unquoted `*`, `?` or `[` and may be
 *   expanded against the VFS. Quoted metacharacters never glob.
 *
 * Redirections are already split out of argv (`echo hi>f` works) and carry the
 * raw, unnormalized target; the executor resolves targets against the cwd at
 * the moment the command runs because `cd` earlier in the line changes it.
 *
 * Pure value object: no agent, workspace, or storage access.
 */
final class VfsShellCommand
{
    /**
     * @param  list<string>  $words
     * @param  list<bool>  $literal
     * @param  list<bool>  $glob
     * @param  list<array{stream: 'stdin'|'stdout'|'stderr', mode: 'read'|'overwrite'|'append'|'merge', path: string}>  $redirections
     * @param  string|null  $stdin  Heredoc or here-string body; replaces piped stdin.
     * @param  bool  $negate  Leading `!`: invert the command's exit status.
     */
    public function __construct(
        public readonly array $words,
        public readonly array $literal,
        public readonly array $glob,
        public readonly array $redirections,
        public readonly ?string $stdin,
        public readonly bool $negate,
        public readonly string $source,
    ) {}

    public function name(): ?string
    {
        return $this->words[0] ?? null;
    }
}
