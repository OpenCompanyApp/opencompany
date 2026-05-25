<?php

namespace App\Console\Commands;

use App\Domain\Web\Managers\WebFetchProviderManager;
use App\Domain\Web\Managers\WebSearchProviderManager;
use App\Domain\Web\Registry\WebProviderRegistry;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Operator diagnostic for provider registration and optional live web probes.
 */
class WebDoctorCommand extends Command
{
    protected $signature = 'web:doctor
        {--workspace= : Workspace id for settings and credentials}
        {--live : Run live search/fetch probes}
        {--query=OpenCompany web search : Query for live search probe}
        {--url=https://example.com : URL for live fetch probe}';

    protected $description = 'Diagnose web provider registration, settings, and optional live calls';

    public function handle(WebProviderRegistry $registry, WebSearchProviderManager $search, WebFetchProviderManager $fetch): int
    {
        $workspace = $this->bindWorkspace();
        $this->components->twoColumnDetail('Workspace', $workspace ? "{$workspace->name} ({$workspace->id})" : 'none');
        $this->components->twoColumnDetail('Search providers', implode(', ', $search->availableProviderIds()) ?: 'none configured');
        $this->components->twoColumnDetail('Fetch providers', implode(', ', $fetch->availableProviderIds()) ?: 'none configured');

        $unconfigured = array_values(array_filter($registry->statuses(), fn (array $row) => $row['enabled'] && ! $row['configured']));
        if ($unconfigured !== []) {
            $this->warn('Enabled but unconfigured providers: '.implode(', ', array_map(fn (array $row) => (string) $row['id'], $unconfigured)));
        }

        if (! $this->option('live')) {
            $this->info('Use --live to run real search/fetch probes.');

            return Command::SUCCESS;
        }

        try {
            $result = $search->search(new WebSearchRequest(
                query: (string) $this->option('query'),
                maxResults: 3,
                workspaceId: $workspace?->id,
                noCache: true,
            ));
            $this->components->twoColumnDetail('Live search', "{$result->provider}: ".count($result->results).' results');
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Live search', '<fg=red>'.$e->getMessage().'</>');
        }

        try {
            $result = $fetch->fetch(new WebFetchRequest(
                url: (string) $this->option('url'),
                mode: 'metadata',
                workspaceId: $workspace?->id,
                noCache: true,
            ));
            $this->components->twoColumnDetail('Live fetch', "{$result->provider}: HTTP ".($result->statusCode ?? 'n/a'));
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Live fetch', '<fg=red>'.$e->getMessage().'</>');
        }

        return Command::SUCCESS;
    }

    private function bindWorkspace(): ?Workspace
    {
        $workspace = is_string($this->option('workspace'))
            ? Workspace::query()->find($this->option('workspace'))
            : Workspace::query()->first();

        if ($workspace !== null) {
            app()->instance('currentWorkspace', $workspace);
        }

        return $workspace;
    }
}
