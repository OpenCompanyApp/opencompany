<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Lists\ManageListItem;
use App\Models\ListItem;
use App\Models\ListItemComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageListItemTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new ManageListItem($agent);

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

    public function test_creates_list_item(): void
    {
        [$tool, $agent] = $this->makeTool();

        $request = new Request([
            'action' => 'create',
            'title' => 'New Task',
            'description' => 'A brand new task',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('List item created', $result);
        $this->assertStringContainsString('New Task', $result);

        $item = ListItem::where('title', 'New Task')->first();
        $this->assertNotNull($item);
        $this->assertEquals('A brand new task', $item->description);
        $this->assertEquals($agent->id, $item->creator_id);
        $this->assertEquals('backlog', $item->status);
        $this->assertEquals('medium', $item->priority);
    }

    public function test_updates_list_item(): void
    {
        [$tool, $agent] = $this->makeTool();

        $item = $this->createListItem([
            'title' => 'Original Title',
            'assignee_id' => $agent->id,
        ]);

        $request = new Request([
            'action' => 'update',
            'listItemId' => $item->id,
            'title' => 'Updated Title',
            'status' => 'done',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('updated', $result);

        $item->refresh();
        $this->assertEquals('Updated Title', $item->title);
        $this->assertEquals('done', $item->status);
        $this->assertNotNull($item->completed_at);
    }

    public function test_deletes_list_item(): void
    {
        [$tool] = $this->makeTool();

        $item = $this->createListItem(['title' => 'To Be Removed']);
        $itemId = $item->id;

        $request = new Request([
            'action' => 'delete',
            'listItemId' => $itemId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('deleted', $result);
        $this->assertStringContainsString('To Be Removed', $result);
        $this->assertNull(ListItem::find($itemId));
    }

    public function test_adds_comment(): void
    {
        [$tool, $agent] = $this->makeTool();

        $item = $this->createListItem(['assignee_id' => $agent->id]);

        $request = new Request([
            'action' => 'add_comment',
            'listItemId' => $item->id,
            'commentContent' => 'This looks good!',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Comment added', $result);

        $comment = ListItemComment::where('list_item_id', $item->id)->first();
        $this->assertNotNull($comment);
        $this->assertEquals('This looks good!', $comment->content);
        $this->assertEquals($agent->id, $comment->author_id);
    }

    public function test_deletes_comment(): void
    {
        [$tool, $agent] = $this->makeTool();

        $item = $this->createListItem(['assignee_id' => $agent->id]);

        $comment = ListItemComment::create([
            'id' => Str::uuid()->toString(),
            'list_item_id' => $item->id,
            'author_id' => $agent->id,
            'content' => 'Temporary note',
        ]);

        $commentId = $comment->id;

        $request = new Request([
            'action' => 'delete_comment',
            'commentId' => $commentId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Comment deleted', $result);
        $this->assertNull(ListItemComment::find($commentId));
    }

    public function test_has_correct_description(): void
    {
        [$tool] = $this->makeTool();

        $this->assertStringContainsString('Create, update, or delete list items', $tool->description());
    }
}
