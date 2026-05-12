<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sleeping agents need both a wake time and human-readable reason for
        // status displays and scheduler resume decisions.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('sleeping_until')->nullable();
            $table->string('sleeping_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sleeping_until', 'sleeping_reason']);
        });
    }
};
