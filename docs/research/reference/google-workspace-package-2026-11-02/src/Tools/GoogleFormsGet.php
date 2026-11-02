<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsGet implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_get';
    }

    public function description(): string
    {
        return <<<'MD'
        Get a Google Form's structure: title, description, settings, all questions with types/options/IDs, and responder URL.
        The form ID is the long string in the Google Forms URL: docs.google.com/forms/d/{formId}/edit
        To list all forms, use google_drive_search with file type "application/vnd.google-apps.form".
        MD;
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

            $form = $this->service->getForm((string) $formId);

            return $this->service->formatFormStructure($form);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID (from the URL).'],
        ];
    }
}
