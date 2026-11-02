<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsMoveItem implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_move_item';
    }

    public function description(): string
    {
        return 'Move an item in a Google Form from one 0-based index to another. Use google_forms_get to see current form structure.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Forms integration is not configured.');
            }

            $formId = $args['form_id'] ?? '';
            if (empty($formId)) {
                return ToolResult::error('formId is required.');
            }

            $from = $args['from'] ?? null;
            $to = $args['to'] ?? null;
            if ($from === null || $to === null) {
                return ToolResult::error('from and to are required (0-based positions).');
            }

            $this->service->batchUpdate((string) $formId, [
                ['moveItem' => [
                    'originalLocation' => ['index' => (int) $from],
                    'newLocation' => ['index' => (int) $to],
                ]],
            ]);

            return ToolResult::success("Item moved from index {$from} to index {$to}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'from' => ['type' => 'integer', 'required' => true, 'description' => 'Current item index (0-based).'],
            'to' => ['type' => 'integer', 'required' => true, 'description' => 'Target index (0-based).'],
        ];
    }
}