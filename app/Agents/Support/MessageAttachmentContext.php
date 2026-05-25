<?php

namespace App\Agents\Support;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\WorkspaceFile;
use App\Services\FileSystemService;
use Illuminate\Support\Str;

/**
 * Formats message attachments for agent context and chat read tools.
 *
 * MessageAttachment stores only chat upload metadata and a download URL. This
 * helper resolves the corresponding WorkspaceFile when the URL points at the
 * app file API, then exposes user-facing paths and bounded text previews. It is
 * intentionally workspace-scoped so an attachment URL cannot become a file
 * lookup escape hatch across tenants.
 */
class MessageAttachmentContext
{
    private const PREVIEW_MAX_BYTES = 16_384;

    private const PREVIEW_MAX_CHARS = 4_000;

    public function __construct(
        private FileSystemService $fileSystemService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function attachmentsForMessage(Message $message, string $workspaceId): array
    {
        $message->loadMissing('attachments');

        return $message->attachments
            ->map(fn (MessageAttachment $attachment): array => $this->attachmentPayload($attachment, $workspaceId))
            ->values()
            ->all();
    }

    public function textForMessage(Message $message, string $workspaceId): string
    {
        $attachments = $this->attachmentsForMessage($message, $workspaceId);
        if ($attachments === []) {
            return '';
        }

        $lines = ['Attached files'];
        foreach ($attachments as $attachment) {
            $label = (string) ($attachment['originalName'] ?? $attachment['filename'] ?? 'file');
            $path = (string) ($attachment['path'] ?? '');
            $mimeType = (string) ($attachment['mimeType'] ?? 'unknown');
            $size = isset($attachment['size']) ? ((string) $attachment['size']).' bytes' : 'unknown size';

            $lines[] = '- '.$label.($path !== '' ? " ({$path})" : '')." - {$mimeType}, {$size}";

            $preview = trim((string) ($attachment['textPreview'] ?? ''));
            if ($preview !== '') {
                $lines[] = "  Preview: {$preview}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentPayload(MessageAttachment $attachment, string $workspaceId): array
    {
        $file = $this->workspaceFileForAttachment($attachment, $workspaceId);

        $payload = [
            'filename' => $attachment->filename,
            'originalName' => $attachment->original_name,
            'mimeType' => $attachment->mime_type,
            'size' => $attachment->size,
            'url' => $attachment->url,
        ];

        if ($file) {
            $payload['workspaceFileId'] = $file->id;
            $payload['path'] = $file->getVirtualPath();
            $payload['name'] = $file->name;
            $payload['mimeType'] = $file->mime_type ?: $payload['mimeType'];
            $payload['size'] = $file->size ?? $payload['size'];

            $preview = $this->textPreview($file);
            if ($preview !== null) {
                $payload['textPreview'] = $preview;
            }
        }

        return array_filter($payload, fn ($value): bool => $value !== null && $value !== '');
    }

    private function workspaceFileForAttachment(MessageAttachment $attachment, string $workspaceId): ?WorkspaceFile
    {
        if (preg_match('#/api/files/([^/]+)/download#', (string) $attachment->url, $matches) !== 1) {
            return null;
        }

        return WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('id', $matches[1])
            ->where('is_folder', false)
            ->first();
    }

    private function textPreview(WorkspaceFile $file): ?string
    {
        $mimeType = (string) $file->mime_type;
        if (! $this->isTextMimeType($mimeType) || ($file->size ?? 0) > self::PREVIEW_MAX_BYTES) {
            return null;
        }

        try {
            $content = (string) $this->fileSystemService->readFileContents($file);
        } catch (\Throwable) {
            return null;
        }

        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $content) ?? $content;
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);

        return $content === '' ? null : Str::limit($content, self::PREVIEW_MAX_CHARS);
    }

    private function isTextMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'text/')
            || in_array($mimeType, [
                'application/json',
                'application/xml',
                'application/javascript',
                'application/yaml',
                'application/x-yaml',
            ], true);
    }
}
