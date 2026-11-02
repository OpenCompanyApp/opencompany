<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Vfs\VfsCp;
use App\Agents\Tools\Vfs\VfsEdit;
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
use App\Agents\Tools\Vfs\VfsStat;
use App\Agents\Tools\Vfs\VfsWrite;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\Spec\VfsOperationSpecRegistry;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers the OpenCompany virtual filesystem tools.
 *
 * Tool meta is generated from VfsOperationSpecRegistry (single source: the
 * meta `description` equals the tool class description()). Five tools are
 * model-visible (exec, read, edit, write, patch); the rest are `scriptOnly`
 * Code Mode helpers (`app.vfs.*`).
 *
 * Permission borrowing (`permissionAs`): a tool whose spec `permission` is
 * another slug has no toggle of its own. ToolRegistry evaluates it as that
 * slug and hides it from the capabilities list, so admins govern the VFS
 * with three toggles: vfs_exec (reads: exec, read, stat, ls, find, rg,
 * exists), vfs_patch (edit, patch) and vfs_write (write, mkdir, cp, mv, rm).
 *
 * `selfGoverned` (vfs_edit): the tool is not wrapped in ApprovalWrappedTool
 * because every write it makes goes through VfsMutationPolicy, which already
 * requests one approval for all gated permissions (see VfsEdit).
 */
class VfsToolProvider implements BuiltInToolProvider
{
    /** @var array<string, class-string<Tool>> */
    private const CLASSES = [
        'vfs_exec' => VfsExec::class,
        'vfs_read' => VfsRead::class,
        'vfs_edit' => VfsEdit::class,
        'vfs_write' => VfsWrite::class,
        'vfs_patch' => VfsPatch::class,
        'vfs_stat' => VfsStat::class,
        'vfs_ls' => VfsLs::class,
        'vfs_find' => VfsFind::class,
        'vfs_rg' => VfsRg::class,
        'vfs_exists' => VfsExists::class,
        'vfs_mkdir' => VfsMkdir::class,
        'vfs_cp' => VfsCp::class,
        'vfs_mv' => VfsMv::class,
        'vfs_rm' => VfsRm::class,
    ];

    public function groupName(): string
    {
        return 'vfs';
    }

    public function groupMeta(): array
    {
        return [
            // Static fallback; ToolRegistry::getAppCatalog() lists the VFS
            // tools this agent actually has.
            'label' => implode(', ', array_map(
                fn ($spec): string => $spec->name,
                array_values(VfsOperationSpecRegistry::direct()),
            )),
            'description' => 'Permission-aware virtual filesystem over workspace data',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:tree-structure';
    }

    public function tools(): array
    {
        $tools = [];
        foreach (VfsOperationSpecRegistry::all() as $slug => $spec) {
            $meta = [
                'class' => self::CLASSES[$slug],
                'type' => $spec->registryType,
                'name' => $spec->title,
                'description' => $spec->description,
                'icon' => $spec->icon,
                'returns' => $spec->resultFields,
            ];
            if (! $spec->direct) {
                $meta['scriptOnly'] = true;
            }
            if ($spec->borrowsPermission()) {
                $meta['permissionAs'] = $spec->permission;
            }
            if ($spec->canMutateWith !== null) {
                $meta['canMutateWith'] = $spec->canMutateWith;
            }
            if ($slug === 'vfs_edit') {
                $meta['selfGoverned'] = true;
            }
            $tools[$slug] = $meta;
        }

        return $tools;
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        return match ($class) {
            VfsExec::class => new VfsExec($agent, app(VfsCommandExecutor::class)),
            VfsRead::class => new VfsRead($agent, app(OpenCompanyVfs::class)),
            VfsEdit::class => new VfsEdit($agent, app(OpenCompanyVfs::class)),
            VfsWrite::class => new VfsWrite($agent, app(OpenCompanyVfs::class)),
            VfsPatch::class => new VfsPatch($agent, app(OpenCompanyVfs::class)),
            VfsStat::class => new VfsStat($agent, app(OpenCompanyVfs::class)),
            VfsLs::class => new VfsLs($agent, app(OpenCompanyVfs::class)),
            VfsFind::class => new VfsFind($agent, app(OpenCompanyVfs::class)),
            VfsRg::class => new VfsRg($agent, app(OpenCompanyVfs::class)),
            VfsExists::class => new VfsExists($agent, app(OpenCompanyVfs::class)),
            VfsMkdir::class => new VfsMkdir($agent, app(OpenCompanyVfs::class)),
            VfsCp::class => new VfsCp($agent, app(OpenCompanyVfs::class)),
            VfsMv::class => new VfsMv($agent, app(OpenCompanyVfs::class)),
            VfsRm::class => new VfsRm($agent, app(OpenCompanyVfs::class)),
            default => throw new \RuntimeException("Unknown VFS tool class: {$class}"),
        };
    }
}
