<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Tables\AddTableColumn;
use App\Agents\Tools\Tables\CreateTable;
use App\Agents\Tools\Tables\DeleteTable;
use App\Agents\Tools\Tables\DeleteTableColumn;
use App\Agents\Tools\Tables\DeleteTableView;
use App\Agents\Tools\Tables\UpdateTable;
use App\Agents\Tools\Tables\UpdateTableView;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableView;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Covers table schema mutations and saved-view workspace boundaries.
 */
class ManageTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_table(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new CreateTable($agent);

        $request = new Request(['name' => 'Projects']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Table created', $result);
        $this->assertStringContainsString('Projects', $result);
        $this->assertDatabaseHas('data_tables', [
            'name' => 'Projects',
            'created_by' => $agent->id,
        ]);
    }

    public function test_updates_table(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Old Name',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = new UpdateTable($agent);
        $request = new Request([
            'tableId' => $table->id,
            'name' => 'New Name',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Table updated', $result);
        $this->assertDatabaseHas('data_tables', [
            'id' => $table->id,
            'name' => 'New Name',
        ]);
    }

    public function test_deletes_table(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'To Delete',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = new DeleteTable($agent);
        $request = new Request([
            'tableId' => $table->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Table To Delete deleted', $result);
        $this->assertDatabaseMissing('data_tables', [
            'id' => $table->id,
        ]);
    }

    public function test_adds_column(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Tasks',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = new AddTableColumn($agent);
        $request = new Request([
            'tableId' => $table->id,
            'name' => 'Status',
            'columnType' => 'select',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Column Status added', $result);
        $this->assertDatabaseHas('data_table_columns', [
            'table_id' => $table->id,
            'name' => 'Status',
            'type' => 'select',
        ]);
    }

    public function test_deletes_column(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Tasks',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Priority',
            'type' => 'text',
            'order' => 0,
            'required' => false,
        ]);

        $tool = new DeleteTableColumn($agent);
        $request = new Request([
            'columnId' => $column->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Column deleted', $result);
        $this->assertDatabaseMissing('data_table_columns', [
            'id' => $column->id,
        ]);
    }

    public function test_table_view_updates_are_workspace_scoped(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $otherWorkspace = Workspace::create(['name' => 'Other Workspace', 'slug' => 'other']);

        $otherTable = DataTable::create([
            'name' => 'Other Table',
            'created_by' => $agent->id,
            'workspace_id' => $otherWorkspace->id,
        ]);
        $view = DataTableView::create([
            'table_id' => $otherTable->id,
            'name' => 'Other View',
            'type' => 'grid',
        ]);

        $result = (new UpdateTableView($agent))->handle(new Request([
            'viewId' => $view->id,
            'name' => 'Changed',
        ]));

        $this->assertStringContainsString('Error updating view', $result);
        $this->assertDatabaseHas('data_table_views', [
            'id' => $view->id,
            'name' => 'Other View',
        ]);
    }

    public function test_table_view_deletes_are_workspace_scoped(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $otherWorkspace = Workspace::create(['name' => 'Other Workspace', 'slug' => 'other']);

        $otherTable = DataTable::create([
            'name' => 'Other Table',
            'created_by' => $agent->id,
            'workspace_id' => $otherWorkspace->id,
        ]);
        $view = DataTableView::create([
            'table_id' => $otherTable->id,
            'name' => 'Other View',
            'type' => 'grid',
        ]);

        $result = (new DeleteTableView($agent))->handle(new Request([
            'viewId' => $view->id,
        ]));

        $this->assertStringContainsString('Error deleting view', $result);
        $this->assertDatabaseHas('data_table_views', [
            'id' => $view->id,
            'name' => 'Other View',
        ]);
    }

    public function test_has_correct_descriptions(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $createTool = new CreateTable($agent);
        $updateTool = new UpdateTable($agent);
        $deleteTool = new DeleteTable($agent);
        $addColumnTool = new AddTableColumn($agent);
        $deleteColumnTool = new DeleteTableColumn($agent);

        $this->assertStringContainsString('Create a new data table', $createTool->description());
        $this->assertStringContainsString('Update an existing data table', $updateTool->description());
        $this->assertStringContainsString('Delete a data table', $deleteTool->description());
        $this->assertStringContainsString('Add a new column', $addColumnTool->description());
        $this->assertStringContainsString('Delete a column', $deleteColumnTool->description());
    }
}
