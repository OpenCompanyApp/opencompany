<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailReply implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_reply';
    }

    public function description(): string
    {
        return 'Reply to an existing Gmail message (maintains the thread).';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $messageId = $args['message_id'] ?? '';
            $threadId = $args['thread_id'] ?? '';
            $body = $args['body'] ?? '';

            if (empty($messageId)) {
                return ToolResult::error('messageId is required.');
            }
            if (empty($threadId)) {
                return ToolResult::error('threadId is required.');
            }
            if (empty($body)) {
                return ToolResult::error('body is required.');
            }

            // Fetch original message to get headers for reply
            $original = $this->service->getMessage($messageId, 'metadata');
            $payload = $original['payload'] ?? [];
            $originalFrom = GmailService::getHeader($payload, 'From');
            $originalSubject = GmailService::getHeader($payload, 'Subject');
            $originalMessageId = GmailService::getHeader($payload, 'Message-ID');

            // Reply to the sender
            $to = $args['to'] ?? $originalFrom;

            // Add Re: prefix if not present
            $subject = $originalSubject;
            if (! str_starts_with(strtolower($subject), 're:')) {
                $subject = "Re: {$subject}";
            }

            $raw = GmailService::buildRawMessage($to, $subject, $body, [
                'cc' => $args['cc'] ?? null,
                'bcc' => $args['bcc'] ?? null,
                'inReplyTo' => $originalMessageId,
                'references' => $originalMessageId,
            ]);

            $result = $this->service->sendMessage([
                'raw' => $raw,
                'threadId' => $threadId,
            ]);

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
            'message_id' => ['type' => 'string', 'required' => true, 'description' => 'Original message ID to reply to.'],
            'thread_id' => ['type' => 'string', 'required' => true, 'description' => 'Thread ID to reply in.'],
            'body' => ['type' => 'string', 'required' => true, 'description' => 'Reply body text.'],
            'to' => ['type' => 'string', 'description' => 'Recipient email address (defaults to original sender).'],
            'cc' => ['type' => 'string', 'description' => 'CC recipients (comma-separated emails).'],
            'bcc' => ['type' => 'string', 'description' => 'BCC recipients (comma-separated emails).'],
        ];
    }
}
