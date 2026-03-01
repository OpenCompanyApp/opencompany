<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Read the contents of a text file. For binary files, returns metadata and a download URL instead.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = $request['path'] ?? null;
            if (!$path) {
                return 'Error: path is required.';
            }

            $file = $this->fileSystemService->resolveVirtualPath($path, $this->agent->workspace_id);
            if (!$file) {
                return "Error: file not found at path '{$path}'.";
            }
            if ($file->is_folder) {
                return "Error: '{$path}' is a folder, not a file. Use list_files instead.";
            }
            if (!$this->permissionService->canAccessFilePath($this->agent, $file)) {
                return "Permission denied: you do not have access to '{$path}'.";
            }

            // For text files, return content directly
            if ($file->mime_type && (str_starts_with($file->mime_type, 'text/') || in_array($file->mime_type, [
                'application/json', 'application/xml', 'application/javascript', 'application/yaml',
            ]))) {
                $content = $this->fileSystemService->readFileContents($file);

                // Truncate very large files
                if (strlen($content) > 100000) {
                    $content = substr($content, 0, 100000) . "\n\n[... truncated, file is " . number_format(strlen($content)) . ' bytes]';
                }

                return $content;
            }

            // For binary files, return metadata
            return json_encode([
                'name' => $file->name,
                'mimeType' => $file->mime_type,
                'size' => $file->size,
                'message' => 'Binary file — content cannot be read as text.',
                'downloadUrl' => $this->fileSystemService->getDownloadUrl($file),
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error reading file: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->required()
                ->description('Virtual file path to read (e.g. "/reports/q1.csv").'),
        ];
    }
}
