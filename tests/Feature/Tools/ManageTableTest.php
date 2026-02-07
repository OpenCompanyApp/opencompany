<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Tables\ManageTable;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageTableTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): ManageTable
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);

        return new ManageTable($agent);
    }

    public function test_creates_table(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new ManageTable($agent);

        $request = new Request(['action' => 'create_table', 'name' => 'Projects']);

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
        ]);

        $tool = new ManageTable($agent);
        $request = new Request([
            'action' => 'update_table',
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
        ]);

        $tool = new ManageTable($agent);
        $request = new Request([
            'action' => 'delete_table',
            'tableId' => $table->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString("Table 'To Delete' deleted", $result);
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
        ]);

        $tool = new ManageTable($agent);
        $request = new Request([
            'action' => 'add_column',
            'tableId' => $table->id,
            'name' => 'Status',
            'columnType' => 'select',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString("Column 'Status' added", $result);
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
        ]);

        $column = DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Priority',
            'type' => 'text',
            'order' => 0,
            'required' => false,
        ]);

        $tool = new ManageTable($agent);
        $request = new Request([
            'action' => 'delete_column',
            'columnId' => $column->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Column deleted', $result);
        $this->assertDatabaseMissing('data_table_columns', [
            'id' => $column->id,
        ]);
    }

    public function test_has_correct_description(): void
    {
        $tool = $this->makeTool();

        $this->assertStringContainsString('Create, update, or delete data tables', $tool->description());
    }
}
