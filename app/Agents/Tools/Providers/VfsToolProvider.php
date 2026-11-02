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
 * also available in mruby Code Mode as the `app.vfs` namespace via the normal
 * script catalog builder. `scriptOnly` helpers (stat, ls, read, rg, ...) are
 * never registered as model tools; their permission is evaluated against the
 * human-visible primitive (`vfs_write` for writes, `vfs_exec` for reads) in
 * ToolRegistry::evaluateToolPermission().
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
                'description' => 'Run safe unix-like VFS commands. Read/navigation commands use read permission; /files mutations such as mkdir, touch, tee, cp, mv, rm, and truncate compose vfs_exec with vfs_write.',
                'icon' => 'ph:terminal-window',
                'canMutateWith' => 'vfs_write',
            ],
            'vfs_patch' => [
                'class' => VfsPatch::class,
                'type' => 'write',
                'name' => 'VFS Patch',
                'description' => 'Patch virtual content with version-aware semantics. Domain-backed paths compose with existing domain permissions and approvals.',
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
                'description' => 'Code Mode helper for statting a virtual path with version and capabilities.',
                'icon' => 'ph:info',
                'scriptOnly' => true,
            ],
            'vfs_ls' => [
                'class' => VfsLs::class,
                'type' => 'read',
                'name' => 'VFS Ls',
                'description' => 'Code Mode helper for listing virtual directories as structured entries.',
                'icon' => 'ph:list-bullets',
                'scriptOnly' => true,
            ],
            'vfs_read' => [
                'class' => VfsRead::class,
                'type' => 'read',
                'name' => 'VFS Read',
                'description' => 'Code Mode helper for reading virtual text content with metadata.',
                'icon' => 'ph:file-text',
                'scriptOnly' => true,
            ],
            'vfs_rg' => [
                'class' => VfsRg::class,
                'type' => 'read',
                'name' => 'VFS Rg',
                'description' => 'Code Mode helper for exact or regex text search over VFS paths.',
                'icon' => 'ph:magnifying-glass',
                'scriptOnly' => true,
            ],
            'vfs_find' => [
                'class' => VfsFind::class,
                'type' => 'read',
                'name' => 'VFS Find',
                'description' => 'Code Mode helper for structured find over bounded VFS trees.',
                'icon' => 'ph:binoculars',
                'scriptOnly' => true,
            ],
            'vfs_search' => [
                'class' => VfsSearch::class,
                'type' => 'read',
                'name' => 'VFS Search',
                'description' => 'Code Mode helper for deterministic lexical VFS search.',
                'icon' => 'ph:text-aa',
                'scriptOnly' => true,
            ],
            'vfs_exists' => [
                'class' => VfsExists::class,
                'type' => 'read',
                'name' => 'VFS Exists',
                'description' => 'Code Mode helper for checking whether a VFS path exists.',
                'icon' => 'ph:check-circle',
                'scriptOnly' => true,
            ],
            'vfs_mkdir' => [
                'class' => VfsMkdir::class,
                'type' => 'write',
                'name' => 'VFS Mkdir',
                'description' => 'Code Mode helper for creating a writable VFS directory.',
                'icon' => 'ph:folder-plus',
                'scriptOnly' => true,
            ],
            'vfs_cp' => [
                'class' => VfsCp::class,
                'type' => 'write',
                'name' => 'VFS Cp',
                'description' => 'Code Mode helper for copying writable VFS files.',
                'icon' => 'ph:copy',
                'scriptOnly' => true,
            ],
            'vfs_mv' => [
                'class' => VfsMv::class,
                'type' => 'write',
                'name' => 'VFS Mv',
                'description' => 'Code Mode helper for moving writable VFS files.',
                'icon' => 'ph:arrows-out-line-horizontal',
                'scriptOnly' => true,
            ],
            'vfs_rm' => [
                'class' => VfsRm::class,
                'type' => 'write',
                'name' => 'VFS Rm',
                'description' => 'Code Mode helper for deleting writable VFS files.',
                'icon' => 'ph:trash',
                'scriptOnly' => true,
            ],
            'vfs_count' => [
                'class' => VfsCount::class,
                'type' => 'read',
                'name' => 'VFS Count',
                'description' => 'Code Mode helper for counting directory entries with truncation metadata.',
                'icon' => 'ph:number-square-one',
                'scriptOnly' => true,
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
