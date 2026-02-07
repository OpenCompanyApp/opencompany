<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Lists\QueryListItems;
use App\Models\ListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryListItemsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new QueryListItems($agent);

        return [$tool, $agent];
    }

    private function createListItem(array $attributes = []): ListItem
    {
        return ListItem::create(array_merge([
            'id' => Str::uuid()->toString(),
            'title' => 'Default Item',
            'description' => 'Default description',
            'status' => 'backlog',
            'priority' => 'medium',
        ], $attributes));
    }

    public function test_lists_all_items(): void
    {
        [$tool, $agent] = $this->makeTool();

        $this->createListItem(['title' => 'Fix login bug', 'assignee_id' => $agent->id]);
        $this->createListItem(['title' => 'Update docs', 'assignee_id' => $agent->id]);

        $request = new Request(['action' => 'list_all']);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Fix login bug', $result);
        $this->assertStringContainsString('Update docs', $result);
        $this->assertStringContainsString('List items (2)', $result);
    }

    public function test_gets_item_details(): void
    {
        [$tool, $agent] = $this->makeTool();

        $item = $this->createListItem([
            'title' => 'Refactor auth',
            'description' => 'Clean up the auth module',
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee_id' => $agent->id,
            'creator_id' => $agent->id,
        ]);

        $request = new Request(['action' => 'get_item', 'listItemId' => $item->id]);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Title: Refactor auth', $result);
        $this->assertStringContainsString('Clean up the auth module', $result);
        $this->assertStringContainsString('Status: in_progress', $result);
        $this->assertStringContainsString('Priority: high', $result);
    }

    public function test_lists_by_status(): void
    {
        [$tool, $agent] = $this->makeTool();

        $this->createListItem(['title' => 'Backlog Item', 'status' => 'backlog', 'assignee_id' => $agent->id]);
        $this->createListItem(['title' => 'Done Item', 'status' => 'done', 'assignee_id' => $agent->id]);

        $request = new Request(['action' => 'list_by_status', 'status' => 'backlog']);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Backlog Item', $result);
        $this->assertStringNotContainsString('Done Item', $result);
        $this->assertStringContainsString("status 'backlog'", $result);
    }

    public function test_lists_by_assignee(): void
    {
        [$tool] = $this->makeTool();

        $assignee = User::factory()->create(['name' => 'Bob Builder']);
        $otherUser = User::factory()->create();

        $this->createListItem(['title' => 'Bob Task', 'assignee_id' => $assignee->id]);
        $this->createListItem(['title' => 'Other Task', 'assignee_id' => $otherUser->id]);

        $request = new Request(['action' => 'list_by_assignee', 'assigneeId' => $assignee->id]);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Bob Task', $result);
        $this->assertStringNotContainsString('Other Task', $result);
        $this->assertStringContainsString('Bob Builder', $result);
    }

    public function test_has_correct_description(): void
    {
        [$tool] = $this->makeTool();

        $this->assertStringContainsString('Query list items', $tool->description());
    }
}
