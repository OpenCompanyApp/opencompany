<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Vfs\VfsCount;
use App\Agents\Tools\Vfs\VfsCp;
use App\Agents\Tools\Vfs\VfsExec;
use App\Agents\Tools\Vfs\VfsExists;
use App\Agents\Tools\Vfs\VfsFind;
use App\Agents\Tools\Vfs\VfsLs;
use App\Agents\Tools\Vfs\VfsMkdir;
use App\Agents\Tools\Vfs\VfsMv;
use App\Agents\Tools\Vfs\VfsPatch;
use App\Agents\Tools\Vfs\VfsRead;
use App\Agents\Tools\Vfs\VfsRg;
use App\Agents\Tools\Vfs\VfsRm;
use App\Agents\Tools\Vfs\VfsSearch;
use App\Agents\Tools\Vfs\VfsStat;
use App\Agents\Tools\Vfs\VfsWrite;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers the OpenCompany virtual filesystem primitive tools.
 *
 * The tools are direct model-visible primitives, while the same tool slugs are
 * also available through Lua as the `app.vfs` namespace via the normal tool
 * catalog builder.
 */
class VfsToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'vfs';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'exec, patch, write',
            'description' => 'Permission-aware virtual filesystem over workspace data',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:tree-structure';
    }

    public function tools(): array
    {
        return [
            'vfs_exec' => [
                'class' => VfsExec::class,
                'type' => 'read',
                'name' => 'VFS Exec',
                'description' => 'Run safe unix-like VFS commands. Read/navigation commands use read permission; /files mutations such as mkdir, touch, tee, cp, mv, rm, and truncate require vfs_write.',
                'icon' => 'ph:terminal-window',
                'canMutateWith' => 'vfs_write',
            ],
            'vfs_patch' => [
                'class' => VfsPatch::class,
                'type' => 'write',
                'name' => 'VFS Patch',
                'description' => 'Patch virtual file content with version-aware search/replace semantics.',
                'icon' => 'ph:file-dashed',
            ],
            'vfs_write' => [
                'class' => VfsWrite::class,
                'type' => 'write',
                'name' => 'VFS Write',
                'description' => 'Create or overwrite writable virtual file content.',
                'icon' => 'ph:file-plus',
            ],
            'vfs_stat' => [
                'class' => VfsStat::class,
                'type' => 'read',
                'name' => 'VFS Stat',
                'description' => 'Lua-only helper for statting a virtual path with version and capabilities.',
                'icon' => 'ph:info',
                'luaOnly' => true,
            ],
            'vfs_ls' => [
                'class' => VfsLs::class,
                'type' => 'read',
                'name' => 'VFS Ls',
                'description' => 'Lua-only helper for listing virtual directories as structured entries.',
                'icon' => 'ph:list-bullets',
                'luaOnly' => true,
            ],
            'vfs_read' => [
                'class' => VfsRead::class,
                'type' => 'read',
                'name' => 'VFS Read',
                'description' => 'Lua-only helper for reading virtual text content with metadata.',
                'icon' => 'ph:file-text',
                'luaOnly' => true,
            ],
            'vfs_rg' => [
                'class' => VfsRg::class,
                'type' => 'read',
                'name' => 'VFS Rg',
                'description' => 'Lua-only helper for exact or regex text search over VFS paths.',
                'icon' => 'ph:magnifying-glass',
                'luaOnly' => true,
            ],
            'vfs_find' => [
                'class' => VfsFind::class,
                'type' => 'read',
                'name' => 'VFS Find',
                'description' => 'Lua-only helper for structured find over bounded VFS trees.',
                'icon' => 'ph:binoculars',
                'luaOnly' => true,
            ],
            'vfs_search' => [
                'class' => VfsSearch::class,
                'type' => 'read',
                'name' => 'VFS Search',
                'description' => 'Lua-only helper for deterministic lexical VFS search.',
                'icon' => 'ph:text-aa',
                'luaOnly' => true,
            ],
            'vfs_exists' => [
                'class' => VfsExists::class,
                'type' => 'read',
                'name' => 'VFS Exists',
                'description' => 'Lua-only helper for checking whether a VFS path exists.',
                'icon' => 'ph:check-circle',
                'luaOnly' => true,
            ],
            'vfs_mkdir' => [
                'class' => VfsMkdir::class,
                'type' => 'write',
                'name' => 'VFS Mkdir',
                'description' => 'Lua-only helper for creating a writable VFS directory.',
                'icon' => 'ph:folder-plus',
                'luaOnly' => true,
            ],
            'vfs_cp' => [
                'class' => VfsCp::class,
                'type' => 'write',
                'name' => 'VFS Cp',
                'description' => 'Lua-only helper for copying writable VFS files.',
                'icon' => 'ph:copy',
                'luaOnly' => true,
            ],
            'vfs_mv' => [
                'class' => VfsMv::class,
                'type' => 'write',
                'name' => 'VFS Mv',
                'description' => 'Lua-only helper for moving writable VFS files.',
                'icon' => 'ph:arrows-out-line-horizontal',
                'luaOnly' => true,
            ],
            'vfs_rm' => [
                'class' => VfsRm::class,
                'type' => 'write',
                'name' => 'VFS Rm',
                'description' => 'Lua-only helper for deleting writable VFS files.',
                'icon' => 'ph:trash',
                'luaOnly' => true,
            ],
            'vfs_count' => [
                'class' => VfsCount::class,
                'type' => 'read',
                'name' => 'VFS Count',
                'description' => 'Lua-only helper for counting directory entries with truncation metadata.',
                'icon' => 'ph:number-square-one',
                'luaOnly' => true,
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        return match ($class) {
            VfsExec::class => new VfsExec($agent, app(VfsCommandExecutor::class)),
            VfsPatch::class => new VfsPatch($agent, app(OpenCompanyVfs::class)),
            VfsWrite::class => new VfsWrite($agent, app(OpenCompanyVfs::class)),
            VfsStat::class => new VfsStat($agent, app(OpenCompanyVfs::class)),
            VfsLs::class => new VfsLs($agent, app(OpenCompanyVfs::class)),
            VfsRead::class => new VfsRead($agent, app(OpenCompanyVfs::class)),
            VfsRg::class => new VfsRg($agent, app(OpenCompanyVfs::class)),
            VfsFind::class => new VfsFind($agent, app(OpenCompanyVfs::class)),
            VfsSearch::class => new VfsSearch($agent, app(OpenCompanyVfs::class)),
            VfsExists::class => new VfsExists($agent, app(OpenCompanyVfs::class)),
            VfsMkdir::class => new VfsMkdir($agent, app(OpenCompanyVfs::class)),
            VfsCp::class => new VfsCp($agent, app(OpenCompanyVfs::class)),
            VfsMv::class => new VfsMv($agent, app(OpenCompanyVfs::class)),
            VfsRm::class => new VfsRm($agent, app(OpenCompanyVfs::class)),
            VfsCount::class => new VfsCount($agent, app(OpenCompanyVfs::class)),
            default => throw new \RuntimeException("Unknown VFS tool class: {$class}"),
        };
    }
}
