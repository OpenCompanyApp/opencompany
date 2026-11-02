<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsDeleteItem implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_delete_item';
    }

    public function description(): string
    {
        return 'Delete an item from a Google Form by its 0-based index. Use google_forms_get to see current form structure.';
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

            $index = $args['index'] ?? null;
            if ($index === null) {
                return ToolResult::error('index is required (0-based item position).');
            }

            $this->service->batchUpdate((string) $formId, [
                ['deleteItem' => [
                    'location' => ['index' => (int) $index],
                ]],
            ]);

            return ToolResult::success("Item at index {$index} deleted.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'index' => ['type' => 'integer', 'required' => true, 'description' => '0-based position of the item to delete.'],
        ];
    }
}