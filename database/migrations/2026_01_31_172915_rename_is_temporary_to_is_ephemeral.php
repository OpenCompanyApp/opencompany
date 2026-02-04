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
        // Rename column in users table
        if (Schema::hasColumn('users', 'is_temporary')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('is_temporary', 'is_ephemeral');
            });
        }

        // Rename column in channels table
        if (Schema::hasColumn('channels', 'is_temporary')) {
            Schema::table('channels', function (Blueprint $table) {
                $table->renameColumn('is_temporary', 'is_ephemeral');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back in users table
        if (Schema::hasColumn('users', 'is_ephemeral')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('is_ephemeral', 'is_temporary');
            });
        }

        // Rename back in channels table
        if (Schema::hasColumn('channels', 'is_ephemeral')) {
            Schema::table('channels', function (Blueprint $table) {
                $table->renameColumn('is_ephemeral', 'is_temporary');
            });
        }
    }
};
