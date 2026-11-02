<?php

namespace Tests\Feature\Vfs;

use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\DataTableView;
use App\Models\Document;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceMember;
use App\Services\FileSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Protects permission-correct, cursor-based traversal for the two potentially
 * large VFS mounts and generated collection files.
 *
 * These tests call the structured tools rather than private adapter methods so
 * cursor binding, response metadata, authorization, and query ordering are all
 * exercised through the same contract used by Code Mode and future runtime callers.
 */
class VfsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create([
            'name' => 'Pagination Agent',
            'status' => 'idle',
            'behavior_mode' => 'autonomous',
        ]);

        WorkspaceDisk::create([
            'id' => 'pagination-disk-local',
            'workspace_id' => $this->workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);
    }

    public function test_document_cursor_advances_past_a_fully_hidden_scan_window(): void
    {
        for ($index = 0; $index < 120; $index++) {
            Document::factory()->folder()->create([
                'title' => sprintf('AAA Hidden Folder %03d', $index),
                'content' => '',
                'author_id' => $this->agent->id,
            ]);
        }

        $allowedFolder = Document::factory()->folder()->create([
            'title' => 'ZZZ Allowed Folder',
            'content' => '',
            'author_id' => $this->agent->id,
        ]);
        $allowedDocument = Document::factory()->create([
            'title' => 'ZZZ Allowed Note',
            'parent_id' => $allowedFolder->id,
            'author_id' => $this->agent->id,
        ]);
        $this->allowScope('folder', $allowedFolder->id);

        $first = $this->tool('vfs_ls', [
            'path' => '/docs/by-id',
            'limit' => 2,
            'maxFiles' => 100,
        ]);
        $second = $this->tool('vfs_ls', [
            'path' => '/docs/by-id',
            'limit' => 2,
            'maxFiles' => 100,
            'cursor' => $first['result']['next_cursor'],
        ]);

        $this->assertTrue($first['ok']);
        $this->assertSame([], $first['result']['items']);
        $this->assertSame(100, $first['result']['skipped']);
        $this->assertSame(100, $first['result']['scanned']);
        $this->assertTrue($first['result']['truncated']);
        $this->assertNotNull($first['result']['next_cursor']);

        $this->assertTrue($second['ok']);
        $this->assertEqualsCanonicalizing(
            [$allowedFolder->id, $allowedDocument->id],
            array_column($second['result']['items'], 'backend_id'),
        );
        $this->assertSame(20, $second['result']['skipped']);
        $this->assertSame(22, $second['result']['scanned']);
        $this->assertFalse($second['result']['truncated']);
    }

    public function test_file_page_limit_is_applied_after_folder_authorization(): void
    {
        $files = app(FileSystemService::class);
        for ($index = 0; $index < 120; $index++) {
            $files->createFolder(
                $this->workspace->id,
                null,
                sprintf('AAA Hidden Folder %03d', $index),
                $this->agent->id,
            );
        }

        $allowedFolder = $files->createFolder(
            $this->workspace->id,
            null,
            'ZZZ Allowed Folder',
            $this->agent->id,
        );
        $allowedFile = $files->writeFile(
            $this->workspace->id,
            $allowedFolder->id,
            'ZZZ Allowed Note.md',
            'allowed',
            $this->agent->id,
        );
        $this->allowScope('file_folder', $allowedFolder->id);

        $response = $this->tool('vfs_ls', [
            'path' => '/files/by-id',
            'limit' => 2,
            'maxFiles' => 200,
        ]);

        $this->assertTrue($response['ok']);
        $this->assertEqualsCanonicalizing(
            [$allowedFolder->id, $allowedFile->id],
            array_column($response['result']['items'], 'backend_id'),
        );
        $this->assertSame(120, $response['result']['skipped']);
        $this->assertSame(122, $response['result']['scanned']);
        $this->assertFalse($response['result']['truncated']);
    }

    public function test_document_and_file_adapters_traverse_every_entry_beyond_one_thousand(): void
    {
        $this->allowScope('file_folder', '*');
        $documentIds = [];
        $fileIds = [];
        $now = now();

        for ($index = 0; $index < 1_005; $index++) {
            $documentIds[] = sprintf('page-doc-%04d', $index);
            $fileIds[] = sprintf('page-file-%04d', $index);
        }

        foreach (array_chunk($documentIds, 200) as $chunk) {
            Document::query()->insert(array_map(fn (string $id): array => [
                'id' => $id,
                'workspace_id' => $this->workspace->id,
                'title' => 'Paged Document '.substr($id, -4),
                'content' => 'pagination fixture',
                'content_format' => 'markdown',
                'author_id' => $this->agent->id,
                'parent_id' => null,
                'is_folder' => false,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        foreach (array_chunk($fileIds, 200) as $chunk) {
            WorkspaceFile::query()->insert(array_map(fn (string $id): array => [
                'id' => $id,
                'workspace_id' => $this->workspace->id,
                'parent_id' => null,
                'name' => 'Paged File '.substr($id, -4),
                'is_folder' => true,
                'workspace_disk_id' => 'pagination-disk-local',
                'owner_id' => $this->agent->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $listedDocumentIds = $this->collectBackendIds('/docs/by-id', limit: 137, maxFiles: 300);
        $listedFileIds = $this->collectBackendIds('/files/by-id', limit: 137, maxFiles: 300);

        $this->assertCount(1_005, array_intersect($documentIds, $listedDocumentIds));
        $this->assertCount(1_005, array_intersect($fileIds, $listedFileIds));
        $this->assertSame(count($listedDocumentIds), count(array_unique($listedDocumentIds)));
        $this->assertSame(count($listedFileIds), count(array_unique($listedFileIds)));
    }

    public function test_cursor_is_path_adapter_and_actor_bound_and_final_count_is_exact(): void
    {
        for ($index = 0; $index < 3; $index++) {
            Document::factory()->create([
                'title' => sprintf('Bound Cursor %02d', $index),
                'author_id' => $this->agent->id,
            ]);
        }

        $first = $this->tool('vfs_ls', ['path' => '/docs/by-id', 'limit' => 1]);
        $cursor = $first['result']['next_cursor'];

        $wrongPath = $this->tool('vfs_ls', ['path' => '/docs', 'limit' => 1, 'cursor' => $cursor]);
        $wrongAdapter = $this->tool('vfs_ls', ['path' => '/files/by-id', 'limit' => 1, 'cursor' => $cursor]);
        $otherAgent = User::factory()->agent()->create(['name' => 'Other Pagination Agent']);
        $wrongActor = $this->tool('vfs_ls', [
            'path' => '/docs/by-id',
            'limit' => 1,
            'cursor' => $cursor,
        ], $otherAgent);

        foreach ([$wrongPath, $wrongAdapter, $wrongActor] as $response) {
            $this->assertFalse($response['ok']);
            $this->assertSame('invalid_request', $response['error']['code']);
        }

        // Cursors are bound to the listing, not to ls versus count, so the count
        // helper can finish the same traversal and report the cumulative total.
        $count = $this->tool('vfs_count', [
            'path' => '/docs/by-id',
            'limit' => 100,
            'cursor' => $cursor,
        ]);
        $this->assertTrue($count['ok']);
        $this->assertTrue($count['result']['count_is_exact']);
        $this->assertSame(1, $count['result']['seen_before']);
        $this->assertSame(3, $count['result']['total_count']);
    }

    public function test_keyset_cursors_do_not_duplicate_or_skip_existing_rows_after_insert_and_delete(): void
    {
        $this->allowScope('file_folder', '*');
        $documents = collect(range(0, 5))->map(fn (int $index): Document => Document::factory()->create([
            'title' => sprintf('Mutation Document %02d', $index),
            'author_id' => $this->agent->id,
        ]));
        $files = collect(range(0, 5))->map(fn (int $index): WorkspaceFile => app(FileSystemService::class)->createFolder(
            $this->workspace->id,
            null,
            sprintf('Mutation Folder %02d', $index),
            $this->agent->id,
        ));

        $documentFirst = $this->tool('vfs_ls', ['path' => '/docs/by-id', 'limit' => 2]);
        $fileFirst = $this->tool('vfs_ls', ['path' => '/files/by-id', 'limit' => 2]);

        Document::factory()->create([
            'title' => 'AAA Inserted Before Document Cursor',
            'author_id' => $this->agent->id,
        ]);
        app(FileSystemService::class)->createFolder(
            $this->workspace->id,
            null,
            'AAA Inserted Before File Cursor',
            $this->agent->id,
        );
        Document::query()->whereKey($documentFirst['result']['items'][0]['backend_id'])->delete();
        WorkspaceFile::query()->whereKey($fileFirst['result']['items'][0]['backend_id'])->delete();

        $listedDocumentIds = [
            ...array_column($documentFirst['result']['items'], 'backend_id'),
            ...$this->collectBackendIds('/docs/by-id', 2, 100, $documentFirst['result']['next_cursor']),
        ];
        $listedFileIds = [
            ...array_column($fileFirst['result']['items'], 'backend_id'),
            ...$this->collectBackendIds('/files/by-id', 2, 100, $fileFirst['result']['next_cursor']),
        ];

        $this->assertEqualsCanonicalizing($documents->pluck('id')->all(), $listedDocumentIds);
        $this->assertEqualsCanonicalizing($files->pluck('id')->all(), $listedFileIds);
        $this->assertSame(count($listedDocumentIds), count(array_unique($listedDocumentIds)));
        $this->assertSame(count($listedFileIds), count(array_unique($listedFileIds)));
    }

    public function test_task_and_list_adapters_traverse_filtered_views_beyond_one_thousand_with_exact_counts(): void
    {
        $taskIds = [];
        $listIds = [];
        $baseTime = now();

        for ($index = 0; $index < 1_005; $index++) {
            $taskIds[] = '00000001-0000-4000-8000-'.sprintf('%012d', $index);
            $listIds[] = '00000002-0000-4000-8000-'.sprintf('%012d', $index);
        }

        foreach (array_chunk($taskIds, 200) as $chunkIndex => $chunk) {
            Task::query()->insert(array_map(
                fn (string $id, int $index): array => $this->taskRow(
                    id: $id,
                    title: 'Paged Task '.substr($id, -4),
                    status: Task::STATUS_PENDING,
                    agentId: $this->agent->id,
                    updatedAt: $baseTime->copy()->subSeconds(($chunkIndex * 200) + $index),
                ),
                $chunk,
                array_keys($chunk),
            ));
        }

        foreach (array_chunk($listIds, 200) as $chunkIndex => $chunk) {
            ListItem::query()->insert(array_map(
                fn (string $id, int $index): array => $this->listRow(
                    id: $id,
                    title: 'Paged Project '.substr($id, -4),
                    updatedAt: $baseTime->copy()->subSeconds(($chunkIndex * 200) + $index),
                ),
                $chunk,
                array_keys($chunk),
            ));
        }

        $listedTaskIds = $this->collectBackendIds('/tasks/open', limit: 137, maxFiles: 300);
        $listedListIds = $this->collectBackendIds('/lists/projects', limit: 137, maxFiles: 300);

        $this->assertCount(1_005, array_intersect($taskIds, $listedTaskIds));
        $this->assertCount(1_005, array_intersect($listIds, $listedListIds));
        $this->assertSame(count($listedTaskIds), count(array_unique($listedTaskIds)));
        $this->assertSame(count($listedListIds), count(array_unique($listedListIds)));
        $this->assertSame(1_005, $this->countAll('/tasks/open', 137, 300));
        $this->assertSame(1_005, $this->countAll('/lists/projects', 137, 300));
    }

    public function test_task_agent_and_list_assignee_filters_remain_bound_across_pages(): void
    {
        $otherAgent = User::factory()->agent()->create(['name' => 'Other Filter Agent']);
        $baseTime = now();
        $expectedTaskIds = [];
        $expectedListIds = [];
        $taskRows = [];

        for ($index = 0; $index < 3; $index++) {
            $id = (string) Str::uuid();
            $expectedTaskIds[] = $id;
            $taskRows[] = $this->taskRow(
                $id,
                "Assigned Completed {$index}",
                Task::STATUS_COMPLETED,
                $this->agent->id,
                $baseTime->copy()->subSeconds($index),
            );
        }
        $taskRows[] = $this->taskRow(
            (string) Str::uuid(),
            'Assigned But Open',
            Task::STATUS_PENDING,
            $this->agent->id,
            $baseTime->copy()->subSeconds(10),
        );
        $taskRows[] = $this->taskRow(
            (string) Str::uuid(),
            'Other Completed',
            Task::STATUS_COMPLETED,
            $otherAgent->id,
            $baseTime->copy()->subSeconds(11),
        );
        Task::query()->insert($taskRows);

        $projectId = (string) Str::uuid();
        $listRows = [$this->listRow($projectId, 'Filter Project', $baseTime)];
        for ($index = 0; $index < 3; $index++) {
            $id = (string) Str::uuid();
            $expectedListIds[] = $id;
            $listRows[] = $this->listRow(
                id: $id,
                title: "Assigned List Item {$index}",
                updatedAt: $baseTime->copy()->subSeconds($index),
                isFolder: false,
                parentId: $projectId,
                assigneeId: $this->agent->id,
            );
        }
        $listRows[] = $this->listRow(
            id: (string) Str::uuid(),
            title: 'Other Assigned List Item',
            updatedAt: $baseTime->copy()->subSeconds(10),
            isFolder: false,
            parentId: $projectId,
            assigneeId: $otherAgent->id,
        );
        ListItem::query()->insert($listRows);

        $listedTaskIds = $this->collectBackendIds(
            '/tasks/by-agent/'.Str::slug($this->agent->name).'/completed',
            1,
            10,
        );
        $listedListIds = $this->collectBackendIds(
            '/lists/by-assignee/'.Str::slug($this->agent->name),
            1,
            10,
        );

        $this->assertEqualsCanonicalizing($expectedTaskIds, $listedTaskIds);
        $this->assertEqualsCanonicalizing($expectedListIds, $listedListIds);
    }

    public function test_task_and_list_keysets_resist_insert_delete_offset_drift(): void
    {
        $baseTime = now();
        $taskIds = [];
        $listIds = [];

        for ($index = 0; $index < 6; $index++) {
            $taskIds[] = (string) Str::uuid();
            $listIds[] = (string) Str::uuid();
        }
        Task::query()->insert(array_map(
            fn (string $id, int $index): array => $this->taskRow(
                $id,
                "Mutation Task {$index}",
                Task::STATUS_PENDING,
                $this->agent->id,
                $baseTime,
            ),
            $taskIds,
            array_keys($taskIds),
        ));
        ListItem::query()->insert(array_map(
            fn (string $id, int $index): array => $this->listRow(
                $id,
                "Mutation Project {$index}",
                $baseTime,
            ),
            $listIds,
            array_keys($listIds),
        ));

        $taskFirst = $this->tool('vfs_ls', ['path' => '/tasks/open', 'limit' => 2]);
        $listFirst = $this->tool('vfs_ls', ['path' => '/lists/projects', 'limit' => 2]);

        Task::query()->insert($this->taskRow(
            (string) Str::uuid(),
            'Inserted Before Task Cursor',
            Task::STATUS_PENDING,
            $this->agent->id,
            $baseTime->copy()->addMinute(),
        ));
        ListItem::query()->insert($this->listRow(
            (string) Str::uuid(),
            'Inserted Before List Cursor',
            $baseTime->copy()->addMinute(),
        ));
        Task::query()->whereKey($taskFirst['result']['items'][0]['backend_id'])->delete();
        ListItem::query()->whereKey($listFirst['result']['items'][0]['backend_id'])->delete();

        $listedTaskIds = [
            ...array_column($taskFirst['result']['items'], 'backend_id'),
            ...$this->collectBackendIds('/tasks/open', 2, 100, $taskFirst['result']['next_cursor']),
        ];
        $listedListIds = [
            ...array_column($listFirst['result']['items'], 'backend_id'),
            ...$this->collectBackendIds('/lists/projects', 2, 100, $listFirst['result']['next_cursor']),
        ];

        $this->assertEqualsCanonicalizing($taskIds, $listedTaskIds);
        $this->assertEqualsCanonicalizing($listIds, $listedListIds);
        $this->assertSame(count($listedTaskIds), count(array_unique($listedTaskIds)));
        $this->assertSame(count($listedListIds), count(array_unique($listedListIds)));
    }

    public function test_channel_adapter_traverses_every_entry_beyond_one_thousand(): void
    {
        $channelIds = [];
        $now = now();
        for ($index = 0; $index < 1_005; $index++) {
            $channelIds[] = 'page-channel-'.sprintf('%04d', $index);
        }

        foreach (array_chunk($channelIds, 200) as $chunk) {
            Channel::query()->insert(array_map(fn (string $id): array => [
                'id' => $id,
                'workspace_id' => $this->workspace->id,
                'name' => 'Paged Channel '.substr($id, -4),
                'type' => 'public',
                'description' => 'pagination fixture',
                'creator_id' => $this->agent->id,
                'is_ephemeral' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $listed = $this->collectBackendIds('/channels/by-id', 137, 300);

        $this->assertCount(1_005, array_intersect($channelIds, $listed));
        $this->assertSame(count($listed), count(array_unique($listed)));
        $this->assertSame(1_005, $this->countAll('/channels/by-id', 137, 300));
    }

    public function test_agents_tables_approvals_and_automations_use_mount_owned_keysets(): void
    {
        $otherAgents = collect(range(1, 4))->map(fn (int $index): User => User::factory()->agent()->create([
            'name' => "Paged Agent {$index}",
        ]));

        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Pagination Table',
            'description' => 'fixture',
            'created_by' => $this->agent->id,
        ]);
        $rows = collect(range(1, 4))->map(fn (int $index): DataTableRow => DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['index' => $index],
            'created_by' => $this->agent->id,
        ]));
        $views = collect(range(1, 4))->map(fn (int $index): DataTableView => DataTableView::create([
            'table_id' => $table->id,
            'name' => "View {$index}",
            'type' => 'grid',
        ]));
        $approvals = collect(range(1, 4))->map(fn (int $index): ApprovalRequest => ApprovalRequest::factory()->create([
            'requester_id' => $this->agent->id,
            'title' => "Pagination approval {$index}",
        ]));
        $automations = collect(range(1, 4))->map(fn (int $index): Automation => Automation::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => "Pagination Automation {$index}",
            'trigger_type' => 'schedule',
            'execution_type' => 'prompt',
            'agent_id' => $this->agent->id,
            'prompt' => 'pagination fixture',
            'cron_expression' => '0 * * * *',
            'timezone' => 'UTC',
            'created_by_id' => $this->agent->id,
        ]));

        $this->assertEqualsCanonicalizing(
            [$this->agent->id, ...$otherAgents->pluck('id')->all()],
            $this->collectBackendIds('/agents/by-id', 2, 10),
        );
        $this->assertEqualsCanonicalizing([$table->id], $this->collectBackendIds('/tables', 1, 10));
        $this->assertEqualsCanonicalizing($rows->pluck('id')->all(), $this->collectBackendIds('/tables/pagination-table/rows/by-id', 2, 10));
        $this->assertEqualsCanonicalizing($views->pluck('id')->all(), $this->collectBackendIds('/tables/pagination-table/views', 2, 10));
        $this->assertEqualsCanonicalizing($approvals->pluck('id')->all(), $this->collectBackendIds('/approvals', 2, 10));
        $this->assertEqualsCanonicalizing($automations->pluck('id')->all(), $this->collectBackendIds('/automations', 2, 10));
    }

    public function test_generated_transcript_table_and_workspace_reads_continue_with_valid_chunks(): void
    {
        $channel = Channel::factory()->public()->create([
            'name' => 'Paged Transcript',
            'creator_id' => $this->agent->id,
        ]);
        $timestamp = now()->startOfSecond();
        $messages = collect(range(1, 5))->map(fn (int $index): Message => Message::factory()->create([
            'id' => 'paged-message-'.$index,
            'channel_id' => $channel->id,
            'author_id' => $this->agent->id,
            'content' => "Transcript line {$index}",
            'timestamp' => $timestamp,
        ]));
        foreach (range(1, 3) as $daysAgo) {
            Message::factory()->create([
                'id' => 'paged-day-'.$daysAgo,
                'channel_id' => $channel->id,
                'author_id' => $this->agent->id,
                'content' => "Older transcript day {$daysAgo}",
                'timestamp' => $timestamp->copy()->subDays($daysAgo),
            ]);
        }

        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Paged Export',
            'created_by' => $this->agent->id,
        ]);
        $rows = collect(range(1, 5))->map(fn (int $index): DataTableRow => DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['row' => $index],
            'created_by' => $this->agent->id,
        ]));

        $human = User::factory()->create(['name' => 'Paged Human']);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'member',
        ]);
        User::factory()->agent()->create(['name' => 'Paged Roster Agent']);

        $transcriptPath = "/channels/by-id/{$channel->id}/messages/{$timestamp->toDateString()}.md";
        $this->assertEqualsCanonicalizing(
            collect(range(0, 3))->map(fn (int $daysAgo): string => $timestamp->copy()->subDays($daysAgo)->toDateString().'.md')->all(),
            $this->collectItemNames("/channels/by-id/{$channel->id}/messages", 2),
        );
        $transcriptChunks = $this->collectReadChunks($transcriptPath, 2);
        $exportChunks = $this->collectReadChunks('/tables/paged-export/rows.ndjson', 2);
        $rosterChunks = $this->collectReadChunks('/workspace/members.json', 1);

        foreach ($messages as $message) {
            $this->assertStringContainsString($message->content, implode("\n", $transcriptChunks));
        }
        $exportedIds = collect($exportChunks)
            ->flatMap(fn (string $chunk): array => array_filter(explode("\n", $chunk)))
            ->map(fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['id'])
            ->all();
        $this->assertEqualsCanonicalizing($rows->pluck('id')->all(), $exportedIds);
        foreach ($rosterChunks as $chunk) {
            $decoded = json_decode($chunk, true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('members', $decoded);
            $this->assertArrayHasKey('agents', $decoded);
        }

        $first = $this->tool('vfs_read', ['path' => $transcriptPath, 'limit' => 1]);
        $wrongPath = $this->tool('vfs_read', [
            'path' => '/tables/paged-export/rows.ndjson',
            'limit' => 1,
            'cursor' => $first['result']['next_cursor'],
        ]);
        $this->assertFalse($wrongPath['ok']);
        $this->assertSame('invalid_request', $wrongPath['error']['code']);
    }

    public function test_generated_read_pages_honor_byte_limits_without_skipping_complete_records(): void
    {
        $channel = Channel::factory()->public()->create([
            'name' => 'Byte Paged Transcript',
            'creator_id' => $this->agent->id,
        ]);
        $timestamp = now()->startOfSecond();
        $messages = collect(range(1, 4))->map(fn (int $index): Message => Message::factory()->create([
            'id' => "byte-message-{$index}",
            'channel_id' => $channel->id,
            'author_id' => $this->agent->id,
            'content' => "Byte transcript marker {$index} ".str_repeat((string) $index, $index === 2 ? 600 : 180),
            'timestamp' => $timestamp,
        ]));

        $table = DataTable::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Byte Export',
            'created_by' => $this->agent->id,
        ]);
        $rows = collect(range(1, 4))->map(fn (int $index): DataTableRow => DataTableRow::create([
            'table_id' => $table->id,
            'data' => [
                'marker' => "byte-row-{$index}",
                'payload' => str_repeat((string) $index, 180),
            ],
            'created_by' => $this->agent->id,
        ]));

        $human = User::factory()->create([
            'name' => 'Byte Human '.str_repeat('H', 120),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $human->id,
            'role' => 'member',
        ]);
        User::factory()->agent()->create([
            'name' => 'Byte Agent '.str_repeat('A', 120),
        ]);

        $transcriptPath = "/channels/by-id/{$channel->id}/messages/{$timestamp->toDateString()}.md";
        $tablePath = '/tables/byte-export/rows.ndjson';
        $rosterPath = '/workspace/members.json';

        // A valid maxBytes value can still be too small for the next complete
        // record. The response must disclose the exact retry budget instead of
        // returning corrupt data or a cursor that silently advances past it.
        foreach ([[$transcriptPath, 1], [$tablePath, 1], [$rosterPath, 39]] as [$path, $maxBytes]) {
            $oversized = $this->tool('vfs_read', [
                'path' => $path,
                'limit' => 100,
                'maxBytes' => $maxBytes,
            ]);

            $this->assertFalse($oversized['ok']);
            $this->assertSame('invalid_request', $oversized['error']['code']);
            $this->assertSame('maxBytes', $oversized['error']['context']['field']);
            $this->assertSame($path, $oversized['error']['context']['path']);
            $this->assertGreaterThan($maxBytes, $oversized['error']['context']['minimum_bytes']);
            $this->assertNotEmpty($oversized['error']['context']['record_id']);

            $retry = $this->tool('vfs_read', [
                'path' => $path,
                'limit' => 100,
                'maxBytes' => $oversized['error']['context']['minimum_bytes'],
            ]);
            $this->assertTrue($retry['ok'], json_encode($retry));
            $this->assertSame(1, $retry['result']['returned']);
            $this->assertLessThanOrEqual(
                $oversized['error']['context']['minimum_bytes'],
                $retry['result']['bytes'],
            );
        }

        $transcriptSingles = $this->collectReadPages($transcriptPath, 1, 2_000_000);
        $tableSingles = $this->collectReadPages($tablePath, 1, 2_000_000);
        $rosterSingles = $this->collectReadPages($rosterPath, 1, 2_000_000);

        $transcriptBudget = max(array_column($transcriptSingles, 'bytes'));
        $tableBudget = max(array_column($tableSingles, 'bytes'));
        $rosterBudget = max(array_column($rosterSingles, 'bytes'));

        // The second transcript record is intentionally larger. The first
        // page's cursor must remain parked before it when the original budget
        // cannot admit it, allowing an exact-budget retry with the same cursor.
        $firstTranscriptPage = $this->tool('vfs_read', [
            'path' => $transcriptPath,
            'limit' => 100,
            'maxBytes' => $transcriptSingles[0]['bytes'],
        ]);
        $this->assertTrue($firstTranscriptPage['ok']);
        $this->assertSame(1, $firstTranscriptPage['result']['returned']);
        $blockedContinuation = $this->tool('vfs_read', [
            'path' => $transcriptPath,
            'limit' => 100,
            'maxBytes' => $transcriptSingles[0]['bytes'],
            'cursor' => $firstTranscriptPage['result']['next_cursor'],
        ]);
        $this->assertFalse($blockedContinuation['ok']);
        $this->assertSame('byte-message-2', $blockedContinuation['error']['context']['record_id']);
        $resizedContinuation = $this->tool('vfs_read', [
            'path' => $transcriptPath,
            'limit' => 100,
            'maxBytes' => $blockedContinuation['error']['context']['minimum_bytes'],
            'cursor' => $firstTranscriptPage['result']['next_cursor'],
        ]);
        $this->assertTrue($resizedContinuation['ok'], json_encode($resizedContinuation));
        $this->assertSame(1, $resizedContinuation['result']['returned']);
        $this->assertStringContainsString($messages[1]->content, $resizedContinuation['result']['content']);

        $transcriptPages = $this->collectReadPages($transcriptPath, 100, $transcriptBudget);
        $tablePages = $this->collectReadPages($tablePath, 100, $tableBudget);
        $rosterPages = $this->collectReadPages($rosterPath, 100, $rosterBudget);

        foreach ([[$transcriptPages, $transcriptBudget], [$tablePages, $tableBudget], [$rosterPages, $rosterBudget]] as [$pages, $maxBytes]) {
            foreach ($pages as $page) {
                $this->assertLessThanOrEqual($maxBytes, $page['bytes']);
                $this->assertSame(strlen($page['content']), $page['bytes']);
                $this->assertGreaterThan(0, $page['returned']);
            }
        }

        $transcript = implode("\n", array_column($transcriptPages, 'content'));
        foreach ($messages as $message) {
            $this->assertSame(1, substr_count($transcript, $message->content));
        }

        $tableIds = collect($tablePages)
            ->flatMap(fn (array $page): array => array_filter(explode("\n", $page['content'])))
            ->map(fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['id'])
            ->all();
        $this->assertEqualsCanonicalizing($rows->pluck('id')->all(), $tableIds);
        $this->assertSame(count($tableIds), count(array_unique($tableIds)));

        $singleRosterIds = $this->workspacePrincipalIds($rosterSingles);
        $bytePagedRosterIds = $this->workspacePrincipalIds($rosterPages);
        $this->assertEqualsCanonicalizing($singleRosterIds, $bytePagedRosterIds);
        $this->assertSame(count($bytePagedRosterIds), count(array_unique($bytePagedRosterIds)));
    }

    public function test_structured_find_resumes_depth_first_state_instead_of_replaying_offsets(): void
    {
        $taskIds = collect(range(1, 5))->map(fn (): string => (string) Str::uuid())->all();
        Task::query()->insert(array_map(
            fn (string $id, int $index): array => $this->taskRow(
                $id,
                "Find Pagination {$index}",
                Task::STATUS_PENDING,
                $this->agent->id,
                now()->subSeconds($index),
            ),
            $taskIds,
            array_keys($taskIds),
        ));

        $cursor = null;
        $found = [];
        do {
            $response = $this->tool('vfs_find', array_filter([
                'paths' => ['/tasks/open'],
                'name' => '*',
                'type' => 'file',
                'limit' => 2,
                'maxFiles' => 20,
                'maxDepth' => 1,
                'cursor' => $cursor,
            ], fn (mixed $value): bool => $value !== null));
            $this->assertTrue($response['ok'], json_encode($response));
            array_push($found, ...array_column($response['result']['items'], 'backend_id'));
            $cursor = $response['result']['next_cursor'];
        } while ($cursor !== null);

        $this->assertEqualsCanonicalizing($taskIds, $found);
        $this->assertSame(count($found), count(array_unique($found)));
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(string $slug, array $arguments, ?User $agent = null): array
    {
        $tool = app(ToolRegistry::class)->instantiateToolBySlug($slug, $agent ?? $this->agent);

        return json_decode($tool->handle(new Request($arguments)), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function collectBackendIds(
        string $path,
        int $limit,
        int $maxFiles,
        ?string $cursor = null,
    ): array {
        $ids = [];
        $pageCount = 0;

        do {
            $response = $this->tool('vfs_ls', array_filter([
                'path' => $path,
                'limit' => $limit,
                'maxFiles' => $maxFiles,
                'cursor' => $cursor,
            ], fn (mixed $value): bool => $value !== null));
            $this->assertTrue($response['ok'], json_encode($response));
            array_push($ids, ...array_column($response['result']['items'], 'backend_id'));
            $cursor = $response['result']['next_cursor'];
            $pageCount++;
            $this->assertLessThan(50, $pageCount, 'Pagination cursor did not converge.');
        } while ($cursor !== null);

        return $ids;
    }

    private function countAll(string $path, int $limit, int $maxFiles): int
    {
        $cursor = null;
        $pageCount = 0;

        do {
            $response = $this->tool('vfs_count', array_filter([
                'path' => $path,
                'limit' => $limit,
                'maxFiles' => $maxFiles,
                'cursor' => $cursor,
            ], fn (mixed $value): bool => $value !== null));
            $this->assertTrue($response['ok'], json_encode($response));
            $cursor = $response['result']['next_cursor'];
            $pageCount++;
            $this->assertLessThan(50, $pageCount, 'Count cursor did not converge.');
        } while ($cursor !== null);

        $this->assertTrue($response['result']['count_is_exact']);

        return $response['result']['total_count'];
    }

    /** @return list<string> */
    private function collectReadChunks(string $path, int $limit): array
    {
        $chunks = [];
        $cursor = null;
        $pageCount = 0;

        do {
            $response = $this->tool('vfs_read', array_filter([
                'path' => $path,
                'limit' => $limit,
                'maxFiles' => 20,
                'cursor' => $cursor,
            ], fn (mixed $value): bool => $value !== null));
            $this->assertTrue($response['ok'], json_encode($response));
            $chunks[] = $response['result']['content'];
            $cursor = $response['result']['next_cursor'];
            $pageCount++;
            $this->assertLessThan(50, $pageCount, 'Read pagination cursor did not converge.');
        } while ($cursor !== null);

        return $chunks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectReadPages(string $path, int $limit, int $maxBytes): array
    {
        $pages = [];
        $cursor = null;
        $pageCount = 0;

        do {
            $response = $this->tool('vfs_read', array_filter([
                'path' => $path,
                'limit' => $limit,
                'maxFiles' => 20,
                'maxBytes' => $maxBytes,
                'cursor' => $cursor,
            ], fn (mixed $value): bool => $value !== null));
            $this->assertTrue($response['ok'], json_encode($response));
            $pages[] = $response['result'];
            $cursor = $response['result']['next_cursor'];
            $pageCount++;
            $this->assertLessThan(50, $pageCount, 'Byte-aware read pagination cursor did not converge.');
        } while ($cursor !== null);

        return $pages;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return list<string>
     */
    private function workspacePrincipalIds(array $pages): array
    {
        return collect($pages)
            ->flatMap(function (array $page): array {
                $decoded = json_decode($page['content'], true, flags: JSON_THROW_ON_ERROR);

                return [
                    ...array_column($decoded['members'], 'user_id'),
                    ...array_column($decoded['agents'], 'id'),
                ];
            })
            ->all();
    }

    /** @return list<string> */
    private function collectItemNames(string $path, int $limit): array
    {
        $names = [];
        $cursor = null;

        do {
            $response = $this->tool('vfs_ls', array_filter([
                'path' => $path,
                'limit' => $limit,
                'cursor' => $cursor,
            ], fn (mixed $value): bool => $value !== null));
            $this->assertTrue($response['ok'], json_encode($response));
            array_push($names, ...array_column($response['result']['items'], 'name'));
            $cursor = $response['result']['next_cursor'];
        } while ($cursor !== null);

        return $names;
    }

    /** @return array<string, mixed> */
    private function taskRow(
        string $id,
        string $title,
        string $status,
        string $agentId,
        \DateTimeInterface $updatedAt,
    ): array {
        return [
            'id' => $id,
            'workspace_id' => $this->workspace->id,
            'title' => $title,
            'description' => 'pagination fixture',
            'type' => Task::TYPE_CUSTOM,
            'status' => $status,
            'priority' => Task::PRIORITY_NORMAL,
            'agent_id' => $agentId,
            'requester_id' => $this->agent->id,
            'source' => Task::SOURCE_MANUAL,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function listRow(
        string $id,
        string $title,
        \DateTimeInterface $updatedAt,
        bool $isFolder = true,
        ?string $parentId = null,
        ?string $assigneeId = null,
    ): array {
        return [
            'id' => $id,
            'workspace_id' => $this->workspace->id,
            'parent_id' => $parentId,
            'is_folder' => $isFolder,
            'title' => $title,
            'description' => 'pagination fixture',
            'status' => 'backlog',
            'assignee_id' => $assigneeId,
            'creator_id' => $this->agent->id,
            'priority' => 'medium',
            'position' => 0,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ];
    }

    private function allowScope(string $scopeType, string $scopeKey): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
    }
}
