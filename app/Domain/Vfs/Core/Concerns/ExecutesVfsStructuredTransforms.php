<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Structured or expression-based read transforms for safe VFS pipelines.
 */
trait ExecutesVfsStructuredTransforms
{
    use ExecutesVfsJqTransforms;

    private function sed(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'sed');
        if (in_array('-i', $tokens, true)) {
            throw VfsError::unsupported('sed -i is not supported; use vfs_patch for targeted edits.');
        }
        $quiet = $this->shortFlags($tokens, ['n', 'E'])['n'] ?? false;
        $expr = $this->optionValue($tokens, ['-e']) ?? ($tokens[0] ?? null);
        if (! is_string($expr) || preg_match('#^s(.)(.*?)\1(.*?)\1g?$#', $expr, $matches) !== 1) {
            if ($expr === 'p') {
                return ['stdout' => $stdin ?? '', 'class' => 'read-transform'];
            }
            throw VfsError::unsupported('Only safe sed substitution and print expressions are supported, e.g. sed s/foo/bar/g.');
        }

        $stdout = preg_replace('/'.preg_quote($matches[2], '/').'/', $matches[3], $stdin ?? '') ?? '';

        return ['stdout' => $quiet ? '' : $stdout, 'class' => 'read-transform'];
    }
}
