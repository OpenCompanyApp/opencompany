<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleDriveService
{
    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    /** Default fields to request for file metadata. */
    private const FILE_FIELDS = 'id,name,mimeType,parents,createdTime,modifiedTime,size,webViewLink,shared,starred,trashed,owners(emailAddress,displayName)';

    /** @var array<string, string> */
    private const GOOGLE_MIME_TYPES = [
        'folder' => 'application/vnd.google-apps.folder',
        'document' => 'application/vnd.google-apps.document',
        'spreadsheet' => 'application/vnd.google-apps.spreadsheet',
        'presentation' => 'application/vnd.google-apps.presentation',
    ];

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * List/search files.
     *
     * @param  array<string, mixed>  $params  q, pageSize, pageToken, orderBy, fields
     * @return array<string, mixed>
     */
    public function listFiles(array $params = []): array
    {
        if (! isset($params['fields'])) {
            $params['fields'] = 'nextPageToken,files(' . self::FILE_FIELDS . ')';
        }

        return $this->client->get(self::BASE_URL . '/files', $params);
    }

    /**
     * Get a single file's metadata.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getFile(string $fileId, array $params = []): array
    {
        if (! isset($params['fields'])) {
            $params['fields'] = self::FILE_FIELDS;
        }

        return $this->client->get(self::BASE_URL . "/files/{$fileId}", $params);
    }

    /**
     * Export a Google Workspace file (Docs, Sheets, Slides) to a given MIME type.
     * Returns raw content (plain text, CSV, HTML, etc.).
     */
    public function exportFile(string $fileId, string $mimeType): string
    {
        return $this->client->getRaw(
            self::BASE_URL . "/files/{$fileId}/export",
            ['mimeType' => $mimeType]
        );
    }

    /**
     * Create a file (metadata-only — for folders and empty Google Docs/Sheets/Slides).
     *
     * @param  array<string, mixed>  $metadata  name, mimeType, parents
     * @return array<string, mixed>
     */
    public function createFile(array $metadata): array
    {
        return $this->client->post(self::BASE_URL . '/files', $metadata);
    }

    /**
     * Copy a file.
     *
     * @param  array<string, mixed>  $metadata  name, parents (optional overrides)
     * @return array<string, mixed>
     */
    public function copyFile(string $fileId, array $metadata = []): array
    {
        return $this->client->post(self::BASE_URL . "/files/{$fileId}/copy", $metadata);
    }

    /**
     * Update file metadata (rename, star, trash, etc.).
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $queryParams  addParents, removeParents
     * @return array<string, mixed>
     */
    public function updateFile(string $fileId, array $metadata = [], array $queryParams = []): array
    {
        // For move operations, addParents/removeParents go as query params.
        // GoogleClient.patch() sends $metadata as body. We need query params too.
        // Build URL with query params manually.
        $url = self::BASE_URL . "/files/{$fileId}";
        if (! empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $this->client->patch($url, $metadata);
    }

    /**
     * Trash a file (reversible).
     *
     * @return array<string, mixed>
     */
    public function trashFile(string $fileId): array
    {
        return $this->updateFile($fileId, ['trashed' => true]);
    }

    /**
     * Permanently delete a file.
     */
    public function deleteFile(string $fileId): void
    {
        $this->client->delete(self::BASE_URL . "/files/{$fileId}");
    }

    /**
     * List permissions on a file.
     *
     * @return array<string, mixed>
     */
    public function listPermissions(string $fileId): array
    {
        return $this->client->get(self::BASE_URL . "/files/{$fileId}/permissions", [
            'fields' => 'permissions(id,type,role,emailAddress,displayName,domain)',
        ]);
    }

    /**
     * Create a permission (share a file).
     *
     * @param  array<string, mixed>  $data  type, role, emailAddress, domain
     * @return array<string, mixed>
     */
    public function createPermission(string $fileId, array $data, bool $sendNotification = true): array
    {
        $url = self::BASE_URL . "/files/{$fileId}/permissions";
        $url .= '?' . http_build_query([
            'sendNotificationEmail' => $sendNotification ? 'true' : 'false',
            'fields' => 'id,type,role,emailAddress,displayName',
        ]);

        return $this->client->post($url, $data);
    }

    /**
     * Delete a permission (unshare a file).
     */
    public function deletePermission(string $fileId, string $permissionId): void
    {
        $this->client->delete(self::BASE_URL . "/files/{$fileId}/permissions/{$permissionId}");
    }

    /**
     * Get user info and storage quota (for testConnection).
     *
     * @return array<string, mixed>
     */
    public function getAbout(): array
    {
        return $this->client->get(self::BASE_URL . '/about', [
            'fields' => 'user(displayName,emailAddress),storageQuota(usage,limit)',
        ]);
    }

    /**
     * Resolve a friendly type name to a Google MIME type.
     */
    public static function resolveMimeType(string $type): ?string
    {
        return self::GOOGLE_MIME_TYPES[$type] ?? null;
    }

    /**
     * Check if a MIME type is a Google Workspace type (exportable).
     */
    public static function isGoogleWorkspaceType(string $mimeType): bool
    {
        return in_array($mimeType, self::GOOGLE_MIME_TYPES, true);
    }

    /**
     * Get the export MIME type for a given Google Workspace MIME type and format.
     */
    public static function getExportMimeType(string $googleMimeType, string $format): ?string
    {
        $map = [
            'application/vnd.google-apps.document' => [
                'text' => 'text/plain',
                'markdown' => 'text/html',
                'csv' => null,
            ],
            'application/vnd.google-apps.spreadsheet' => [
                'text' => 'text/tab-separated-values',
                'csv' => 'text/csv',
                'markdown' => 'text/csv',
            ],
            'application/vnd.google-apps.presentation' => [
                'text' => 'text/plain',
                'csv' => null,
                'markdown' => 'text/plain',
            ],
        ];

        return $map[$googleMimeType][$format] ?? null;
    }

    /**
     * Format file size in human-readable form.
     */
    public static function formatSize(int|string $bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
