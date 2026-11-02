<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsSearchContacts implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_search_contacts';
    }

    public function description(): string
    {
        return 'Fuzzy search Google Contacts by name, email, or phone. Matches partial strings (e.g., "john", "acme.com", "555"). Use this to look up contacts before sending emails with gmail_send.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $query = $args['query'] ?? '';
            if (empty($query)) {
                return ToolResult::error('query is required for search.');
            }

            $pageSize = isset($args['max_results']) ? min((int) $args['max_results'], 30) : 10;

            $result = $this->service->searchContacts($query, $pageSize);
            $results = $result['results'] ?? [];

            if (empty($results)) {
                return ToolResult::success('No contacts found.');
            }

            $contacts = [];
            foreach ($results as $entry) {
                $person = $entry['person'] ?? [];
                if (! empty($person)) {
                    $contacts[] = GoogleContactsService::formatContact($person);
                }
            }

            return ToolResult::success([
                'count' => count($contacts),
                'contacts' => $contacts,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'required' => true, 'description' => 'Search query (name, email, or phone).'],
            'max_results' => ['type' => 'integer', 'description' => 'Max results to return (default: 10, max: 30).'],
        ];
    }
}
