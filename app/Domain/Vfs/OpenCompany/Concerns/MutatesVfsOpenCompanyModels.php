<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

/**
 * Composite mutation concern for the OpenCompany VFS adapter.
 *
 * Document, file, and structured-record mutation rules are split into narrower
 * traits while this keeps OpenCompanyVfs imports stable.
 */
trait MutatesVfsOpenCompanyModels
{
    use MutatesVfsAgentArea;
    use MutatesVfsDocumentRecords;
    use MutatesVfsDocuments;
    use MutatesVfsFiles;
    use MutatesVfsListItems;
    use MutatesVfsTableRows;
    use PatchesVfsRecords;
}
