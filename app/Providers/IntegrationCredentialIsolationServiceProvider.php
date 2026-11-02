<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Prevents integration API clients from carrying credentials across workspaces.
 *
 * Owns: one invariant at the app/package boundary. Integration packages
 * (`OpenCompany\Integrations\*`) register their API clients as container
 * SINGLETONS whose factories read credentials from the CredentialResolver at
 * first resolution. In a long-running process (queue worker, Octane) the first
 * workspace's credentials would then be reused for every later workspace —
 * a cross-tenant credential leak (Roadmap Phase 0, P0 #12).
 *
 * This provider re-registers every such shared binding as non-shared after all
 * package providers have registered, so each resolution builds a client from
 * the CURRENT workspace's credentials, and drops any instance already built.
 *
 * Agent breadcrumb: this is intentionally app-local instead of a package fix.
 * The package system is scheduled for removal (Phase 0 nuclear cleanup), and
 * vendor/ must never be patched. Do not "simplify" this away while any
 * `OpenCompany\Integrations\*` provider still calls `singleton()`.
 */
class IntegrationCredentialIsolationServiceProvider extends ServiceProvider
{
    private const PACKAGE_NAMESPACE = 'OpenCompany\\Integrations\\';

    public function boot(): void
    {
        foreach ($this->app->getBindings() as $abstract => $binding) {
            if (! ($binding['shared'] ?? false) || ! str_starts_with((string) $abstract, self::PACKAGE_NAMESPACE)) {
                continue;
            }

            // Invariant: rebind before first use in this process; forget any
            // instance a provider's boot() may already have resolved.
            $this->app->forgetInstance($abstract);
            $this->app->bind($abstract, $binding['concrete'], shared: false);
        }
    }
}
