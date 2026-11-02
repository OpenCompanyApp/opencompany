<?php

namespace App\Domain\Authorization\Policy;

/**
 * Classifies every tool into a governance action class
 * (docs/architecture/governance-model.md, "Action classes and default gates";
 * Product Direction pillar 5: gate by reversibility, not by "write").
 *
 * Owns the one reviewable table that maps built-in tools to classes. Rules:
 * - `config('governance.floor_tools')` adds floor entries (never removes);
 * - MCP tools (`mcp_{server}__{tool}`) are `external_send` — server hints
 *   (readOnlyHint/destructiveHint) and declarations are never trusted;
 * - an explicit app-owned `action_class` in tool metadata wins next;
 * - a built-in declared `read` is `read`;
 * - anything unlisted or unknown is `external_send` (fail closed).
 *
 * `code_exec` is classed `read` on purpose: it is a container, and every
 * capability callback it makes is classified and gated individually.
 */
final class ActionClassifier
{
    public const READ = 'read';
    public const REVERSIBLE_EDIT = 'reversible_edit';
    public const DELETE = 'delete';
    public const PURGE = 'purge';
    public const EXTERNAL_DRAFT = 'external_draft';
    public const EXTERNAL_SEND = 'external_send';
    public const SPEND = 'spend';
    public const EXPOSURE_WIDENING = 'exposure_widening';
    public const STRUCTURAL = 'structural';
    public const GOVERNANCE = 'governance';
    public const FLOOR = 'floor';

    /** Classes whose effects stay inside the workspace and can be reverted. */
    public const REVERSIBLE_CLASSES = [self::READ, self::REVERSIBLE_EDIT, self::DELETE, self::EXTERNAL_DRAFT];

    /** Classes that leave the workspace or widen it; trigger-started runs always ask. */
    public const EXTERNAL_CLASSES = [self::EXTERNAL_SEND, self::SPEND, self::EXPOSURE_WIDENING];

    /** @var array<string, string> Built-in write tools by class. Reads come from metadata. */
    private const TABLE = [
        // Reversible internal edits (recorded per run; revertible).
        'update_task' => self::REVERSIBLE_EDIT, 'add_task_step' => self::REVERSIBLE_EDIT,
        'update_task_step' => self::REVERSIBLE_EDIT, 'set_task_status' => self::REVERSIBLE_EDIT,
        'create_task_step' => self::REVERSIBLE_EDIT,
        'save_memory' => self::REVERSIBLE_EDIT, 'edit_memory' => self::REVERSIBLE_EDIT,
        'send_channel_message' => self::REVERSIBLE_EDIT, // external channels gate inside the tool
        'edit_message' => self::REVERSIBLE_EDIT, 'pin_message' => self::REVERSIBLE_EDIT,
        'add_message_reaction' => self::REVERSIBLE_EDIT, 'remove_message_reaction' => self::REVERSIBLE_EDIT,
        'create_document' => self::REVERSIBLE_EDIT, 'update_document' => self::REVERSIBLE_EDIT,
        'add_document_comment' => self::REVERSIBLE_EDIT, 'resolve_document_comment' => self::REVERSIBLE_EDIT,
        'restore_document_version' => self::REVERSIBLE_EDIT,
        'write_file' => self::REVERSIBLE_EDIT, 'create_folder' => self::REVERSIBLE_EDIT,
        'move_file' => self::REVERSIBLE_EDIT, 'copy_file' => self::REVERSIBLE_EDIT,
        'add_table_row' => self::REVERSIBLE_EDIT, 'update_table_row' => self::REVERSIBLE_EDIT,
        'bulk_add_table_rows' => self::REVERSIBLE_EDIT,
        'create_table_view' => self::REVERSIBLE_EDIT, 'update_table_view' => self::REVERSIBLE_EDIT,
        'create_calendar_event' => self::REVERSIBLE_EDIT, 'update_calendar_event' => self::REVERSIBLE_EDIT,
        'update_calendar_attendee' => self::REVERSIBLE_EDIT, 'remove_calendar_attendee' => self::REVERSIBLE_EDIT,
        'create_list_item' => self::REVERSIBLE_EDIT, 'update_list_item' => self::REVERSIBLE_EDIT,
        'add_list_item_comment' => self::REVERSIBLE_EDIT, 'convert_list_item_to_task' => self::REVERSIBLE_EDIT,
        'vfs_patch' => self::REVERSIBLE_EDIT, 'vfs_write' => self::REVERSIBLE_EDIT,
        'vfs_mkdir' => self::REVERSIBLE_EDIT, 'vfs_cp' => self::REVERSIBLE_EDIT, 'vfs_mv' => self::REVERSIBLE_EDIT,
        'render_svg' => self::REVERSIBLE_EDIT, 'run_routine' => self::REVERSIBLE_EDIT,
        'contact_agent' => self::REVERSIBLE_EDIT, // relationship gate: canContactAgent()
        'wait' => self::READ, 'wait_for_approval' => self::READ, // control flow
        'code_exec' => self::READ,
        'test_mcp_server' => self::READ, 'discover_mcp_tools' => self::READ,

        // Delete = move to trash (soft delete, restorable for 30 days). Only
        // models with SoftDeletes belong here.
        'delete_document' => self::DELETE,
        'delete_table_row' => self::DELETE, 'bulk_delete_table_rows' => self::DELETE,
        'delete_list_item' => self::DELETE,
        // VFS rm moves documents, files, rows and list items to their trash
        // (restorable; purge is never a VFS operation), so it is a delete.
        'vfs_rm' => self::DELETE,

        // Hard deletes of records that have no trash yet are purges. Move a
        // tool to DELETE only once its model soft-deletes.
        // The delete_file domain tool still hard-deletes workspace files
        // (FileSystemService::deleteFile); VFS rm trashes them instead.
        'delete_file' => self::PURGE,
        'delete_document_comment' => self::PURGE, 'delete_table_view' => self::PURGE,
        'delete_calendar_event' => self::PURGE, 'delete_list_item_comment' => self::PURGE,
        'delete_message' => self::PURGE, 'forget_memory' => self::PURGE, 'delete_routine' => self::PURGE,

        // Structural: schema and container changes are proposals.
        'create_table' => self::STRUCTURAL, 'update_table' => self::STRUCTURAL, 'delete_table' => self::STRUCTURAL,
        'add_table_column' => self::STRUCTURAL, 'update_table_column' => self::STRUCTURAL,
        'delete_table_column' => self::STRUCTURAL, 'reorder_table_columns' => self::STRUCTURAL,
        'create_list_status' => self::STRUCTURAL, 'update_list_status' => self::STRUCTURAL,
        'delete_list_status' => self::STRUCTURAL, 'create_item_template' => self::STRUCTURAL,
        'update_item_template' => self::STRUCTURAL, 'delete_item_template' => self::STRUCTURAL,
        'create_channel' => self::STRUCTURAL, 'create_routine' => self::STRUCTURAL,
        'update_routine' => self::STRUCTURAL,

        // Governance: agents, identity, grants, membership.
        'create_agent' => self::GOVERNANCE, 'update_agent' => self::GOVERNANCE, 'delete_agent' => self::GOVERNANCE,
        'update_agent_identity_file' => self::GOVERNANCE, 'update_agent_tool_permissions' => self::GOVERNANCE,
        'update_agent_channel_access' => self::GOVERNANCE, 'update_agent_folder_access' => self::GOVERNANCE,
        'update_agent_file_folder_access' => self::GOVERNANCE, 'update_agent_integration_access' => self::GOVERNANCE,
        'add_channel_member' => self::GOVERNANCE,
        'remove_channel_member' => self::GOVERNANCE,

        // Exposure widening: new outside reach for the workspace.
        'add_mcp_server' => self::EXPOSURE_WIDENING, 'update_mcp_server' => self::EXPOSURE_WIDENING,
        'remove_mcp_server' => self::GOVERNANCE, 'setup_integration_webhook' => self::EXPOSURE_WIDENING,
        'join_external_channel' => self::EXPOSURE_WIDENING,
        // Trusting an external identity as a workspace member removes
        // untrusted-content labels from everything it sends.
        'link_external_user' => self::EXPOSURE_WIDENING,
        // Acts on a third-party chat platform.
        'leave_external_channel' => self::EXTERNAL_SEND,
    ];

    /**
     * @param  array<string, mixed>|null  $meta  normalized tool metadata, if known
     */
    public function classify(string $toolSlug, ?array $meta = null): string
    {
        // Invariant: install-level floor additions win over any declaration.
        if (in_array($toolSlug, (array) config('governance.floor_tools', []), true)) {
            return self::FLOOR;
        }

        // Invariant: MCP classification runs before any declared class, so a
        // remote server can never self-describe its tools into `read`.
        if (preg_match('/^mcp_[a-z0-9_]+__.+$/', $toolSlug) === 1) {
            return self::EXTERNAL_SEND;
        }
        $declared = $meta['action_class'] ?? null;
        if (is_string($declared) && $declared !== '') {
            return $declared;
        }
        if (isset(self::TABLE[$toolSlug])) {
            return self::TABLE[$toolSlug];
        }
        if (($meta['type'] ?? null) === 'read') {
            return self::READ;
        }

        // Invariant: unclassified or unknown tools fail closed as external.
        return self::EXTERNAL_SEND;
    }

    public function isReversible(string $class): bool
    {
        return in_array($class, self::REVERSIBLE_CLASSES, true);
    }
}
