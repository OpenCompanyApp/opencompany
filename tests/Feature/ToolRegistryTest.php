<?php

namespace Tests\Feature;

use App\Agents\Tools\Memory\RecallMemory;
use App\Agents\Tools\Memory\SaveMemory;
use App\Agents\Tools\System\ApprovalWrappedTool;
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

    /**
     * Map inner tool class => whether the registry wrapped it for approval.
     *
     * @return array<class-string, bool>
     */
    private function wrapState(): array
    {
        $state = [];
        foreach ($this->registry->getToolsForAgent($this->agent) as $tool) {
            $wrapped = $tool instanceof ApprovalWrappedTool;
            $state[get_class($wrapped ? $tool->getInnerTool() : $tool)] = $wrapped;
        }

        return $state;
    }

    // Policy model default (behaviour modes deleted): with no rules every
    // tool is exposed, writes are wrapped for approval, reads are not.
    public function test_default_policy_wraps_write_tools_but_not_read_tools(): void
    {
        $tools = $this->registry->getToolsForAgent($this->agent);
        $this->assertCount($this->baseToolCount, $tools);

        $state = $this->wrapState();
        $this->assertTrue($state[SaveMemory::class], 'save_memory (write) should be wrapped by default');
        $this->assertFalse($state[RecallMemory::class], 'recall_memory (read) should run without approval');
    }

    public function test_excludes_denied_tools(): void
    {
        // Use a direct-group tool (save_memory is in the 'memory' group)
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'save_memory',
            'permission' => 'deny',
        ]);

        $tools = $this->registry->getToolsForAgent($this->agent);

        $this->assertCount($this->baseToolCount - 1, $tools);
    }

    // An admin rule requiring approval wraps even a read tool.
    public function test_wraps_tools_with_db_approval_required(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'recall_memory',
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $this->assertCount($this->baseToolCount, $this->registry->getToolsForAgent($this->agent));
        $this->assertTrue($this->wrapState()[RecallMemory::class]);
    }

    // An admin allow-without-approval rule unwraps exactly that write tool.
    public function test_db_allow_rule_unwraps_write_tool(): void
    {
        $this->createPermission([
            'scope_type' => 'tool',
            'scope_key' => 'save_memory',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $state = $this->wrapState();
        $this->assertFalse($state[SaveMemory::class]);
        $this->assertTrue($state[\App\Agents\Tools\Memory\EditMemory::class], 'other writes stay gated');
    }

    // ── getAllToolsMeta Tests ───────────────────────────────────────────

    public function test_get_all_tools_meta_complete_structure(): void
    {
        $meta = $this->registry->getAllToolsMeta($this->agent);

        // getAllToolsMeta returns ALL tools (not just direct), so it should be more than direct count
        $this->assertGreaterThan($this->baseToolCount, count($meta));

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
        $tool = $this->registry->instantiateToolBySlug('save_memory', $this->agent);

        $this->assertInstanceOf(SaveMemory::class, $tool);
    }

    public function test_instantiate_tool_by_slug_null_for_unknown(): void
    {
        $tool = $this->registry->instantiateToolBySlug('nonexistent', $this->agent);

        $this->assertNull($tool);
    }

    public function test_app_catalog_includes_ruby_docs_first_guidance(): void
    {
        $catalog = $this->registry->getAppCatalog($this->agent);

        $this->assertStringContainsString('Always call code_read_doc(namespace) before writing code', $catalog);
        $this->assertStringContainsString('Do not assume raw upstream API response shapes', $catalog);
        $this->assertStringContainsString('one minimal read-only code_exec call before multi-step logic', $catalog);
    }
}
