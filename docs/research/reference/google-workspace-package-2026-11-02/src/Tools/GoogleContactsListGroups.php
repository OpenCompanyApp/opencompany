<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsListGroups implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_list_groups';
    }

    public function description(): string
    {
        return 'List all Google Contact groups/labels (e.g., Friends, Family, custom groups) with member counts.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $result = $this->service->listContactGroups();
            $groups = $result['contactGroups'] ?? [];

            if (empty($groups)) {
                return ToolResult::success('No contact groups found.');
            }

            $formatted = [];
            foreach ($groups as $group) {
                $groupType = $group['groupType'] ?? '';
                $formatted[] = array_filter([
                    'resourceName' => $group['resourceName'] ?? '',
                    'name' => $group['name'] ?? $group['formattedName'] ?? '',
                    'type' => $groupType,
                    'memberCount' => $group['memberCount'] ?? 0,
                ], fn ($v) => $v !== '' && $v !== 0);
            }

            return ToolResult::success([
                'count' => count($formatted),
                'groups' => $formatted,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [];
    }
}
