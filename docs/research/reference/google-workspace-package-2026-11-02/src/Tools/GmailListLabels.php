<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailListLabels implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_list_labels';
    }

    public function description(): string
    {
        return 'List all labels in the Gmail mailbox (INBOX, SENT, custom labels, etc.).';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $result = $this->service->listLabels();
            $labels = $result['labels'] ?? [];

            if (empty($labels)) {
                return ToolResult::success('No labels found.');
            }

            $formatted = array_map(fn (array $label) => [
                'id' => $label['id'] ?? '',
                'name' => $label['name'] ?? '',
                'type' => $label['type'] ?? '',
            ], $labels);

            return ToolResult::success(['count' => count($formatted), 'labels' => $formatted]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [];
    }
}
