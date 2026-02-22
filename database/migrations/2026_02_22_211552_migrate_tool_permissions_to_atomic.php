<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Map old composite tool slugs to their new atomic tool slugs.
     */
    private const SLUG_MAP = [
        // Chat
        'read_channel' => ['read_recent_messages', 'read_thread', 'read_pinned_messages'],
        'manage_message' => ['edit_message', 'delete_message', 'pin_message', 'add_message_reaction', 'remove_message_reaction'],
        'discover_external_channels' => ['list_external_channels', 'join_external_channel', 'leave_external_channel'],

        // Docs
        'query_documents' => ['list_documents', 'get_document', 'get_document_tree'],
        'manage_document' => ['create_document', 'update_document', 'delete_document'],
        'comment_on_document' => ['add_document_comment', 'resolve_document_comment', 'delete_document_comment'],

        // Tables
        'query_table' => ['list_tables', 'get_table', 'get_table_rows', 'search_table_rows'],
        'manage_table' => ['create_table', 'update_table', 'delete_table', 'add_table_column', 'update_table_column', 'delete_table_column'],
        'manage_table_rows' => ['add_table_row', 'update_table_row', 'delete_table_row', 'bulk_add_table_rows'],

        // Calendar
        'query_calendar' => ['list_calendar_events', 'get_calendar_event'],
        'manage_calendar_event' => ['create_calendar_event', 'update_calendar_event', 'delete_calendar_event'],

        // Lists
        'query_list_items' => ['list_all_items', 'get_list_item', 'list_items_by_status', 'list_items_by_assignee', 'list_projects', 'list_item_statuses'],
        'manage_list_item' => ['create_list_item', 'update_list_item', 'delete_list_item', 'add_list_item_comment', 'delete_list_item_comment'],
        'manage_list_status' => ['create_list_status', 'update_list_status', 'delete_list_status'],

        // Tasks
        'update_current_task' => ['update_task', 'add_task_step', 'update_task_step', 'set_task_status'],

        // Workspace
        'query_workspace' => ['list_agents', 'list_members', 'get_agent_details', 'get_agent_permissions', 'list_integrations', 'get_integration_config', 'list_available_models', 'list_automation_rules'],
        'manage_agent' => ['create_agent', 'update_agent', 'delete_agent', 'read_agent_identity_file', 'update_agent_identity_file'],
        'manage_agent_permissions' => ['update_agent_tool_permissions', 'update_agent_channel_access', 'update_agent_folder_access', 'update_agent_integration_access'],
        'manage_integration' => ['get_integration_setup', 'update_integration_config', 'test_integration_connection', 'setup_integration_webhook', 'link_external_user'],
        'manage_mcp_server' => ['list_mcp_servers', 'add_mcp_server', 'update_mcp_server', 'remove_mcp_server', 'test_mcp_server', 'discover_mcp_tools'],
        'manage_channel' => ['create_channel', 'add_channel_member', 'remove_channel_member'],
        'manage_automation' => ['create_automation_rule', 'update_automation_rule', 'delete_automation_rule', 'create_item_template', 'update_item_template', 'delete_item_template', 'create_schedule', 'update_schedule', 'delete_schedule', 'list_schedules', 'enable_schedule', 'disable_schedule', 'trigger_schedule'],
    ];

    public function up(): void
    {
        foreach (self::SLUG_MAP as $oldSlug => $newSlugs) {
            $permissions = DB::table('agent_permissions')
                ->where('scope_type', 'tool')
                ->where('scope_key', $oldSlug)
                ->get();

            foreach ($permissions as $perm) {
                foreach ($newSlugs as $newSlug) {
                    DB::table('agent_permissions')->insertOrIgnore([
                        'id' => Str::uuid()->toString(),
                        'agent_id' => $perm->agent_id,
                        'scope_type' => 'tool',
                        'scope_key' => $newSlug,
                        'permission' => $perm->permission,
                        'requires_approval' => $perm->requires_approval,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Delete old permission rows
            DB::table('agent_permissions')
                ->where('scope_type', 'tool')
                ->where('scope_key', $oldSlug)
                ->delete();
        }

        // Remap pending approval requests
        foreach (self::SLUG_MAP as $oldSlug => $newSlugs) {
            DB::table('approval_requests')
                ->where('status', 'pending')
                ->whereJsonContains('tool_execution_context->tool_slug', $oldSlug)
                ->update(['status' => 'denied']);
        }
    }

    public function down(): void
    {
        // Reverse: collapse atomic permissions back to composite ones
        foreach (self::SLUG_MAP as $oldSlug => $newSlugs) {
            // For each agent that has ANY of the new slugs, create one old slug permission
            $agentIds = DB::table('agent_permissions')
                ->where('scope_type', 'tool')
                ->whereIn('scope_key', $newSlugs)
                ->distinct()
                ->pluck('agent_id');

            foreach ($agentIds as $agentId) {
                // Take the most restrictive permission from any of the new slugs
                $perm = DB::table('agent_permissions')
                    ->where('scope_type', 'tool')
                    ->where('agent_id', $agentId)
                    ->whereIn('scope_key', $newSlugs)
                    ->first();

                if ($perm) {
                    DB::table('agent_permissions')->insertOrIgnore([
                        'id' => Str::uuid()->toString(),
                        'agent_id' => $agentId,
                        'scope_type' => 'tool',
                        'scope_key' => $oldSlug,
                        'permission' => $perm->permission,
                        'requires_approval' => $perm->requires_approval,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Delete atomic permission rows
            DB::table('agent_permissions')
                ->where('scope_type', 'tool')
                ->whereIn('scope_key', $newSlugs)
                ->delete();
        }
    }
};
