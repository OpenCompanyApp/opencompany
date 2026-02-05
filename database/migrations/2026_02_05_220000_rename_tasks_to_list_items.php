<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the tasks system to lists system.
     * Tasks → ListItems (items in a kanban board/list)
     * This frees up "tasks" for the new cases-style work tracking.
     */
    public function up(): void
    {
        // Rename main tables
        Schema::rename('tasks', 'list_items');
        Schema::rename('task_collaborators', 'list_item_collaborators');
        Schema::rename('task_comments', 'list_item_comments');
        Schema::rename('task_templates', 'list_templates');
        Schema::rename('task_automation_rules', 'list_automation_rules');

        // Update foreign key column names in list_item_collaborators
        Schema::table('list_item_collaborators', function (Blueprint $table) {
            $table->renameColumn('task_id', 'list_item_id');
        });

        // Update foreign key column names in list_item_comments
        Schema::table('list_item_comments', function (Blueprint $table) {
            $table->renameColumn('task_id', 'list_item_id');
        });

        // Update foreign key column names in list_automation_rules
        Schema::table('list_automation_rules', function (Blueprint $table) {
            $table->renameColumn('template_id', 'list_template_id');
        });
    }

    public function down(): void
    {
        // Reverse column renames first
        Schema::table('list_automation_rules', function (Blueprint $table) {
            $table->renameColumn('list_template_id', 'template_id');
        });

        Schema::table('list_item_comments', function (Blueprint $table) {
            $table->renameColumn('list_item_id', 'task_id');
        });

        Schema::table('list_item_collaborators', function (Blueprint $table) {
            $table->renameColumn('list_item_id', 'task_id');
        });

        // Rename tables back
        Schema::rename('list_automation_rules', 'task_automation_rules');
        Schema::rename('list_templates', 'task_templates');
        Schema::rename('list_item_comments', 'task_comments');
        Schema::rename('list_item_collaborators', 'task_collaborators');
        Schema::rename('list_items', 'tasks');
    }
};
