<?php

namespace Tests\Feature;

use App\Agents\Tools\System\ApprovalWrappedTool;
use App\Agents\Tools\System\GetToolInfo;
use App\Agents\Tools\Chat\ListChannels;
use App\Agents\Tools\Plausible\PlausibleListGoals;
use App\Agents\Tools\Plausible\PlausibleListSites;
use App\Agents\Tools\Plausible\PlausibleQueryStats;
use App\Agents\Tools\Plausible\PlausibleRealtimeVisitors;
use App\Agents\Tools\Calendar\QueryCalendar;
use App\Agents\Tools\Lists\QueryListItems;
use App\Agents\Tools\Tables\QueryTable;
use App\Agents\Tools\Chat\ReadChannel;
use App\Agents\Tools\Docs\SearchDocuments;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Agents\Tools\ToolRegistry;
use App\Agents\Tools\Workspace\QueryWorkspace;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(ToolRegistry::class);
        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
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

        $this->assertCount(37, $tools);

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

        $this->assertCount(36, $tools);
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

        $this->assertCount(37, $tools);

        $wrappedTools = array_filter($tools, fn ($t) => $t instanceof ApprovalWrappedTool);
        $this->assertCount(1, $wrappedTools);

        $wrapped = reset($wrappedTools);
        $this->assertInstanceOf(SendChannelMessage::class, $wrapped->getInnerTool());
    }

    public function test_wraps_write_tools_in_supervised_mode(): void
    {
        $this->agent->update(['behavior_mode' => 'supervised']);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount(37, $tools);

        $readToolClasses = [
            SearchDocuments::class,
            ReadChannel::class,
            ListChannels::class,
            QueryTable::class,
            QueryCalendar::class,
            QueryListItems::class,
            PlausibleQueryStats::class,
            PlausibleRealtimeVisitors::class,
            PlausibleListSites::class,
            PlausibleListGoals::class,
            GetToolInfo::class,
            QueryWorkspace::class,
        ];

        $unwrappedTools = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ApprovalWrappedTool) {
                // Write tools should be wrapped — inner tool should NOT be a read tool
                $inner = $tool->getInnerTool();
                foreach ($readToolClasses as $readClass) {
                    $this->assertNotInstanceOf($readClass, $inner);
                }
            } else {
                // Unwrapped tools should be one of the read tools
                $unwrappedTools[] = $tool;
            }
        }

        $this->assertCount(12, $unwrappedTools);

        foreach ($unwrappedTools as $tool) {
            $isReadTool = false;
            foreach ($readToolClasses as $readClass) {
                if ($tool instanceof $readClass) {
                    $isReadTool = true;
                    break;
                }
            }
            $this->assertTrue($isReadTool, get_class($tool) . ' should be a read tool');
        }
    }

    public function test_wraps_all_tools_in_strict_mode(): void
    {
        $this->agent->update(['behavior_mode' => 'strict']);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount(37, $tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(ApprovalWrappedTool::class, $tool);
        }
    }

    // ── getAllToolsMeta Tests ───────────────────────────────────────────

    public function test_get_all_tools_meta_complete_structure(): void
    {
        $meta = $this->registry->getAllToolsMeta($this->agent);

        $this->assertCount(37, $meta);

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
