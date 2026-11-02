<?php

namespace Tests\Evals\Vfs\Support;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Models\AgentPermission;
use App\Models\Channel;
use App\Models\DataTable;
use App\Models\DataTableColumn;
use App\Models\DataTableRow;
use App\Models\Document;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;

/**
 * Deterministic eval workspace ("Acme") with known answers.
 *
 * Built inside a RefreshDatabase test (sqlite :memory:), never against the
 * dev database. `base` is always seeded; task-specific extras (`archive130`,
 * `leads250`, `docs40`, `todo_exists`, `pricing_gated`) are added on demand
 * so unrelated tasks stay small.
 *
 * Permission layout mirrors tests/Feature/Vfs (AgentPermission rows):
 * - Atlas (manager): folder allow on Handbook (so HR is hidden and
 *   unwritable: a folder allow restricts reads too), file_folder `*`.
 * - Forge (manager): same grants; the scripted second writer in mixed tasks.
 * - Nova: owns task T-13.
 *
 * Every object a checker needs is in `$ids`, every known answer in
 * `$expected`. Nothing here talks to a model.
 */
final class EvalWorld
{
    public User $atlas;

    public User $forge;

    public User $nova;

    public User $human;

    public Channel $channel;

    /** @var array<string, string> */
    public array $ids = [];

    /** @var array<string, mixed> */
    public array $expected = [];

    /** @var array<string, array<string, string>> table => column name => column id */
    public array $columns = [];

    public static function build(Workspace $workspace, array $extras): self
    {
        $world = new self;
        $world->seed($workspace, $extras);

        return $world;
    }

    /** @param list<string> $extras */
    private function seed(Workspace $workspace, array $extras): void
    {
        $this->human = User::factory()->create(['name' => 'Rutger', 'type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $this->human->id, 'role' => 'admin']);
        $this->atlas = User::factory()->agent('manager')->create(['name' => 'Atlas', 'status' => 'idle', 'workspace_id' => $workspace->id]);
        $this->forge = User::factory()->agent('manager')->create(['name' => 'Forge', 'status' => 'idle', 'workspace_id' => $workspace->id]);
        $this->nova = User::factory()->agent('manager')->create(['name' => 'Nova', 'status' => 'idle', 'workspace_id' => $workspace->id]);

        WorkspaceDisk::create([
            'id' => 'disk-eval',
            'workspace_id' => $workspace->id,
            'name' => 'Local',
            'driver' => 'local',
            'config' => [],
            'is_default' => true,
            'enabled' => true,
        ]);

        // The originating conversation: approvals post their outcome here.
        $this->channel = Channel::factory()->create(['type' => 'dm', 'workspace_id' => $workspace->id, 'name' => 'atlas-dm', 'creator_id' => $this->human->id]);
        app(ToolRegistry::class)->setChannelContext($this->channel->id);

        $this->seedDocs($extras);
        $this->seedTables($workspace, $extras);
        $this->seedTasks($workspace);

        foreach ([$this->atlas, $this->forge] as $agent) {
            $this->allow($agent, 'folder', $this->ids['folder.handbook']);
            $this->allow($agent, 'file_folder', '*');
        }
        if (in_array('pricing_gated', $extras, true)) {
            $this->allow($this->atlas, 'tool', 'update_table_row', requiresApproval: true);
            $this->atlas->update(['must_wait_for_approval' => true]);
        }
        if (in_array('todo_exists', $extras, true)) {
            app(OpenCompanyVfs::class)->write($this->forge, '/files/notes/todo.md', "- call the bank\n- renew domain\n");
            $this->expected['todo_original'] = "- call the bank\n- renew domain\n";
        }
    }

    /** @param list<string> $extras */
    private function seedDocs(array $extras): void
    {
        $handbook = $this->doc('Handbook', null, null, folder: true);
        $hr = $this->doc('HR', null, null, folder: true);
        $engineering = $this->doc('Engineering', $handbook, null, folder: true);
        $runbooks = $this->doc('Runbooks', $engineering, null, folder: true);
        $this->ids['folder.handbook'] = $handbook->id;
        $this->ids['folder.hr'] = $hr->id;

        $this->ids['doc.refund'] = $this->doc('Refund Policy', $handbook, "# Refund Policy\n\nCustomers can request a refund within 14 days of purchase.\nRefunds go back to the original payment method.\n\n## Contact\n\nQuestions: support@acme.test\n")->id;
        $this->ids['doc.onboarding'] = $this->doc('Onboarding', $handbook, "# Onboarding\n\nWelcome to Acme!\n\n## Access\n\nAsk #help for access to the admin tools.\nLaptops are issued on day one.\n")->id;
        $this->ids['doc.playbook'] = $this->doc('Support Playbook', $handbook, "# Support Playbook\n\nEscalate angry customers to a lead.\nFor money-back requests follow the refund policy.\n")->id;
        $this->ids['doc.billing_faq'] = $this->doc('Billing FAQ', $handbook, "# Billing FAQ\n\nInvoices are sent monthly.\nRefunds take 5-10 business days to appear.\n")->id;
        $this->ids['doc.pricing_notes'] = $this->doc('Pricing Notes', $handbook, "# Pricing Notes\n\nAnnual plans get two months free.\n")->id;
        $this->ids['doc.roadmap'] = $this->doc('Roadmap', $handbook, "# Roadmap\n\n## Goals\n\nShip billing v2 in Q3.\n\n## Timeline\n\nBeta in Q3, general availability after.\n\n## Risks\n\nQ3 hiring may slip.\n")->id;
        $this->ids['doc.deploy'] = $this->doc('Deploy Checklist', $runbooks, "# Deploy Checklist\n\n1. Run migrations.\n2. Warm caches.\n")->id;
        $this->ids['doc.salaries'] = $this->doc('Salaries', $hr, "# Salaries\n\nCONFIDENTIAL-SALARY-BANDS: L1 50k, L2 70k.\n")->id;
        $this->ids['doc.hr_refunds'] = $this->doc('Refund Exceptions for Staff', $hr, "# Refund Exceptions for Staff\n\nStaff refunds are approved by HR.\n")->id;

        $this->expected['refund_docs'] = ['Refund Policy', 'Support Playbook', 'Billing FAQ'];
        $this->expected['refund_forbidden'] = ['Refund Exceptions for Staff'];
        $this->expected['refund_original'] = Document::find($this->ids['doc.refund'])->content;
        $this->expected['roadmap_original'] = Document::find($this->ids['doc.roadmap'])->content;
        $this->expected['salaries_original'] = Document::find($this->ids['doc.salaries'])->content;
        $this->expected['deploy_paths'] = ['/docs/by-id/'.$this->ids['doc.deploy'], '/docs/handbook/engineering/runbooks/deploy-checklist'];

        if (in_array('docs40', $extras, true)) {
            $policies = $this->doc('Policies', $handbook, null, folder: true);
            for ($i = 1; $i <= 39; $i++) {
                $this->doc(sprintf('Policy %02d', $i), $policies, "# Policy {$i}\n\nThis policy covers topic {$i}. Review yearly.\n");
            }
            $this->doc('Disputes and Chargebacks', $policies, "# Disputes and Chargebacks\n\nOur chargeback window: 45 days from the statement date.\nEvidence goes to finance.\n");
            $this->expected['chargeback_title'] = 'Disputes and Chargebacks';
        }
        if (in_array('archive130', $extras, true)) {
            $archive = $this->doc('Archive', $handbook, null, folder: true);
            for ($i = 1; $i <= 130; $i++) {
                $this->doc(sprintf('Archived Note %03d', $i), $archive, "Archived note {$i}.\n");
            }
            $this->expected['archive_count'] = 130;
        }
    }

    /** @param list<string> $extras */
    private function seedTables(Workspace $workspace, array $extras): void
    {
        $customers = $this->table($workspace, 'Customers', ['Name' => 'text', 'Status' => 'text', 'Plan' => 'text', 'MRR' => 'number']);
        foreach ([
            'acme' => ['Acme Corp', 'active', 'pro', 500],
            'globex' => ['Globex', 'active', 'team', 290],
            'initech' => ['Initech', 'active', 'starter', 490],
            'umbrella' => ['Umbrella', 'churned', 'pro', 300],
        ] as $key => [$name, $status, $plan, $mrr]) {
            $this->ids["row.{$key}"] = $this->row($customers, ['Name' => $name, 'Status' => $status, 'Plan' => $plan, 'MRR' => $mrr])->id;
        }
        $this->ids['table.customers'] = $customers->id;
        $this->expected['active_mrr'] = 1280;

        $pricing = $this->table($workspace, 'Pricing', ['Plan' => 'text', 'Price' => 'number']);
        foreach (['starter' => 29, 'pro' => 99, 'team' => 199] as $plan => $price) {
            $this->ids["row.price_{$plan}"] = $this->row($pricing, ['Plan' => $plan, 'Price' => $price])->id;
        }
        $this->ids['table.pricing'] = $pricing->id;

        if (in_array('leads250', $extras, true)) {
            $leads = $this->table($workspace, 'Leads', ['Name' => 'text', 'Email' => 'text', 'Company' => 'text', 'Source' => 'text']);
            $sources = ['Website', 'Referral', 'Ads', 'Event'];
            $webinar = [17, 48, 90, 133, 176, 201, 244];
            for ($i = 1; $i <= 250; $i++) {
                $source = in_array($i, $webinar, true) ? 'Webinar' : $sources[$i % 4];
                $email = $i === 231 ? 'zoe@example.test' : "lead{$i}@example.test";
                $company = $i === 231 ? 'Zephyr Analytics' : "Company {$i}";
                $this->row($leads, ['Name' => "Lead {$i}", 'Email' => $email, 'Company' => $company, 'Source' => $source]);
            }
            $this->ids['table.leads'] = $leads->id;
            $this->expected['webinar_count'] = count($webinar);
            $this->expected['zoe_company'] = 'Zephyr Analytics';
        }
    }

    private function seedTasks(Workspace $workspace): void
    {
        foreach (['t12' => ['T-12: Renew SSL certificate', $this->atlas], 't13' => ['T-13: Rotate API keys', $this->nova]] as $key => [$title, $agent]) {
            $this->ids["task.{$key}"] = Task::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'title' => $title,
                'type' => 'custom',
                'status' => 'pending',
                'agent_id' => $agent->id,
                'requester_id' => $this->human->id,
                'source' => 'manual',
            ])->id;
        }
    }

    private function doc(string $title, ?Document $parent, ?string $content, bool $folder = false): Document
    {
        return Document::factory()->create([
            'title' => $title,
            'is_folder' => $folder,
            'parent_id' => $parent?->id,
            'content' => $content ?? '',
            'author_id' => $this->human->id,
        ]);
    }

    /** @param array<string, string> $columns */
    private function table(Workspace $workspace, string $name, array $columns): DataTable
    {
        $table = DataTable::create(['workspace_id' => $workspace->id, 'name' => $name, 'created_by' => $this->human->id]);
        $order = 0;
        foreach ($columns as $column => $type) {
            $this->columns[$name][$column] = DataTableColumn::create(['table_id' => $table->id, 'name' => $column, 'type' => $type, 'order' => $order++, 'required' => false])->id;
        }

        return $table;
    }

    /** @param array<string, mixed> $values */
    private function row(DataTable $table, array $values): DataTableRow
    {
        $data = [];
        foreach ($values as $column => $value) {
            $data[$this->columns[$table->name][$column]] = $value;
        }

        return DataTableRow::create(['table_id' => $table->id, 'created_by' => $this->human->id, 'data' => $data]);
    }

    /** Row values keyed by column name. */
    public function rowValues(string $table, string $rowKey): array
    {
        $row = DataTableRow::query()->find($this->ids["row.{$rowKey}"]);
        $out = [];
        foreach ($this->columns[$table] as $name => $id) {
            $out[$name] = $row?->data[$id] ?? null;
        }

        return $out;
    }

    private function allow(User $agent, string $scopeType, string $scopeKey, bool $requiresApproval = false): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
            'permission' => 'allow',
            'requires_approval' => $requiresApproval,
        ]);
    }

    /**
     * Resolve `{{key}}` placeholders (ids and expected values) in oracle steps
     * and prompts.
     */
    public function interpolate(string $text): string
    {
        return (string) preg_replace_callback('/\{\{([a-z0-9_.]+)\}\}/', function (array $m): string {
            return (string) ($this->ids[$m[1]] ?? (is_scalar($this->expected[$m[1]] ?? null) ? $this->expected[$m[1]] : $m[0]));
        }, $text);
    }
}
