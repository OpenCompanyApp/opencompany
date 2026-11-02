<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every run (and the task it executes) carries the causality chain of the
 * event or delegation that started it (docs/planning/connections-events-
 * routines.md, "Loop protection").
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'agent_runs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('causality_id')->nullable();
                $blueprint->unsignedSmallInteger('causality_depth')->default(0);
                $blueprint->index('causality_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'agent_runs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_causality_id_index');
                $blueprint->dropColumn(['causality_id', 'causality_depth']);
            });
        }
    }
};
