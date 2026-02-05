<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('awaiting_approval_id')->nullable()->after('behavior_mode');
            $table->boolean('must_wait_for_approval')->default(false)->after('awaiting_approval_id');

            $table->foreign('awaiting_approval_id')
                ->references('id')
                ->on('approval_requests')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['awaiting_approval_id']);
            $table->dropColumn(['awaiting_approval_id', 'must_wait_for_approval']);
        });
    }
};
