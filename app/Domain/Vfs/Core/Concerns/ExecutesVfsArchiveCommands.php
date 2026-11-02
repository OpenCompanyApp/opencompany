<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use ZipArchive;

/**
 * Archive and multi-output file commands for the VFS shell executor.
 *
 * These commands intentionally operate on bounded in-memory archives instead of
 * delegating to host binaries. Reads still flow through the VFS adapter and
 * writes still require the same vfs_write capability as primitive file tools.
 *
 * Invariant: every byte that ends up in a written file (archive members on
 * create, extracted members, split chunks) comes from a content read, never a
 * display read. Display truncation would otherwise be persisted as a corrupt
 * archive or a prefix plus "[... truncated]". Sizes are bounded by the content
 * ceiling (config vfs.max_content_bytes) per operation instead.
 */
trait ExecutesVfsArchiveCommands
{
    private function tar(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'tar');
        $flags = $this->shortFlags($tokens, ['c', 'x', 't', 'z', 'j', 'J', 'v']);
        if (($flags['j'] ?? false) || ($flags['J'] ?? false)) {
            throw VfsError::unsupported('tar bzip2/xz compression is not supported; use -z for gzip or plain tar.');
        }
        $modes = array_filter([$flags['c'] ?? false, $flags['x'] ?? false, $flags['t'] ?? false]);
        if (count($modes) !== 1) {
            throw VfsError::invalid('tar requires exactly one of -c, -x, or -t.');
        }

        if ($flags['c'] ?? false) {
            $archive = $this->buildTarArchive($this->archiveEntriesFromTokens($agent, $tokens, $cwd, $budget, ['-f', '-C', '--strip-components', '--exclude']));
            if ($flags['z'] ?? false) {
                $encoded = gzencode($archive);
                if ($encoded === false) {
                    throw VfsError::invalid('tar could not gzip the archive.');
                }
                $archive = $encoded;
            }

            $file = $this->optionValue($tokens, ['-f']);
            if ($file !== null && $file !== '-') {
                $this->assertWriteCommandAllowed($agent, 'vfs_write');
                $this->vfs->write($agent, $this->path($file, $cwd), $archive, 'overwrite', permissionAlreadyChecked: true);

                return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write'];
            }

            return ['stdout' => $archive, 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $archive = $this->archiveInput($agent, $tokens, $cwd, $budget, $stdin, ['-f', '-C', '--strip-components', '--exclude']);
        if ($flags['z'] ?? false) {
            $ceiling = $this->archiveContentCeiling();
            $decoded = @gzdecode($archive, $ceiling + 1);
            if ($decoded === false) {
                throw VfsError::invalid('tar archive is not valid gzip data, or it expands beyond the content limit.', ['max_content_bytes' => $ceiling]);
            }
            $this->assertArchiveBytesWithinCeiling(strlen($decoded), 'tar');
            $archive = $decoded;
        }
        $entries = $this->parseTarArchive($archive, $budget);

        if ($flags['t'] ?? false) {
            return ['stdout' => implode("\n", array_keys($entries)), 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $destination = $this->archiveDestination($tokens, $cwd);
        $strip = $this->integerOptionValue($tokens, ['--strip-components'], 'tar', 'strip components') ?? 0;
        $written = [];
        foreach ($entries as $name => $content) {
            $relative = $this->stripArchiveComponents($name, $strip);
            if ($relative === null) {
                continue;
            }
            $path = $this->safeArchiveOutputPath($destination, $relative);
            $this->vfs->write($agent, $path, $content, 'overwrite', permissionAlreadyChecked: true);
            $written[] = $path;
        }

        return ['stdout' => implode("\n", $written), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function zip(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertZipExtensionAvailable();
        $this->assertSupportedOptionsForCommand($tokens, 'zip');
        $archivePath = $this->zipArchivePath($tokens, $cwd);
        $entries = $this->archiveEntriesFromTokens($agent, array_slice($tokens, $this->zipArchiveTokenIndex($tokens) + 1), $cwd, $budget, []);
        if ($entries === []) {
            throw VfsError::invalid('zip requires at least one readable input path.');
        }
        $junkPaths = $this->shortFlags($tokens, ['r', 'j', 'q'])['j'] ?? false;

        $tmp = tempnam(sys_get_temp_dir(), 'vfs_zip_');
        if ($tmp === false) {
            throw VfsError::invalid('zip could not allocate a temporary archive.');
        }
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw VfsError::invalid('zip could not create the archive.');
        }
        foreach ($entries as $name => $content) {
            $zip->addFromString($junkPaths ? basename($name) : $name, $content);
        }
        $zip->close();
        $archive = file_get_contents($tmp);
        @unlink($tmp);
        if ($archive === false) {
            throw VfsError::invalid('zip could not read the generated archive.');
        }

        $this->vfs->write($agent, $archivePath, $archive, 'overwrite', permissionAlreadyChecked: true);

        return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write'];
    }

    private function unzip(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertZipExtensionAvailable();
        $this->assertSupportedOptionsForCommand($tokens, 'unzip');
        $flags = $this->shortFlags($tokens, ['o', 'l', 'q', 'p', 't']);
        $archive = $this->archiveInput($agent, $tokens, $cwd, $budget, $stdin, ['-d']);
        $entries = $this->zipEntries($archive, $budget);

        if ($flags['t'] ?? false) {
            return ['stdout' => implode("\n", array_map(fn (string $name): string => "testing: {$name} OK", array_keys($entries))), 'cwd' => $cwd, 'class' => 'read-transform'];
        }
        if (($flags['l'] ?? false) || ($flags['p'] ?? false)) {
            // -p prints member bytes: that is display output, so it is
            // budgeted (and marked) like any other read.
            return ['stdout' => ($flags['p'] ?? false) ? VfsText::fit(implode('', $entries), $budget, 'unzip -p') : implode("\n", array_keys($entries)), 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $destination = $this->archiveDestination($tokens, $cwd, '-d');
        $written = [];
        foreach ($entries as $name => $content) {
            $path = $this->safeArchiveOutputPath($destination, $name);
            if (! ($flags['o'] ?? false)) {
                try {
                    $this->vfs->stat($agent, $path);
                    throw VfsError::invalid("Path already exists: {$path}", ['path' => $path]);
                } catch (VfsError $e) {
                    if ($e->errorCode !== 'not_found') {
                        throw $e;
                    }
                }
            }
            $this->vfs->write($agent, $path, $content, 'overwrite', permissionAlreadyChecked: true);
            $written[] = $path;
        }

        return ['stdout' => implode("\n", $written), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function split(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'split');
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, ['-l', '-b', '-n', '-a']);
        $source = $paths[0] ?? throw VfsError::invalid('split requires an input path.');
        $prefix = $paths[1] ?? $this->path('x', $cwd);
        $content = $this->vfs->readContent($agent, $source);
        $suffixLength = $this->integerOptionValue($tokens, ['-a'], 'split', 'suffix length') ?? 2;
        $chunks = $this->splitChunks($content, $tokens);
        $written = [];
        foreach ($chunks as $index => $chunk) {
            $suffix = $this->splitSuffix($index, $suffixLength, $this->shortFlags($tokens, ['d'])['d'] ?? false);
            $path = $prefix.$suffix;
            $this->vfs->write($agent, $path, $chunk, 'overwrite', permissionAlreadyChecked: true);
            $written[] = $path;
        }

        return ['stdout' => implode("\n", $written), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function csplit(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'csplit');
        $args = $this->positionalTokens($tokens, ['-f', '-n', '-b']);
        $source = isset($args[0]) ? $this->path($args[0], $cwd) : throw VfsError::invalid('csplit requires an input path.');
        $spec = $args[1] ?? null;
        $content = $this->vfs->readContent($agent, $source);
        $prefix = $this->path($this->optionValue($tokens, ['-f']) ?? 'xx', $cwd);
        $digits = $this->integerOptionValue($tokens, ['-n'], 'csplit', 'suffix digits') ?? 2;
        $chunks = $this->csplitChunks($content, is_string($spec) ? $spec : null);
        $written = [];
        foreach ($chunks as $index => $chunk) {
            $path = $prefix.str_pad((string) $index, $digits, '0', STR_PAD_LEFT);
            $this->vfs->write($agent, $path, $chunk, 'overwrite', permissionAlreadyChecked: true);
            $written[] = (string) strlen($chunk);
        }

        return ['stdout' => implode("\n", $written), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @return list<string>
     */
    private function positionalTokens(array $tokens, array $valueOptions): array
    {
        $args = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, $valueOptions, true)) {
                $i++;

                continue;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            $args[] = $token;
        }

        return $args;
    }

    private function link(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'ln');
        if ($this->shortFlags($tokens, ['s', 'f', 'n', 'v'])['s'] ?? false) {
            throw VfsError::unsupported('ln -s requires first-class VFS symlink entries, which are not enabled.');
        }
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, []);
        if (count($paths) < 2) {
            throw VfsError::invalid('ln requires source and destination paths.');
        }

        return $this->copy($agent, [$paths[0], $paths[1]], $cwd);
    }

    /**
     * @return array<string, string>
     */
    private function archiveEntriesFromTokens(User $agent, array $tokens, string $cwd, VfsBudget $budget, array $valueOptions): array
    {
        $entries = [];
        $bytes = 0;
        foreach ($this->pathArgsWithValueOptions($tokens, $cwd, $valueOptions) as $path) {
            foreach ($this->archiveEntriesForPath($agent, $path, $budget, basename($path), 0) as $name => $content) {
                $bytes += strlen($content);
                $this->assertArchiveBytesWithinCeiling($bytes, 'archive creation');
                $entries[$name] = $content;
            }
        }
        if ($entries === []) {
            throw VfsError::invalid('archive creation requires at least one readable input path.');
        }

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function archiveEntriesForPath(User $agent, string $path, VfsBudget $budget, string $name, int $depth): array
    {
        $stat = $this->vfs->stat($agent, $path);
        if (($stat['type'] ?? null) !== 'directory') {
            return [$this->safeArchiveName($name) => $this->vfs->readContent($agent, $path)];
        }
        if ($depth >= $budget->maxDepth) {
            return [];
        }
        $entries = [];
        foreach ($this->vfs->listBounded($agent, $path, $budget) as $entry) {
            foreach ($this->archiveEntriesForPath($agent, $entry->path, $budget, trim($name.'/'.$entry->name, '/'), $depth + 1) as $childName => $content) {
                if (count($entries) >= $budget->maxFiles) {
                    break 2;
                }
                $entries[$childName] = $content;
            }
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
            $archive .= $this->tarHeader($name, strlen($content)).$content.str_repeat("\0", (512 - (strlen($content) % 512)) % 512);
        }

        return $archive.str_repeat("\0", 1024);
    }

    private function tarHeader(string $name, int $size): string
    {
        $name = substr($this->safeArchiveName($name), 0, 100);
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
        $checksum = 0;
        foreach (str_split($header) as $byte) {
            $checksum += ord($byte);
        }

        return substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);
    }

    /**
     * @return array<string, string>
     */
    private function parseTarArchive(string $archive, VfsBudget $budget): array
    {
        $entries = [];
        for ($offset = 0; $offset + 512 <= strlen($archive); $offset += 512) {
            $header = substr($archive, $offset, 512);
            if (trim($header, "\0") === '') {
                break;
            }
            $name = rtrim(substr($header, 0, 100), "\0");
            $size = octdec(trim(substr($header, 124, 12)) ?: '0');
            $type = substr($header, 156, 1);
            $offset += 512;
            $content = substr($archive, $offset, $size);
            if ($type !== '5' && $name !== '') {
                // Full member bytes: extraction writes these back. The archive
                // itself was already bounded by the content ceiling.
                $entries[$this->safeArchiveName($name)] = $content;
                if (count($entries) >= $budget->maxFiles) {
                    break;
                }
            }
            $offset += (int) (ceil($size / 512) * 512) - 512;
        }

        return $entries;
    }

    private function archiveInput(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin, array $valueOptions): string
    {
        if ($stdin !== null) {
            // Piped archive bytes are only trustworthy if no read upstream in
            // this command was display-truncated; a cut archive is corrupt.
            if ($budget->truncations() > 0) {
                throw VfsError::invalid('Archive input from a pipe was truncated at maxBytes.', [
                    'hint' => 'Pass the archive path with -f (or as an argument) instead of piping it.',
                ]);
            }
            $this->assertArchiveBytesWithinCeiling(strlen($stdin), 'archive input');

            return $stdin;
        }
        $file = $this->optionValue($tokens, ['-f']);
        if ($file !== null && $file !== '-') {
            return $this->vfs->readContent($agent, $this->path($file, $cwd));
        }
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, $valueOptions);
        $path = $paths[0] ?? throw VfsError::invalid('archive command requires an archive path or stdin.');

        return $this->vfs->readContent($agent, $path);
    }

    private function archiveDestination(array $tokens, string $cwd, string $option = '-C'): string
    {
        $destination = $this->optionValue($tokens, [$option]) ?? $cwd;
        $path = $this->path($destination, $cwd);
        if (! str_starts_with($path, '/files')) {
            throw VfsError::notWritable($path);
        }

        return $path;
    }

    private function safeArchiveOutputPath(string $destination, string $name): string
    {
        $safe = $this->safeArchiveName($name);

        return rtrim($destination, '/').'/'.$safe;
    }

    private function safeArchiveName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $parts = array_values(array_filter(explode('/', $name), fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..'));
        if ($parts === []) {
            throw VfsError::invalid('archive entry path is empty or unsafe.');
        }

        return implode('/', $parts);
    }

    private function stripArchiveComponents(string $name, int $strip): ?string
    {
        $parts = explode('/', $this->safeArchiveName($name));
        $parts = array_slice($parts, $strip);

        return $parts === [] ? null : implode('/', $parts);
    }

    private function archiveContentCeiling(): int
    {
        return max(1, (int) config('vfs.max_content_bytes', 20 * 1024 * 1024));
    }

    /**
     * Bound the total bytes one archive operation may hold in memory or write.
     */
    private function assertArchiveBytesWithinCeiling(int $bytes, string $operation): void
    {
        $ceiling = $this->archiveContentCeiling();
        if ($bytes > $ceiling) {
            throw VfsError::invalid("{$operation} exceeds the VFS content limit ({$ceiling} bytes).", [
                'bytes' => $bytes,
                'max_content_bytes' => $ceiling,
                'hint' => 'Archive or extract fewer or smaller files per command.',
            ]);
        }
    }

    private function assertZipExtensionAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw VfsError::unsupported('PHP ZipArchive support is not available in this runtime.');
        }
    }

    private function zipArchiveTokenIndex(array $tokens): int
    {
        foreach ($tokens as $index => $token) {
            if (str_starts_with($token, '-')) {
                continue;
            }

            return $index;
        }

        throw VfsError::invalid('zip requires an archive path.');
    }

    private function zipArchivePath(array $tokens, string $cwd): string
    {
        return $this->path($tokens[$this->zipArchiveTokenIndex($tokens)], $cwd);
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
        try {
            for ($i = 0; $i < $zip->numFiles && count($entries) < $budget->maxFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || str_ends_with($name, '/')) {
                    continue;
                }
                // Check the declared size before inflating the member.
                $declared = $zip->statIndex($i)['size'] ?? 0;
                $this->assertArchiveBytesWithinCeiling($bytes + (int) $declared, 'unzip');
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }
                $bytes += strlen($content);
                $this->assertArchiveBytesWithinCeiling($bytes, 'unzip');
                // Full member bytes: extraction writes these back.
                $entries[$this->safeArchiveName($name)] = $content;
            }
        } finally {
            $zip->close();
            @unlink($tmp);
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function splitChunks(string $content, array $tokens): array
    {
        $bytes = $this->byteOptionValue($tokens, ['-b'], 'split', 'byte count');
        if ($bytes !== null) {
            return str_split($content, max(1, $bytes));
        }
        $linesPerFile = $this->integerOptionValue($tokens, ['-l'], 'split', 'line count') ?? 1000;
        $lines = explode("\n", $content);

        return array_map(fn (array $chunk): string => implode("\n", $chunk), array_chunk($lines, max(1, $linesPerFile)));
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
     * @return list<string>
     */
    private function csplitChunks(string $content, ?string $spec): array
    {
        if ($spec === null) {
            throw VfsError::invalid('csplit requires a split pattern or line number.');
        }
        $lines = explode("\n", $content);
        if (preg_match('/^\d+$/', $spec) === 1) {
            $line = max(1, (int) $spec);

            return [implode("\n", array_slice($lines, 0, $line - 1)), implode("\n", array_slice($lines, $line - 1))];
        }
        if (preg_match('#^/(.*)/$#', $spec, $matches) === 1) {
            foreach ($lines as $index => $line) {
                if (@preg_match('/'.$matches[1].'/', $line) === 1) {
                    return [implode("\n", array_slice($lines, 0, $index)), implode("\n", array_slice($lines, $index))];
                }
            }

            throw VfsError::invalid('csplit pattern did not match.');
        }

        throw VfsError::unsupported('csplit supports numeric line specs and /regex/ specs.');
    }
}
