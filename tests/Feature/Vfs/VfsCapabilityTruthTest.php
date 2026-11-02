<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Services\ApprovalExecutionService;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Protects the contract between advertised VFS mutation capabilities and the
 * exact policy enforced when an agent executes that mutation.
 *
 * These scenarios intentionally exercise OpenCompanyVfs directly. Tool wrappers
 * remain a second outer boundary, but the domain service must be safe and
 * truthful for Code Mode, approved replay, background work, and future callers too.
 */
class VfsCapabilityTruthTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create([
            'name' => 'Capability Agent',
            'status' => 'idle',
            'behavior_mode' => 'autonomous',
        ]);

        WorkspaceDisk::create([
            'id' => 'capability-disk-local',
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);

        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'file_folder',
            'scope_key' => '*',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
    }

    public function test_allowed_entries_advertise_only_executable_mutations_on_display_and_canonical_paths(): void
    {
        [$documentFolder, $document] = $this->documentFixture();
        $this->allowDocumentFolder($documentFolder);
        [$fileFolder, $textFile] = $this->fileFixture();
        $binaryFile = app(FileSystemService::class)->writeFile(
            $this->workspace->id,
            $fileFolder->id,
            'payload.bin',
            "\x00\x01\x02",
            $this->agent->id,
        );
        $binaryFile->update(['mime_type' => 'application/octet-stream']);
        $task = $this->taskFixture($this->agent);
        [$project, $item] = $this->listFixture($this->agent);

        $vfs = app(OpenCompanyVfs::class);
        $documentPaths = [
            "/docs/by-id/{$document->id}",
            '/docs/'.Str::slug($documentFolder->title).'/'.Str::slug($document->title),
        ];
        foreach ($documentPaths as $path) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertContains('patch', $stat['capabilities'], $path);
            $this->assertSame('allowed', $stat['metadata']['mutation_actions']['patch']['permission'], $path);
        }

        $filePaths = [
            "/files/by-id/{$textFile->id}",
            '/files'.$textFile->getVirtualPath(),
        ];
        foreach ($filePaths as $path) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertContains('patch', $stat['capabilities'], $path);
            $this->assertContains('write', $stat['capabilities'], $path);
            $this->assertSame('allowed', $stat['metadata']['mutation_actions']['patch']['permission'], $path);
            $this->assertSame('allowed', $stat['metadata']['mutation_actions']['write']['permission'], $path);
        }

        $folderStat = $vfs->stat($this->agent, "/files/by-id/{$fileFolder->id}");
        $this->assertContains('write', $folderStat['capabilities']);
        $this->assertSame('allowed', $folderStat['metadata']['mutation_permission']);

        $binaryStat = $vfs->stat($this->agent, "/files/by-id/{$binaryFile->id}");
        $this->assertNotContains('read', $binaryStat['capabilities']);
        $this->assertNotContains('search', $binaryStat['capabilities']);
        $this->assertNotContains('patch', $binaryStat['capabilities']);
        $this->assertContains('write', $binaryStat['capabilities']);
        $this->assertSame('denied', $binaryStat['metadata']['mutation_actions']['patch']['permission']);
        $this->assertSame('allowed', $binaryStat['metadata']['mutation_actions']['write']['permission']);

        $taskStat = $vfs->stat($this->agent, "/tasks/by-id/{$task->id}");
        $this->assertContains('patch', $taskStat['capabilities']);
        $this->assertSame('allowed', $taskStat['metadata']['mutation_permission']);

        $listPaths = [
            "/lists/by-id/{$item->id}",
            "/lists/by-id/{$project->id}/".Str::slug($item->title).'.md',
        ];
        foreach ($listPaths as $path) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertContains('patch', $stat['capabilities'], $path);
            $this->assertSame('allowed', $stat['metadata']['mutation_permission'], $path);
        }
    }

    public function test_denied_primitive_permissions_remove_capabilities_and_block_the_same_paths(): void
    {
        [$documentFolder, $document] = $this->documentFixture();
        $this->allowDocumentFolder($documentFolder);
        [$fileFolder, $textFile] = $this->fileFixture();
        $task = $this->taskFixture($this->agent);
        [, $item] = $this->listFixture($this->agent);
        $this->toolPermission('vfs_patch', 'deny');
        $this->toolPermission('vfs_write', 'deny');

        $vfs = app(OpenCompanyVfs::class);
        foreach ([
            "/docs/by-id/{$document->id}",
            "/tasks/by-id/{$task->id}",
            "/lists/by-id/{$item->id}",
        ] as $path) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertNotContains('patch', $stat['capabilities'], $path);
            $this->assertSame('denied', $stat['metadata']['mutation_permission'], $path);
            $this->assertSame('vfs_patch', $stat['metadata']['mutation_denied_tool'], $path);
        }

        $fileStat = $vfs->stat($this->agent, "/files/by-id/{$textFile->id}");
        $this->assertNotContains('patch', $fileStat['capabilities']);
        $this->assertNotContains('write', $fileStat['capabilities']);
        $this->assertSame('vfs_patch', $fileStat['metadata']['mutation_actions']['patch']['denied_tool']);
        $this->assertSame('vfs_write', $fileStat['metadata']['mutation_actions']['write']['denied_tool']);

        $folderStat = $vfs->stat($this->agent, "/files/by-id/{$fileFolder->id}");
        $this->assertNotContains('write', $folderStat['capabilities']);
        $this->assertSame('vfs_write', $folderStat['metadata']['mutation_denied_tool']);

        $this->assertVfsError('permission_denied', fn () => $vfs->patch(
            $this->agent,
            "/tasks/by-id/{$task->id}",
            json_encode(['description' => 'must not change'], JSON_THROW_ON_ERROR),
        ));
        $this->assertVfsError('permission_denied', fn () => $vfs->write(
            $this->agent,
            "/files/by-id/{$fileFolder->id}/blocked.md",
            'must not be written',
        ));

        $this->assertSame('Original task description', $task->fresh()->description);
        $this->assertFalse($this->workspaceFileExists('blocked.md'));
        $this->assertSame(0, ApprovalRequest::query()->count());
    }

    public function test_approval_required_capabilities_create_replayable_approvals_for_eligible_paths(): void
    {
        [$fileFolder] = $this->fileFixture();
        $task = $this->taskFixture($this->agent);
        $this->toolPermission('vfs_patch', 'allow', true);
        $this->toolPermission('vfs_write', 'allow', true);

        $vfs = app(OpenCompanyVfs::class);
        $taskPath = "/tasks/by-id/{$task->id}";
        $taskStat = $vfs->stat($this->agent, $taskPath);
        $this->assertContains('patch', $taskStat['capabilities']);
        $this->assertSame('requires_approval', $taskStat['metadata']['mutation_actions']['patch']['permission']);
        $this->assertSame('vfs_patch', $taskStat['metadata']['mutation_actions']['patch']['approval_tool']);

        $this->assertVfsError('approval_required', fn () => $vfs->patch(
            $this->agent,
            $taskPath,
            json_encode(['description' => 'approved task description'], JSON_THROW_ON_ERROR),
        ));
        $this->assertSame('Original task description', $task->fresh()->description);

        $taskApproval = ApprovalRequest::query()->latest()->firstOrFail();
        $this->assertSame($taskPath, $taskApproval->tool_execution_context['vfs_approval']['subject']);
        app(ApprovalExecutionService::class)->resolve($taskApproval, 'approved');
        $this->assertSame('approved task description', $task->fresh()->description);

        $folderPath = "/files/by-id/{$fileFolder->id}";
        $folderStat = $vfs->stat($this->agent, $folderPath);
        $this->assertContains('write', $folderStat['capabilities']);
        $this->assertSame('requires_approval', $folderStat['metadata']['mutation_actions']['write']['permission']);
        $this->assertSame('vfs_write', $folderStat['metadata']['mutation_actions']['write']['approval_tool']);

        $writePath = "{$folderPath}/approved.md";
        $this->assertVfsError('approval_required', fn () => $vfs->write(
            $this->agent,
            $writePath,
            'approved file content',
        ));
        $this->assertFalse($this->workspaceFileExists('approved.md'));

        $writeApproval = ApprovalRequest::query()
            ->where('status', 'pending')
            ->where('tool_execution_context->tool_slug', 'vfs_write')
            ->firstOrFail();
        $this->assertSame($writePath, $writeApproval->tool_execution_context['vfs_approval']['subject']);
        app(ApprovalExecutionService::class)->resolve($writeApproval, 'approved');
        $this->assertTrue($this->workspaceFileExists('approved.md'));
    }

    public function test_domain_ineligible_paths_neither_advertise_patch_nor_create_approvals(): void
    {
        [, $document] = $this->documentFixture();
        $otherAgent = User::factory()->agent()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Other Owner',
        ]);
        $task = $this->taskFixture($otherAgent);
        [, $item] = $this->listFixture($otherAgent);
        $this->toolPermission('vfs_patch', 'allow', true);

        $vfs = app(OpenCompanyVfs::class);
        $cases = [
            "/docs/by-id/{$document->id}" => json_encode(['search' => 'Original', 'replace' => 'Blocked'], JSON_THROW_ON_ERROR),
            "/tasks/by-id/{$task->id}" => json_encode(['description' => 'Blocked'], JSON_THROW_ON_ERROR),
            "/lists/by-id/{$item->id}" => json_encode(['description' => 'Blocked'], JSON_THROW_ON_ERROR),
        ];

        foreach ($cases as $path => $patch) {
            $stat = $vfs->stat($this->agent, $path);
            $this->assertNotContains('patch', $stat['capabilities'], $path);
            $this->assertSame('denied', $stat['metadata']['mutation_permission'], $path);
            $this->assertNotEmpty($stat['metadata']['mutation_denied_reason'], $path);
            $this->assertVfsError('permission_denied', fn () => $vfs->patch($this->agent, $path, $patch));
        }

        $this->assertSame(0, ApprovalRequest::query()->count());
    }

    /** @return array{Document, Document} */
    private function documentFixture(): array
    {
        $folder = Document::factory()->create([
            'title' => 'Capability Documents',
            'is_folder' => true,
            'author_id' => $this->agent->id,
        ]);
        $document = Document::factory()->create([
            'title' => 'Capability Document',
            'parent_id' => $folder->id,
            'content' => 'Original document content',
            'author_id' => $this->agent->id,
        ]);

        return [$folder, $document];
    }

    private function allowDocumentFolder(Document $folder): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'folder',
            'scope_key' => $folder->id,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
    }

    /** @return array{WorkspaceFile, WorkspaceFile} */
    private function fileFixture(): array
    {
        $files = app(FileSystemService::class);
        $folder = $files->createFolder($this->workspace->id, null, 'capability-files', $this->agent->id);
        $file = $files->writeFile($this->workspace->id, $folder->id, 'note.md', 'Original file content', $this->agent->id);

        return [$folder, $file];
    }

    private function taskFixture(User $assignee): Task
    {
        return Task::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Capability Task',
            'description' => 'Original task description',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $assignee->id,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_MANUAL,
        ]);
    }

    /** @return array{ListItem, ListItem} */
    private function listFixture(User $owner): array
    {
        $project = ListItem::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'title' => 'Capability Project',
            'description' => 'Project folder',
            'is_folder' => true,
            'status' => 'backlog',
            'creator_id' => $owner->id,
        ]);
        $item = ListItem::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'parent_id' => $project->id,
            'title' => 'Capability Item',
            'description' => 'Original list description',
            'is_folder' => false,
            'status' => 'backlog',
            'priority' => 'normal',
            'creator_id' => $owner->id,
            'assignee_id' => $owner->id,
        ]);

        return [$project, $item];
    }

    private function toolPermission(string $tool, string $permission, bool $requiresApproval = false): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => $tool,
            'permission' => $permission,
            'requires_approval' => $requiresApproval,
        ]);
    }

    private function workspaceFileExists(string $name): bool
    {
        return WorkspaceFile::forWorkspace()->where('name', $name)->exists();
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertVfsError(string $expectedCode, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected VFS error {$expectedCode}.");
        } catch (VfsError $error) {
            $this->assertSame($expectedCode, $error->errorCode);
        }
    }
}
