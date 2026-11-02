<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GmailService
{
    private const BASE_URL = 'https://www.googleapis.com/gmail/v1';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Get the authenticated user's profile.
     *
     * @return array<string, mixed>
     */
    public function getProfile(): array
    {
        return $this->client->get(self::BASE_URL . '/users/me/profile');
    }

    /**
     * List messages (returns minimal data — IDs and threadIds).
     *
     * @param  array<string, mixed>  $params  q, maxResults, pageToken, labelIds
     * @return array<string, mixed>
     */
    public function listMessages(array $params = []): array
    {
        return $this->client->get(self::BASE_URL . '/users/me/messages', $params);
    }

    /**
     * Get a single message.
     *
     * @param  array<int, string>|null  $metadataHeaders  Headers to include when format is 'metadata' (e.g., ['From', 'Subject'])
     * @return array<string, mixed>
     */
    public function getMessage(string $messageId, string $format = 'full', ?array $metadataHeaders = null): array
    {
        $params = ['format' => $format];

        if ($metadataHeaders !== null && $format === 'metadata') {
            $params['metadataHeaders'] = implode(',', $metadataHeaders);
        }

        return $this->client->get(self::BASE_URL . "/users/me/messages/{$messageId}", $params);
    }

    /**
     * Send a message (base64url-encoded RFC 2822).
     *
     * @param  array<string, mixed>  $data  raw (base64url), threadId (optional)
     * @return array<string, mixed>
     */
    public function sendMessage(array $data): array
    {
        return $this->client->post(self::BASE_URL . '/users/me/messages/send', $data);
    }

    /**
     * Trash a message.
     *
     * @return array<string, mixed>
     */
    public function trashMessage(string $messageId): array
    {
        return $this->client->post(self::BASE_URL . "/users/me/messages/{$messageId}/trash");
    }

    /**
     * Untrash a message.
     *
     * @return array<string, mixed>
     */
    public function untrashMessage(string $messageId): array
    {
        return $this->client->post(self::BASE_URL . "/users/me/messages/{$messageId}/untrash");
    }

    /**
     * Modify a message (add/remove labels).
     *
     * @param  array<string, mixed>  $data  addLabelIds, removeLabelIds
     * @return array<string, mixed>
     */
    public function modifyMessage(string $messageId, array $data): array
    {
        return $this->client->post(self::BASE_URL . "/users/me/messages/{$messageId}/modify", $data);
    }

    /**
     * Batch modify messages.
     *
     * @param  array<string, mixed>  $data  ids, addLabelIds, removeLabelIds
     */
    public function batchModifyMessages(array $data): void
    {
        $this->client->post(self::BASE_URL . '/users/me/messages/batchModify', $data);
    }

    /**
     * Create a draft.
     *
     * @param  array<string, mixed>  $data  message.raw (base64url)
     * @return array<string, mixed>
     */
    public function createDraft(array $data): array
    {
        return $this->client->post(self::BASE_URL . '/users/me/drafts', $data);
    }

    /**
     * Send a draft.
     *
     * @param  array<string, mixed>  $data  id
     * @return array<string, mixed>
     */
    public function sendDraft(array $data): array
    {
        return $this->client->post(self::BASE_URL . '/users/me/drafts/send', $data);
    }

    /**
     * List labels.
     *
     * @return array<string, mixed>
     */
    public function listLabels(): array
    {
        return $this->client->get(self::BASE_URL . '/users/me/labels');
    }

    /**
     * Download an attachment's raw bytes.
     */
    public function getAttachment(string $messageId, string $attachmentId): string
    {
        $response = $this->client->get(
            self::BASE_URL . "/users/me/messages/{$messageId}/attachments/{$attachmentId}"
        );

        return self::base64UrlDecode($response['data'] ?? '');
    }

    /**
     * Build a base64url-encoded RFC 2822 message.
     *
     * @param  array<string, string|null>  $options  cc, bcc, inReplyTo, references
     */
    public static function buildRawMessage(
        string $to,
        string $subject,
        string $body,
        array $options = [],
    ): string {
        $headers = [];
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$subject}";
        $headers[] = 'Content-Type: text/plain; charset="UTF-8"';
        $headers[] = 'MIME-Version: 1.0';

        if (! empty($options['cc'])) {
            $headers[] = "Cc: {$options['cc']}";
        }
        if (! empty($options['bcc'])) {
            $headers[] = "Bcc: {$options['bcc']}";
        }
        if (! empty($options['inReplyTo'])) {
            $headers[] = "In-Reply-To: {$options['inReplyTo']}";
        }
        if (! empty($options['references'])) {
            $headers[] = "References: {$options['references']}";
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        return self::base64UrlEncode($message);
    }

    /**
     * Base64url encode (RFC 4648).
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url decode (RFC 4648).
     */
    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Extract the text body from a Gmail message payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function extractBody(array $payload): string
    {
        // Direct body
        if (! empty($payload['body']['data'])) {
            return self::base64UrlDecode($payload['body']['data']);
        }

        // Walk parts tree — prefer text/plain over text/html
        $parts = $payload['parts'] ?? [];
        $plainText = '';
        $htmlText = '';

        foreach ($parts as $part) {
            $mimeType = $part['mimeType'] ?? '';

            if ($mimeType === 'text/plain' && ! empty($part['body']['data'])) {
                $plainText = self::base64UrlDecode($part['body']['data']);
            } elseif ($mimeType === 'text/html' && ! empty($part['body']['data'])) {
                $htmlText = self::base64UrlDecode($part['body']['data']);
            } elseif (str_starts_with($mimeType, 'multipart/') && ! empty($part['parts'])) {
                // Recurse into nested multipart
                $nested = self::extractBody($part);
                if ($nested !== '') {
                    if ($plainText === '') {
                        $plainText = $nested;
                    }
                }
            }
        }

        if ($plainText !== '') {
            return $plainText;
        }

        if ($htmlText !== '') {
            return strip_tags($htmlText);
        }

        return '';
    }

    /**
     * Extract a header value from a message payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function getHeader(array $payload, string $name): string
    {
        $headers = $payload['headers'] ?? [];
        foreach ($headers as $header) {
            if (strcasecmp($header['name'] ?? '', $name) === 0) {
                return $header['value'] ?? '';
            }
        }

        return '';
    }
}
