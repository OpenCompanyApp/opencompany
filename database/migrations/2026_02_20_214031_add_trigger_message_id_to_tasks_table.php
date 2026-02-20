<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('trigger_message_id')->nullable()->after('channel_id');
            $table->foreign('trigger_message_id')->references('id')->on('messages')->nullOnDelete();
            $table->index(['trigger_message_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['trigger_message_id']);
            $table->dropIndex(['trigger_message_id', 'agent_id']);
            $table->dropColumn('trigger_message_id');
        });
    }
};
