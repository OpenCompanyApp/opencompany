<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Telescope wiring: entry filtering, sensitive-data redaction, and the access gate.
 *
 * Security boundary: Telescope shows request payloads, queries, jobs, and logs
 * for EVERY workspace on the instance. Workspace membership or workspace-admin
 * role must therefore never grant access. Anyone can become admin of a
 * workspace they create. Outside `local`, only humans whose email appears in
 * config('telescope.allowed_emails') (TELESCOPE_ALLOWED_EMAILS) may view it. An
 * empty allowlist means nobody. In `local`, Telescope's own authorization
 * callback admits everyone regardless of this gate (vendor behaviour in
 * TelescopeApplicationServiceProvider::authorization()).
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            // In local, record everything
            if ($isLocal) {
                return true;
            }

            // Always record these
            if ($entry->isReportableException() ||
                $entry->isFailedJob() ||
                $entry->isScheduledTask() ||
                $entry->hasMonitoredTag()) {
                return true;
            }

            // Record all jobs, requests, and logs
            if (in_array($entry->type, ['job', 'request', 'log'])) {
                return true;
            }

            // Record slow queries (> 100ms)
            if ($entry->type === 'query' && ($entry->content['slow'] ?? false)) {
                return true;
            }

            // Drop noisy entries (cache, model, event, etc.)
            return false;
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // Telegram Mini App init_data is a signed bearer-like credential.
        Telescope::hideRequestParameters(['_token', 'init_data']);

        // Webhook shared secrets and gateway API keys travel in headers, so
        // they must never be persisted in Telescope request entries.
        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
            'x-webhook-secret',
            'x-telegram-bot-api-secret-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     * It is an explicit email allowlist: human users only, matched
     * case-insensitively against config('telescope.allowed_emails'). Agents
     * and every non-listed human are denied, including workspace admins. See
     * the class docblock for why roles are not enough.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user): bool {
            if ($user->type !== 'human' || ! is_string($user->email) || $user->email === '') {
                return false;
            }

            return in_array(strtolower(trim($user->email)), self::allowedEmails(), true);
        });
    }

    /**
     * Normalized allowlist from config. Accepts a comma-separated string (the
     * env form) or an array, lowercases entries, and drops blanks.
     *
     * @return list<string>
     */
    private static function allowedEmails(): array
    {
        $configured = config('telescope.allowed_emails', []);
        $entries = is_string($configured) ? explode(',', $configured) : (array) $configured;

        return array_values(array_filter(
            array_map(fn ($email) => strtolower(trim((string) $email)), $entries),
            fn (string $email) => $email !== '',
        ));
    }
}
