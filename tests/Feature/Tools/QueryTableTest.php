<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Tables\GetTable;
use App\Agents\Tools\Tables\GetTableRows;
use App\Agents\Tools\Tables\ListTables;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_all_tables(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        DataTable::create([
            'name' => 'Customers',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = new ListTables($agent);
        $request = new Request([]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Customers', $result);
        $this->assertStringContainsString('Data tables (1)', $result);
    }

    public function test_gets_table_with_columns(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Employees',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Full Name',
            'type' => 'text',
            'order' => 0,
            'required' => true,
        ]);

        DataTableColumn::create([
            'table_id' => $table->id,
            'name' => 'Email',
            'type' => 'email',
            'order' => 1,
            'required' => false,
        ]);

        $tool = new GetTable($agent);
        $request = new Request(['tableId' => $table->id]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Table: Employees', $result);
        $this->assertStringContainsString('Full Name [text]', $result);
        $this->assertStringContainsString('(required)', $result);
        $this->assertStringContainsString('Email [email]', $result);
        $this->assertStringContainsString('Columns (2)', $result);
    }

    public function test_gets_rows(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $table = DataTable::create([
            'name' => 'Contacts',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['name' => 'Alice', 'email' => 'alice@example.com'],
            'created_by' => $agent->id,
        ]);

        $tool = new GetTableRows($agent);
        $request = new Request(['tableId' => $table->id]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Contacts', $result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('alice@example.com', $result);
    }

    public function test_returns_empty_when_no_tables(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new ListTables($agent);
        $request = new Request([]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('No data tables found', $result);
    }

    public function test_has_correct_descriptions(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $listTool = new ListTables($agent);
        $getTool = new GetTable($agent);
        $getRowsTool = new GetTableRows($agent);

        $this->assertStringContainsString('List all data tables', $listTool->description());
        $this->assertStringContainsString('structure', $getTool->description());
        $this->assertStringContainsString('rows', $getRowsTool->description());
    }
}
