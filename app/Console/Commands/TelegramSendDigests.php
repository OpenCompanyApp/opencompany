<?php

namespace App\Console\Commands;

use App\Domain\Chat\Telegram\Application\TelegramDigestService;
use App\Models\Workspace;
use Illuminate\Console\Command;

/**
 * Dispatches due Telegram workspace digest cards from persisted subscriptions.
 *
 * The command is intentionally thin: scheduling cadence lives in Laravel's
 * console scheduler, while digest eligibility, idempotency, provider send, and
 * deferred-notification reconciliation stay in TelegramDigestService.
 */
class TelegramSendDigests extends Command
{
    protected $signature = 'telegram:send-digests {--force : Send current-window digests even when not due or already sent}';

    protected $description = 'Send due Telegram workspace digests and batch deferred Telegram notifications';

    public function handle(TelegramDigestService $digests): int
    {
        if (! app()->bound('currentWorkspace')) {
            $workspace = Workspace::first();
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        $summary = $digests->sendDueDigests((bool) $this->option('force'));

        $this->components->info(sprintf(
            'Telegram digests processed: %d sent, %d skipped, %d failed.',
            $summary['sent'],
            $summary['skipped'],
            $summary['failed'],
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
