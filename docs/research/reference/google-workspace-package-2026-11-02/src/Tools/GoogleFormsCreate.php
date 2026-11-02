<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsCreate implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_create';
    }

    public function description(): string
    {
        return 'Create a new Google Form with a title, optional description, and optional quiz mode. Auto-publishes. Returns form ID, edit URL, and responder URL.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Forms integration is not configured.');
            }

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title is required.');
            }

            $result = $this->service->createForm((string) $title);
            $formId = $result['formId'] ?? '';
            $responderUri = $result['responderUri'] ?? '';
            $editUrl = "https://docs.google.com/forms/d/{$formId}/edit";

            // Add description if provided
            $description = $args['description'] ?? '';
            if ($description !== '') {
                $this->service->batchUpdate($formId, [
                    ['updateFormInfo' => [
                        'info' => ['description' => (string) $description],
                        'updateMask' => 'description',
                    ]],
                ]);
            }

            // Enable quiz mode if requested
            $isQuiz = (bool) ($args['is_quiz'] ?? false);
            if ($isQuiz) {
                $this->service->batchUpdate($formId, [
                    ['updateSettings' => [
                        'settings' => ['quizSettings' => ['isQuiz' => true]],
                        'updateMask' => 'quizSettings.isQuiz',
                    ]],
                ]);
            }

            // Auto-publish the form
            $this->service->setPublishSettings($formId, [
                'publishSettings' => [
                    'isPublished' => true,
                    'isAcceptingResponses' => true,
                ],
            ]);

            $lines = [
                'Form created.',
                "Title: \"$title\"",
                "ID: {$formId}",
                "Edit URL: {$editUrl}",
            ];

            if ($responderUri !== '') {
                $lines[] = "Responder URL: {$responderUri}";
            }

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Title of the new form.'],
            'description' => ['type' => 'string', 'description' => 'Description of the form.'],
            'is_quiz' => ['type' => 'boolean', 'description' => 'Enable quiz mode (default false).'],
        ];
    }
}