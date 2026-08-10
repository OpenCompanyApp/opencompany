<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Tables\AddTableColumn;
use App\Agents\Tools\Tables\AddTableRow;
use App\Agents\Tools\Tables\BulkAddTableRows;
use App\Agents\Tools\Tables\BulkDeleteTableRows;
use App\Agents\Tools\Tables\CreateTable;
use App\Agents\Tools\Tables\CreateTableView;
use App\Agents\Tools\Tables\DeleteTable;
use App\Agents\Tools\Tables\DeleteTableColumn;
use App\Agents\Tools\Tables\DeleteTableRow;
use App\Agents\Tools\Tables\DeleteTableView;
use App\Agents\Tools\Tables\GetTable;
use App\Agents\Tools\Tables\GetTableRows;
use App\Agents\Tools\Tables\ListTables;
use App\Agents\Tools\Tables\ListTableViews;
use App\Agents\Tools\Tables\ReorderTableColumns;
use App\Agents\Tools\Tables\SearchTableRows;
use App\Agents\Tools\Tables\UpdateTable;
use App\Agents\Tools\Tables\UpdateTableColumn;
use App\Agents\Tools\Tables\UpdateTableRow;
use App\Agents\Tools\Tables\UpdateTableView;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers structured-table tools.
 *
 * Tables expose schema, row, and saved-view operations from one provider so
 * permissions and Code Mode docs present table work as a single capability group.
 */
class TablesToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'tables';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list, get, search, create, update, delete, columns, rows, views',
            'description' => 'Structured data tables',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:table';
    }

    public function tools(): array
    {
        return [
            'list_tables' => [
                'class' => ListTables::class,
                'type' => 'read',
                'name' => 'List Tables',
                'description' => 'List all data tables in the workspace.',
                'icon' => 'ph:table',
            ],
            'get_table' => [
                'class' => GetTable::class,
                'type' => 'read',
                'name' => 'Get Table',
                'description' => 'Get a table structure and columns.',
                'icon' => 'ph:table',
            ],
            'get_table_rows' => [
                'class' => GetTableRows::class,
                'type' => 'read',
                'name' => 'Get Table Rows',
                'description' => 'Retrieve rows from a data table.',
                'icon' => 'ph:table',
            ],
            'search_table_rows' => [
                'class' => SearchTableRows::class,
                'type' => 'read',
                'name' => 'Search Table Rows',
                'description' => 'Search rows by matching a term against row data.',
                'icon' => 'ph:magnifying-glass',
            ],
            'create_table' => [
                'class' => CreateTable::class,
                'type' => 'write',
                'name' => 'Create Table',
                'description' => 'Create a new data table.',
                'icon' => 'ph:table',
            ],
            'update_table' => [
                'class' => UpdateTable::class,
                'type' => 'write',
                'name' => 'Update Table',
                'description' => 'Update a table name or description.',
                'icon' => 'ph:table',
            ],
            'delete_table' => [
                'class' => DeleteTable::class,
                'type' => 'write',
                'name' => 'Delete Table',
                'description' => 'Delete a table and all its data.',
                'icon' => 'ph:table',
            ],
            'add_table_column' => [
                'class' => AddTableColumn::class,
                'type' => 'write',
                'name' => 'Add Table Column',
                'description' => 'Add a column to a table.',
                'icon' => 'ph:table',
            ],
            'update_table_column' => [
                'class' => UpdateTableColumn::class,
                'type' => 'write',
                'name' => 'Update Table Column',
                'description' => 'Update a column name, type, or options.',
                'icon' => 'ph:table',
            ],
            'delete_table_column' => [
                'class' => DeleteTableColumn::class,
                'type' => 'write',
                'name' => 'Delete Table Column',
                'description' => 'Delete a column from a table.',
                'icon' => 'ph:table',
            ],
            'reorder_table_columns' => [
                'class' => ReorderTableColumns::class,
                'type' => 'write',
                'name' => 'Reorder Table Columns',
                'description' => 'Reorder columns in a table.',
                'icon' => 'ph:arrows-down-up',
            ],
            'add_table_row' => [
                'class' => AddTableRow::class,
                'type' => 'write',
                'name' => 'Add Table Row',
                'description' => 'Add a row to a data table.',
                'icon' => 'ph:rows',
            ],
            'update_table_row' => [
                'class' => UpdateTableRow::class,
                'type' => 'write',
                'name' => 'Update Table Row',
                'description' => 'Update an existing row.',
                'icon' => 'ph:rows',
            ],
            'delete_table_row' => [
                'class' => DeleteTableRow::class,
                'type' => 'write',
                'name' => 'Delete Table Row',
                'description' => 'Delete a row from a table.',
                'icon' => 'ph:rows',
            ],
            'bulk_add_table_rows' => [
                'class' => BulkAddTableRows::class,
                'type' => 'write',
                'name' => 'Bulk Add Table Rows',
                'description' => 'Add multiple rows at once.',
                'icon' => 'ph:rows',
            ],
            'bulk_delete_table_rows' => [
                'class' => BulkDeleteTableRows::class,
                'type' => 'write',
                'name' => 'Bulk Delete Table Rows',
                'description' => 'Delete multiple rows at once.',
                'icon' => 'ph:rows',
            ],
            'list_table_views' => [
                'class' => ListTableViews::class,
                'type' => 'read',
                'name' => 'List Table Views',
                'description' => 'List saved views for a table.',
                'icon' => 'ph:eye',
            ],
            'create_table_view' => [
                'class' => CreateTableView::class,
                'type' => 'write',
                'name' => 'Create Table View',
                'description' => 'Create a saved table view (grid, kanban, gallery, calendar).',
                'icon' => 'ph:eye',
            ],
            'update_table_view' => [
                'class' => UpdateTableView::class,
                'type' => 'write',
                'name' => 'Update Table View',
                'description' => 'Update a saved table view.',
                'icon' => 'ph:eye',
            ],
            'delete_table_view' => [
                'class' => DeleteTableView::class,
                'type' => 'write',
                'name' => 'Delete Table View',
                'description' => 'Delete a saved table view.',
                'icon' => 'ph:eye',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Table tools share the same constructor and each tool performs its own
        // table/view/row validation inside the current workspace.
        return new $class($agent);
    }
}
