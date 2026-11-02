<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Preserve original source before disabling every legacy script schedule.
     * Language is never inferred from text: Lua and JavaScript can contain
     * fragments that also compile as Ruby but have different semantics.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('automations', 'script_runtime')) {
            Schema::table('automations', fn (Blueprint $table) => $table->string('script_runtime')->nullable());
        }
        Schema::table('automations', function (Blueprint $table): void {
            $table->string('script_digest', 64)->nullable();
            $table->string('script_engine_digest', 64)->nullable();
            $table->timestamp('script_validated_at')->nullable();
        });
        Schema::create('automation_script_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->uuid('automation_id')->index();
            $table->string('runtime')->nullable();
            $table->longText('source');
            $table->string('source_digest', 64);
            $table->string('engine_digest', 64)->nullable();
            $table->string('status');
            $table->timestamp('created_at');
            $table->unique(['automation_id', 'source_digest', 'engine_digest', 'status'], 'script_revision_identity');
        });

        DB::table('automations')->where('execution_type', 'script')->orderBy('id')
            ->chunkById(100, function ($automations): void {
                foreach ($automations as $automation) {
                    DB::transaction(function () use ($automation): void {
                        DB::table('automation_script_revisions')->insert([
                            'id' => (string) Str::uuid(), 'workspace_id' => $automation->workspace_id,
                            'automation_id' => $automation->id, 'runtime' => $automation->script_runtime,
                            'source' => (string) $automation->script,
                            'source_digest' => hash('sha256', (string) $automation->script),
                            'status' => 'legacy_disabled', 'created_at' => now(),
                        ]);
                        // Leave script and previous result untouched. The immutable
                        // revision survives later editing of the automation source.
                        DB::table('automations')->where('id', $automation->id)->update([
                            'is_active' => false, 'script_runtime' => null,
                            'script_digest' => null, 'script_engine_digest' => null,
                            'script_validated_at' => null,
                        ]);
                    });
                }
            }, 'id');
    }

    /** Runtime retirement cannot safely restore old executors or erase source history. */
    public function down(): void
    {
        throw new RuntimeException('Ruby runtime migration is forward-only. Use a reviewed application/data recovery procedure.');
    }
};
