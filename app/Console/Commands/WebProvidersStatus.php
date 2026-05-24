<?php

namespace App\Console\Commands;

use App\Domain\Web\Registry\WebProviderRegistry;
use App\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Lists configured web providers and their runtime availability.
 */
class WebProvidersStatus extends Command
{
    protected $signature = 'web:providers {--workspace= : Workspace id for workspace-scoped credentials}';

    protected $description = 'List web search/fetch providers and configuration status';

    public function handle(WebProviderRegistry $providers): int
    {
        $this->bindWorkspace();

        $this->table(['Provider', 'Label', 'Enabled', 'Configured', 'Capabilities'], array_map(
            fn (array $row) => [
                $row['id'],
                $row['label'],
                $row['enabled'] ? 'yes' : 'no',
                $row['configured'] ? 'yes' : 'no',
                implode(', ', is_array($row['capabilities']) ? $row['capabilities'] : []),
            ],
            $providers->statuses(),
        ));

        return Command::SUCCESS;
    }

    private function bindWorkspace(): void
    {
        $workspace = is_string($this->option('workspace'))
            ? Workspace::query()->find($this->option('workspace'))
            : Workspace::query()->first();

        if ($workspace !== null) {
            app()->instance('currentWorkspace', $workspace);
        }
    }
}
