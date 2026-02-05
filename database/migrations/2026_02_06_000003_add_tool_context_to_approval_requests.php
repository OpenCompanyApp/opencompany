<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->jsonb('tool_execution_context')->nullable()->after('responded_at');
            $table->string('channel_id')->nullable()->after('tool_execution_context');

            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
            $table->dropColumn(['tool_execution_context', 'channel_id']);
        });
    }
};
