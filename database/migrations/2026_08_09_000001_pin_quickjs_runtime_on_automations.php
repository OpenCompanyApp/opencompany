<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pin new JavaScript automations while disabling legacy scripts whose
     * language cannot be inferred or safely translated automatically.
     */
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->string('script_runtime')->nullable()->after('script');
        });

        DB::table('automations')
            ->where('execution_type', 'script')
            ->orderBy('id')
            ->chunkById(100, function ($automations): void {
                foreach ($automations as $automation) {
                    $lastResult = json_decode((string) ($automation->last_result ?? ''), true);
                    if (! is_array($lastResult)) {
                        $lastResult = [];
                    }

                    // Preserve the previous result verbatim under the existing
                    // keys and append a durable operator-facing migration note.
                    $lastResult['_runtime_migration'] = [
                        'status' => 'disabled',
                        'reason' => 'Legacy script requires manual JavaScript rewrite and validation.',
                        'target_runtime' => 'quickjs-v1',
                    ];

                    DB::table('automations')
                        ->where('id', $automation->id)
                        ->update([
                            'is_active' => false,
                            'script_runtime' => null,
                            'last_result' => json_encode($lastResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('script_runtime');
        });
    }
};
