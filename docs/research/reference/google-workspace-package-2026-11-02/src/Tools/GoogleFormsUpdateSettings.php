<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsUpdateSettings implements Tool
{
    /** @var array<int, string> Valid email collection types */
    private const EMAIL_COLLECTION_TYPES = [
        'DO_NOT_COLLECT', 'VERIFIED', 'RESPONDER_INPUT',
    ];

    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_update_settings';
    }

    public function description(): string
    {
        return 'Update Google Form settings such as quiz mode and email collection. At least one setting must be provided.';
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

            $settings = [];
            $fields = [];

            if (isset($args['is_quiz'])) {
                $settings['quizSettings'] = ['isQuiz' => (bool) $args['is_quiz']];
                $fields[] = 'quizSettings.isQuiz';
            }

            if (isset($args['email_collection'])) {
                $emailType = strtoupper((string) $args['email_collection']);
                if (! in_array($emailType, self::EMAIL_COLLECTION_TYPES, true)) {
                    return ToolResult::error('emailCollection must be one of: ' . implode(', ', self::EMAIL_COLLECTION_TYPES) . '.');
                }
                $settings['emailCollection'] = $emailType;
                $fields[] = 'emailCollection';
            }

            if (empty($fields)) {
                return ToolResult::error('At least one setting is required (isQuiz, emailCollection).');
            }

            $this->service->batchUpdate((string) $formId, [
                ['updateSettings' => [
                    'settings' => $settings,
                    'updateMask' => implode(',', $fields),
                ]],
            ]);

            return ToolResult::success('Settings updated (' . implode(', ', $fields) . ').');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'is_quiz' => ['type' => 'boolean', 'description' => 'Enable or disable quiz mode.'],
            'email_collection' => ['type' => 'string', 'description' => 'Email collection: DO_NOT_COLLECT, VERIFIED, or RESPONDER_INPUT.'],
        ];
    }
}