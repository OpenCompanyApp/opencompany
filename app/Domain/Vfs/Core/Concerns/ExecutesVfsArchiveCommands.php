<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsArgs;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use ZipArchive;

/**
 * Archive and multi-output file commands: tar, zip, unzip, split, csplit, ln.
 *
 * In-memory archives only (no host binaries). Reads go through
 * OpenCompanyVfs; every write passes assertWriteCommandAllowed first.
 *
 * Invariants:
 * - Every byte written into an archive or extracted file comes from a full
 *   content read, never a display read.
 * - Creation is bounded globally: at most maxFiles members in total (not per
 *   directory) and at most the content ceiling in bytes; exceeding either
 *   fails before anything is written.
 * - Extraction is bounded by maxFiles members and the content ceiling, and
 *   member names are sanitized (no "..", no absolute paths) under a /files
 *   destination.
 */
trait ExecutesVfsArchiveCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function tar(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $args = $this->args('tar', $words);
        $modes = array_filter([$args->has('c'), $args->has('x'), $args->has('t')]);
        if (count($modes) !== 1) {
            throw VfsError::invalid('tar requires exactly one of -c, -x, or -t.');
        }
        $file = $args->value('-f');

        if ($args->has('c')) {
            $base = $args->value('-C');
            $entries = $this->archiveEntries($agent, $args->operands, $base === null ? $cwd : $this->path($base, $cwd), $base !== null, $budget, $args->values('--exclude'));
            $archive = $this->buildTarArchive($entries);
            if ($args->has('z')) {
                $archive = gzencode($archive) ?: throw VfsError::invalid('tar could not gzip the archive.');
            }
            $listing = $args->has('v') ? $this->linesOut(array_keys($entries)) : '';
            if ($file !== null && $file !== '-') {
                $this->assertWriteCommandAllowed($agent, 'vfs_write');
                $this->vfs->write($agent, $this->path($file, $cwd), $archive, 'overwrite', permissionAlreadyChecked: true);

                return ['stdout' => $listing, 'cwd' => $cwd, 'class' => 'write'];
            }

            return ['stdout' => $archive, 'stderr' => $listing, 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $archive = $this->archiveInput($agent, $file, $args, $cwd, $stdin);
        if ($args->has('z')) {
            $ceiling = $this->contentCeiling();
            $decoded = @gzdecode($archive, $ceiling + 1);
            if ($decoded === false || strlen($decoded) > $ceiling) {
                throw VfsError::invalid('tar archive is not valid gzip data, or it expands beyond the content limit.', ['max_content_bytes' => $ceiling]);
            }
            $archive = $decoded;
        }
        $entries = $this->parseTarArchive($archive, $budget);

        if ($args->has('t')) {
            $lines = $args->has('v')
                ? array_map(fn (string $name, string $content): string => str_pad((string) strlen($content), 10, ' ', STR_PAD_LEFT).' '.$name, array_keys($entries), $entries)
                : array_keys($entries);

            return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $destination = $this->archiveDestination($args->value('-C'), $cwd);
        $strip = $args->int('--strip-components') ?? 0;
        $written = [];
        foreach ($entries as $name => $content) {
            $relative = $this->stripArchiveComponents($name, $strip);
            if ($relative === null) {
                continue;
            }
            $path = rtrim($destination, '/').'/'.$this->safeArchiveName($relative);
            $this->vfs->write($agent, $path, $content, 'overwrite', permissionAlreadyChecked: true);
            $written[] = $path;
        }

        // VFS deviation: extraction always lists the written paths.
        return ['stdout' => $this->linesOut($written), 'cwd' => $cwd, 'class' => 'write', 'written' => $written];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function zip(User $agent, array $words, string $cwd, VfsBudget $budget): array
    {
        $args = $this->args('zip', $words);
        $this->assertZipExtensionAvailable();
        $operands = $args->operands;
        $archivePath = isset($operands[0]) ? $this->path(array_shift($operands), $cwd) : throw VfsError::invalid('zip requires an archive path and inputs: zip -r out.zip DIR');
        if ($operands === []) {
            throw VfsError::invalid('zip requires at least one input path.');
        }
        if (! $args->has('r')) {
            foreach ($operands as $operand) {
                if (($this->statOrNull($agent, $this->path($operand, $cwd))['type'] ?? null) === 'directory') {
                    throw VfsError::invalid("zip: {$operand} is a folder; use zip -r to add folders.");
                }
            }
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $entries = $this->archiveEntries($agent, $operands, $cwd, false, $budget, []);

        $tmp = tempnam(sys_get_temp_dir(), 'vfs_zip_');
        if ($tmp === false) {
            throw VfsError::invalid('zip could not allocate a temporary archive.');
        }
        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                throw VfsError::invalid('zip could not create the archive.');
            }
            foreach ($entries as $name => $content) {
                $zip->addFromString($args->has('j') ? basename($name) : $name, $content);
            }
            $zip->close();
            $archive = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
        $this->vfs->write($agent, $archivePath, $archive, 'overwrite', permissionAlreadyChecked: true);

        return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write', 'members' => array_keys($entries)];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function unzip(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $args = $this->args('unzip', $words);
        $this->assertZipExtensionAvailable();
        $archive = $this->archiveInput($agent, null, $args, $cwd, $stdin);
        $entries = $this->zipEntries($archive, $budget);

        if ($args->has('t')) {
            return ['stdout' => $this->linesOut(array_map(fn (string $name): string => "    testing: {$name}   OK", array_keys($entries))), 'cwd' => $cwd, 'class' => 'read-transform'];
        }
        if ($args->has('l')) {
            return ['stdout' => $this->linesOut(array_map(fn (string $name, string $content): string => str_pad((string) strlen($content), 9, ' ', STR_PAD_LEFT).'  '.$name, array_keys($entries), $entries)), 'cwd' => $cwd, 'class' => 'read-transform'];
        }
        if ($args->has('p')) {
            return ['stdout' => implode('', $entries), 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $destination = $this->archiveDestination($args->value('-d'), $cwd);
        $written = [];
        foreach ($entries as $name => $content) {
            $path = rtrim($destination, '/').'/'.$this->safeArchiveName($name);
            if (! $args->has('o') && $this->statOrNull($agent, $path) !== null) {
                throw VfsError::invalid("unzip: {$path} already exists; nothing after it was extracted.", ['path' => $path, 'completed_paths' => $written, 'hint' => 'Pass -o to overwrite existing files.']);
            }
            $this->vfs->write($agent, $path, $content, 'overwrite', permissionAlreadyChecked: true);
            $written[] = $path;
        }

        return ['stdout' => $args->has('q') ? '' : $this->linesOut(array_map(fn (string $path): string => "  inflating: {$path}", $written)), 'cwd' => $cwd, 'class' => 'write', 'written' => $written];
    }

    /**
     * split [-l N | -b SIZE | -n CHUNKS] [-d] [-a N] INPUT [PREFIX]
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function split(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('split', $words);
        $paths = $this->operandPaths($args, $cwd);
        $source = $paths[0] ?? throw VfsError::invalid('split requires an input path.');
        $prefix = isset($args->operands[1]) ? $this->path($args->operands[1], $cwd) : $this->path('x', $cwd);
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $content = $this->readFull($agent, $source);
        $suffixLength = $args->int('-a', 1) ?? 2;

        if ($args->provided('-b')) {
            $bytes = $this->parseSize((string) $args->value('-b'), 'split -b');
            $chunks = $content === '' ? [] : str_split($content, max(1, $bytes));
        } elseif ($args->provided('-n')) {
            $count = $args->int('-n', 1) ?? 1;
            $size = (int) ceil(strlen($content) / $count);
            $chunks = $size === 0 ? [] : str_split($content, $size);
        } else {
            $linesPerFile = $args->int('-l', 1) ?? 1000;
            $lines = preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $chunks = array_map(fn (array $chunk): string => implode('', $chunk), array_chunk($lines, $linesPerFile));
        }
        $budget = $this->currentBudget ?? new VfsBudget;
        if (count($chunks) > $budget->maxFiles) {
            throw VfsError::invalid('split would create '.count($chunks)." files (maxFiles {$budget->maxFiles}); nothing was written.", ['hint' => 'Use larger pieces or raise maxFiles.']);
        }
        $written = [];
        foreach ($chunks as $index => $chunk) {
            $path = $prefix.$this->splitSuffix($index, $suffixLength, $args->has('d'));
            $this->vfs->write($agent, $path, $chunk, 'overwrite', permissionAlreadyChecked: true);
            $written[] = $path;
        }

        // VFS deviation: split lists the files it wrote.
        return ['stdout' => $this->linesOut($written), 'cwd' => $cwd, 'class' => 'write', 'written' => $written];
    }

    private function parseSize(string $value, string $option): int
    {
        if (preg_match('/^(\d+)([kKmMgG]?)$/', $value, $m) !== 1) {
            throw VfsError::invalid("{$option} requires a byte count such as 1000, 10k, or 1M.");
        }

        return (int) $m[1] * match (strtolower($m[2])) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            default => 1,
        };
    }

    private function splitSuffix(int $index, int $length, bool $numeric): string
    {
        if ($numeric) {
            return str_pad((string) $index, $length, '0', STR_PAD_LEFT);
        }
        $letters = '';
        do {
            $letters = chr(ord('a') + ($index % 26)).$letters;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return str_pad($letters, $length, 'a', STR_PAD_LEFT);
    }

    /**
     * csplit [-s] [-f PREFIX] [-n DIGITS] FILE PATTERN (line number or /regex/).
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function csplit(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('csplit', $words);
        $source = isset($args->operands[0]) ? $this->path($args->operands[0], $cwd) : throw VfsError::invalid('csplit requires an input path and a pattern.');
        $spec = $args->operands[1] ?? throw VfsError::invalid('csplit requires a split pattern (line number or /regex/).');
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $content = $this->readFull($agent, $source);
        $prefix = $this->path($args->value('-f') ?? 'xx', $cwd);
        $digits = $args->int('-n') ?? 2;
        $lines = preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (preg_match('/^\d+$/', $spec) === 1) {
            $at = max(1, (int) $spec) - 1;
        } elseif (preg_match('#^/(.*)/$#', $spec, $m) === 1) {
            $at = null;
            foreach ($lines as $index => $line) {
                if (@preg_match('~'.str_replace('~', '\~', $m[1]).'~', rtrim($line, "\n")) === 1) {
                    $at = $index;

                    break;
                }
            }
            if ($at === null) {
                throw VfsError::invalid("csplit: {$spec}: no match.");
            }
        } else {
            throw VfsError::unsupported('csplit supports a line number or a /regex/ pattern.');
        }
        $chunks = [implode('', array_slice($lines, 0, $at)), implode('', array_slice($lines, $at))];
        $sizes = [];
        foreach ($chunks as $index => $chunk) {
            $this->vfs->write($agent, $prefix.str_pad((string) $index, $digits, '0', STR_PAD_LEFT), $chunk, 'overwrite', permissionAlreadyChecked: true);
            $sizes[] = (string) strlen($chunk);
        }

        return ['stdout' => $args->has('s') ? '' : $this->linesOut($sizes), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * ln SRC DEST: the VFS has no links, so ln copies (ln -s is refused in
     * RoutesVfsCommands::assertNoRefusedFlags).
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function link(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('ln', $words);
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) !== 2) {
            throw VfsError::invalid('ln requires source and destination paths.');
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $existing = $this->statOrNull($agent, $paths[1]);
        if ($existing !== null && ! $args->has('f')) {
            throw VfsError::invalid("ln: '{$paths[1]}' already exists", ['path' => $paths[1], 'hint' => 'Use ln -f to replace it.']);
        }
        $lines = [];
        $this->copyFile($agent, $paths[0], $paths[1], false, $lines);

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * Collect archive members from operand paths, bounded globally.
     *
     * @param  list<string>  $operands
     * @param  list<string>  $excludes
     * @return array<string, string>
     */
    private function archiveEntries(User $agent, array $operands, string $base, bool $relativeToBase, VfsBudget $budget, array $excludes): array
    {
        if ($operands === []) {
            throw VfsError::invalid('archive creation requires at least one input path.');
        }
        $entries = [];
        $bytes = 0;
        $ceiling = $this->contentCeiling();
        foreach ($operands as $operand) {
            $root = $this->path($operand, $base);
            $walk = $this->walkFiles($agent, [$root], $budget);
            if ($walk['truncated']) {
                throw VfsError::invalid("archive input {$root} has more files or levels than the VFS budget allows (maxFiles {$budget->maxFiles}, maxDepth {$budget->maxDepth}); nothing was written.", ['hint' => 'Archive sub-folders separately or raise maxFiles/maxDepth.']);
            }
            $rootName = $relativeToBase ? ltrim($operand, '/') : basename($root);
            foreach ($walk['files'] as $file) {
                $name = $file === $root ? $rootName : trim($rootName.'/'.ltrim(substr($file, strlen(rtrim($root, '/'))), '/'), '/');
                $name = $this->safeArchiveName($name);
                foreach ($excludes as $glob) {
                    if (fnmatch($glob, $name) || fnmatch($glob, basename($name))) {
                        continue 2;
                    }
                }
                if (count($entries) >= $budget->maxFiles) {
                    throw VfsError::invalid("archive would contain more than maxFiles ({$budget->maxFiles}) members; nothing was written.", ['hint' => 'Raise maxFiles or archive fewer paths.']);
                }
                $content = $this->readFull($agent, $file);
                $bytes += strlen($content);
                if ($bytes > $ceiling) {
                    throw VfsError::invalid("archive would exceed the VFS content limit ({$ceiling} bytes); nothing was written.", ['bytes' => $bytes, 'max_content_bytes' => $ceiling]);
                }
                $entries[$name] = $content;
            }
        }
        if ($entries === []) {
            throw VfsError::invalid('archive creation found no readable files.');
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function buildTarArchive(array $entries): string
    {
        $archive = '';
        foreach ($entries as $name => $content) {
            if (strlen($name) > 100) {
                throw VfsError::invalid("tar member name is longer than 100 bytes: {$name}");
            }
            $archive .= $this->tarHeader($name, strlen($content)).$content.str_repeat("\0", (512 - (strlen($content) % 512)) % 512);
        }

        return $archive.str_repeat("\0", 1024);
    }

    private function tarHeader(string $name, int $size): string
    {
        $header = str_pad($name, 100, "\0")
            .sprintf('%07o', 0644)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%011o', $size)."\0"
            .sprintf('%011o', time())."\0"
            .'        '
            .'0'
            .str_repeat("\0", 100)
            .'ustar'."\0".'00'
            .str_repeat("\0", 247);
        $checksum = array_sum(array_map('ord', str_split($header)));

        return substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);
    }

    /**
     * @return array<string, string>
     */
    private function parseTarArchive(string $archive, VfsBudget $budget): array
    {
        $entries = [];
        for ($offset = 0; $offset + 512 <= strlen($archive);) {
            $header = substr($archive, $offset, 512);
            if (trim($header, "\0") === '') {
                break;
            }
            $name = rtrim(substr($header, 0, 100), "\0");
            $prefix = rtrim(substr($header, 345, 155), "\0");
            if ($prefix !== '' && substr($header, 257, 5) === 'ustar') {
                $name = $prefix.'/'.$name;
            }
            $size = (int) octdec(trim(substr($header, 124, 12), " \0") ?: '0');
            $type = substr($header, 156, 1);
            $offset += 512;
            if (in_array($type, ['0', "\0", ''], true) && $name !== '') {
                if (count($entries) >= $budget->maxFiles) {
                    throw VfsError::invalid("tar archive has more than maxFiles ({$budget->maxFiles}) members.", ['hint' => 'Raise maxFiles to list or extract it.']);
                }
                $entries[$this->safeArchiveName($name)] = substr($archive, $offset, $size);
            }
            $offset += (int) (ceil($size / 512) * 512);
        }

        return $entries;
    }

    private function archiveInput(User $agent, ?string $file, VfsArgs $args, string $cwd, ?string $stdin): string
    {
        if ($file === null || $file === '-') {
            $path = $file === null ? ($args->operands[0] ?? null) : null;
            if ($path === null) {
                if ($stdin === null) {
                    throw VfsError::invalid('archive command requires an archive path (-f FILE) or stdin.');
                }
                if (($this->currentBudget?->truncations() ?? 0) > 0) {
                    throw VfsError::invalid('Archive input from a pipe was truncated at maxBytes.', ['hint' => 'Pass the archive path with -f (or as an argument) instead of piping it.']);
                }
                $this->assertPipeWithinBudget($stdin);

                return $stdin;
            }
            $file = $path;
        }

        return $this->readFull($agent, $this->path($file, $cwd));
    }

    private function archiveDestination(?string $destination, string $cwd): string
    {
        $path = $this->path($destination ?? $cwd, $cwd);
        if (! str_starts_with($path.'/', '/files/')) {
            throw VfsError::notWritable($path);
        }

        return $path;
    }

    private function safeArchiveName(string $name): string
    {
        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $name)), fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..'));
        if ($parts === []) {
            throw VfsError::invalid('archive entry path is empty or unsafe.');
        }

        return implode('/', $parts);
    }

    private function stripArchiveComponents(string $name, int $strip): ?string
    {
        $parts = array_slice(explode('/', $this->safeArchiveName($name)), $strip);

        return $parts === [] ? null : implode('/', $parts);
    }

    private function assertZipExtensionAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw VfsError::unsupported('PHP ZipArchive support is not available in this runtime.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function zipEntries(string $archive, VfsBudget $budget): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vfs_unzip_');
        if ($tmp === false) {
            throw VfsError::invalid('unzip could not allocate a temporary archive.');
        }
        file_put_contents($tmp, $archive);
        $zip = new ZipArchive;
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw VfsError::invalid('unzip input is not a valid zip archive.');
        }
        $entries = [];
        $bytes = 0;
        $ceiling = $this->contentCeiling();
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || str_ends_with($name, '/')) {
                    continue;
                }
                if (count($entries) >= $budget->maxFiles) {
                    throw VfsError::invalid("zip archive has more than maxFiles ({$budget->maxFiles}) members.", ['hint' => 'Raise maxFiles to list or extract it.']);
                }
                // Check the declared size before inflating the member.
                $declared = (int) ($zip->statIndex($i)['size'] ?? 0);
                if ($bytes + $declared > $ceiling) {
                    throw VfsError::invalid("zip archive expands beyond the VFS content limit ({$ceiling} bytes).", ['max_content_bytes' => $ceiling]);
                }
                $content = $zip->getFromIndex($i, $ceiling - $bytes + 1);
                if ($content === false) {
                    continue;
                }
                $bytes += strlen($content);
                if ($bytes > $ceiling) {
                    throw VfsError::invalid("zip archive expands beyond the VFS content limit ({$ceiling} bytes).", ['max_content_bytes' => $ceiling]);
                }
                $entries[$this->safeArchiveName($name)] = $content;
            }
        } finally {
            $zip->close();
            @unlink($tmp);
        }

        return $entries;
    }
}
