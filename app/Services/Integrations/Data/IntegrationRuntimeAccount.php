<?php

namespace App\Services\Integrations\Data;

class IntegrationRuntimeAccount
{
    public function __construct(
        public readonly string $integrationId,
        public readonly ?string $alias,
        public readonly bool $isDefault,
    ) {}
}
