<?php

namespace App\Domain\Vfs\Core;

/**
 * The single quote-aware scanner for VFS shell command lines.
 *
 * Owns: splitting a command line into `;`/newline/`&&`/`||` lists, `|`
 * pipelines and simple commands; quote removal (single quotes are fully
 * literal, double quotes unescape only `"` `\` `$` and backtick); line
 * continuations; `#` comments; redirection operators (`<`, `>`, `>>`, `>|`,
 * `N>`, `N>>`, `N>&M`, `>&N`, `&>`, `&>>`, `|&`); heredocs (`<<`, `<<-`, quoted
 * or bare delimiters, bodies never expanded) and here-strings (`<<<`); the `$?`
 * status parameter; and a leading `!` that negates a command's status.
 *
 * Does not own: execution, path normalization, glob expansion, or option
 * parsing. It never touches storage, so VfsCommandExecutor::classifyEffect can
 * use it to classify untrusted input.
 *
 * Invariant: this is the only place that decides what is quoted. Earlier
 * versions had four scanners that disagreed about backslashes inside single
 * quotes (`grep 'C:\' f` failed); every consumer must go through parse().
 *
 * Rejected syntax throws `unsupported` with a suggestion, so an agent always
 * learns the alternative instead of getting a silently different command.
 */
final class VfsShellLexer
{
    /** Placeholder the executor replaces with the previous exit status. */
    public const STATUS_PLACEHOLDER = "\x00VFS_STATUS\x00";

    private const CODE_MODE_HINT = 'Use literal values, xargs for loops, or Code Mode (code_exec) for scripting.';

    private string $input = '';

    private int $length = 0;

    private int $pos = 0;

    /** @var list<array{operator: string|null, pipeline: list<VfsShellCommand>}> */
    private array $groups = [];

    /** @var list<VfsShellCommand> */
    private array $pipeline = [];

    private ?string $groupOperator = null;

    /** Whether the parser is waiting for a command after `|`, `&&` or `||`. */
    private bool $awaitingCommand = false;

    private string $lastOperator = '';

    // Current simple command under construction.
    /** @var list<string> */
    private array $words = [];

    /** @var list<bool> */
    private array $literal = [];

    /** @var list<bool> */
    private array $glob = [];

    /** @var list<array{stream: 'stdin'|'stdout'|'stderr', mode: 'read'|'overwrite'|'append'|'merge', path: string}> */
    private array $redirections = [];

    private ?string $stdin = null;

    private bool $negate = false;

    private int $commandStart = 0;

    // Current word under construction.
    private string $word = '';

    private bool $wordStarted = false;

    private bool $wordLiteral = false;

    private bool $wordGlob = false;

    /** @var array{stream: 'stdin'|'stdout'|'stderr', mode: 'read'|'overwrite'|'append', op: string}|null */
    private ?array $pendingRedirect = null;

    /** Heredoc operator seen; the next word is its delimiter. */
    private ?bool $pendingHeredocStripTabs = null;

    private bool $pendingHereString = false;

    /**
     * Heredocs whose bodies start after the next unquoted newline, in order.
     *
     * @var list<array{delimiter: string, strip_tabs: bool, slot: int}>
     */
    private array $heredocQueue = [];

    /** Slot of the heredoc that owns the current command's stdin. */
    private ?int $commandHeredocSlot = null;

    private int $nextSlot = 0;

    /**
     * Parse a command line into and-or lists of pipelines.
     *
     * @return list<array{operator: string|null, pipeline: list<VfsShellCommand>}>
     *
     * @throws VfsError invalid_request for malformed syntax, unsupported for syntax the VFS does not emulate
     */
    public static function parse(string $input): array
    {
        return (new self)->run($input);
    }

    /**
     * @return list<array{operator: string|null, pipeline: list<VfsShellCommand>}>
     */
    private function run(string $input): array
    {
        $this->input = $input;
        $this->length = strlen($input);
        $this->pos = 0;
        $this->commandStart = 0;

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === ' ' || $char === "\t" || $char === "\r") {
                $this->finishWord();
                $this->pos++;

                continue;
            }
            if ($char === "\n") {
                $this->finishWord();
                $this->pos++;
                $this->readHeredocBodies();
                $this->newline();

                continue;
            }
            if ($char === '#' && ! $this->wordStarted) {
                // Comment to end of line; the newline itself still separates.
                while ($this->pos < $this->length && $this->input[$this->pos] !== "\n") {
                    $this->pos++;
                }

                continue;
            }
            if ($char === '\\') {
                $next = $this->input[$this->pos + 1] ?? null;
                if ($next === null) {
                    throw VfsError::invalid('Trailing backslash at end of command.', ['hint' => 'Remove the backslash or quote it as "\\\\".']);
                }
                $this->pos += 2;
                if ($next === "\n") {
                    continue;
                }
                $this->appendLiteral($next);

                continue;
            }
            if ($char === "'") {
                $end = strpos($this->input, "'", $this->pos + 1);
                if ($end === false) {
                    throw VfsError::invalid('Unterminated single quote.', ['hint' => 'Close the quote, or use double quotes when the text itself contains a single quote.']);
                }
                $this->appendLiteral(substr($this->input, $this->pos + 1, $end - $this->pos - 1));
                $this->pos = $end + 1;

                continue;
            }
            if ($char === '"') {
                $this->readDoubleQuoted();

                continue;
            }
            if ($char === '`') {
                throw VfsError::unsupported('Backtick command substitution is not supported in VFS commands.', ['suggestion' => 'Run the inner command first and use its output literally, or pipe it with xargs. '.self::CODE_MODE_HINT]);
            }
            if ($char === '$') {
                if ($this->readDollar(false)) {
                    continue;
                }
                $this->appendRaw('$');
                $this->pos++;

                continue;
            }
            if ($char === ';') {
                if (($this->input[$this->pos + 1] ?? null) === ';') {
                    throw VfsError::invalid('Syntax error near `;;` (an empty command between separators).', ['hint' => 'Use a single ; between commands.']);
                }
                $this->finishWord();
                $this->pos++;
                $this->endGroup(';', true);

                continue;
            }
            if ($char === '&') {
                $next = $this->input[$this->pos + 1] ?? null;
                if ($next === '&') {
                    $this->finishWord();
                    $this->pos += 2;
                    $this->endGroup('&&', false);

                    continue;
                }
                if ($next === '>') {
                    // &> file / &>> file: stdout and stderr to the same file.
                    $this->finishWord();
                    $append = ($this->input[$this->pos + 2] ?? null) === '>';
                    $this->pos += $append ? 3 : 2;
                    $this->pendingRedirect = ['stream' => 'stdout', 'mode' => $append ? 'append' : 'overwrite', 'op' => $append ? '&>>' : '&>'];
                    $this->redirectBoth = true;

                    continue;
                }
                throw VfsError::unsupported('Background execution with & is not supported in VFS commands.', ['suggestion' => 'Commands run synchronously; drop the trailing &.']);
            }
            if ($char === '|') {
                $next = $this->input[$this->pos + 1] ?? null;
                $this->finishWord();
                if ($next === '|') {
                    $this->pos += 2;
                    $this->endGroup('||', false);

                    continue;
                }
                if ($next === '&') {
                    $this->redirections[] = ['stream' => 'stderr', 'mode' => 'merge', 'path' => '&1'];
                    $this->pos += 2;
                } else {
                    $this->pos++;
                }
                $this->endCommand('|');
                $this->awaitingCommand = true;
                $this->lastOperator = '|';

                continue;
            }
            if ($char === '(' || $char === ')') {
                throw VfsError::unsupported('Subshell syntax is not supported in VFS commands.', ['suggestion' => 'Use ; or && to sequence commands, quote literal parentheses, or escape them (find \\( ... \\)). '.self::CODE_MODE_HINT]);
            }
            if ($char === '<' || $char === '>') {
                $this->readRedirection($char);

                continue;
            }

            if ($char === '*' || $char === '?' || $char === '[') {
                $this->wordGlob = true;
            }
            $this->appendRaw($char);
            $this->pos++;
        }

        $this->finishWord();
        if ($this->heredocQueue !== []) {
            throw VfsError::invalid('Heredoc body is missing: put the content on the lines after the command and end it with a line containing only '.$this->heredocQueue[0]['delimiter'].'.', [
                'hint' => "Example: cat <<'EOF' > /files/note.md (newline) text (newline) EOF",
            ]);
        }
        if ($this->awaitingCommand && $this->isCommandEmpty()) {
            throw VfsError::invalid("Missing command after {$this->lastOperator}.");
        }
        $this->endGroup(null, true);

        return $this->groups;
    }

    /** Set by `&>`: the next target receives stderr too. */
    private bool $redirectBoth = false;

    private function appendRaw(string $text): void
    {
        if (! $this->wordStarted) {
            $this->wordStarted = true;
            $this->wordLiteral = false;
        }
        $this->word .= $text;
    }

    private function appendLiteral(string $text): void
    {
        if (! $this->wordStarted) {
            $this->wordStarted = true;
            $this->wordLiteral = true;
        }
        $this->word .= $text;
    }

    /**
     * Read a double-quoted span starting at the opening quote.
     */
    private function readDoubleQuoted(): void
    {
        $this->appendLiteral('');
        $this->pos++;
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            if ($char === '"') {
                $this->pos++;

                return;
            }
            if ($char === '\\') {
                $next = $this->input[$this->pos + 1] ?? '';
                if ($next === "\n") {
                    $this->pos += 2;

                    continue;
                }
                if (in_array($next, ['"', '\\', '$', '`'], true)) {
                    $this->word .= $next;
                    $this->pos += 2;

                    continue;
                }
                $this->word .= '\\';
                $this->pos++;

                continue;
            }
            if ($char === '`') {
                throw VfsError::unsupported('Backtick command substitution is not supported in VFS commands.', ['suggestion' => self::CODE_MODE_HINT]);
            }
            if ($char === '$' && $this->readDollar(true)) {
                continue;
            }
            $this->word .= $char;
            $this->pos++;
        }

        throw VfsError::invalid('Unterminated double quote.', ['hint' => 'Close the quote; escape literal double quotes inside it as \\".']);
    }

    /**
     * Handle `$` at the current position. Returns true when it consumed input
     * (only `$?`); throws for variables and substitutions; returns false when
     * the `$` is literal (end of word, before a space, `$1`-style regex anchors
     * inside quotes are still literal).
     */
    private function readDollar(bool $inDoubleQuotes): bool
    {
        $next = $this->input[$this->pos + 1] ?? '';
        if ($next === '?') {
            if (! $inDoubleQuotes) {
                $this->appendRaw('');
            }
            $this->word .= self::STATUS_PLACEHOLDER;
            $this->pos += 2;

            return true;
        }
        if ($next === '(') {
            throw VfsError::unsupported('Command substitution $(...) is not supported in VFS commands.', ['suggestion' => 'Run the inner command first and use its output literally, or pipe it into xargs. '.self::CODE_MODE_HINT]);
        }
        if ($next === '{' || preg_match('/^[A-Za-z_]$/', $next) === 1) {
            throw VfsError::unsupported('Shell variables are not supported in VFS commands.', ['suggestion' => self::CODE_MODE_HINT.' Quote the text with single quotes if the $ is literal (e.g. awk \'{print $1}\').']);
        }

        return false;
    }

    private function readRedirection(string $char): void
    {
        // A word made only of digits directly before the operator is the fd.
        $fd = null;
        if ($this->wordStarted && ! $this->wordLiteral && preg_match('/^\d+$/', $this->word) === 1) {
            $fd = (int) $this->word;
            $this->word = '';
            $this->wordStarted = false;
        } else {
            $this->finishWord();
        }
        if ($this->pendingRedirect !== null || $this->pendingHeredocStripTabs !== null || $this->pendingHereString) {
            throw VfsError::invalid('Redirection requires a target path.', ['hint' => 'Write the target directly after the operator, e.g. > /files/out.txt.']);
        }

        if ($char === '<') {
            if ($fd !== null && $fd !== 0) {
                throw VfsError::unsupported("Unsupported file descriptor redirection: {$fd}<", ['suggestion' => 'Only stdin (<), stdout (>, 1>), and stderr (2>) are supported.']);
            }
            $next = $this->input[$this->pos + 1] ?? '';
            if ($next === '(') {
                throw VfsError::unsupported('Process substitution is not supported in VFS commands.', ['suggestion' => 'Write intermediate output to a /files path first, then compare files. '.self::CODE_MODE_HINT]);
            }
            if ($next === '<') {
                if (($this->input[$this->pos + 2] ?? '') === '<') {
                    $this->pos += 3;
                    $this->pendingHereString = true;

                    return;
                }
                $stripTabs = ($this->input[$this->pos + 2] ?? '') === '-';
                $this->pos += $stripTabs ? 3 : 2;
                $this->pendingHeredocStripTabs = $stripTabs;

                return;
            }
            if ($next === '&') {
                throw VfsError::unsupported('Input descriptor duplication (<&) is not supported in VFS commands.');
            }
            $this->pos++;
            $this->pendingRedirect = ['stream' => 'stdin', 'mode' => 'read', 'op' => '<'];

            return;
        }

        $fd ??= 1;
        if ($fd > 2) {
            throw VfsError::unsupported("Unsupported file descriptor redirection: {$fd}>", ['suggestion' => 'Only stdout (>, 1>) and stderr (2>) can be redirected.']);
        }
        $stream = $fd === 2 ? 'stderr' : 'stdout';
        $next = $this->input[$this->pos + 1] ?? '';
        if ($next === '&') {
            // N>&M duplicates a descriptor; the target is digits only.
            $this->pos += 2;
            $target = '';
            while ($this->pos < $this->length && ctype_digit($this->input[$this->pos])) {
                $target .= $this->input[$this->pos];
                $this->pos++;
            }
            if ($target === '' && ($this->input[$this->pos] ?? '') === '-') {
                throw VfsError::unsupported('Closing descriptors (>&-) is not supported in VFS commands.');
            }
            if ($target === '') {
                // `>&file` is bash shorthand for `&>file`.
                $this->pendingRedirect = ['stream' => 'stdout', 'mode' => 'overwrite', 'op' => '>&'];
                $this->redirectBoth = true;

                return;
            }
            if (! in_array($target, ['1', '2'], true)) {
                throw VfsError::unsupported("Unsupported file descriptor redirection: {$fd}>&{$target}", ['suggestion' => 'Use 2>&1 to merge stderr into stdout or >&2 to send stdout to stderr.']);
            }
            if ((string) $fd !== $target) {
                $this->redirections[] = ['stream' => $stream, 'mode' => 'merge', 'path' => '&'.$target];
            }

            return;
        }
        if ($next === '>') {
            $this->pos += 2;
            $this->pendingRedirect = ['stream' => $stream, 'mode' => 'append', 'op' => $fd === 2 ? '2>>' : '>>'];

            return;
        }
        $this->pos += $next === '|' ? 2 : 1;
        $this->pendingRedirect = ['stream' => $stream, 'mode' => 'overwrite', 'op' => $fd === 2 ? '2>' : '>'];
    }

    private function finishWord(): void
    {
        if (! $this->wordStarted) {
            return;
        }

        $word = $this->word;
        $literal = $this->wordLiteral;
        $glob = $this->wordGlob;
        $this->word = '';
        $this->wordStarted = false;
        $this->wordLiteral = false;
        $this->wordGlob = false;

        if ($this->pendingHereString) {
            $this->pendingHereString = false;
            $this->stdin = $word."\n";
            $this->commandHeredocSlot = null;

            return;
        }
        if ($this->pendingHeredocStripTabs !== null) {
            $slot = $this->nextSlot++;
            $this->heredocQueue[] = ['delimiter' => $word, 'strip_tabs' => $this->pendingHeredocStripTabs, 'slot' => $slot];
            $this->pendingHeredocStripTabs = null;
            $this->commandHeredocSlot = $slot;
            $this->stdin = '';

            return;
        }
        if ($this->pendingRedirect !== null) {
            $redirect = $this->pendingRedirect;
            $this->pendingRedirect = null;
            $this->redirections[] = ['stream' => $redirect['stream'], 'mode' => $redirect['mode'], 'path' => $word];
            if ($this->redirectBoth) {
                $this->redirectBoth = false;
                $this->redirections[] = ['stream' => 'stderr', 'mode' => 'merge', 'path' => '&1'];
            }

            return;
        }

        if ($this->words === [] && ! $literal) {
            if ($word === '!' && ! $this->negate) {
                $this->negate = true;

                return;
            }
            $this->assertSupportedCommandWord($word);
        }
        $this->words[] = $word;
        $this->literal[] = $literal;
        $this->glob[] = $glob;
        $this->awaitingCommand = false;
    }

    /**
     * Reject shell keywords and assignments that would otherwise run as an
     * unknown command or silently drop syntax.
     */
    private function assertSupportedCommandWord(string $word): void
    {
        if (in_array($word, ['if', 'then', 'else', 'elif', 'fi', 'for', 'while', 'until', 'do', 'done', 'case', 'esac', 'function', 'select'], true)) {
            throw VfsError::unsupported("Shell control flow ({$word}) is not supported in VFS commands.", [
                'suggestion' => 'Chain commands with && / || / ;, loop with `... | xargs -I{} cmd {}`, test conditions with `test` or `[ ]`, or use Code Mode (code_exec) for real scripts.',
            ]);
        }
        if ($word === '{' || $word === '}' || $word === '[[') {
            throw VfsError::unsupported("Shell grouping ({$word}) is not supported in VFS commands.", [
                'suggestion' => 'Use ; or && to sequence commands and `[ ... ]` for tests. '.self::CODE_MODE_HINT,
            ]);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $word) === 1) {
            throw VfsError::unsupported('Shell variable assignment is not supported in VFS commands.', ['suggestion' => self::CODE_MODE_HINT]);
        }
    }

    private function isCommandEmpty(): bool
    {
        return $this->words === [] && $this->redirections === [] && $this->stdin === null && ! $this->negate;
    }

    /**
     * Close the current simple command and add it to the pipeline.
     */
    private function endCommand(string $terminator): void
    {
        $this->assertNoPendingRedirect();
        if ($this->isCommandEmpty()) {
            throw VfsError::invalid($terminator === '|' ? 'Missing command before |.' : "Missing command before {$terminator}.");
        }
        if ($this->words === []) {
            throw VfsError::invalid('Redirection without a command.', ['hint' => 'Use `cat <<EOF > path` or `printf "" > path` to write a file.']);
        }

        $this->pipeline[] = new VfsShellCommand(
            words: $this->words,
            literal: $this->literal,
            glob: $this->glob,
            redirections: $this->redirections,
            stdin: $this->commandHeredocSlot !== null ? null : $this->stdin,
            negate: $this->negate,
            source: rtrim(trim(substr($this->input, $this->commandStart, max(0, $this->pos - $this->commandStart))), " \t\n|&;"),
        );
        $this->commandStart = $this->pos;
        if ($this->commandHeredocSlot !== null) {
            $this->heredocOwners[$this->commandHeredocSlot] = [count($this->groups), count($this->pipeline) - 1];
        }
        $this->words = [];
        $this->literal = [];
        $this->glob = [];
        $this->redirections = [];
        $this->stdin = null;
        $this->negate = false;
        $this->commandHeredocSlot = null;
    }

    /**
     * Where each heredoc's body must be attached: slot => [group index, pipeline index].
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private array $heredocOwners = [];

    /**
     * Close the current pipeline as one and-or list item.
     *
     * @param  bool  $allowEmpty  `;` and newlines may follow nothing (blank lines, trailing `;`)
     */
    private function endGroup(?string $nextOperator, bool $allowEmpty): void
    {
        $this->assertNoPendingRedirect();
        if ($this->isCommandEmpty()) {
            if ($this->awaitingCommand) {
                throw VfsError::invalid("Missing command after {$this->lastOperator}.");
            }
            if ($this->pipeline === []) {
                if (! $allowEmpty) {
                    throw VfsError::invalid('Missing command before '.($nextOperator ?? 'end of input').'.');
                }
                // Blank lines and stray `;` are empty statements.
                $this->commandStart = $this->pos;

                return;
            }
        } else {
            $this->endCommand($nextOperator ?? ';');
        }

        $this->groups[] = ['operator' => $this->groupOperator, 'pipeline' => $this->pipeline];
        $this->pipeline = [];
        $this->groupOperator = $nextOperator === null ? null : $nextOperator;
        if ($nextOperator === '&&' || $nextOperator === '||') {
            $this->awaitingCommand = true;
            $this->lastOperator = $nextOperator;
        }
    }

    private function newline(): void
    {
        // A newline after |, && or || continues the pipeline/list (bash rule).
        if ($this->awaitingCommand && $this->isCommandEmpty()) {
            return;
        }
        $this->endGroup(';', true);
    }

    private function assertNoPendingRedirect(): void
    {
        if ($this->pendingRedirect !== null) {
            throw VfsError::invalid("Redirection {$this->pendingRedirect['op']} requires a target path.", ['hint' => 'Write the target directly after the operator, e.g. > /files/out.txt.']);
        }
        if ($this->pendingHeredocStripTabs !== null) {
            throw VfsError::invalid('Heredoc << requires a delimiter word, e.g. <<\'EOF\'.');
        }
        if ($this->pendingHereString) {
            throw VfsError::invalid('Here-string <<< requires a word, e.g. <<< "text".');
        }
    }

    /**
     * After an unquoted newline, consume the bodies of queued heredocs line by
     * line until each delimiter line. Bodies are never expanded (VFS has no
     * variables), so quoted and bare delimiters behave the same.
     */
    private function readHeredocBodies(): void
    {
        foreach ($this->heredocQueue as $heredoc) {
            $body = '';
            $closed = false;
            while ($this->pos < $this->length) {
                $end = strpos($this->input, "\n", $this->pos);
                $line = $end === false ? substr($this->input, $this->pos) : substr($this->input, $this->pos, $end - $this->pos);
                $this->pos = $end === false ? $this->length : $end + 1;
                $line = rtrim($line, "\r");
                if ($heredoc['strip_tabs']) {
                    $line = ltrim($line, "\t");
                }
                if (rtrim($line, " \t") === $heredoc['delimiter']) {
                    $closed = true;

                    break;
                }
                $body .= $line."\n";
            }
            if (! $closed) {
                throw VfsError::invalid("Heredoc is not terminated: add a line containing only {$heredoc['delimiter']}.", [
                    'delimiter' => $heredoc['delimiter'],
                ]);
            }
            $this->attachHeredoc($heredoc['slot'], $body);
        }
        $this->heredocQueue = [];
    }

    /**
     * Attach a body to the command that declared it. The command may still be
     * under construction (heredoc on the last command of the line) or already
     * closed into a pipeline/group.
     */
    private function attachHeredoc(int $slot, string $body): void
    {
        if ($this->commandHeredocSlot === $slot) {
            $this->stdin = $body;
            $this->commandHeredocSlot = null;

            return;
        }

        $owner = $this->heredocOwners[$slot] ?? null;
        if ($owner === null) {
            return;
        }
        [$groupIndex, $pipeIndex] = $owner;
        if ($groupIndex === count($this->groups)) {
            $this->pipeline[$pipeIndex] = $this->withStdin($this->pipeline[$pipeIndex], $body);

            return;
        }
        $this->groups[$groupIndex]['pipeline'][$pipeIndex] = $this->withStdin($this->groups[$groupIndex]['pipeline'][$pipeIndex], $body);
    }

    private function withStdin(VfsShellCommand $command, string $body): VfsShellCommand
    {
        return new VfsShellCommand($command->words, $command->literal, $command->glob, $command->redirections, $body, $command->negate, $command->source);
    }
}
