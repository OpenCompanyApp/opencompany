<?php

namespace App\Domain\Ai\Codex\Contracts;

use App\Domain\Ai\Codex\ValueObjects\CodexToken;

/**
 * Stores Codex OAuth tokens for OpenCompany's app-owned Codex gateway.
 *
 * The contract deliberately exposes value objects instead of Eloquent models so
 * token refresh and runtime calls do not depend on persistence details or leak
 * encrypted model attributes across the AI runtime boundary.
 */
interface CodexTokenStore
{
    public function current(): ?CodexToken;

    public function save(CodexToken $token): CodexToken;

    public function clear(): void;
}
