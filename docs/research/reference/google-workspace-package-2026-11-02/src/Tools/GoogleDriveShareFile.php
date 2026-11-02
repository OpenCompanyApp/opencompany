<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveShareFile implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_share_file';
    }

    public function description(): string
    {
        return <<<'MD'
        Share a Google Drive file or folder. Provide `fileId`, `role` ("reader", "writer", "commenter"), and one of:
        - `email`: share with a specific user (e.g., "alice@example.com")
        - `domain`: share with an entire domain (e.g., "example.com")
        - `type` set to `"anyone"`: make accessible to anyone with the link (no email/domain needed)
        - `notify` (optional, default true): send email notification (only for email shares)
        MD;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Drive integration is not configured.');
            }

            $fileId = $args['file_id'] ?? '';
            if (empty($fileId)) {
                return ToolResult::error('fileId is required.');
            }

            $role = $args['role'] ?? '';
            if (! in_array($role, ['reader', 'writer', 'commenter'], true)) {
                return ToolResult::error('role must be "reader", "writer", or "commenter".');
            }

            $email = $args['email'] ?? '';
            $domain = $args['domain'] ?? '';
            $type = $args['type'] ?? '';

            $permissionData = ['role' => $role];

            if ($type === 'anyone') {
                $permissionData['type'] = 'anyone';
            } elseif ($email !== '') {
                $permissionData['type'] = 'user';
                $permissionData['emailAddress'] = $email;
            } elseif ($domain !== '') {
                $permissionData['type'] = 'domain';
                $permissionData['domain'] = $domain;
            } else {
                return ToolResult::error('Provide email, domain, or type="anyone".');
            }

            $notify = ($args['notify'] ?? 'true') !== 'false';

            $result = $this->service->createPermission($fileId, $permissionData, $notify);
            $target = match (true) {
                $type === 'anyone' => 'anyone with the link',
                $email !== '' => $email,
                default => $domain,
            };

            return ToolResult::success([
                'message' => "Shared with {$target} as {$role}.",
                'permissionId' => $result['id'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'file_id' => ['type' => 'string', 'required' => true, 'description' => 'File/folder ID to share.'],
            'role' => ['type' => 'string', 'required' => true, 'description' => 'Permission role: "reader", "writer", or "commenter".'],
            'type' => ['type' => 'string', 'description' => 'Set to "anyone" to share with anyone who has the link.'],
            'email' => ['type' => 'string', 'description' => 'Email address to share with a specific user.'],
            'domain' => ['type' => 'string', 'description' => 'Domain to share with (e.g., "example.com").'],
            'notify' => ['type' => 'string', 'description' => 'Send email notification: "true" (default) or "false".'],
        ];
    }
}
