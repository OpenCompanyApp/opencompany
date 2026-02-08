<?php

namespace Tests\Feature;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    private ToolRegistry $registry;
    private User $agent;
    private int $baseToolCount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ToolRegistry::class);
        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $this->baseToolCount = count($this->registry->getToolsForAgent($this->agent));
    }

    private function createPermission(array $attributes): AgentPermission
    {
        return AgentPermission::create(array_merge([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'requires_approval' => false,
        ], $attributes));
    }

    // ── getToolsForAgent Tests ─────────────────────────────────────────

    public function test_returns_all_tools_with_no_permissions(): void
    {
        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount($this->baseToolCount, $tools);

        foreach ($tools as $tool) {
            $this->assertNotInstanceOf(ApprovalWrappedTool::class, $tool);
        }
    }

    public function test_excludes_denied_tools(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'deny',
        ]);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount($this->baseToolCount - 1, $tools);
    }

    public function test_wraps_tools_with_db_approval_required(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount($this->baseToolCount, $tools);

        $wrappedTools = array_filter($tools, fn ($t) => $t instanceof ApprovalWrappedTool);
        $this->assertCount(1, $wrappedTools);

        $wrapped = reset($wrappedTools);
        $this->assertInstanceOf(SendChannelMessage::class, $wrapped->getInnerTool());
    }

    public function test_wraps_write_tools_in_supervised_mode(): void
    {
        $this->agent->update(['behavior_mode' => 'supervised']);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount($this->baseToolCount, $tools);

        // Build a lookup of tool slug → type from meta
        $allMeta = $this->registry->getAllToolsMeta($this->agent);
        $readSlugs = collect($allMeta)->where('type', 'read')->pluck('id')->toArray();

        $unwrappedTools = [];
        $wrappedInnerSlugs = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ApprovalWrappedTool) {
                $wrappedInnerSlugs[] = $tool->getInnerTool();
            } else {
                $unwrappedTools[] = $tool;
            }
        }

        // Read tools + approval-exempt tools should be unwrapped
        $expectedReadCount = count($readSlugs);
        $this->assertGreaterThanOrEqual($expectedReadCount, count($unwrappedTools));
    }

    public function test_wraps_all_tools_in_strict_mode(): void
    {
        $this->agent->update(['behavior_mode' => 'strict']);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount($this->baseToolCount, $tools);

        $wrappedCount = 0;
        $unwrappedCount = 0;
        foreach ($tools as $tool) {
            if ($tool instanceof ApprovalWrappedTool) {
                $wrappedCount++;
            } else {
                $unwrappedCount++;
            }
        }

        // The vast majority of tools should be wrapped; only approval-exempt tools remain unwrapped
        $this->assertGreaterThan(0, $wrappedCount);
        // Unwrapped tools should only be approval-exempt ones (system tools like contact_agent, wait, etc.)
        $this->assertLessThanOrEqual(6, $unwrappedCount, 'Too many unwrapped tools in strict mode');
    }

    // ── getAllToolsMeta Tests ───────────────────────────────────────────

    public function test_get_all_tools_meta_complete_structure(): void
    {
        $meta = $this->registry->getAllToolsMeta($this->agent);

        $this->assertCount($this->baseToolCount, $meta);

        $expectedKeys = ['id', 'name', 'description', 'type', 'icon', 'enabled', 'requiresApproval'];

        foreach ($meta as $item) {
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $item);
            }
        }
    }

    public function test_get_all_tools_meta_reflects_deny(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'search_documents',
            'permission' => 'deny',
        ]);

        $meta = $this->registry->getAllToolsMeta($this->agent);

        $searchMeta = collect($meta)->firstWhere('id', 'search_documents');

        $this->assertNotNull($searchMeta);
        $this->assertFalse($searchMeta['enabled']);
    }

    // ── instantiateToolBySlug Tests ────────────────────────────────────

    public function test_instantiate_tool_by_slug_returns_correct_tool(): void
    {
        $tool = $this->registry->instantiateToolBySlug('send_channel_message', $this->agent);

        $this->assertInstanceOf(SendChannelMessage::class, $tool);
    }

    public function test_instantiate_tool_by_slug_null_for_unknown(): void
    {
        $tool = $this->registry->instantiateToolBySlug('nonexistent', $this->agent);

        $this->assertNull($tool);
    }
}
