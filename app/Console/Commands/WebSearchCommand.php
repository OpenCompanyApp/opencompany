<?php

namespace App\Console\Commands;

use App\Domain\Web\Managers\WebSearchProviderManager;
use App\Domain\Web\Support\WebToolFormatter;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Runs a web search through the same provider manager used by agent tools.
 */
class WebSearchCommand extends Command
{
    protected $signature = 'web:search
        {query : Search query}
        {--provider= : Provider override}
        {--max=8 : Maximum results}
        {--workspace= : Workspace id for settings and credentials}
        {--no-cache : Bypass cached search results}';

    protected $description = 'Run a web search through the configured web provider chain';

    public function handle(WebSearchProviderManager $providers, WebToolFormatter $formatter): int
    {
        $workspace = $this->bindWorkspace();

        $request = new WebSearchRequest(
            query: (string) $this->argument('query'),
            provider: is_string($this->option('provider')) ? $this->option('provider') : null,
            maxResults: (int) $this->option('max'),
            noCache: (bool) $this->option('no-cache'),
            workspaceId: $workspace?->id,
        );

        $this->line($formatter->search($providers->search($request)));

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
