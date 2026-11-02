<?php

namespace App\Domain\Routines\Builtins;

use App\Domain\Chat\Telegram\Application\TelegramDigestService;
use App\Models\Routine;
use App\Models\TelegramSubscription;

/**
 * Built-in routine that sends due Telegram workspace digests. It replaces the
 * hardcoded `telegram:send-digests` cron.
 *
 * Runs every minute because each subscription has its own local digest time;
 * TelegramDigestService decides what is due and guarantees one delivery per
 * digest window, so a redelivered run is harmless. No LLM is involved.
 *
 * Applies only to workspaces with an enabled `workspace_digest` subscription.
 */
class TelegramDigestRoutine implements BuiltinRoutine
{
    public const KEY = 'telegram.digest';

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Telegram digest';
    }

    public function description(): string
    {
        return 'Sends due Telegram workspace digests and batches deferred Telegram notifications.';
    }

    public function cronExpression(): string
    {
        return '* * * * *';
    }

    public function applicableWorkspaceIds(): array
    {
        return TelegramSubscription::query()
            ->where('event_type', 'workspace_digest')
            ->where('enabled', true)
            ->distinct()
            ->pluck('workspace_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    public function run(Routine $routine): array
    {
        // Resolve per run: TelegramService binds the bot token of the
        // workspace that is current at construction time.
        $summary = app(TelegramDigestService::class)->sendDueDigests($routine->workspace_id);
        if ($summary['failed'] > 0) {
            throw new \RuntimeException(sprintf(
                'Telegram digests: %d sent, %d skipped, %d failed.',
                $summary['sent'], $summary['skipped'], $summary['failed'],
            ));
        }

        return $summary;
    }
}
