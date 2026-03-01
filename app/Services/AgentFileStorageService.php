<?php

namespace App\Services;

use App\Models\User;
use OpenCompany\IntegrationCore\Contracts\AgentFileStorage;

class AgentFileStorageService implements AgentFileStorage
{
    public function __construct(
        private FileSystemService $fileSystemService,
    ) {}

    public function saveFile(
        object $agent,
        string $filename,
        string $content,
        string $mimeType,
        ?string $subfolder = null,
    ): array {
        /** @var User $agent */
        $homeFolder = $this->fileSystemService->ensureAgentHomeFolder($agent);
        $parentId = $homeFolder->id;

        if ($subfolder) {
            $sub = $this->fileSystemService->createFolder(
                $agent->workspace_id,
                $homeFolder->id,
                $subfolder,
                $agent->id,
            );
            $parentId = $sub->id;
        }

        $file = $this->fileSystemService->writeFile(
            $agent->workspace_id,
            $parentId,
            $filename,
            $content,
            $agent->id,
            $mimeType,
        );

        return [
            'id' => $file->id,
            'path' => $file->getVirtualPath(),
            'url' => "/api/files/{$file->id}/download",
        ];
    }
}
