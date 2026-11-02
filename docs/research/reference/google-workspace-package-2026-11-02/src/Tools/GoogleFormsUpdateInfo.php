<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsUpdateInfo implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_update_info';
    }

    public function description(): string
    {
        return 'Update a Google Form title and/or description. At least one of title or description must be provided.';
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

            $title = $args['title'] ?? null;
            $description = $args['description'] ?? null;

            if ($title === null && $description === null) {
                return ToolResult::error('At least one of title or description is required.');
            }

            $info = [];
            $fields = [];

            if ($title !== null) {
                $info['title'] = (string) $title;
                $fields[] = 'title';
            }
            if ($description !== null) {
                $info['description'] = (string) $description;
                $fields[] = 'description';
            }

            $this->service->batchUpdate((string) $formId, [
                ['updateFormInfo' => [
                    'info' => $info,
                    'updateMask' => implode(',', $fields),
                ]],
            ]);

            return ToolResult::success('Form info updated (' . implode(', ', $fields) . ').');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'title' => ['type' => 'string', 'description' => 'New title for the form.'],
            'description' => ['type' => 'string', 'description' => 'New description for the form.'],
        ];
    }
}