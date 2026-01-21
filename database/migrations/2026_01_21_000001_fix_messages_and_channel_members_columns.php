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
        // Add created_at and updated_at to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamps();
        });

        // Copy existing timestamp values to created_at
        DB::statement('UPDATE messages SET created_at = timestamp WHERE timestamp IS NOT NULL');

        // Add last_read_at to channel_members table
        Schema::table('channel_members', function (Blueprint $table) {
            $table->timestamp('last_read_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('channel_members', function (Blueprint $table) {
            $table->dropColumn('last_read_at');
        });
    }
};
