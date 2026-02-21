<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('scheduled_automations', 'automations');

        Schema::table('automations', function (Blueprint $table) {
            $table->string('trigger_type')->default('schedule')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn('trigger_type');
        });

        Schema::rename('automations', 'scheduled_automations');
    }
};
