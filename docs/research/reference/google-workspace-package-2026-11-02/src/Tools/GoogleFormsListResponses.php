<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsListResponses implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_list_responses';
    }

    public function description(): string
    {
        return <<<'MD'
        List responses to a Google Form with question labels.
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

            $after = $args['after'] ?? null;
            $pageSize = (int) ($args['page_size'] ?? 10);
            $pageToken = $args['page_token'] ?? null;

            $filter = null;
            if ($after !== null && $after !== '') {
                $filter = "timestamp >= {$after}";
            }

            $responsesData = $this->service->listResponses(
                (string) $formId,
                $filter,
                $pageSize,
                $pageToken !== null ? (string) $pageToken : null,
            );

            // Fetch form to get question labels
            $form = $this->service->getForm((string) $formId);

            return $this->service->formatResponses($responsesData, $form);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID (from the URL).'],
            'after' => ['type' => 'string', 'description' => 'Only responses after this timestamp (RFC3339).'],
            'page_size' => ['type' => 'integer', 'description' => 'Max responses per page (default 10, max 5000).'],
            'page_token' => ['type' => 'string', 'description' => 'Pagination token from previous response.'],
        ];
    }
}
