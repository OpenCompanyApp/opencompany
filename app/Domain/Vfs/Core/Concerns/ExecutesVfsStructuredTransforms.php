<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsJqQuery;
use App\Domain\Vfs\Core\VfsSedScript;
use App\Models\User;

/**
 * Program-driven transforms: sed (VfsSedScript) and jq (VfsJqQuery).
 *
 * `sed -i` invariant: an in-place edit goes through the same governed
 * mutation path the agent would hit with a direct tool for that root, using
 * the version of the content it read, so policy, approvals, version
 * conflicts, and audit all apply:
 *
 * - /files/...: vfs_exec + vfs_write via assertWriteCommandAllowed, then a
 *   version-checked OpenCompanyVfs::write (a concurrent change -> conflict).
 * - /docs/..., /agents/...: OpenCompanyVfs::patch with a minimal unique
 *   search/replace anchor and the read version (vfs_patch policy; approval
 *   replays through vfs_patch; stale bases follow the text-patch contract).
 * - every other root: refused with an actionable error naming vfs_edit/
 *   vfs_patch (records need structured patches, not regex rewrites).
 */
trait ExecutesVfsStructuredTransforms
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function sed(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('sed', $words);
        $scripts = $args->values('-e');
        $offset = 0;
        if ($scripts === []) {
            $script = $args->operands[0] ?? throw VfsError::invalid("sed requires a script, e.g. sed 's/old/new/g' file.");
            $scripts = [$script];
            $offset = 1;
        }
        $program = VfsSedScript::parse($scripts, $args->has('E', 'r'));
        $quiet = $args->has('n');

        if ($args->has('i')) {
            $paths = $this->operandPaths($args, $cwd, $offset);
            if ($paths === [] || in_array('-', $paths, true)) {
                throw VfsError::invalid('sed -i requires file paths (it cannot edit stdin).');
            }
            $edited = [];
            $this->eachOperand($paths, function (string $path) use ($agent, $program, $quiet, &$edited): void {
                if ($this->sedInPlace($agent, $path, $program, $quiet)) {
                    $edited[] = $path;
                }
            });

            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write', 'edited' => $edited];
        }

        if ($args->has('s')) {
            $out = '';
            foreach ($this->inputsFrom($agent, $args, $cwd, $stdin, $offset) as $input) {
                $out .= $program->run($input['content'], $quiet);
            }

            return ['stdout' => $out, 'class' => 'read-transform'];
        }

        return ['stdout' => $program->run($this->inputFrom($agent, $args, $cwd, $stdin, $offset), $quiet), 'class' => 'read-transform'];
    }

    /**
     * Apply a sed program to one path in place. Returns whether it changed.
     */
    private function sedInPlace(User $agent, string $path, VfsSedScript $program, bool $quiet): bool
    {
        $root = $this->mountRoot($path);
        if (! in_array($root, ['files', 'docs', 'agents'], true)) {
            throw VfsError::unsupported("sed -i cannot edit /{$root} paths: they are structured records, not text files.", [
                'path' => $path,
                'suggestion' => 'Use vfs_edit/vfs_patch with a structured patch for records (tasks, lists, table rows), or sed without -i to preview.',
            ]);
        }
        if ($root === 'files') {
            // Invariant: policy before the facade (the write below skips its own check).
            $this->assertWriteCommandAllowed($agent, 'vfs_write');
        }
        $current = $this->vfs->readContentWithVersion($agent, $path);
        $updated = $program->run($current['content'], $quiet);
        if ($updated === $current['content']) {
            return false;
        }

        if ($root === 'files') {
            $this->vfs->write($agent, $path, $updated, 'overwrite', $current['version'], permissionAlreadyChecked: true);

            return true;
        }

        if ($current['content'] === '') {
            throw VfsError::unsupported("sed -i cannot edit the empty document {$path}.", ['path' => $path, 'suggestion' => 'Write the first content with vfs_write or vfs_edit.']);
        }
        $patch = $this->minimalTextPatch($current['content'], $updated);
        $encoded = json_encode($patch, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->vfs->patch($agent, $path, $encoded, $current['version'], array_filter([
            'path' => $path,
            'patch' => $encoded,
            'version' => $current['version'],
        ], fn (mixed $value): bool => $value !== null));

        return true;
    }

    /**
     * Smallest line-aligned search/replace pair that turns $old into $new and
     * whose search text occurs exactly once in $old (expanding by whole lines
     * until unique). Approvers then see a focused change, not the whole doc.
     *
     * @return array{search: string, replace: string}
     */
    private function minimalTextPatch(string $old, string $new): array
    {
        $oldLength = strlen($old);
        $newLength = strlen($new);
        $prefix = 0;
        $max = min($oldLength, $newLength);
        while ($prefix < $max && $old[$prefix] === $new[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $max - $prefix && $old[$oldLength - 1 - $suffix] === $new[$newLength - 1 - $suffix]) {
            $suffix++;
        }
        // Snap to line boundaries.
        $start = $prefix === 0 ? 0 : (int) (strrpos(substr($old, 0, $prefix), "\n") === false ? 0 : strrpos(substr($old, 0, $prefix), "\n") + 1);
        $endOld = $oldLength - $suffix;
        $nextNewline = strpos($old, "\n", $endOld);
        $endOld = $nextNewline === false ? $oldLength : $nextNewline + 1;
        $tail = $oldLength - $endOld;

        while (true) {
            $search = substr($old, $start, $endOld - $start);
            if ($search !== '' && substr_count($old, $search) === 1) {
                return ['search' => $search, 'replace' => substr($new, $start, $newLength - $tail - $start)];
            }
            if ($start === 0 && $endOld === $oldLength) {
                return ['search' => $old, 'replace' => $new];
            }
            if ($start > 0) {
                $previous = strrpos(substr($old, 0, $start - 1), "\n");
                $start = $previous === false ? 0 : $previous + 1;
            }
            if ($endOld < $oldLength) {
                $next = strpos($old, "\n", $endOld);
                $endOld = $next === false ? $oldLength : $next + 1;
                $tail = $oldLength - $endOld;
            }
        }
    }

    /**
     * Whether argv asks for an in-place edit (used by classifyEffect).
     *
     * @param  list<string>  $words
     * @param  list<bool>  $literal
     */
    private function sedInPlaceRequested(array $words, array $literal): bool
    {
        foreach ($words as $index => $word) {
            if ($word === '--') {
                return false;
            }
            if (! ($literal[$index] ?? false) && ($word === '--in-place' || str_starts_with($word, '--in-place=') || preg_match('/^-[a-zA-Z]*i/', $word) === 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The backup suffix of `-i.bak` / `-iSUFFIX` / `--in-place=SUFFIX`, if any.
     *
     * @param  list<string>  $words
     */
    private function sedInPlaceSuffix(array $words): ?string
    {
        foreach ($words as $index => $word) {
            if ($word === '--' || ($this->argLiteral[$index] ?? false)) {
                continue;
            }
            if (str_starts_with($word, '--in-place=')) {
                return substr($word, 11);
            }
            if (preg_match('/^-[nErs]*i(.+)$/', $word, $m) === 1 && preg_match('/^[nErs]+$/', $m[1]) !== 1) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function jq(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('jq', $words);
        $query = VfsJqQuery::parse($args->operands[0] ?? '.');
        $raw = $args->has('r', 'j');
        $compact = $args->has('c');

        if ($args->has('n')) {
            $values = [null];
        } else {
            $values = [];
            foreach ($this->inputsFrom($agent, $args, $cwd, $stdin, 1) as $input) {
                array_push($values, ...VfsJqQuery::decodeStream($input['content']));
            }
            if ($args->has('s')) {
                $values = [$values];
            }
        }

        $outputs = [];
        $last = null;
        $produced = false;
        foreach ($values as $value) {
            foreach ($query->run($value) as $result) {
                $outputs[] = $raw && is_string($result) ? $result : VfsJqQuery::encode($result, $compact);
                $last = $result;
                $produced = true;
            }
        }

        $status = 0;
        if ($args->has('e')) {
            $status = ! $produced ? 4 : (($last === null || $last === false) ? 1 : 0);
        }
        $stdout = $args->has('j') ? implode('', $outputs) : $this->linesOut($outputs);

        return ['stdout' => $stdout, 'class' => 'read-transform', 'exit_code' => $status];
    }
}
