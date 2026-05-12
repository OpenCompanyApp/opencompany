<?php

namespace App\Services\Integrations\Data;

/**
 * Lightweight account descriptor exposed by IntegrationRuntime.
 *
 * It intentionally contains no credentials; callers only need the integration
 * ID, optional alias, and whether the account is the default choice.
 */
class IntegrationRuntimeAccount
{
    public function __construct(
        public readonly string $integrationId,
        public readonly ?string $alias,
        public readonly bool $isDefault,
    ) {}
}
