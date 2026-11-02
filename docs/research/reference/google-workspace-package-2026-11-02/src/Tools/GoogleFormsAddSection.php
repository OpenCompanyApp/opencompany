<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsAddSection implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_add_section';
    }

    public function description(): string
    {
        return 'Add a page break / section to a Google Form. Omit index to add at end. Use google_forms_get to see current form structure.';
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

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title is required.');
            }

            $item = [
                'title' => (string) $title,
                'pageBreakItem' => new \stdClass(),
            ];

            $description = $args['description'] ?? '';
            if ($description !== '') {
                $item['description'] = (string) $description;
            }

            $createRequest = ['createItem' => ['item' => $item]];

            $index = $args['index'] ?? null;
            if ($index !== null) {
                $createRequest['createItem']['location'] = ['index' => (int) $index];
            }

            $this->service->batchUpdate((string) $formId, [$createRequest]);

            $location = $index !== null ? "at index {$index}" : 'at end';

            return ToolResult::success("Section \"$title\" added {$location}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Title of the section.'],
            'description' => ['type' => 'string', 'description' => 'Description of the section.'],
            'index' => ['type' => 'integer', 'description' => 'Insert position (0-based). Omit to add at end.'],
        ];
    }
}