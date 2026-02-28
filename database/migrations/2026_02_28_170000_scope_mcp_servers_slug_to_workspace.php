<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['workspace_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'slug']);
            $table->unique('slug');
        });
    }
};
