<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_summaries', function (Blueprint $table) {
            $table->integer('flush_count')->default(0)->after('compaction_count');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_summaries', function (Blueprint $table) {
            $table->dropColumn('flush_count');
        });
    }
};
