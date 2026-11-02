<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailArchive implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_archive';
    }

    public function description(): string
    {
        return 'Archive one or more Gmail messages (remove from inbox). Provide messageIds (comma-separated) for batch operations.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $messageIds = $this->getMessageIds($args);
            if ($messageIds === null) {
                return ToolResult::error('messageId or messageIds is required.');
            }

            $data = ['removeLabelIds' => ['INBOX']];

            if (count($messageIds) === 1) {
                $this->service->modifyMessage($messageIds[0], $data);
            } else {
                $data['ids'] = $messageIds;
                $this->service->batchModifyMessages($data);
            }

            $count = count($messageIds);

            return ToolResult::success("{$count} message(s) archived.");
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
            'message_id' => ['type' => 'string', 'description' => 'Single message ID to archive.'],
            'message_ids' => ['type' => 'string', 'description' => 'Comma-separated message IDs for batch operations.'],
        ];
    }
}
