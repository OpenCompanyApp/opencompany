<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsDelete implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_delete';
    }

    public function description(): string
    {
        return 'Permanently delete a Google Contact by resource name.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $resourceName = $args['resource_name'] ?? '';
            if (empty($resourceName)) {
                return ToolResult::error('resourceName is required.');
            }

            $this->service->deleteContact($resourceName);

            return ToolResult::success('Contact deleted.');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'resource_name' => ['type' => 'string', 'required' => true, 'description' => 'Contact resource name (e.g., "people/c1234567890").'],
        ];
    }
}
