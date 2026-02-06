<?php

namespace Database\Seeders;

use App\Models\Capability;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CapabilitySeeder extends Seeder
{
    public function run(): void
    {
        $capabilities = [
            // Communication
            [
                'name' => 'send_channel_message',
                'display_name' => 'Send Channel Message',
                'description' => 'Send a message to a channel in the workspace.',
                'icon' => 'ph:chat-circle',
                'category' => 'communication',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'read_channel',
                'display_name' => 'Read Channel',
                'description' => 'Read recent messages, threads, or pinned messages from a channel.',
                'icon' => 'ph:chat-dots',
                'category' => 'communication',
                'kind' => 'read',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'manage_message',
                'display_name' => 'Manage Message',
                'description' => 'Delete, pin, or add/remove reactions on a message.',
                'icon' => 'ph:chat-circle-dots',
                'category' => 'communication',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => true,
            ],
            // Documents
            [
                'name' => 'search_documents',
                'display_name' => 'Search Documents',
                'description' => 'Search workspace documents by keyword.',
                'icon' => 'ph:magnifying-glass',
                'category' => 'data',
                'kind' => 'read',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'manage_document',
                'display_name' => 'Manage Document',
                'description' => 'Create, update, or delete a document or folder.',
                'icon' => 'ph:file-text',
                'category' => 'data',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => true,
            ],
            [
                'name' => 'comment_on_document',
                'display_name' => 'Comment on Document',
                'description' => 'Add, resolve, or delete comments on a document.',
                'icon' => 'ph:chat-teardrop-text',
                'category' => 'data',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            // Tables
            [
                'name' => 'query_table',
                'display_name' => 'Query Table',
                'description' => 'List tables, get schema, or search and filter rows.',
                'icon' => 'ph:table',
                'category' => 'data',
                'kind' => 'read',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'manage_table',
                'display_name' => 'Manage Table',
                'description' => 'Create, update, or delete tables and columns.',
                'icon' => 'ph:table',
                'category' => 'data',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => true,
            ],
            [
                'name' => 'manage_table_rows',
                'display_name' => 'Manage Table Rows',
                'description' => 'Add, update, or delete rows in a data table.',
                'icon' => 'ph:rows',
                'category' => 'data',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            // Calendar
            [
                'name' => 'query_calendar',
                'display_name' => 'Query Calendar',
                'description' => 'List events by date range or view event details.',
                'icon' => 'ph:calendar',
                'category' => 'calendar',
                'kind' => 'read',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'manage_calendar_event',
                'display_name' => 'Manage Calendar Event',
                'description' => 'Create, update, or delete calendar events with attendees.',
                'icon' => 'ph:calendar-plus',
                'category' => 'calendar',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => true,
            ],
            // Lists
            [
                'name' => 'query_list_items',
                'display_name' => 'Query List Items',
                'description' => 'Browse, filter, and search kanban board items.',
                'icon' => 'ph:kanban',
                'category' => 'lists',
                'kind' => 'read',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'manage_list_item',
                'display_name' => 'Manage List Item',
                'description' => 'Create, update, or delete list items and their comments.',
                'icon' => 'ph:list-plus',
                'category' => 'lists',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            // Tasks
            [
                'name' => 'create_task_step',
                'display_name' => 'Create Task Step',
                'description' => 'Log a progress step on a task you are working on.',
                'icon' => 'ph:list-checks',
                'category' => 'tasks',
                'kind' => 'write',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            // External
            [
                'name' => 'send_telegram_notification',
                'display_name' => 'Send Telegram Notification',
                'description' => 'Send a notification message to a Telegram chat.',
                'icon' => 'ph:telegram-logo',
                'category' => 'communication',
                'kind' => 'write',
                'default_enabled' => false,
                'default_requires_approval' => false,
            ],
            // Control flow
            [
                'name' => 'wait_for_approval',
                'display_name' => 'Wait For Approval',
                'description' => 'Pause execution until a pending approval is decided.',
                'icon' => 'ph:pause-circle',
                'category' => 'control',
                'kind' => 'execute',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
            [
                'name' => 'wait',
                'display_name' => 'Wait',
                'description' => 'Suspend execution for a specified number of minutes, then auto-resume.',
                'icon' => 'ph:timer',
                'category' => 'control',
                'kind' => 'execute',
                'default_enabled' => true,
                'default_requires_approval' => false,
            ],
        ];

        foreach ($capabilities as $i => $capability) {
            Capability::updateOrCreate(
                ['name' => $capability['name']],
                array_merge($capability, [
                    'id' => Str::uuid()->toString(),
                    'sort_order' => $i,
                ])
            );
        }
    }
}
