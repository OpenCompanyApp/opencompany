<?php

namespace App\Domain\Vfs\OpenCompany;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Concerns\AuthorizesVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\BuildsVfsToolCatalogIndex;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsAgents;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsApprovals;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsAutomations;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsCollaboration;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsFiles;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsLists;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsTasks;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsTools;
use App\Domain\Vfs\OpenCompany\Concerns\MountsVfsWorkspace;
use App\Domain\Vfs\OpenCompany\Concerns\MutatesVfsContent;
use App\Domain\Vfs\OpenCompany\Concerns\MutatesVfsOpenCompanyModels;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDirectEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDocumentFileEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsDocuments;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsEntries;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsPaths;
use App\Domain\Vfs\OpenCompany\Concerns\ResolvesVfsResourceDirectEntries;
use App\Domain\Vfs\OpenCompany\Concerns\RunsVfsFileMutations;
use App\Domain\Vfs\OpenCompany\Concerns\SearchesVfsContent;
use App\Domain\Vfs\OpenCompany\Concerns\StatsVfsPaths;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;

/**
 * OpenCompany-backed virtual filesystem service.
 *
 * This facade owns routing and public VFS operations. Domain-specific mounts and
 * mutation helpers live in traits under OpenCompany\Concerns so new mounts can
 * be added without concentrating every model rule in this one class again.
 */
class OpenCompanyVfs
{
    use AuthorizesVfsDocuments;
    use BuildsVfsToolCatalogIndex;
    use MountsVfsAgents;
    use MountsVfsApprovals;
    use MountsVfsAutomations;
    use MountsVfsCollaboration;
    use MountsVfsDocuments;
    use MountsVfsFiles;
    use MountsVfsLists;
    use MountsVfsTasks;
    use MountsVfsTools;
    use MountsVfsWorkspace;
    use MutatesVfsContent;
    use MutatesVfsOpenCompanyModels;
    use ResolvesVfsDirectEntries;
    use ResolvesVfsDocumentFileEntries;
    use ResolvesVfsDocuments;
    use ResolvesVfsEntries;
    use ResolvesVfsPaths;
    use ResolvesVfsResourceDirectEntries;
    use RunsVfsFileMutations;
    use SearchesVfsContent;
    use StatsVfsPaths;

    public function __construct(
        private readonly AgentPermissionService $permissions,
        private readonly FileSystemService $files,
        private readonly ToolRegistry $tools,
        private readonly VfsTextSearchService $textSearch,
    ) {}

    /**
     * @return list<VfsEntry>
     */
    public function list(User $agent, string $path, ?VfsBudget $budget = null): array
    {
        $budget ??= new VfsBudget;
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if ($segments === []) {
            return array_slice($this->rootEntries(), 0, $budget->maxEntries);
        }

        $entries = match ($segments[0]) {
            'docs' => $this->listDocs($agent, $segments, $path, $budget),
            'files' => $this->listFiles($agent, $segments, $path, $budget),
            'agents' => $this->listAgents($agent, $segments, $path, $budget),
            'tasks' => $this->listTasks($agent, $segments, $path, $budget),
            'lists' => $this->listLists($agent, $segments, $path, $budget),
            'channels' => $this->listChannels($agent, $segments, $path, $budget),
            'tables' => $this->listTables($agent, $segments, $path, $budget),
            'tools' => $this->listTools($path),
            'approvals' => $this->listApprovals($agent, $segments, $path, $budget),
            'automations' => $this->listAutomations($agent, $segments, $path, $budget),
            'workspace' => $this->listWorkspace($path),
            default => throw VfsError::notFound($path),
        };

        return array_slice($entries, 0, $budget->maxEntries);
    }

    public function read(User $agent, string $path, ?VfsBudget $budget = null): string
    {
        $budget ??= new VfsBudget;
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if ($segments === []) {
            return $this->entriesToText($this->rootEntries());
        }

        return match ($segments[0]) {
            'docs' => $this->readDocs($agent, $segments, $path, $budget),
            'files' => $this->readFiles($agent, $segments, $path, $budget),
            'agents' => $this->readAgents($agent, $segments, $path, $budget),
            'tasks' => $this->readTasks($agent, $segments, $path, $budget),
            'lists' => $this->readLists($agent, $segments, $path, $budget),
            'channels' => $this->readChannels($agent, $segments, $path, $budget),
            'tables' => $this->readTables($agent, $segments, $path, $budget),
            'tools' => $this->readTools($agent, $path),
            'approvals' => $this->readApprovals($agent, $segments, $path, $budget),
            'automations' => $this->readAutomations($agent, $segments, $path, $budget),
            'workspace' => $this->readWorkspace($agent, $segments, $path),
            default => throw VfsError::notFound($path),
        };
    }
}
