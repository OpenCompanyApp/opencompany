<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // External message IDs let chat bridges correlate provider messages
        // with local records for replies, edits, and deduplication.
        Schema::table('messages', function (Blueprint $table) {
            $table->string('external_message_id')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('external_message_id');
        });
    }
};
