<?php

namespace App\Domain\Web\Contracts;

use App\Domain\Web\Enums\WebCapability;

/**
 * Shared metadata contract for web providers.
 *
 * Search and fetch adapters are app-owned runtime primitives. Provider classes
 * must describe only their stable identity and capabilities here; credentials,
 * workspace policy, and agent permissions stay outside adapters.
 */
interface WebProvider
{
    public function id(): string;

    public function label(): string;

    public function isAvailable(): bool;

    public function supports(WebCapability $capability): bool;
}
