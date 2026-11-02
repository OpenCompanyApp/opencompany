<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsUpdateQuestion implements Tool
{
    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_update_question';
    }

    public function description(): string
    {
        return 'Update a question in a Google Form by its 0-based index. Can update title, description, required status, and options (for choice questions). Use google_forms_get to see current form structure.';
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

            // Fetch current form to get the item at this index
            $form = $this->service->getForm((string) $formId);
            $items = $form['items'] ?? [];

            if ((int) $index >= count($items) || (int) $index < 0) {
                return ToolResult::error('index ' . $index . ' is out of range. Form has ' . count($items) . ' items.');
            }

            $currentItem = $items[(int) $index];

            // Build updated item
            $updateMask = [];

            if (isset($args['title'])) {
                $currentItem['title'] = (string) $args['title'];
                $updateMask[] = 'title';
            }

            if (isset($args['description'])) {
                $currentItem['description'] = (string) $args['description'];
                $updateMask[] = 'description';
            }

            if (isset($args['required']) && isset($currentItem['questionItem'])) {
                $currentItem['questionItem']['question']['required'] = (bool) $args['required'];
                $updateMask[] = 'questionItem.question.required';
            }

            if (isset($args['options']) && isset($currentItem['questionItem'])) {
                $question = $currentItem['questionItem']['question'] ?? [];
                if (isset($question['choiceQuestion'])) {
                    $options = is_array($args['options']) ? $args['options'] : [];
                    $choiceOptions = [];
                    foreach ($options as $opt) {
                        $choiceOptions[] = ['value' => (string) $opt];
                    }
                    $currentItem['questionItem']['question']['choiceQuestion']['options'] = $choiceOptions;
                    $updateMask[] = 'questionItem.question.choiceQuestion.options';
                }
            }

            if (empty($updateMask)) {
                return ToolResult::error('At least one update field is required (title, description, required, options).');
            }

            $this->service->batchUpdate((string) $formId, [
                ['updateItem' => [
                    'item' => $currentItem,
                    'location' => ['index' => (int) $index],
                    'updateMask' => implode(',', $updateMask),
                ]],
            ]);

            return ToolResult::success('Question at index ' . $index . ' updated (' . implode(', ', $updateMask) . ').');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'index' => ['type' => 'integer', 'required' => true, 'description' => '0-based item position of the question to update.'],
            'title' => ['type' => 'string', 'description' => 'New title for the question.'],
            'description' => ['type' => 'string', 'description' => 'New description/help text for the question.'],
            'required' => ['type' => 'boolean', 'description' => 'Whether the question is required.'],
            'options' => ['type' => 'array', 'description' => 'New options array (for choice questions: multiple_choice, checkbox, dropdown).'],
        ];
    }
}