<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsList implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_list';
    }

    public function description(): string
    {
        return 'List all Google Contacts sorted by first name with pagination.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $pageSize = isset($args['max_results']) ? min((int) $args['max_results'], 100) : 20;
            $pageToken = $args['page_token'] ?? null;

            $result = $this->service->listContacts($pageSize, $pageToken);
            $connections = $result['connections'] ?? [];

            if (empty($connections)) {
                return ToolResult::success('No contacts found.');
            }

            $contacts = array_map(
                fn (array $person) => GoogleContactsService::formatContact($person),
                $connections
            );

            $output = [
                'count' => count($contacts),
                'totalPeople' => (int) ($result['totalPeople'] ?? 0),
                'contacts' => $contacts,
            ];

            if (isset($result['nextPageToken'])) {
                $output['nextPageToken'] = $result['nextPageToken'];
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'max_results' => ['type' => 'integer', 'description' => 'Max results to return (default: 20, max: 100).'],
            'page_token' => ['type' => 'string', 'description' => 'Pagination token from previous response.'],
        ];
    }
}
