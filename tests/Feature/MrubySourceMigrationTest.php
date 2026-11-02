<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('preserves exact legacy sources and disables only script schedules', function () {
    // A disposable pre-migration schema protects both installation paths: the
    // existing database may not have any runtime metadata at all.
    config(['database.connections.mruby_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::setDefaultConnection('mruby_fixture');
    Schema::clearResolvedInstance('db.schema');
    Schema::create('automations', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('workspace_id');
        $table->string('execution_type');
        $table->text('script')->nullable();
        $table->boolean('is_active');
        $table->text('last_result')->nullable();
    });
    DB::table('automations')->insert([
        ['id' => 'legacy-l', 'workspace_id' => 'workspace-a', 'execution_type' => 'script',
            'script' => 'local result = 42; return result', 'is_active' => true, 'last_result' => '{"previous":true}'],
        ['id' => 'ambiguous', 'workspace_id' => 'workspace-b', 'execution_type' => 'script',
            'script' => '42', 'is_active' => true, 'last_result' => null],
        ['id' => 'prompt', 'workspace_id' => 'workspace-a', 'execution_type' => 'prompt',
            'script' => null, 'is_active' => true, 'last_result' => null],
    ]);
    $migration = require database_path('migrations/2026_09_08_000001_pin_mruby_runtime_on_automations.php');
    $migration->up();
    expect(DB::table('automations')->where('execution_type', 'script')->where('is_active', true)->count())->toBe(0)
        ->and((bool) DB::table('automations')->where('id', 'prompt')->value('is_active'))->toBeTrue()
        ->and(DB::table('automation_script_revisions')->count())->toBe(2)
        ->and(DB::table('automations')->where('id', 'legacy-l')->value('last_result'))->toBe('{"previous":true}');
    $revision = DB::table('automation_script_revisions')->where('automation_id', 'legacy-l')->first();
    expect($revision->source)->toBe('local result = 42; return result')
        ->and($revision->workspace_id)->toBe('workspace-a')
        ->and($revision->source_digest)->toBe(hash('sha256', $revision->source))
        ->and($revision->status)->toBe('legacy_disabled');
    expect(DB::table('automations')->where('id', 'ambiguous')->value('script_runtime'))->toBeNull();
});
