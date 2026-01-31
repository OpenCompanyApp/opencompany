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
        Schema::table('data_tables', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->string('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('data_table_rows', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->string('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->string('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting would require setting non-null values first
    }
};
