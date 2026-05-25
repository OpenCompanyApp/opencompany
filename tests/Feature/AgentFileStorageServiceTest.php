<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Services\AgentFileStorageService;
use App\Services\FileSystemService;
use Mockery;
use Tests\TestCase;

/**
 * Verifies agent file writes stay inside safe virtual paths.
 */
class AgentFileStorageServiceTest extends TestCase
{
    public function test_rejects_path_like_filenames(): void
    {
        $service = new AgentFileStorageService(Mockery::mock(FileSystemService::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid output filename.');

        $service->saveFile($this->agent(), '../secret.txt', 'content', 'text/plain');
    }

    public function test_rejects_parent_segments_in_subfolder(): void
    {
        $filesystem = Mockery::mock(FileSystemService::class);
        $filesystem->shouldReceive('ensureAgentHomeFolder')
            ->once()
            ->andReturn(new WorkspaceFile(['id' => 'home', 'name' => 'agent']));

        $service = new AgentFileStorageService($filesystem);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid output subfolder.');

        $service->saveFile($this->agent(), 'report.txt', 'content', 'text/plain', 'reports/../secrets');
    }

    public function test_writes_nested_safe_subfolders_under_agent_home(): void
    {
        $agent = $this->agent();
        $filesystem = Mockery::mock(FileSystemService::class);

        $filesystem->shouldReceive('ensureAgentHomeFolder')
            ->once()
            ->with($agent)
            ->andReturn(new WorkspaceFile(['id' => 'home', 'name' => 'agent']));

        $filesystem->shouldReceive('createFolder')
            ->once()
            ->with('workspace-1', 'home', 'reports', 'agent-1')
            ->andReturn(new WorkspaceFile(['id' => 'reports', 'name' => 'reports']));

        $filesystem->shouldReceive('createFolder')
            ->once()
            ->with('workspace-1', 'reports', 'charts', 'agent-1')
            ->andReturn(new WorkspaceFile(['id' => 'charts', 'name' => 'charts']));

        $filesystem->shouldReceive('writeFile')
            ->once()
            ->with('workspace-1', 'charts', 'chart.svg', '<svg />', 'agent-1', 'image/svg+xml')
            ->andReturn(new WorkspaceFile(['id' => 'file-1', 'name' => 'chart.svg']));

        $result = (new AgentFileStorageService($filesystem))
            ->saveFile($agent, 'chart.svg', '<svg />', 'image/svg+xml', 'reports/charts');

        $this->assertSame('file-1', $result['id']);
        $this->assertSame('/chart.svg', $result['path']);
        $this->assertSame('/api/files/file-1/download', $result['url']);
    }

    private function agent(): User
    {
        return new User([
            'id' => 'agent-1',
            'workspace_id' => 'workspace-1',
            'name' => 'Agent',
            'type' => 'agent',
        ]);
    }
}
