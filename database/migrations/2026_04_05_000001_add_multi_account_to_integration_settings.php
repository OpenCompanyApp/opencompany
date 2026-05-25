<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Integration settings: multi-account support
        Schema::table('integration_settings', function (Blueprint $table) {
            $table->string('account_alias', 32)->default('')->after('integration_id');
            $table->boolean('is_default')->default(true)->after('enabled');

            $table->dropUnique(['workspace_id', 'integration_id']);
            $table->unique(['workspace_id', 'integration_id', 'account_alias']);
        });

        // MCP servers: multi-account support
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->string('account_alias', 32)->default('')->after('slug');

            $table->dropUnique(['workspace_id', 'slug']);
            $table->unique(['workspace_id', 'slug', 'account_alias']);
        });
    }

    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'slug', 'account_alias']);
            $table->unique(['workspace_id', 'slug']);

            $table->dropColumn('account_alias');
        });

        Schema::table('integration_settings', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'integration_id', 'account_alias']);
            $table->unique(['workspace_id', 'integration_id']);

            $table->dropColumn(['account_alias', 'is_default']);
        });
    }
};
