<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsGet implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_get';
    }

    public function description(): string
    {
        return 'Get full details of a single Google Contact including notes, websites, and group memberships.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $resourceName = $args['resource_name'] ?? '';
            if (empty($resourceName)) {
                return ToolResult::error('resourceName is required (e.g., "people/c1234567890").');
            }

            $person = $this->service->getContact($resourceName);
            $contact = GoogleContactsService::formatContact($person);

            return ToolResult::success($contact);
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
