<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleFormsService;

class GoogleFormsAddQuestion implements Tool
{
    /** @var array<int, string> Valid question types */
    private const QUESTION_TYPES = [
        'text', 'paragraph', 'multiple_choice', 'checkbox', 'dropdown',
        'scale', 'date', 'time', 'rating',
    ];

    public function __construct(
        private GoogleFormsService $service,
    ) {}

    public function name(): string
    {
        return 'google_forms_add_question';
    }

    public function description(): string
    {
        return 'Add a question to a Google Form. Supports types: text, paragraph, multiple_choice, checkbox, dropdown, scale, date, time, rating. Use options for choice types. Use low/high/lowLabel/highLabel for scale. Use ratingScale/ratingIcon for rating. Use includeTime/includeYear for date. Use duration for time. Omit index to add at end. Use google_forms_get to see current form structure before editing.';
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

            $type = $args['type'] ?? '';
            if (empty($type) || ! in_array((string) $type, self::QUESTION_TYPES, true)) {
                return ToolResult::error('type is required. Valid values: ' . implode(', ', self::QUESTION_TYPES) . '.');
            }

            // Build options array from request
            $options = $args['options'] ?? [];
            if (! is_array($options)) {
                $options = [];
            }

            $item = $this->service->buildQuestionItem([
                'type' => (string) $type,
                'title' => (string) $title,
                'description' => (string) ($args['description'] ?? ''),
                'required' => (bool) ($args['required'] ?? false),
                'options' => $options,
                'low' => $args['low'] ?? 1,
                'high' => $args['high'] ?? 5,
                'lowLabel' => (string) ($args['low_label'] ?? ''),
                'highLabel' => (string) ($args['high_label'] ?? ''),
                'ratingScale' => $args['rating_scale'] ?? 5,
                'ratingIcon' => (string) ($args['rating_icon'] ?? 'STAR'),
                'includeTime' => (bool) ($args['include_time'] ?? false),
                'includeYear' => (bool) ($args['include_year'] ?? true),
                'duration' => (bool) ($args['duration'] ?? false),
            ]);

            $createRequest = ['createItem' => ['item' => $item]];

            $index = $args['index'] ?? null;
            if ($index !== null) {
                $createRequest['createItem']['location'] = ['index' => (int) $index];
            }

            $this->service->batchUpdate((string) $formId, [$createRequest]);

            $location = $index !== null ? "at index {$index}" : 'at end';

            return ToolResult::success("Question added {$location}: [{$type}] \"$title\"");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'form_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Forms form ID.'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Question title text.'],
            'type' => ['type' => 'string', 'required' => true, 'description' => 'Question type: text, paragraph, multiple_choice, checkbox, dropdown, scale, date, time, or rating.'],
            'required' => ['type' => 'boolean', 'description' => 'Whether the question is required (default false).'],
            'description' => ['type' => 'string', 'description' => 'Help text / description for the question.'],
            'options' => ['type' => 'array', 'description' => 'Array of option strings (for multiple_choice, checkbox, dropdown).'],
            'low' => ['type' => 'integer', 'description' => 'For scale: low value (default 1).'],
            'high' => ['type' => 'integer', 'description' => 'For scale: high value (default 5).'],
            'low_label' => ['type' => 'string', 'description' => 'For scale: label for the low end.'],
            'high_label' => ['type' => 'string', 'description' => 'For scale: label for the high end.'],
            'rating_scale' => ['type' => 'integer', 'description' => 'For rating: scale level 3-10 (default 5).'],
            'rating_icon' => ['type' => 'string', 'description' => 'For rating: STAR (default), HEART, or THUMB_UP.'],
            'include_time' => ['type' => 'boolean', 'description' => 'For date: include time (default false).'],
            'include_year' => ['type' => 'boolean', 'description' => 'For date: include year (default true).'],
            'duration' => ['type' => 'boolean', 'description' => 'For time: duration mode instead of time-of-day (default false).'],
            'index' => ['type' => 'integer', 'description' => 'Insert position (0-based). Omit to add at end.'],
        ];
    }
}