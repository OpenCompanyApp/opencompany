<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsGetResponse implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_get_response';
    }

    public function description(): string
    {
        return <<<'MD'
        Get a single response to a Google Form by response ID, with question labels.
        The form ID is the long string in the Google Forms URL: docs.google.com/forms/d/{formId}/edit
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

            $responseId = $args['response_id'] ?? '';
            if (empty($responseId)) {
                return ToolResult::error('responseId is required.');
            }

            $response = $this->service->getResponse((string) $formId, (string) $responseId);

            // Fetch form for question labels
            $form = $this->service->getForm((string) $formId);

            // Format as single response
            return $this->service->formatResponses(['responses' => [$response]], $form);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID (from the URL).'],
            'response_id' => ['type' => 'string', 'required' => true, 'description' => 'Response ID.'],
        ];
    }
}