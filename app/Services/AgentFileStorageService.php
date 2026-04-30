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
        $safeFilename = $this->safeFilename($filename);
        $homeFolder = $this->fileSystemService->ensureAgentHomeFolder($agent);
        $parentId = $homeFolder->id;

        if ($subfolder) {
            foreach ($this->safeSubfolderSegments($subfolder) as $segment) {
                $sub = $this->fileSystemService->createFolder(
                    $agent->workspace_id,
                    $parentId,
                    $segment,
                    $agent->id,
                );
                $parentId = $sub->id;
            }
        }

        $file = $this->fileSystemService->writeFile(
            $agent->workspace_id,
            $parentId,
            $safeFilename,
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

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename);
        $basename = basename(str_replace('\\', '/', $filename));

        if (
            $filename === ''
            || $filename === '.'
            || $filename === '..'
            || $basename !== $filename
            || preg_match('/[\x00-\x1F\x7F]/', $filename) === 1
        ) {
            throw new \InvalidArgumentException('Invalid output filename.');
        }

        return $filename;
    }

    /**
     * @return list<string>
     */
    private function safeSubfolderSegments(string $subfolder): array
    {
        $path = trim(str_replace('\\', '/', $subfolder), '/');
        if ($path === '') {
            return [];
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || preg_match('/[\x00-\x1F\x7F]/', $segment) === 1
            ) {
                throw new \InvalidArgumentException('Invalid output subfolder.');
            }

            $segments[] = $segment;
        }

        return $segments;
    }
}
