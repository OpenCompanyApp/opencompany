<?php

namespace Tests\Feature\Vfs;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\VfsTextSearchService;
use App\Models\AgentPermission;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2 (Stream C2) search: index candidates are visited before the live
 * walk, folder/table/channel scopes narrow the candidate queries, table rows
 * are searchable on every page, expensive regexes fail loudly, and
 * count/truncated tell the truth.
 */
class VfsSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private OpenCompanyVfs $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle']);
        $this->vfs = app(OpenCompanyVfs::class);
    }

    // Regression (starvation): the live walk of /docs used to run first and
    // spend the whole file budget before any index candidate was read.
    public function test_index_candidates_are_visited_before_the_live_walk(): void
    {
        foreach (range(1, 6) as $index) {
            Document::factory()->create(['title' => "Aardvark {$index}", 'content' => 'filler', 'author_id' => $this->agent->id]);
        }
        $target = Document::factory()->create(['title' => 'Zulu', 'content' => "intro\nthe quokka clause\n", 'author_id' => $this->agent->id]);

        $result = $this->vfs->searchWithMetadata($this->agent, 'quokka', ['/docs'], new VfsBudget(maxFiles: 2));

        $this->assertSame('source_index+live', $result['backend']);
        $this->assertSame(1, $result['count']);
        // Readable friendly path for output; canonical by-id for structure.
        $this->assertSame('/docs/zulu', $result['matches'][0]['path']);
        $this->assertSame("/docs/by-id/{$target->id}", $result['matches'][0]['canonical_path']);
        $this->assertSame(2, $result['matches'][0]['line']);
        // Files were left unscanned, and the result says so.
        $this->assertTrue($result['truncated']);
        $this->assertSame('max_files', $result['truncated_reason']);
    }

    // Candidates inside a requested folder used to be dropped because their
    // by-id paths are not textually under /docs/<folder>.
    public function test_searching_a_doc_folder_uses_candidates_inside_that_folder(): void
    {
        $folder = Document::factory()->create(['title' => 'Policies', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $sub = Document::factory()->create(['title' => 'Travel', 'is_folder' => true, 'parent_id' => $folder->id, 'author_id' => $this->agent->id]);
        foreach (range(1, 4) as $index) {
            Document::factory()->create(['title' => "Aa {$index}", 'parent_id' => $folder->id, 'content' => 'filler', 'author_id' => $this->agent->id]);
        }
        $inside = Document::factory()->create(['title' => 'Per diem', 'parent_id' => $sub->id, 'content' => 'okapi allowance', 'author_id' => $this->agent->id]);
        Document::factory()->create(['title' => 'Outside', 'content' => 'okapi elsewhere', 'author_id' => $this->agent->id]);

        $result = $this->vfs->searchWithMetadata($this->agent, 'okapi', ['/docs/policies'], new VfsBudget(maxFiles: 1));

        $this->assertSame('source_index+live', $result['backend']);
        $this->assertSame(['/docs/policies/travel/per-diem'], array_column($result['matches'], 'path'));
        $this->assertSame(["/docs/by-id/{$inside->id}"], array_column($result['matches'], 'canonical_path'));
    }

    // Table rows used to be searchable only through the first page of
    // rows.ndjson; the row text is now an index candidate on every page.
    public function test_table_rows_beyond_the_first_page_are_searchable(): void
    {
        $table = DataTable::create(['workspace_id' => $this->workspace->id, 'name' => 'Leads', 'created_by' => $this->agent->id]);
        $column = DataTableColumn::create(['table_id' => $table->id, 'name' => 'Company', 'type' => 'text', 'order' => 0, 'required' => false]);
        foreach (range(1, 130) as $index) {
            DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => "Company {$index}"], 'created_by' => $this->agent->id]);
        }
        $row = DataTableRow::create(['table_id' => $table->id, 'data' => [$column->id => 'Café Narwhal GmbH'], 'created_by' => $this->agent->id]);

        $result = $this->vfs->searchWithMetadata($this->agent, 'Narwhal', ['/tables'], new VfsBudget(maxFiles: 5));
        // The row's own path comes first (candidate); the live walk may add
        // rows.ndjson when the row happens to be on its first page.
        $this->assertSame("/tables/leads/rows/by-id/{$row->id}.json", $result['matches'][0]['path'] ?? null);

        $this->assertSame("/tables/by-id/{$table->id}/rows/by-id/{$row->id}.json", $result['matches'][0]['canonical_path'] ?? null);

        // Stored row JSON escapes non-ASCII (\u00e9); the candidate query
        // matches the escaped form too. Candidates are canonical by-id
        // paths (table slugs may collide).
        $this->assertSame(
            ["/tables/by-id/{$table->id}/rows/by-id/{$row->id}.json"],
            app(VfsTextSearchService::class)->candidatePaths($this->agent, 'Café', ['/tables'], new VfsBudget(maxFiles: 5)),
        );

        // Scoped to the table directory as well.
        $scoped = $this->vfs->searchWithMetadata($this->agent, 'Narwhal', ['/tables/leads'], new VfsBudget(maxFiles: 5));
        $this->assertSame("/tables/leads/rows/by-id/{$row->id}.json", $scoped['matches'][0]['path'] ?? null);
    }

    // Catastrophic backtracking used to be swallowed as "no match".
    public function test_expensive_regex_is_an_explicit_invalid_request(): void
    {
        Document::factory()->create(['title' => 'Bomb', 'content' => str_repeat('a', 40).'!', 'author_id' => $this->agent->id]);

        try {
            $this->vfs->searchWithMetadata($this->agent, '(a+)+$', ['/docs'], null, regex: true);
            $this->fail('expected regex_too_expensive');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('regex_too_expensive', $e->context['reason']);
        }

        try {
            $this->vfs->searchWithMetadata($this->agent, '([unclosed', ['/docs'], null, regex: true);
            $this->fail('expected regex_invalid');
        } catch (VfsError $e) {
            $this->assertSame('invalid_request', $e->errorCode);
            $this->assertSame('regex_invalid', $e->context['reason']);
        }

        // The backtrack limit is restored after the search.
        $this->assertSame('1000000', ini_get('pcre.backtrack_limit'));
    }

    // truncated means "more output is known to exist", nothing else.
    public function test_count_and_truncated_are_truthful(): void
    {
        Document::factory()->create(['title' => 'Two', 'content' => "ibex one\nibex two\n", 'author_id' => $this->agent->id]);

        $exact = $this->vfs->searchWithMetadata($this->agent, 'ibex', ['/docs'], new VfsBudget(maxMatches: 2));
        $this->assertSame(2, $exact['count']);
        $this->assertFalse($exact['truncated']);
        $this->assertNull($exact['truncated_reason']);

        Document::factory()->create(['title' => 'Three', 'content' => 'ibex three', 'author_id' => $this->agent->id]);
        $over = $this->vfs->searchWithMetadata($this->agent, 'ibex', ['/docs'], new VfsBudget(maxMatches: 2));
        $this->assertSame(2, $over['count']);
        $this->assertTrue($over['truncated']);
        $this->assertSame('max_matches', $over['truncated_reason']);
    }

    // Unreadable candidates are re-checked by the mount and only skipped.
    public function test_candidates_are_permission_checked(): void
    {
        $allowed = Document::factory()->create(['title' => 'Open', 'is_folder' => true, 'author_id' => $this->agent->id]);
        $visible = Document::factory()->create(['title' => 'A', 'parent_id' => $allowed->id, 'content' => 'tapir visible', 'author_id' => $this->agent->id]);
        Document::factory()->create(['title' => 'B', 'content' => 'tapir hidden', 'author_id' => $this->agent->id]);
        AgentPermission::create(['id' => (string) Str::uuid(), 'agent_id' => $this->agent->id, 'scope_type' => 'folder', 'scope_key' => $allowed->id, 'permission' => 'allow', 'requires_approval' => false]);

        $result = $this->vfs->searchWithMetadata($this->agent, 'tapir', ['/']);

        $this->assertSame(["/docs/by-id/{$visible->id}"], array_values(array_unique(array_column($result['matches'], 'canonical_path'))));
        $this->assertSame(['/docs/open/a'], array_values(array_unique(array_column($result['matches'], 'path'))));
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
    }

    // Channel candidates map messages to transcript paths without one
    // channel lookup per message.
    public function test_channel_candidates_do_not_query_per_message(): void
    {
        foreach (range(1, 3) as $channelIndex) {
            $channel = Channel::factory()->public()->create(['name' => "room {$channelIndex}", 'creator_id' => $this->agent->id]);
            foreach (range(1, 10) as $messageIndex) {
                Message::factory()->create(['channel_id' => $channel->id, 'author_id' => $this->agent->id, 'content' => "gecko {$messageIndex}", 'timestamp' => now()]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $paths = app(VfsTextSearchService::class)->candidatePaths(
            $this->agent,
            'gecko',
            ['/channels'],
            new VfsBudget(maxFiles: 100, maxDepth: 6),
            visibleChannels: Channel::forWorkspace(),
        );
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(3, $paths);
        $this->assertLessThanOrEqual(3, $queries);
    }
}
