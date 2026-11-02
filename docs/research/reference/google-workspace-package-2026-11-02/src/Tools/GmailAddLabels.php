<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailAddLabels implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_add_labels';
    }

    public function description(): string
    {
        return 'Add labels to one or more Gmail messages. Provide messageIds (comma-separated) for batch operations.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $labelIds = $args['label_ids'] ?? '';
            if (empty($labelIds)) {
                return ToolResult::error('labelIds is required.');
            }

            $messageIds = $this->getMessageIds($args);
            if ($messageIds === null) {
                return ToolResult::error('messageId or messageIds is required.');
            }

            $labels = array_map('trim', explode(',', $labelIds));
            $data = ['addLabelIds' => $labels];

            if (count($messageIds) === 1) {
                $this->service->modifyMessage($messageIds[0], $data);
            } else {
                $data['ids'] = $messageIds;
                $this->service->batchModifyMessages($data);
            }

            $count = count($messageIds);

            return ToolResult::success("{$count} message(s) labels added successfully.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function getMessageIds(array $args): ?array
    {
        if (isset($args['message_ids'])) {
            $ids = array_map('trim', explode(',', $args['message_ids']));

            return array_filter($ids, fn (string $id) => $id !== '');
        }

        if (isset($args['message_id']) && $args['message_id'] !== '') {
            return [$args['message_id']];
        }

        return null;
    }

    public function parameters(): array
    {
        return [
            'message_id' => ['type' => 'string', 'description' => 'Single message ID to add labels to.'],
            'message_ids' => ['type' => 'string', 'description' => 'Comma-separated message IDs for batch operations.'],
            'label_ids' => ['type' => 'string', 'required' => true, 'description' => 'Comma-separated label IDs to add.'],
        ];
    }
}
