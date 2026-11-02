<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;
use OpenCompany\IntegrationCore\Contracts\AgentFileStorage;

class GmailSaveAttachment implements Tool
{
    public function __construct(
        private GmailService $service,
        private AgentFileStorage $fileStorage,
        private object $agent,
    ) {}

    public function name(): string
    {
        return 'gmail_save_attachment';
    }

    public function description(): string
    {
        return <<<'MD'
        Download an email attachment and save it to workspace files.
        Requires a messageId and attachmentId (both returned by gmail_read).
        The file is saved under the agent's folder and can be browsed in the Files page.
        MD;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $messageId = $args['message_id'] ?? '';
            if (empty($messageId)) {
                return ToolResult::error('messageId is required.');
            }

            $attachmentId = $args['attachment_id'] ?? '';
            if (empty($attachmentId)) {
                return ToolResult::error('attachmentId is required.');
            }

            $filename = $args['filename'] ?? '';
            if (empty($filename)) {
                return ToolResult::error('filename is required.');
            }

            $mimeType = $args['mime_type'] ?? 'application/octet-stream';

            $bytes = $this->service->getAttachment($messageId, $attachmentId);

            if (empty($bytes)) {
                return ToolResult::error('Attachment is empty or could not be downloaded.');
            }

            $result = $this->fileStorage->saveFile($this->agent, $filename, $bytes, $mimeType, 'gmail');

            return ToolResult::success([
                'filename' => $filename,
                'path' => $result['path'],
                'url' => $result['url'],
                'size' => strlen($bytes),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'message_id' => ['type' => 'string', 'required' => true, 'description' => 'Gmail message ID containing the attachment.'],
            'attachment_id' => ['type' => 'string', 'required' => true, 'description' => 'Attachment ID from the gmail_read response.'],
            'filename' => ['type' => 'string', 'required' => true, 'description' => 'Filename to save as (e.g. "invoice.pdf"). Use the filename from gmail_read.'],
            'mime_type' => ['type' => 'string', 'description' => 'MIME type of the attachment (e.g. "application/pdf"). Use the mimeType from gmail_read.'],
        ];
    }
}
