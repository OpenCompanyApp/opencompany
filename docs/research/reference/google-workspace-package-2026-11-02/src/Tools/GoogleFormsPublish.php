<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsPublish implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_publish';
    }

    public function description(): string
    {
        return 'Set publish settings for a Google Form: publish/unpublish and accept/stop accepting responses. At least one of published or acceptingResponses must be provided.';
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

            $published = $args['published'] ?? null;
            $acceptingResponses = $args['accepting_responses'] ?? null;

            if ($published === null && $acceptingResponses === null) {
                return ToolResult::error('At least one of published or acceptingResponses is required.');
            }

            $publishSettings = [];
            $changes = [];

            if ($published !== null) {
                $publishSettings['isPublished'] = (bool) $published;
                $changes[] = $published ? 'published' : 'unpublished';
            }

            if ($acceptingResponses !== null) {
                $publishSettings['isAcceptingResponses'] = (bool) $acceptingResponses;
                $changes[] = $acceptingResponses ? 'accepting responses' : 'not accepting responses';
            }

            $this->service->setPublishSettings((string) $formId, [
                'publishSettings' => $publishSettings,
            ]);

            return ToolResult::success('Form is now ' . implode(' and ', $changes) . '.');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'published' => ['type' => 'boolean', 'description' => 'Publish or unpublish the form.'],
            'accepting_responses' => ['type' => 'boolean', 'description' => 'Accept or stop accepting responses.'],
        ];
    }
}