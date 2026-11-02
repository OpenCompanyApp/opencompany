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

    /*
    | Model-facing tool result cap (bytes of encoded JSON, ~25k tokens). A
    | larger direct vfs_* result is saved under the agent's
    | /files/agents/{slug}/.results/ folder through the normal governed write
    | path and replaced by a preview plus `offloaded_path`; if the agent cannot
    | write there without approval, the result is cut with a hint instead.
    | Code Mode callbacks are not offloaded (they have their own result limit).
    */
    'tool_result_max_bytes' => (int) env('VFS_TOOL_RESULT_MAX_BYTES', 100_000),

    /* Bytes of the offloaded result kept inline as a preview. */
    'tool_result_preview_bytes' => (int) env('VFS_TOOL_RESULT_PREVIEW_BYTES', 8_000),

    // Aggregate stdout cap for one vfs_exec command line (all groups, pipes
    // and xargs runs together). Output beyond it is dropped and replaced by a
    // final "[truncated: ...]" line plus output_truncated: true.
    'max_exec_output_bytes' => (int) env('VFS_MAX_EXEC_OUTPUT_BYTES', 100_000),

    // /workspace/changes.ndjson delivers an event only once its ULID time is
    // this many seconds old, so rows whose transactions commit late (or come
    // from a server with a slightly skewed clock) are never skipped by a
    // saved cursor. Changes appear in the feed up to this long after they
    // happened. 0 delivers immediately (tests only).
    'change_feed_settle_seconds' => (int) env('VFS_CHANGE_FEED_SETTLE_SECONDS', 5),
];
