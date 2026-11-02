<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleFormsService
{
    private const BASE_URL = 'https://forms.googleapis.com/v1';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    // ─── API Methods ───

    /**
     * Get a form by ID (full JSON).
     *
     * @return array<string, mixed>
     */
    public function getForm(string $formId): array
    {
        return $this->client->get(self::BASE_URL . '/forms/' . $formId);
    }

    /**
     * Create a new blank form.
     *
     * @return array<string, mixed>
     */
    public function createForm(string $title): array
    {
        return $this->client->post(self::BASE_URL . '/forms', [
            'info' => ['title' => $title],
        ]);
    }

    /**
     * Apply batch updates to a form.
     *
     * @param  array<int, array<string, mixed>>  $requests
     * @return array<string, mixed>
     */
    public function batchUpdate(string $formId, array $requests): array
    {
        return $this->client->post(
            self::BASE_URL . '/forms/' . $formId . ':batchUpdate',
            ['requests' => $requests]
        );
    }

    /**
     * Set publish settings for a form.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function setPublishSettings(string $formId, array $settings): array
    {
        return $this->client->post(
            self::BASE_URL . '/forms/' . $formId . ':setPublishSettings',
            $settings
        );
    }

    /**
     * List form responses.
     *
     * @return array<string, mixed>
     */
    public function listResponses(string $formId, ?string $filter = null, int $pageSize = 10, ?string $pageToken = null): array
    {
        $query = ['pageSize' => $pageSize];

        if ($filter !== null) {
            $query['filter'] = $filter;
        }
        if ($pageToken !== null) {
            $query['pageToken'] = $pageToken;
        }

        return $this->client->get(self::BASE_URL . '/forms/' . $formId . '/responses', $query);
    }

    /**
     * Get a single form response.
     *
     * @return array<string, mixed>
     */
    public function getResponse(string $formId, string $responseId): array
    {
        return $this->client->get(self::BASE_URL . '/forms/' . $formId . '/responses/' . $responseId);
    }

    // ─── Helper Methods ───

    /**
     * Format a form into a readable structure outline.
     *
     * @param  array<string, mixed>  $form
     */
    public function formatFormStructure(array $form): string
    {
        $formId = $form['formId'] ?? '';
        $info = $form['info'] ?? [];
        $title = $info['title'] ?? 'Untitled';
        $description = $info['description'] ?? '';
        $responderUri = $form['responderUri'] ?? '';
        $settings = $form['settings'] ?? [];
        $items = $form['items'] ?? [];

        $lines = [];
        $lines[] = "Form: \"{$title}\" (id: {$formId})";

        if ($responderUri !== '') {
            $lines[] = "URL: {$responderUri}";
        }

        if ($description !== '') {
            $lines[] = "Description: {$description}";
        }

        // Settings summary
        $isQuiz = ! empty($settings['quizSettings']['isQuiz']);
        $quizLabel = $isQuiz ? 'Yes' : 'No';
        $lines[] = "Settings: Quiz={$quizLabel}";

        if (empty($items)) {
            $lines[] = '';
            $lines[] = 'No questions yet.';

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = 'Questions:';

        $questionCount = 0;
        $sectionCount = 0;

        foreach ($items as $i => $item) {
            $itemTitle = $item['title'] ?? '';
            $itemDescription = $item['description'] ?? '';

            if (isset($item['questionItem'])) {
                $questionCount++;
                $question = $item['questionItem']['question'] ?? [];
                $required = ! empty($question['required']);
                $requiredLabel = $required ? ', required' : '';
                $typeLabel = $this->getQuestionTypeLabel($question);

                $line = ($i + 1) . ". [{$typeLabel}{$requiredLabel}] \"{$itemTitle}\"";

                // Add options/details
                $details = $this->getQuestionDetails($question);
                if ($details !== '') {
                    $line .= " — {$details}";
                }

                $lines[] = $line;

                if ($itemDescription !== '') {
                    $lines[] = "   Help: {$itemDescription}";
                }
            } elseif (isset($item['questionGroupItem'])) {
                $questionCount++;
                $lines[] = ($i + 1) . ". [grid] \"{$itemTitle}\"";
            } elseif (isset($item['pageBreakItem'])) {
                $sectionCount++;
                $lines[] = "--- Section: \"{$itemTitle}\" ---";
                if ($itemDescription !== '') {
                    $lines[] = "    {$itemDescription}";
                }
            } elseif (isset($item['textItem'])) {
                $lines[] = ($i + 1) . ". [text] \"{$itemTitle}\"";
                if ($itemDescription !== '') {
                    $lines[] = "   {$itemDescription}";
                }
            } elseif (isset($item['imageItem'])) {
                $lines[] = ($i + 1) . '. [image]';
            } elseif (isset($item['videoItem'])) {
                $lines[] = ($i + 1) . '. [video]';
            }
        }

        $lines[] = '';
        $summary = "Total: {$questionCount} " . ($questionCount === 1 ? 'question' : 'questions');
        if ($sectionCount > 0) {
            $summary .= ", {$sectionCount} " . ($sectionCount === 1 ? 'section' : 'sections');
        }
        $lines[] = $summary;

        return implode("\n", $lines);
    }

    /**
     * Format responses with question labels.
     *
     * @param  array<string, mixed>  $responsesData
     * @param  array<string, mixed>  $form
     */
    public function formatResponses(array $responsesData, array $form): string
    {
        $responses = $responsesData['responses'] ?? [];
        $nextPageToken = $responsesData['nextPageToken'] ?? null;
        $totalCount = $responsesData['totalUpdateCount'] ?? count($responses);

        $title = $form['info']['title'] ?? 'Untitled';

        // Build questionId -> title map
        $questionMap = $this->buildQuestionMap($form);

        if (empty($responses)) {
            return "No responses to \"{$title}\".";
        }

        $lines = [];
        $lines[] = count($responses) . " response(s) to \"{$title}\"";
        $lines[] = '';

        foreach ($responses as $i => $response) {
            $responseId = $response['responseId'] ?? '';
            $createTime = $response['createTime'] ?? '';
            $respondentEmail = $response['respondentEmail'] ?? '';

            $header = 'Response ' . ($i + 1);
            if ($createTime !== '') {
                // Format ISO timestamp to readable
                $header .= ' (' . $this->formatTimestamp($createTime);
                if ($respondentEmail !== '') {
                    $header .= ", {$respondentEmail}";
                }
                $header .= ')';
            }
            $header .= " [id: {$responseId}]";
            $lines[] = $header . ':';

            $answers = $response['answers'] ?? [];
            if (empty($answers)) {
                $lines[] = '  (no answers)';
            } else {
                foreach ($answers as $questionId => $answer) {
                    $label = $questionMap[$questionId] ?? "Question {$questionId}";
                    $value = $this->formatAnswerValue($answer);
                    $lines[] = "  {$label} -> {$value}";
                }
            }

            $lines[] = '';
        }

        if ($nextPageToken !== null) {
            $lines[] = "More responses available (use pageToken: \"{$nextPageToken}\")";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a question item for batchUpdate from tool parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function buildQuestionItem(array $params): array
    {
        $type = $params['type'] ?? 'text';
        $title = $params['title'] ?? '';
        $description = $params['description'] ?? '';
        $required = (bool) ($params['required'] ?? false);

        $question = ['required' => $required];

        switch ($type) {
            case 'text':
                $question['textQuestion'] = ['paragraph' => false];
                break;

            case 'paragraph':
                $question['textQuestion'] = ['paragraph' => true];
                break;

            case 'multiple_choice':
                $options = $this->buildChoiceOptions($params['options'] ?? []);
                $question['choiceQuestion'] = [
                    'type' => 'RADIO',
                    'options' => $options,
                ];
                break;

            case 'checkbox':
                $options = $this->buildChoiceOptions($params['options'] ?? []);
                $question['choiceQuestion'] = [
                    'type' => 'CHECKBOX',
                    'options' => $options,
                ];
                break;

            case 'dropdown':
                $options = $this->buildChoiceOptions($params['options'] ?? []);
                $question['choiceQuestion'] = [
                    'type' => 'DROP_DOWN',
                    'options' => $options,
                ];
                break;

            case 'scale':
                $question['scaleQuestion'] = [
                    'low' => (int) ($params['low'] ?? 1),
                    'high' => (int) ($params['high'] ?? 5),
                    'lowLabel' => $params['lowLabel'] ?? '',
                    'highLabel' => $params['highLabel'] ?? '',
                ];
                break;

            case 'date':
                $question['dateQuestion'] = [
                    'includeTime' => (bool) ($params['includeTime'] ?? false),
                    'includeYear' => (bool) ($params['includeYear'] ?? true),
                ];
                break;

            case 'time':
                $question['timeQuestion'] = [
                    'duration' => (bool) ($params['duration'] ?? false),
                ];
                break;

            case 'rating':
                $ratingQuestion = [
                    'ratingScaleLevel' => (int) ($params['ratingScale'] ?? 5),
                ];
                $iconType = strtoupper($params['ratingIcon'] ?? 'STAR');
                if (in_array($iconType, ['STAR', 'HEART', 'THUMB_UP'], true)) {
                    $ratingQuestion['iconType'] = $iconType;
                }
                $question['ratingQuestion'] = $ratingQuestion;
                break;

            default:
                // Default to text
                $question['textQuestion'] = ['paragraph' => false];
        }

        $item = [
            'title' => $title,
            'questionItem' => ['question' => $question],
        ];

        if ($description !== '') {
            $item['description'] = $description;
        }

        return $item;
    }

    // ─── Private Helpers ───

    /**
     * Get a human-readable label for a question type.
     *
     * @param  array<string, mixed>  $question
     */
    private function getQuestionTypeLabel(array $question): string
    {
        if (isset($question['textQuestion'])) {
            return ! empty($question['textQuestion']['paragraph']) ? 'paragraph' : 'text';
        }
        if (isset($question['choiceQuestion'])) {
            $choiceType = $question['choiceQuestion']['type'] ?? 'RADIO';

            return match ($choiceType) {
                'RADIO' => 'multiple_choice',
                'CHECKBOX' => 'checkbox',
                'DROP_DOWN' => 'dropdown',
                default => 'choice',
            };
        }
        if (isset($question['scaleQuestion'])) {
            return 'scale';
        }
        if (isset($question['dateQuestion'])) {
            return 'date';
        }
        if (isset($question['timeQuestion'])) {
            return 'time';
        }
        if (isset($question['ratingQuestion'])) {
            return 'rating';
        }
        if (isset($question['fileUploadQuestion'])) {
            return 'file_upload';
        }

        return 'unknown';
    }

    /**
     * Get details string for a question (options, scale range, etc.).
     *
     * @param  array<string, mixed>  $question
     */
    private function getQuestionDetails(array $question): string
    {
        if (isset($question['choiceQuestion'])) {
            $options = $question['choiceQuestion']['options'] ?? [];
            $optionValues = [];
            foreach ($options as $option) {
                if (isset($option['value'])) {
                    $optionValues[] = (string) $option['value'];
                }
            }

            return 'Options: ' . implode(', ', $optionValues);
        }

        if (isset($question['scaleQuestion'])) {
            $scale = $question['scaleQuestion'];
            $low = $scale['low'] ?? 1;
            $high = $scale['high'] ?? 5;
            $lowLabel = $scale['lowLabel'] ?? '';
            $highLabel = $scale['highLabel'] ?? '';
            $result = "{$low}";
            if ($lowLabel !== '') {
                $result .= " ({$lowLabel})";
            }
            $result .= " to {$high}";
            if ($highLabel !== '') {
                $result .= " ({$highLabel})";
            }

            return $result;
        }

        if (isset($question['ratingQuestion'])) {
            $rating = $question['ratingQuestion'];
            $scale = $rating['ratingScaleLevel'] ?? 5;
            $icon = strtolower($rating['iconType'] ?? 'STAR');

            return "{$scale} {$icon}s";
        }

        return '';
    }

    /**
     * Build questionId -> question title map from form.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, string>
     */
    private function buildQuestionMap(array $form): array
    {
        $map = [];
        $items = $form['items'] ?? [];

        foreach ($items as $item) {
            $title = $item['title'] ?? '';

            if (isset($item['questionItem'])) {
                $questionId = $item['questionItem']['question']['questionId'] ?? null;
                if ($questionId !== null) {
                    $map[(string) $questionId] = $title;
                }
            } elseif (isset($item['questionGroupItem'])) {
                $questions = $item['questionGroupItem']['questions'] ?? [];
                foreach ($questions as $q) {
                    $questionId = $q['question']['questionId'] ?? null;
                    $rowTitle = $q['title'] ?? $title;
                    if ($questionId !== null) {
                        $map[(string) $questionId] = $rowTitle;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Format a response answer value to readable string.
     *
     * @param  array<string, mixed>  $answer
     */
    private function formatAnswerValue(array $answer): string
    {
        $textAnswers = $answer['textAnswers'] ?? null;
        if ($textAnswers !== null) {
            $answers = $textAnswers['answers'] ?? [];
            $values = [];
            foreach ($answers as $a) {
                $values[] = (string) ($a['value'] ?? '');
            }

            return implode(', ', $values);
        }

        return '(no value)';
    }

    /**
     * Format ISO timestamp to a readable format.
     */
    private function formatTimestamp(string $iso): string
    {
        try {
            $dt = new \DateTimeImmutable($iso);

            return $dt->format('Y-m-d H:i');
        } catch (\Exception) {
            return $iso;
        }
    }

    /**
     * Build choice options array from string values.
     *
     * @param  array<int, mixed>  $options
     * @return array<int, array{value: string}>
     */
    private function buildChoiceOptions(array $options): array
    {
        $result = [];
        foreach ($options as $option) {
            $result[] = ['value' => (string) $option];
        }

        // Ensure at least one option
        if (empty($result)) {
            $result[] = ['value' => 'Option 1'];
        }

        return $result;
    }
}
