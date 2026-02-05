<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\QueryTable;
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

    private function makeTool(?User $agent = null): QueryTable
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);

        return new QueryTable($agent);
    }

    public function test_lists_all_tables(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        DataTable::create([
            'name' => 'Customers',
            'created_by' => $agent->id,
        ]);

        $tool = new QueryTable($agent);
        $request = new Request(['action' => 'list_tables']);

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

        $tool = new QueryTable($agent);
        $request = new Request(['action' => 'get_table', 'tableId' => $table->id]);

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
        ]);

        DataTableRow::create([
            'table_id' => $table->id,
            'data' => ['name' => 'Alice', 'email' => 'alice@example.com'],
            'created_by' => $agent->id,
        ]);

        $tool = new QueryTable($agent);
        $request = new Request(['action' => 'get_rows', 'tableId' => $table->id]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Contacts', $result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('alice@example.com', $result);
    }

    public function test_returns_empty_when_no_tables(): void
    {
        $tool = $this->makeTool();
        $request = new Request(['action' => 'list_tables']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('No data tables found', $result);
    }

    public function test_has_correct_description(): void
    {
        $tool = $this->makeTool();

        $this->assertStringContainsString('Query data tables', $tool->description());
    }
}
