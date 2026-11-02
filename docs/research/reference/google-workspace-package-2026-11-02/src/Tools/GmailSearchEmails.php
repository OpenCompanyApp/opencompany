<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailSearchEmails implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_search_emails';
    }

    public function description(): string
    {
        return 'Search Gmail messages using Gmail query syntax (e.g., "from:alice subject:meeting is:unread has:attachment after:2026-02-01"). Returns message summaries with headers. Max 100 per page.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $params = [];

            if (isset($args['query'])) {
                $params['q'] = $args['query'];
            }
            if (isset($args['max_results'])) {
                $params['maxResults'] = (string) min((int) $args['max_results'], 100);
            } else {
                $params['maxResults'] = '10';
            }
            if (isset($args['page_token'])) {
                $params['pageToken'] = $args['page_token'];
            }
            if (isset($args['label_ids'])) {
                $params['labelIds'] = $args['label_ids'];
            }

            // Get message IDs
            $result = $this->service->listMessages($params);
            $messageRefs = $result['messages'] ?? [];

            if (empty($messageRefs)) {
                return ToolResult::success('No messages found.');
            }

            // Fetch metadata for each message
            $messages = [];
            foreach ($messageRefs as $ref) {
                $msgId = $ref['id'] ?? '';
                if (empty($msgId)) {
                    continue;
                }

                $msg = $this->service->getMessage($msgId, 'metadata');
                $payload = $msg['payload'] ?? [];

                $messages[] = [
                    'id' => $msg['id'] ?? '',
                    'threadId' => $msg['threadId'] ?? '',
                    'from' => GmailService::getHeader($payload, 'From'),
                    'to' => GmailService::getHeader($payload, 'To'),
                    'subject' => GmailService::getHeader($payload, 'Subject'),
                    'date' => GmailService::getHeader($payload, 'Date'),
                    'snippet' => $msg['snippet'] ?? '',
                    'labelIds' => $msg['labelIds'] ?? [],
                ];
            }

            $output = ['count' => count($messages), 'messages' => $messages];
            if (isset($result['nextPageToken'])) {
                $output['nextPageToken'] = $result['nextPageToken'];
            }
            if (isset($result['resultSizeEstimate'])) {
                $output['resultSizeEstimate'] = $result['resultSizeEstimate'];
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'description' => 'Gmail search query (e.g., "from:alice subject:meeting is:unread").'],
            'max_results' => ['type' => 'integer', 'description' => 'Max results to return (default: 10, max: 100).'],
            'page_token' => ['type' => 'string', 'description' => 'Pagination token from previous response.'],
            'label_ids' => ['type' => 'string', 'description' => 'Comma-separated label IDs to filter by.'],
        ];
    }
}
