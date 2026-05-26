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
        $this->assertSupportedOptions($tokens, 'sed');
        $expr = $tokens[0] ?? null;
        if (! is_string($expr) || preg_match('#^s(.)(.*?)\1(.*?)\1g?$#', $expr, $matches) !== 1) {
            throw VfsError::unsupported('Only safe sed substitution expressions are supported, e.g. sed s/foo/bar/g.');
        }

        return ['stdout' => preg_replace('/'.preg_quote($matches[2], '/').'/', $matches[3], $stdin ?? '') ?? '', 'class' => 'read-transform'];
    }
}
