<?php

/*
|--------------------------------------------------------------------------
| OpenCompany virtual filesystem
|--------------------------------------------------------------------------
|
| App-owned limits for the VFS adapter (app/Domain/Vfs). Display reads are
| budgeted per call (VfsBudget::maxBytes) and may end with a visible
| "[... truncated]" line. Content reads (OpenCompanyVfs::readContent) are the
| read half of every read-modify-write path and must never be truncated, so they
| use this hard ceiling instead: above it the operation fails with
| invalid_request rather than silently persisting a prefix.
|
*/

return [
    'max_content_bytes' => (int) env('VFS_MAX_CONTENT_BYTES', 20 * 1024 * 1024),
];
