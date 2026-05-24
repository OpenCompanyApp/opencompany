<?php

namespace App\Console\Commands;

use App\Domain\Web\Managers\WebFetchProviderManager;
use App\Domain\Web\Support\WebToolFormatter;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Fetches a URL through the configured web fetch provider chain.
 */
class WebFetchCommand extends Command
{
    protected $signature = 'web:fetch
        {url : URL to fetch}
        {--provider= : Provider override}
        {--mode=main : metadata, outline, main, full, section, match, or chunk}
        {--format=markdown : markdown, text, or html}
        {--max=12000 : Maximum content characters}
        {--workspace= : Workspace id for settings and credentials}
        {--no-cache : Bypass cached fetch results}';

    protected $description = 'Fetch and extract a URL through configured web providers';

    public function handle(WebFetchProviderManager $providers, WebToolFormatter $formatter): int
    {
        $workspace = $this->bindWorkspace();

        $request = new WebFetchRequest(
            url: (string) $this->argument('url'),
            provider: is_string($this->option('provider')) ? $this->option('provider') : null,
            mode: (string) $this->option('mode'),
            format: (string) $this->option('format'),
            maxChars: (int) $this->option('max'),
            noCache: (bool) $this->option('no-cache'),
            workspaceId: $workspace?->id,
        );

        $this->line($formatter->fetch($providers->fetch($request), $request->mode, true, true));

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
