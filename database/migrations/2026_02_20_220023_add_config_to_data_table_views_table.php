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
        Schema::table('data_table_views', function (Blueprint $table) {
            $table->json('config')->nullable()->after('hidden_columns');
        });
    }

    public function down(): void
    {
        Schema::table('data_table_views', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};
