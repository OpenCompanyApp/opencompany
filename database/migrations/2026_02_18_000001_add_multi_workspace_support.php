<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Create new tables ───────────────────────────────────

        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('owner_id')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('user_id');
            $table->string('role')->default('member'); // 'admin' or 'member'
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('email');
            $table->string('role')->default('member');
            $table->string('token', 64)->unique();
            $table->string('inviter_id');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('inviter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['workspace_id', 'email']);
        });

        // ─── 2. Create the default workspace ────────────────────────

        $workspaceId = Str::uuid()->toString();
        $now = now();

        DB::table('workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Default',
            'slug' => 'default',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ─── 3. Add workspace_id to users (nullable for agents) ─────

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable()->after('type');
        });

        // Set workspace_id for all agent users
        DB::table('users')
            ->where('type', 'agent')
            ->update(['workspace_id' => $workspaceId]);

        // Add FK after backfill
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            $table->index('workspace_id');
        });

        // ─── 4. Add all human users as admin of default workspace ───

        $firstHumanId = null;
        $humans = DB::table('users')->where('type', 'human')->get();

        foreach ($humans as $human) {
            if ($firstHumanId === null) {
                $firstHumanId = $human->id;
            }

            DB::table('workspace_members')->insert([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $workspaceId,
                'user_id' => $human->id,
                'role' => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Set workspace owner
        if ($firstHumanId) {
            DB::table('workspaces')
                ->where('id', $workspaceId)
                ->update(['owner_id' => $firstHumanId]);
        }

        // ─── 5. Add workspace_id to all Tier 1 tables ──────────────

        $tables = [
            'channels',
            'tasks',
            'documents',
            'list_items',
            'integration_settings',
            'app_settings',
            'activities',
            'notifications',
            'calendar_events',
            'data_tables',
            'scheduled_automations',
            'mcp_servers',
            'prism_api_keys',
            'list_statuses',
            'list_templates',
            'document_chunks',
            'conversation_summaries',
            'embedding_cache',
            'calendar_feeds',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'workspace_id')) {
                // Add nullable column
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('workspace_id')->nullable();
                });

                // Backfill with default workspace
                DB::table($tableName)->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);

                // Make NOT NULL, add FK + index
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->uuid('workspace_id')->nullable(false)->change();
                    $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
                    $table->index('workspace_id');
                });
            }
        }

        // ─── 6. Update unique constraints for workspace scoping ─────

        // integration_settings: (integration_id) → (workspace_id, integration_id)
        if (Schema::hasTable('integration_settings')) {
            Schema::table('integration_settings', function (Blueprint $table) {
                $table->dropUnique(['integration_id']);
                $table->unique(['workspace_id', 'integration_id']);
            });
        }

        // app_settings: (key) → (workspace_id, key)
        if (Schema::hasTable('app_settings')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->dropUnique(['key']);
                $table->unique(['workspace_id', 'key']);
            });
        }

        // list_statuses: (slug) → (workspace_id, slug)
        if (Schema::hasTable('list_statuses')) {
            Schema::table('list_statuses', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->unique(['workspace_id', 'slug']);
            });
        }
    }

    public function down(): void
    {
        // Restore unique constraints
        if (Schema::hasTable('list_statuses') && Schema::hasColumn('list_statuses', 'workspace_id')) {
            Schema::table('list_statuses', function (Blueprint $table) {
                $table->dropUnique(['workspace_id', 'slug']);
                $table->unique('slug');
            });
        }

        if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'workspace_id')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->dropUnique(['workspace_id', 'key']);
                $table->unique('key');
            });
        }

        if (Schema::hasTable('integration_settings') && Schema::hasColumn('integration_settings', 'workspace_id')) {
            Schema::table('integration_settings', function (Blueprint $table) {
                $table->dropUnique(['workspace_id', 'integration_id']);
                $table->unique('integration_id');
            });
        }

        // Remove workspace_id from all Tier 1 tables
        $tables = [
            'channels', 'tasks', 'documents', 'list_items',
            'integration_settings', 'app_settings', 'activities',
            'notifications', 'calendar_events', 'data_tables',
            'scheduled_automations', 'mcp_servers', 'prism_api_keys',
            'list_statuses', 'list_templates', 'document_chunks',
            'conversation_summaries', 'embedding_cache', 'calendar_feeds',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['workspace_id']);
                    $table->dropIndex(['workspace_id']);
                    $table->dropColumn('workspace_id');
                });
            }
        }

        // Remove workspace_id from users
        if (Schema::hasColumn('users', 'workspace_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['workspace_id']);
                $table->dropIndex(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }

        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
