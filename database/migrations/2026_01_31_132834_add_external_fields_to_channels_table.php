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
        Schema::table('channels', function (Blueprint $table) {
            $table->string('external_provider')->nullable()->after('type');
            $table->string('external_id')->nullable()->after('external_provider');
            $table->json('external_config')->nullable()->after('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['external_provider', 'external_id', 'external_config']);
        });
    }
};
