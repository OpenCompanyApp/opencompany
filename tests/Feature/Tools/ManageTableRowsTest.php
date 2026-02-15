<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Tables\ManageTableRows;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageTableRowsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): ManageTableRows
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);

        return new ManageTableRows($agent);
    }

    public function test_adds_row(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Contacts',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = new ManageTableRows($agent);
        $request = new Request([
            'action' => 'add_row',
            'tableId' => $table->id,
            'data' => '{"name":"Alice","email":"alice@example.com"}',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Row added', $result);

        $row = DataTableRow::where('table_id', $table->id)->first();
        $this->assertNotNull($row);

        $columns = $table->columns()->get()->keyBy(fn ($c) => strtolower($c->name));
        $this->assertEquals('Alice', $row->data[$columns->get('name')->id]);
        $this->assertEquals('alice@example.com', $row->data[$columns->get('email')->id]);
    }

    public function test_updates_row(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Contacts',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        // Create row through the tool so data keys are column UUIDs
        $tool = new ManageTableRows($agent);
        $addRequest = new Request([
            'action' => 'add_row',
            'tableId' => $table->id,
            'data' => '{"name":"Alice","email":"alice@example.com"}',
        ]);
        $tool->handle($addRequest);

        $row = DataTableRow::where('table_id', $table->id)->first();

        $updateRequest = new Request([
            'action' => 'update_row',
            'tableId' => $table->id,
            'rowId' => $row->id,
            'data' => '{"email":"alice@newdomain.com","phone":"555-1234"}',
        ]);

        $result = $tool->handle($updateRequest);

        $this->assertStringContainsString('Row updated', $result);

        $row->refresh();
        $columns = $table->columns()->get()->keyBy(fn ($c) => strtolower($c->name));
        $this->assertEquals('Alice', $row->data[$columns->get('name')->id]);
        $this->assertEquals('alice@newdomain.com', $row->data[$columns->get('email')->id]);
        $this->assertEquals('555-1234', $row->data[$columns->get('phone')->id]);
    }

    public function test_deletes_row(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Contacts',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $row = DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['name' => 'Alice'],
            'created_by' => $agent->id,
        ]);

        $tool = new ManageTableRows($agent);
        $request = new Request([
            'action' => 'delete_row',
            'tableId' => $table->id,
            'rowId' => $row->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Row deleted', $result);
        $this->assertDatabaseMissing('data_table_rows', [
            'id' => $row->id,
        ]);
    }

    public function test_bulk_adds_rows(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Employees',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = new ManageTableRows($agent);
        $rows = json_encode([
            ['name' => 'Alice', 'role' => 'Engineer'],
            ['name' => 'Bob', 'role' => 'Designer'],
            ['name' => 'Carol', 'role' => 'Manager'],
        ]);

        $request = new Request([
            'action' => 'bulk_add',
            'tableId' => $table->id,
            'rows' => $rows,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Added 3 rows', $result);
        $this->assertEquals(3, DataTableRow::where('table_id', $table->id)->count());
    }

    public function test_has_correct_description(): void
    {
        $tool = $this->makeTool();

        $this->assertStringContainsString('Add, update, or delete rows', $tool->description());
    }
}
