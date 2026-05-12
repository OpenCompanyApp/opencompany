<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MCP server slugs only need to be unique within a workspace, allowing
        // different tenants to use the same friendly server name.
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['workspace_id', 'slug']);
        });
    }

    public function down(): void
    {
        // Restore the original global uniqueness constraint for rollback.
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'slug']);
            $table->unique('slug');
        });
    }
};
