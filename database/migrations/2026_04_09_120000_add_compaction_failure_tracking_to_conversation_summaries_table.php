<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Failure counters and circuit timing keep repeated compaction errors
        // from hammering the model provider on every prompt.
        Schema::table('conversation_summaries', function (Blueprint $table) {
            $table->integer('compaction_failure_count')->default(0)->after('flush_count');
            $table->timestamp('last_compaction_failed_at')->nullable()->after('compaction_failure_count');
            $table->timestamp('compaction_circuit_open_until')->nullable()->after('last_compaction_failed_at');
            $table->text('last_compaction_error')->nullable()->after('compaction_circuit_open_until');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'compaction_failure_count',
                'last_compaction_failed_at',
                'compaction_circuit_open_until',
                'last_compaction_error',
            ]);
        });
    }
};
