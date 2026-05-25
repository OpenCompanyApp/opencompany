<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\CalendarEvent;
use App\Models\Document;
use App\Models\Message;
use App\Models\Task;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramInteraction;
use App\Models\TelegramSubscription;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use App\Services\TelegramService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends scheduled Telegram workspace digests for conversation-scoped subscriptions.
 *
 * The digest service owns only the outbound digest card and the reconciliation
 * bookkeeping around deferred Telegram notifications. It does not decide which
 * chats should subscribe; webhook commands and Mini App settings persist that
 * policy in TelegramSubscription rows.
 */
class TelegramDigestService
{
    private const RENDERER_VERSION = 'telegram-workspace-digest:v1';

    public function __construct(private readonly TelegramService $telegram) {}

    /**
     * Send all currently due digest subscriptions.
     *
     * @return array{sent:int, skipped:int, failed:int}
     */
    public function sendDueDigests(bool $force = false): array
    {
        $summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        TelegramSubscription::query()
            ->where('event_type', 'workspace_digest')
            ->where('enabled', true)
            ->orderBy('id')
            ->get()
            ->each(function (TelegramSubscription $subscription) use (&$summary, $force): void {
                $result = $this->sendDigestForSubscription($subscription, $force);
                $summary[$result]++;
            });

        return $summary;
    }

    /**
     * Send one digest subscription when its configured local time has elapsed.
     *
     * @return 'sent'|'skipped'|'failed'
     */
    public function sendDigestForSubscription(TelegramSubscription $subscription, bool $force = false): string
    {
        if ($subscription->scope_type !== 'telegram_conversation') {
            return 'skipped';
        }

        $workspace = Workspace::find($subscription->workspace_id);
        if (! $workspace) {
            return 'skipped';
        }

        $conversation = TelegramConversation::query()
            ->where('workspace_id', $subscription->workspace_id)
            ->where('integration_setting_id', $subscription->integration_setting_id)
            ->where('id', $subscription->scope_id)
            ->whereNull('archived_at')
            ->first();

        if (! $conversation) {
            return 'skipped';
        }

        $window = $this->digestWindow($subscription);
        if (! $force && ! $window['due']) {
            return 'skipped';
        }

        if (! $force && $this->alreadySent($subscription, $window['key'])) {
            return 'skipped';
        }

        $payload = $this->buildDigestPayload($workspace, $conversation, $subscription, $window);
        $delivery = TelegramDelivery::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $subscription->workspace_id,
            'integration_setting_id' => $subscription->integration_setting_id,
            'source_type' => TelegramSubscription::class,
            'source_id' => $subscription->id,
            'chat_id' => $conversation->chat_id,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'parse_mode' => 'HTML',
            'renderer_version' => self::RENDERER_VERSION,
            'status' => 'pending',
            'request_payload' => $payload,
        ]);

        try {
            $result = $this->telegram->sendMessage(
                $conversation->chat_id,
                $payload['text'],
                replyMarkup: $payload['reply_markup'],
                messageThreadId: TelegramService::messageThreadIdForTopic($conversation->topic_id),
                directMessagesTopicId: TelegramService::directMessagesTopicId($conversation->direct_messages_topic_id),
            );

            $delivery->update([
                'status' => 'sent',
                'telegram_message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null,
                'parse_mode' => $this->parseModeFromResult($result),
                'response_payload' => $result,
                'attempts' => 1,
                'sent_at' => now(),
            ]);

            $this->markDeferredDeliveriesBatched($payload['deferred_delivery_ids'], $delivery, $window['key']);

            return 'sent';
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'provider_error_code' => $e::class,
                'provider_error_message' => Str::limit($e->getMessage(), 2000),
                'attempts' => 1,
            ]);

            Log::warning('Telegram digest delivery failed', [
                'workspace_id' => $subscription->workspace_id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * @return array{key:string, schedule:string, due:bool, start:CarbonInterface, end:CarbonInterface}
     */
    private function digestWindow(TelegramSubscription $subscription): array
    {
        $timezone = $subscription->timezone ?: 'UTC';
        $now = now($timezone);
        $schedule = in_array($subscription->schedule, ['weekly', 'daily'], true) ? $subscription->schedule : 'daily';
        $time = $this->configuredTime($subscription);
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $dueAt = $now->copy()->setTime($hour, $minute);
        $start = $schedule === 'weekly' ? $now->copy()->startOfWeek() : $now->copy()->startOfDay();
        $key = sprintf('%s:%s:%s', $subscription->id, $schedule, $schedule === 'weekly' ? $now->format('o-\WW') : $now->toDateString());

        return [
            'key' => $key,
            'schedule' => $schedule,
            'due' => $now->greaterThanOrEqualTo($dueAt),
            'start' => $start->utc(),
            'end' => $now->copy()->utc(),
        ];
    }

    private function configuredTime(TelegramSubscription $subscription): string
    {
        $time = $subscription->filters['time'] ?? '09:00';

        return is_string($time) && preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '09:00';
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function parseModeFromResult(array $result): string
    {
        return ($result['_opencompany_parse_mode_fallback'] ?? false) ? 'plain_text_fallback' : 'HTML';
    }

    private function alreadySent(TelegramSubscription $subscription, string $digestKey): bool
    {
        return TelegramDelivery::query()
            ->where('workspace_id', $subscription->workspace_id)
            ->where('integration_setting_id', $subscription->integration_setting_id)
            ->where('source_type', TelegramSubscription::class)
            ->where('source_id', $subscription->id)
            ->where('renderer_version', self::RENDERER_VERSION)
            ->whereIn('status', ['pending', 'sent'])
            ->get()
            ->contains(fn (TelegramDelivery $delivery) => ($delivery->request_payload['digest_key'] ?? null) === $digestKey);
    }

    /**
     * @param  array{key:string, schedule:string, due:bool, start:CarbonInterface, end:CarbonInterface}  $window
     * @return array<string, mixed>
     */
    private function buildDigestPayload(Workspace $workspace, TelegramConversation $conversation, TelegramSubscription $subscription, array $window): array
    {
        $counts = $this->workspaceCounts($workspace, $window['start'], $window['end']);
        $deferredDeliveries = $this->deferredDeliveriesForDigest($conversation, $window['start'], $window['end']);
        $nextAutomation = $this->nextAutomation($workspace);
        $text = $this->renderDigestText($workspace, $window, $counts, $deferredDeliveries, $nextAutomation);

        return [
            'method' => 'sendMessage',
            'digest_key' => $window['key'],
            'subscription_id' => $subscription->id,
            'conversation_id' => $conversation->id,
            'schedule' => $window['schedule'],
            'timezone' => $subscription->timezone ?: 'UTC',
            'window_start' => $window['start']->toIso8601String(),
            'window_end' => $window['end']->toIso8601String(),
            'text' => $text,
            'reply_markup' => $this->digestButtons($workspace, $conversation, $subscription),
            'counts' => $counts,
            'next_automation_id' => $nextAutomation?->id,
            'deferred_delivery_ids' => $deferredDeliveries->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function workspaceCounts(Workspace $workspace, CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'messages' => Message::query()
                ->whereBetween('timestamp', [$start, $end])
                ->whereHas('channel', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->count(),
            'tasks_completed' => Task::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', Task::STATUS_COMPLETED)
                ->whereBetween('completed_at', [$start, $end])
                ->count(),
            'failed_work' => Task::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', Task::STATUS_FAILED)
                ->whereBetween('completed_at', [$start, $end])
                ->count(),
            'docs_changed' => Document::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_folder', false)
                ->whereBetween('updated_at', [$start, $end])
                ->count(),
            'files_uploaded' => WorkspaceFile::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_folder', false)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'calendar_events' => CalendarEvent::query()
                ->where('workspace_id', $workspace->id)
                ->whereBetween('start_at', [$end, $end->copy()->addDay()])
                ->count(),
            'pending_approvals' => ApprovalRequest::query()
                ->where('status', 'pending')
                ->whereHas('channel', fn ($query) => $query->where('workspace_id', $workspace->id))
                ->count(),
        ];
    }

    /**
     * @return Collection<int, TelegramDelivery>
     */
    private function deferredDeliveriesForDigest(TelegramConversation $conversation, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return TelegramDelivery::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('integration_setting_id', $conversation->integration_setting_id)
            ->where('chat_id', $conversation->chat_id)
            ->where('topic_id', $conversation->topic_id)
            ->where('direct_messages_topic_id', $conversation->direct_messages_topic_id)
            ->where('status', 'deferred')
            ->whereBetween('created_at', [$start, $end])
            ->oldest()
            ->limit(25)
            ->get();
    }

    private function nextAutomation(Workspace $workspace): ?Automation
    {
        return Automation::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->first();
    }

    /**
     * @param  array<string, int>  $counts
     * @param  Collection<int, TelegramDelivery>  $deferredDeliveries
     * @param  array{key:string, schedule:string, due:bool, start:CarbonInterface, end:CarbonInterface}  $window
     */
    private function renderDigestText(Workspace $workspace, array $window, array $counts, Collection $deferredDeliveries, ?Automation $nextAutomation): string
    {
        $title = ucfirst($window['schedule']).' Digest - '.htmlspecialchars($workspace->name, ENT_QUOTES, 'UTF-8');
        $lines = [
            '<b>'.$title.'</b>',
            '',
            'Messages: '.$counts['messages'],
            'Tasks completed: '.$counts['tasks_completed'],
            'Failed work: '.$counts['failed_work'],
            'Docs changed: '.$counts['docs_changed'],
            'Files uploaded: '.$counts['files_uploaded'],
            'Calendar next 24h: '.$counts['calendar_events'],
            'Pending approvals: '.$counts['pending_approvals'],
            'Deferred alerts: '.$deferredDeliveries->count(),
        ];

        if ($nextAutomation) {
            $lines[] = 'Next automation: '.htmlspecialchars($nextAutomation->name, ENT_QUOTES, 'UTF-8').' at '.$nextAutomation->next_run_at?->timezone($nextAutomation->timezone)->format('M j H:i');
        } else {
            $lines[] = 'Next automation: none scheduled';
        }

        $alertLines = $this->deferredAlertLines($deferredDeliveries);
        if ($alertLines !== []) {
            $lines[] = '';
            $lines[] = '<b>Batched alerts</b>';
            array_push($lines, ...$alertLines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, TelegramDelivery>  $deferredDeliveries
     * @return list<string>
     */
    private function deferredAlertLines(Collection $deferredDeliveries): array
    {
        return $deferredDeliveries
            ->take(5)
            ->map(function (TelegramDelivery $delivery): ?string {
                return match ($delivery->source_type) {
                    Task::class => $this->deferredTaskLine($delivery),
                    Automation::class => $this->deferredAutomationLine($delivery),
                    ApprovalRequest::class => $this->deferredApprovalLine($delivery),
                    default => null,
                };
            })
            ->filter()
            ->values()
            ->all();
    }

    private function deferredTaskLine(TelegramDelivery $delivery): ?string
    {
        if (! $delivery->source_id) {
            return null;
        }

        $task = Task::find($delivery->source_id);
        if (! $task) {
            return null;
        }

        $eventType = $delivery->request_payload['event_type'] ?? 'task';
        $label = $eventType === 'task_failed' ? 'Failed' : 'Completed';

        return '- '.$label.': '.htmlspecialchars(Str::limit($task->title, 80), ENT_QUOTES, 'UTF-8');
    }

    private function deferredAutomationLine(TelegramDelivery $delivery): ?string
    {
        if (! $delivery->source_id) {
            return null;
        }

        $automation = Automation::find($delivery->source_id);
        if (! $automation) {
            return null;
        }

        return '- Automation failed: '.htmlspecialchars(Str::limit($automation->name, 80), ENT_QUOTES, 'UTF-8');
    }

    private function deferredApprovalLine(TelegramDelivery $delivery): ?string
    {
        if (! $delivery->source_id) {
            return null;
        }

        $approval = ApprovalRequest::find($delivery->source_id);
        if (! $approval) {
            return null;
        }

        return '- Approval needed: '.htmlspecialchars(Str::limit($approval->title, 80), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return array{inline_keyboard:list<list<array<string, string>>>}
     */
    private function digestButtons(Workspace $workspace, TelegramConversation $conversation, TelegramSubscription $subscription): array
    {
        $base = rtrim((string) config('app.url'), '/').'/w/'.$workspace->slug;
        $snooze = $this->snoozeInteraction($conversation, $subscription);

        return [
            'inline_keyboard' => [
                [
                    ['text' => 'Approvals', 'url' => $base.'/approvals'],
                    ['text' => 'Failed work', 'url' => $base.'/tasks'],
                ],
                [
                    ['text' => 'Open dashboard', 'url' => $base],
                    ['text' => 'Snooze', 'callback_data' => $snooze->token],
                ],
                [
                    ['text' => 'Settings', 'url' => $base.'/integrations'],
                ],
            ],
        ];
    }

    private function snoozeInteraction(TelegramConversation $conversation, TelegramSubscription $subscription): TelegramInteraction
    {
        return TelegramInteraction::create(TelegramInteraction::withPayloadChecksum([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $conversation->workspace_id,
            'integration_setting_id' => $conversation->integration_setting_id,
            'token' => TelegramInteraction::newToken(),
            'interaction_type' => 'notification_snooze',
            'target_type' => TelegramConversation::class,
            'target_id' => $conversation->id,
            'payload' => [
                'conversation_id' => $conversation->id,
                'digest_subscription_id' => $subscription->id,
                'duration' => '2h',
            ],
            'allowed_actor_rule' => [
                'type' => 'linked_workspace_member',
            ],
            'expires_at' => now()->addDays(7),
        ]));
    }

    /**
     * @param  list<string>  $deliveryIds
     */
    private function markDeferredDeliveriesBatched(array $deliveryIds, TelegramDelivery $digestDelivery, string $digestKey): void
    {
        if ($deliveryIds === []) {
            return;
        }

        TelegramDelivery::query()
            ->whereIn('id', $deliveryIds)
            ->where('status', 'deferred')
            ->get()
            ->each(function (TelegramDelivery $delivery) use ($digestDelivery, $digestKey): void {
                $delivery->update([
                    'status' => 'batched',
                    'response_payload' => [
                        'digest_delivery_id' => $digestDelivery->id,
                        'digest_key' => $digestKey,
                    ],
                    'sent_at' => now(),
                ]);
            });
    }
}
