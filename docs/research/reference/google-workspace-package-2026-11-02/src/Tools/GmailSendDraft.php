<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailSendDraft implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_send_draft';
    }

    public function description(): string
    {
        return 'Send a previously created Gmail draft by its ID.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $draftId = $args['draft_id'] ?? '';
            if (empty($draftId)) {
                return ToolResult::error('draftId is required.');
            }

            $result = $this->service->sendDraft(['id' => $draftId]);

            return ToolResult::success([
                'id' => $result['id'] ?? '',
                'threadId' => $result['threadId'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'draft_id' => ['type' => 'string', 'required' => true, 'description' => 'Draft ID to send.'],
        ];
    }
}
