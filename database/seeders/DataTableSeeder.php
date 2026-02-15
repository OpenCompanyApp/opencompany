<?php

namespace Database\Seeders;

use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class DataTableSeeder extends Seeder
{
    use WithoutModelEvents;

    private string $workspaceId;

    public function run(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $this->workspaceId = $workspace->id;

        $this->createProductCatalog();
        $this->createSprintTracker();
        $this->createContactList();
    }

    private function createProductCatalog(): void
    {
        $table = DataTable::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Product Catalog',
            'description' => 'Company product inventory and pricing',
            'icon' => 'ph:package',
            'created_by' => 'h1',
            'workspace_id' => $this->workspaceId,
        ]);

        $colName = $this->createColumn($table->id, 'Name', 'text', 0, true);
        $colPrice = $this->createColumn($table->id, 'Price', 'number', 1, true);
        $colCategory = $this->createColumn($table->id, 'Category', 'select', 2, true, [
            'choices' => ['Electronics', 'Software', 'Services', 'Hardware', 'Accessories'],
        ]);
        $colInStock = $this->createColumn($table->id, 'In Stock', 'checkbox', 3, false);
        $colUrl = $this->createColumn($table->id, 'URL', 'url', 4, false);

        $rows = [
            [
                $colName->id => 'OpenCompany Platform License',
                $colPrice->id => 299,
                $colCategory->id => 'Software',
                $colInStock->id => true,
                $colUrl->id => 'https://opencompany.io/platform',
            ],
            [
                $colName->id => 'Enterprise Support Package',
                $colPrice->id => 1499,
                $colCategory->id => 'Services',
                $colInStock->id => true,
                $colUrl->id => 'https://opencompany.io/support',
            ],
            [
                $colName->id => 'Developer Workstation Kit',
                $colPrice->id => 849,
                $colCategory->id => 'Hardware',
                $colInStock->id => false,
                $colUrl->id => 'https://opencompany.io/hardware/workstation',
            ],
            [
                $colName->id => 'USB-C Docking Station',
                $colPrice->id => 189,
                $colCategory->id => 'Accessories',
                $colInStock->id => true,
                $colUrl->id => 'https://opencompany.io/accessories/dock',
            ],
            [
                $colName->id => 'Smart Monitor 27"',
                $colPrice->id => 549,
                $colCategory->id => 'Electronics',
                $colInStock->id => true,
                $colUrl->id => 'https://opencompany.io/electronics/monitor-27',
            ],
            [
                $colName->id => 'Cloud Storage Add-on',
                $colPrice->id => 49,
                $colCategory->id => 'Software',
                $colInStock->id => true,
                $colUrl->id => 'https://opencompany.io/addons/storage',
            ],
        ];

        foreach ($rows as $data) {
            DataTableRow::create([
                'id' => Str::uuid()->toString(),
                'table_id' => $table->id,
                'data' => $data,
                'created_by' => 'h1',
            ]);
        }
    }

    private function createSprintTracker(): void
    {
        $table = DataTable::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Sprint Tracker',
            'description' => 'Current sprint tasks and progress',
            'icon' => 'ph:kanban',
            'created_by' => 'h1',
            'workspace_id' => $this->workspaceId,
        ]);

        $colTask = $this->createColumn($table->id, 'Task', 'text', 0, true);
        $colStatus = $this->createColumn($table->id, 'Status', 'select', 1, true, [
            'choices' => ['To Do', 'In Progress', 'Done', 'Blocked'],
        ]);
        $colAssignee = $this->createColumn($table->id, 'Assignee', 'text', 2, true);
        $colDueDate = $this->createColumn($table->id, 'Due Date', 'date', 3, false);
        $colPoints = $this->createColumn($table->id, 'Points', 'number', 4, false);

        $rows = [
            [
                $colTask->id => 'Implement user authentication flow',
                $colStatus->id => 'Done',
                $colAssignee->id => 'Logic',
                $colDueDate->id => '2026-02-03',
                $colPoints->id => 8,
            ],
            [
                $colTask->id => 'Design dashboard wireframes',
                $colStatus->id => 'Done',
                $colAssignee->id => 'Pixel',
                $colDueDate->id => '2026-02-04',
                $colPoints->id => 5,
            ],
            [
                $colTask->id => 'Build notification system',
                $colStatus->id => 'In Progress',
                $colAssignee->id => 'Logic',
                $colDueDate->id => '2026-02-10',
                $colPoints->id => 13,
            ],
            [
                $colTask->id => 'Write API documentation',
                $colStatus->id => 'In Progress',
                $colAssignee->id => 'Echo',
                $colDueDate->id => '2026-02-08',
                $colPoints->id => 5,
            ],
            [
                $colTask->id => 'Set up CI/CD pipeline',
                $colStatus->id => 'Blocked',
                $colAssignee->id => 'Logic',
                $colDueDate->id => '2026-02-07',
                $colPoints->id => 8,
            ],
            [
                $colTask->id => 'Conduct user interviews',
                $colStatus->id => 'To Do',
                $colAssignee->id => 'Scout',
                $colDueDate->id => '2026-02-14',
                $colPoints->id => 3,
            ],
            [
                $colTask->id => 'Performance audit and optimization',
                $colStatus->id => 'To Do',
                $colAssignee->id => 'Nova',
                $colDueDate->id => '2026-02-15',
                $colPoints->id => 8,
            ],
            [
                $colTask->id => 'Create onboarding tutorial',
                $colStatus->id => 'To Do',
                $colAssignee->id => 'Echo',
                $colDueDate->id => '2026-02-17',
                $colPoints->id => 5,
            ],
        ];

        foreach ($rows as $data) {
            DataTableRow::create([
                'id' => Str::uuid()->toString(),
                'table_id' => $table->id,
                'data' => $data,
                'created_by' => 'h1',
            ]);
        }
    }

    private function createContactList(): void
    {
        $table = DataTable::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Contact List',
            'description' => 'Key business contacts and partners',
            'icon' => 'ph:address-book',
            'created_by' => 'h1',
            'workspace_id' => $this->workspaceId,
        ]);

        $colName = $this->createColumn($table->id, 'Name', 'text', 0, true);
        $colEmail = $this->createColumn($table->id, 'Email', 'email', 1, true);
        $colCompany = $this->createColumn($table->id, 'Company', 'text', 2, false);
        $colRole = $this->createColumn($table->id, 'Role', 'text', 3, false);
        $colLastContact = $this->createColumn($table->id, 'Last Contact', 'date', 4, false);

        $rows = [
            [
                $colName->id => 'Sarah Chen',
                $colEmail->id => 'sarah.chen@techventures.io',
                $colCompany->id => 'TechVentures',
                $colRole->id => 'VP of Engineering',
                $colLastContact->id => '2026-01-28',
            ],
            [
                $colName->id => 'Marcus Rivera',
                $colEmail->id => 'marcus@cloudscale.com',
                $colCompany->id => 'CloudScale',
                $colRole->id => 'CTO',
                $colLastContact->id => '2026-02-01',
            ],
            [
                $colName->id => 'Aisha Patel',
                $colEmail->id => 'aisha.patel@designhub.co',
                $colCompany->id => 'DesignHub',
                $colRole->id => 'Creative Director',
                $colLastContact->id => '2026-01-15',
            ],
            [
                $colName->id => 'James O\'Brien',
                $colEmail->id => 'jobrien@nexuspartners.com',
                $colCompany->id => 'Nexus Partners',
                $colRole->id => 'Managing Partner',
                $colLastContact->id => '2026-02-04',
            ],
            [
                $colName->id => 'Yuki Tanaka',
                $colEmail->id => 'yuki@dataflow.jp',
                $colCompany->id => 'DataFlow Inc',
                $colRole->id => 'Head of Product',
                $colLastContact->id => '2026-01-22',
            ],
            [
                $colName->id => 'Elena Vasquez',
                $colEmail->id => 'elena.v@brightpath.org',
                $colCompany->id => 'BrightPath',
                $colRole->id => 'Director of Operations',
                $colLastContact->id => '2026-01-30',
            ],
            [
                $colName->id => 'Daniel Kim',
                $colEmail->id => 'dkim@innovateai.dev',
                $colCompany->id => 'InnovateAI',
                $colRole->id => 'Lead Architect',
                $colLastContact->id => '2026-02-03',
            ],
        ];

        foreach ($rows as $data) {
            DataTableRow::create([
                'id' => Str::uuid()->toString(),
                'table_id' => $table->id,
                'data' => $data,
                'created_by' => 'h1',
            ]);
        }
    }

    private function createColumn(
        string $tableId,
        string $name,
        string $type,
        int $order,
        bool $required,
        ?array $options = null,
    ): DataTableColumn {
        return DataTableColumn::create([
            'id' => Str::uuid()->toString(),
            'table_id' => $tableId,
            'name' => $name,
            'type' => $type,
            'options' => $options,
            'order' => $order,
            'required' => $required,
        ]);
    }
}
