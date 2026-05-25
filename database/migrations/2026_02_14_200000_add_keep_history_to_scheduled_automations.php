<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // keep_history controls whether scheduled automation runs leave durable
        // chat/task history behind.
        Schema::table('scheduled_automations', function (Blueprint $table) {
            $table->boolean('keep_history')->default(true)->after('channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_automations', function (Blueprint $table) {
            $table->dropColumn('keep_history');
        });
    }
};
