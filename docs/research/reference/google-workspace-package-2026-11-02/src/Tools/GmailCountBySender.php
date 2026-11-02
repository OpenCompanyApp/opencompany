<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GmailService;

class GmailCountBySender implements Tool
{
    public function __construct(
        private GmailService $service,
    ) {}

    public function name(): string
    {
        return 'gmail_count_by_sender';
    }

    public function description(): string
    {
        return 'Count all matching Gmail messages grouped by sender. Automatically paginates through ALL results (handles thousands of messages). Returns top senders sorted by count. Use for questions like "who sends me the most email?" or "count unread by sender".';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Gmail integration is not configured.');
            }

            $params = ['maxResults' => '500'];

            if (isset($args['query'])) {
                $params['q'] = $args['query'];
            }
            if (isset($args['label_ids'])) {
                $params['labelIds'] = $args['label_ids'];
            }

            /** @var array<string, int> $senderCounts */
            $senderCounts = [];
            $totalProcessed = 0;
            $maxMessages = 10000; // Safety limit

            do {
                $result = $this->service->listMessages($params);
                $messageRefs = $result['messages'] ?? [];

                if (empty($messageRefs)) {
                    break;
                }

                // Fetch only From header for each message
                $ids = array_map(fn (array $ref) => $ref['id'] ?? '', $messageRefs);
                $ids = array_filter($ids, fn (string $id) => $id !== '');

                foreach ($ids as $msgId) {
                    $msg = $this->service->getMessage($msgId, 'metadata', ['From']);
                    $from = GmailService::getHeader($msg['payload'] ?? [], 'From');

                    if ($from !== '') {
                        $normalized = $this->normalizeFrom($from);
                        $senderCounts[$normalized] = ($senderCounts[$normalized] ?? 0) + 1;
                    }
                }

                $totalProcessed += count($ids);
                $params['pageToken'] = $result['nextPageToken'] ?? null;

            } while (! empty($params['pageToken']) && $totalProcessed < $maxMessages);

            if (empty($senderCounts)) {
                return ToolResult::success('No messages found.');
            }

            // Sort by count descending
            arsort($senderCounts);

            $topSenders = array_slice($senderCounts, 0, 50, true);
            $formatted = [];
            foreach ($topSenders as $sender => $count) {
                $formatted[] = ['sender' => $sender, 'count' => $count];
            }

            $output = [
                'totalMessages' => $totalProcessed,
                'uniqueSenders' => count($senderCounts),
                'topSenders' => $formatted,
            ];

            if ($totalProcessed >= $maxMessages) {
                $output['note'] = "Reached limit of {$maxMessages} messages. Results may be partial.";
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Normalize a From header to just the email address.
     * "John Doe <john@example.com>" → "john@example.com"
     * "john@example.com" → "john@example.com"
     */
    private function normalizeFrom(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return strtolower($matches[1]);
        }

        return strtolower(trim($from));
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'description' => 'Gmail search query to filter messages (e.g., "is:unread", "after:2026-01-01").'],
            'label_ids' => ['type' => 'string', 'description' => 'Comma-separated label IDs to filter by.'],
        ];
    }
}
