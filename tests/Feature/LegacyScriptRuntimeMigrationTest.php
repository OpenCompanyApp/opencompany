<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Protects the destructive-looking part of the hard cut: source and prior
 * results stay intact, while scripts of unknown language are made inert.
 */
class LegacyScriptRuntimeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutover_preserves_source_and_results_but_disables_legacy_scripts(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        Automation::create([
            'id' => 'legacy-cutover',
            'workspace_id' => $this->workspace->id,
            'name' => 'Legacy source',
            'execution_type' => 'script',
            'agent_id' => $agent->id,
            'script' => 'legacy source stays byte-for-byte',
            'script_runtime' => null,
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $creator->id,
            'is_active' => true,
            'keep_history' => true,
            'last_result' => ['existing' => ['nested' => true]],
        ]);

        // Recreate the pre-cutover table shape before invoking the migration
        // directly; RefreshDatabase has already applied it for normal tests.
        Schema::table('automations', fn ($table) => $table->dropColumn('script_runtime'));
        $migration = require database_path('migrations/2026_08_09_000001_pin_quickjs_runtime_on_automations.php');
        $migration->up();

        $automation = Automation::findOrFail('legacy-cutover');
        $this->assertSame('legacy source stays byte-for-byte', $automation->script);
        $this->assertFalse($automation->is_active);
        $this->assertNull($automation->script_runtime);
        $this->assertTrue($automation->last_result['existing']['nested']);
        $this->assertSame('disabled', $automation->last_result['_runtime_migration']['status']);
        $this->assertSame('quickjs-v1', $automation->last_result['_runtime_migration']['target_runtime']);
    }
}
