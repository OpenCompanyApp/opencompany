<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailSendEmail implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_send_email';
    }

    public function description(): string
    {
        return 'Send an email directly via Gmail.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $to = $args['to'] ?? '';
            $subject = $args['subject'] ?? '';
            $body = $args['body'] ?? '';

            if (empty($to)) {
                return ToolResult::error('to is required.');
            }
            if (empty($subject)) {
                return ToolResult::error('subject is required.');
            }
            if (empty($body)) {
                return ToolResult::error('body is required.');
            }

            $raw = GmailService::buildRawMessage($to, $subject, $body, [
                'cc' => $args['cc'] ?? null,
                'bcc' => $args['bcc'] ?? null,
            ]);

            $result = $this->service->sendMessage(['raw' => $raw]);

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
            'to' => ['type' => 'string', 'required' => true, 'description' => 'Recipient email address.'],
            'subject' => ['type' => 'string', 'required' => true, 'description' => 'Email subject.'],
            'body' => ['type' => 'string', 'required' => true, 'description' => 'Email body text.'],
            'cc' => ['type' => 'string', 'description' => 'CC recipients (comma-separated emails).'],
            'bcc' => ['type' => 'string', 'description' => 'BCC recipients (comma-separated emails).'],
        ];
    }
}
