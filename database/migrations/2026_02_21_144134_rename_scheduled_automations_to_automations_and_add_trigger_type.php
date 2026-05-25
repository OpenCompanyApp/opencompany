<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename to the broader automations concept before adding non-schedule
        // triggers such as event-driven or manual runs.
        Schema::rename('scheduled_automations', 'automations');

        Schema::table('automations', function (Blueprint $table) {
            $table->string('trigger_type')->default('schedule')->after('name');
        });
    }

    public function down(): void
    {
        // Drop trigger_type before renaming back so older code sees the original
        // scheduled_automations shape.
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('trigger_type');
        });

        Schema::rename('automations', 'scheduled_automations');
    }
};
