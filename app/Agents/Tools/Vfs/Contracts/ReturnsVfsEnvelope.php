<?php

namespace App\Agents\Tools\Vfs\Contracts;

/**
 * Marker for tools whose string result is the VFS JSON envelope
 * `{ok: bool, result?: {...}, error?: {code, message, context}}`.
 *
 * IntegrationRuntime relies on this marker (not on slug prefixes) to turn an
 * `ok:false` envelope into a typed Code Mode failure carrying `error.code`,
 * and an `approval_required` envelope into the same approval-pending latch as
 * any other gated callback. Every tool under App\Agents\Tools\Vfs must
 * implement it; VfsCapabilityTruthTest guards that.
 */
interface ReturnsVfsEnvelope {}
