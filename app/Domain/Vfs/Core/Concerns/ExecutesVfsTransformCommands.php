<?php

namespace App\Domain\Vfs\Core\Concerns;

/**
 * Composite transform concern for the VFS command executor.
 *
 * Kept as the single executor import point while the actual command families
 * live in narrower traits below this namespace.
 */
trait ExecutesVfsTransformCommands
{
    use ExecutesVfsComparisonTransforms;
    use ExecutesVfsFieldTransforms;
    use ExecutesVfsOutputTransforms;
    use ExecutesVfsStructuredTransforms;
    use ExecutesVfsTextFilters;
}
