<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Files\CopyFile;
use App\Agents\Tools\Files\CreateFolder;
use App\Agents\Tools\Files\DeleteFile;
use App\Agents\Tools\Files\GetFileInfo;
use App\Agents\Tools\Files\ListDisks;
use App\Agents\Tools\Files\ListFiles;
use App\Agents\Tools\Files\MoveFile;
use App\Agents\Tools\Files\ReadFile;
use App\Agents\Tools\Files\SearchFiles;
use App\Agents\Tools\Files\WriteFile;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;

class FilesToolProvider implements BuiltInToolProvider
{
    public function __construct(
        private AgentPermissionService $permissionService,
    ) {}

    public function groupName(): string
    {
        return 'files';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'disks, list, read, info, search, write, mkdir, move, copy, delete',
            'description' => 'Workspace file storage',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:folder-open';
    }

    public function tools(): array
    {
        return [
            'list_disks' => [
                'class' => ListDisks::class,
                'type' => 'read',
                'name' => 'List Disks',
                'description' => 'List available storage disks in the workspace.',
                'icon' => 'ph:hard-drives',
            ],
            'list_files' => [
                'class' => ListFiles::class,
                'type' => 'read',
                'name' => 'List Files',
                'description' => 'List files and folders at a given path.',
                'icon' => 'ph:folder-open',
            ],
            'read_file' => [
                'class' => ReadFile::class,
                'type' => 'read',
                'name' => 'Read File',
                'description' => 'Read the contents of a text file.',
                'icon' => 'ph:file-text',
            ],
            'get_file_info' => [
                'class' => GetFileInfo::class,
                'type' => 'read',
                'name' => 'Get File Info',
                'description' => 'Get metadata about a file or folder.',
                'icon' => 'ph:info',
            ],
            'search_files' => [
                'class' => SearchFiles::class,
                'type' => 'read',
                'name' => 'Search Files',
                'description' => 'Search files by name across accessible folders.',
                'icon' => 'ph:magnifying-glass',
            ],
            'write_file' => [
                'class' => WriteFile::class,
                'type' => 'write',
                'name' => 'Write File',
                'description' => 'Create or overwrite a text file.',
                'icon' => 'ph:file-plus',
            ],
            'create_folder' => [
                'class' => CreateFolder::class,
                'type' => 'write',
                'name' => 'Create Folder',
                'description' => 'Create a folder at the given path.',
                'icon' => 'ph:folder-plus',
            ],
            'move_file' => [
                'class' => MoveFile::class,
                'type' => 'write',
                'name' => 'Move File',
                'description' => 'Move or rename a file or folder.',
                'icon' => 'ph:arrows-left-right',
            ],
            'copy_file' => [
                'class' => CopyFile::class,
                'type' => 'write',
                'name' => 'Copy File',
                'description' => 'Copy a file to another location.',
                'icon' => 'ph:copy',
            ],
            'delete_file' => [
                'class' => DeleteFile::class,
                'type' => 'write',
                'name' => 'Delete File',
                'description' => 'Delete a file or folder.',
                'icon' => 'ph:trash',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        if ($class === ListDisks::class) {
            return new ListDisks($agent);
        }

        return new $class($agent, $this->permissionService, app(FileSystemService::class));
    }
}
