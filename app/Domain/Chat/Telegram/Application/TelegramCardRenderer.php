<?php

namespace App\Domain\Chat\Telegram\Application;

use App\Domain\Calendar\Application\ManageCalendarEvents;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\CalendarEvent;
use App\Models\DataTable;
use App\Models\Document;
use App\Models\IntegrationSetting;
use App\Models\ListItem;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramIntegrationProfile;
use App\Models\TelegramUpdateReceipt;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Renders compact Telegram-native cards for OpenCompany chat surfaces.
 *
 * The renderer owns message shape and button layout only. It does not authorize,
 * mutate workspace state, create callback tokens, or decide routing. Callers pass
 * already-created opaque callback tokens so Telegram buttons remain a transport
 * hint while the webhook pipeline keeps all policy checks server-side.
 */
class TelegramCardRenderer
{
    private const CARD_MAX_LINES = 48;

    private const CARD_MAX_LINE_CHARS = 220;

    private const CARD_MAX_CHARS = 3400;

    public const COMMAND_CENTER_VERSION = 'telegram-command-center-card:v1';

    public const STATUS_VERSION = 'telegram-status-card:v1';

    public const TASKS_VERSION = 'telegram-tasks-card:v1';

    public const WORKLOAD_VERSION = 'telegram-workload-card:v1';

    public const DASHBOARD_VERSION = 'telegram-dashboard-card:v1';

    public const AGENTS_VERSION = 'telegram-agents-card:v1';

    public const APPROVALS_VERSION = 'telegram-approvals-card:v1';

    public const ACTIVITY_VERSION = 'telegram-activity-card:v1';

    public const FILES_VERSION = 'telegram-files-card:v1';

    public const DOCS_VERSION = 'telegram-docs-card:v1';

    public const AUTOMATIONS_VERSION = 'telegram-automations-card:v1';

    public const AUTOMATION_STATUS_VERSION = 'telegram-automation-status-card:v1';

    public const AUTOMATION_HISTORY_VERSION = 'telegram-automation-history-card:v1';

    public const AUTOMATION_FAILURES_VERSION = 'telegram-automation-failures-card:v1';

    public const AUTOMATION_ACTION_VERSION = 'telegram-automation-action-card:v1';

    public const CALENDAR_VERSION = 'telegram-calendar-card:v1';

    public const CALENDAR_DRAFT_VERSION = 'telegram-calendar-draft-card:v1';

    public const LISTS_VERSION = 'telegram-lists-card:v1';

    public const LIST_DRAFT_VERSION = 'telegram-list-draft-card:v1';

    public const TABLES_VERSION = 'telegram-tables-card:v1';

    public const TABLE_ROW_DRAFT_VERSION = 'telegram-table-row-draft-card:v1';

    public const TABLE_CLARIFY_VERSION = 'telegram-table-clarify-card:v1';

    public const RUN_CARD_VERSION = 'telegram-run-card:v1';

    public const TASK_CARD_VERSION = 'telegram-task-card:v1';

    public const TASK_CARD_UPDATED_VERSION = 'telegram-task-card:updated:v1';

    public const DIGEST_COMMAND_VERSION = 'telegram-digest-command-card:v1';

    public const NOTIFY_COMMAND_VERSION = 'telegram-notify-command-card:v1';

    public const SNOOZE_COMMAND_VERSION = 'telegram-snooze-command-card:v1';

    public const SETTINGS_VERSION = 'telegram-settings-card:v1';

    public const HELP_VERSION = 'telegram-help-card:v1';

    public const LINK_VERSION = 'telegram-link-card:v1';

    public const HEALTH_VERSION = 'telegram-health-card:v1';

    public const TOPIC_VERSION = 'telegram-topic-card:v1';

    public const CANCEL_COMMAND_VERSION = 'telegram-cancel-command-card:v1';

    public const RESUME_COMMAND_VERSION = 'telegram-resume-command-card:v1';

    public const MEDIA_CAPTURE_VERSION = 'telegram-media-capture-card:v1';

    public const MEDIA_FOLDER_PICKER_VERSION = 'telegram-media-folder-picker-card:v1';

    public const MEDIA_DOCUMENT_PICKER_VERSION = 'telegram-media-document-picker-card:v1';

    public const NOTICE_VERSION = 'telegram-notice-card:v1';

    /**
     * Convert renderer-owned plain card text into compact Telegram HTML.
     *
     * Renderer methods intentionally compose newline-oriented plain text because
     * that shape is easy to test and safe to build from runtime data. This method
     * is the shared Telegram presentation pass used by sends and edits so cards
     * keep the same hierarchy over their full lifecycle.
     */
    public function cardHtml(string $text): string
    {
        $formatted = [];
        $titleRendered = false;

        foreach ($this->cardLines($text) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $formatted[] = '';

                continue;
            }

            if (! $titleRendered) {
                $formatted[] = '<b>'.$this->telegramHtml($trimmed).'</b>';
                $titleRendered = true;

                continue;
            }

            if (str_starts_with($trimmed, '- ')) {
                $formatted[] = '• '.$this->telegramHtml(substr($trimmed, 2));

                continue;
            }

            if ($this->isSectionHeading($trimmed)) {
                $formatted[] = '<b>'.$this->telegramHtml($trimmed).'</b>';

                continue;
            }

            $formatted[] = $this->formatLabelLine($trimmed);
        }

        return implode("\n", $formatted);
    }

    /**
     * @return list<string>
     */
    private function cardLines(string $text): array
    {
        $rawLines = preg_split('/\\r\\n|\\n|\\r/', trim($text)) ?: [];
        $lines = [];
        $characters = 0;
        $truncated = false;

        foreach ($rawLines as $line) {
            if (count($lines) >= self::CARD_MAX_LINES) {
                $truncated = true;
                break;
            }

            $trimmed = trim($line);
            if (mb_strlen($trimmed) > self::CARD_MAX_LINE_CHARS) {
                $trimmed = Str::limit($trimmed, self::CARD_MAX_LINE_CHARS);
                $truncated = true;
            }

            $nextCharacters = $characters + mb_strlen($trimmed) + 1;
            if ($nextCharacters > self::CARD_MAX_CHARS) {
                $truncated = true;
                break;
            }

            $lines[] = $trimmed;
            $characters = $nextCharacters;
        }

        if ($truncated) {
            while ($lines !== [] && end($lines) === '') {
                array_pop($lines);
            }

            $lines[] = '';
            $lines[] = 'More detail is available in OpenCompany.';
        }

        return $lines;
    }

    private function formatLabelLine(string $line): string
    {
        if (preg_match('/^([^:]{2,42}):\\s*(.*)$/u', $line, $matches) === 1) {
            $label = (string) $matches[1];
            if ($label === trim($label) && preg_match('/^[A-Z][A-Za-z0-9 ()\\/-]*$/u', $label) === 1) {
                return '<b>'.$this->telegramHtml($label).':</b> '.$this->telegramHtml($matches[2]);
            }
        }

        return $this->telegramHtml($line);
    }

    private function isSectionHeading(string $line): bool
    {
        if (str_contains($line, ':') || str_contains($line, '.') || str_contains($line, '/')) {
            return false;
        }

        return str_word_count($line) <= 4 && mb_strlen($line) <= 40;
    }

    private function telegramHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function laneLabel(TelegramConversation $conversation): string
    {
        if ($conversation->chat_type === 'private') {
            return 'Main chat';
        }

        if ($conversation->topic_id) {
            return 'This topic';
        }

        return 'This chat';
    }

    private function responseModeLabel(TelegramConversation $conversation): string
    {
        if ($conversation->archived_at) {
            return 'Muted';
        }

        return match ($conversation->mode) {
            'free_response' => 'Whenever helpful',
            'observed' => 'Only when asked',
            'ignored' => 'Muted',
            default => 'When you ask',
        };
    }

    private function agentStatusSuffix(?string $status): string
    {
        return match ($status) {
            'working' => ' - working',
            'offline' => ' - offline',
            default => '',
        };
    }

    /**
     * Build the primary Telegram home card as a user-facing agent remote.
     * Internal lane IDs, raw modes, queue internals, and diagnostics belong in
     * admin surfaces; this card should answer only who will respond, where the
     * conversation lives, whether work is happening, and whether approvals wait.
     */
    public function commandCenterText(Workspace $workspace, TelegramConversation $conversation, bool $linked): string
    {
        $pendingApprovals = ApprovalRequest::where('status', 'pending')
            ->where(function ($query) use ($workspace) {
                $query->whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
                    ->orWhereHas('requester', fn ($user) => $user->where('workspace_id', $workspace->id));
            })
            ->count();
        $laneTasks = Task::where('workspace_id', $workspace->id)
            ->when($conversation->channel_id, fn ($query) => $query->where('channel_id', $conversation->channel_id))
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->count();
        $agentLabel = $conversation->defaultAgent?->name ?? 'your workspace agent';
        $workLabel = match (true) {
            $laneTasks === 0 => 'Nothing running',
            $laneTasks === 1 => '1 item in progress',
            default => "{$laneTasks} items in progress",
        };

        return implode("\n", [
            'OpenCompany',
            '',
            "{$agentLabel} is active",
            'Lane: '.$this->laneLabel($conversation),
            "Work: {$workLabel}",
            'Approvals: '.($pendingApprovals > 0 ? "{$pendingApprovals} waiting" : 'none'),
            '',
            $linked
                ? "Just send a message to work with {$agentLabel}."
                : 'Link your account to let Telegram act on your workspace.',
        ]);
    }

    public function statusText(Workspace $workspace, TelegramConversation $conversation): string
    {
        $taskQuery = Task::where('workspace_id', $workspace->id)
            ->when($conversation->channel_id, fn ($query) => $query->where('channel_id', $conversation->channel_id));
        $activeTasks = (clone $taskQuery)
            ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->latest()
            ->limit(3)
            ->get();
        $queuedTasks = (clone $taskQuery)->where('status', Task::STATUS_PENDING)->count();
        $pendingApprovals = ApprovalRequest::where('status', 'pending')
            ->where(function ($query) use ($workspace) {
                $query->whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
                    ->orWhereHas('requester', fn ($user) => $user->where('workspace_id', $workspace->id));
            })
            ->count();
        $agentLabel = $conversation->defaultAgent?->name ?? 'Your workspace agent';

        $lines = ['Status', ''];
        $lines[] = "{$agentLabel} is active in ".$this->laneLabel($conversation).'.';
        $lines[] = '';

        if ($activeTasks->isNotEmpty()) {
            $lines[] = 'Now';
            foreach ($activeTasks as $task) {
                $lines[] = '- '.$this->cardSnippet($task->title, 72);
            }
        } else {
            $lines[] = 'Now';
            $lines[] = '- Nothing running';
        }

        if ($queuedTasks > 0) {
            $lines[] = '';
            $lines[] = 'Waiting';
            $lines[] = '- '.$queuedTasks.' request'.($queuedTasks === 1 ? '' : 's');
        }

        $lines[] = '';
        $lines[] = 'Approvals';
        $lines[] = '- '.($pendingApprovals > 0 ? "{$pendingApprovals} waiting" : 'None');

        return implode("\n", $lines);
    }

    public function tasksText(Workspace $workspace): string
    {
        $active = Task::where('workspace_id', $workspace->id)
            ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->latest()
            ->limit(5)
            ->get();

        $pending = Task::where('workspace_id', $workspace->id)->where('status', Task::STATUS_PENDING)->count();
        $completedToday = Task::where('workspace_id', $workspace->id)
            ->where('status', Task::STATUS_COMPLETED)
            ->whereDate('completed_at', today())
            ->count();

        $lines = ['Tasks', ''];
        $lines[] = 'Active/paused: '.$active->count();
        $lines[] = "Pending: {$pending}";
        $lines[] = "Completed today: {$completedToday}";

        if ($active->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Top active work';
            foreach ($active as $task) {
                $lines[] = "- {$task->status}: {$task->title} (".Str::limit($task->id, 8, '').')';
            }
        }

        $lines[] = '';
        $lines[] = 'Use /task <id> for one task.';

        return implode("\n", $lines);
    }

    public function taskCardText(Task $task): string
    {
        $task->loadMissing(['agent', 'steps', 'workspace']);

        $agent = $task->agent?->name ?? 'The agent';
        $lines = [$this->taskCardTitle($task, $agent), ''];
        $lines[] = $this->cardSnippet($task->title ?: 'Untitled request', 140);
        $lines = [
            ...$lines,
            ...$this->taskBatchLines($task),
        ];
        if ($task->due_at) {
            $lines[] = 'Due '.$task->due_at->timezone((string) config('app.timezone', 'UTC'))->format('D M j H:i');
        }

        $lines = [
            ...$lines,
            ...$this->taskTimingLines($task),
            ...$this->taskFriendlyQueueLines($task),
            ...$this->taskStepSummaryLines($task),
            ...$this->taskProblemLines($task),
        ];

        return implode("\n", $lines);
    }

    private function taskCardTitle(Task $task, string $agent): string
    {
        return match ($task->status) {
            Task::STATUS_PENDING => "{$agent} queued this",
            Task::STATUS_ACTIVE => "{$agent} is working",
            Task::STATUS_PAUSED => "{$agent} paused this",
            Task::STATUS_COMPLETED => "{$agent} finished",
            Task::STATUS_FAILED => "{$agent} hit a problem",
            Task::STATUS_CANCELLED => "{$agent} stopped this",
            default => "{$agent} is handling this",
        };
    }

    /**
     * @return list<string>
     */
    private function taskBatchLines(Task $task): array
    {
        $context = is_array($task->context) ? $task->context : [];
        $batch = is_array($context['telegram_text_batch'] ?? null) ? $context['telegram_text_batch'] : [];
        if ($batch === []) {
            return [];
        }

        $ids = is_array($batch['message_ids'] ?? null) ? $batch['message_ids'] : [];
        $count = max((int) ($batch['message_count'] ?? 0), count($ids));
        if ($count < 2) {
            return [];
        }

        $lines = ["{$count} messages included"];
        $latest = trim((string) ($batch['last_part'] ?? ''));
        if ($latest !== '') {
            $lines[] = 'Latest: '.$this->cardSnippet($latest, 120);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function taskTimingLines(Task $task): array
    {
        if ($task->completed_at) {
            return ['', 'Finished '.$task->completed_at->diffForHumans()];
        }

        if ($task->started_at) {
            return ['', 'Working for '.$this->taskElapsedText($task)];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function taskFriendlyQueueLines(Task $task): array
    {
        $context = is_array($task->context) ? $task->context : [];
        $queue = is_array($context['telegram_lane_queue'] ?? null) ? $context['telegram_lane_queue'] : null;
        if (! $queue) {
            return [];
        }

        $openCount = max(0, (int) ($queue['open_count'] ?? 0));
        if ($openCount < 1) {
            return [];
        }

        return ['', 'Waiting behind other work.'];
    }

    /**
     * @return list<string>
     */
    private function taskStepSummaryLines(Task $task): array
    {
        $steps = $task->steps;
        if ($steps->isEmpty()) {
            return [];
        }

        $total = $steps->count();
        $completed = $steps->where('status', TaskStep::STATUS_COMPLETED)->count();
        $inProgress = $steps->firstWhere('status', TaskStep::STATUS_IN_PROGRESS);
        $current = $inProgress ?: $steps
            ->whereNotIn('status', [TaskStep::STATUS_COMPLETED, TaskStep::STATUS_SKIPPED])
            ->first();

        $lines = ['', "Progress: {$completed}/{$total} steps"];

        if ($current) {
            $lines[] = 'Current step: '.Str::limit($current->description, 120);
        } elseif ($latest = $steps->sortByDesc('created_at')->first()) {
            $lines[] = 'Latest step: '.Str::limit($latest->description, 120);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function taskProblemLines(Task $task): array
    {
        $lines = [];
        $context = is_array($task->context) ? $task->context : [];
        $result = is_array($task->result) ? $task->result : [];
        $blockedReason = $context['blocked_reason'] ?? $context['blockedReason'] ?? null;

        if (! $task->isClosed() && is_scalar($blockedReason) && trim((string) $blockedReason) !== '') {
            $lines[] = '';
            $lines[] = 'Needs attention: '.Str::limit((string) $blockedReason, 180);
        }

        $error = $result['error'] ?? null;
        if (is_scalar($error) && trim((string) $error) !== '') {
            if ($lines === []) {
                $lines[] = '';
            }
            $lines[] = 'Problem: '.Str::limit((string) $error, 180);
        }

        return $lines;
    }

    private function taskElapsedText(Task $task): string
    {
        $startedAt = $task->started_at;
        if (! $startedAt) {
            return 'not started';
        }

        $end = $task->completed_at ?: now();

        return $startedAt->diffForHumans($end, [
            'parts' => 2,
            'short' => true,
            'syntax' => Carbon::DIFF_ABSOLUTE,
        ]);
    }

    public function agentsText(Workspace $workspace): string
    {
        $agents = User::where('workspace_id', $workspace->id)
            ->where('type', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        if ($agents->isEmpty()) {
            return "Agents\n\nNo agents are configured in this workspace.";
        }

        $lines = ['Choose agent', ''];

        foreach ($agents->take(8) as $agent) {
            $lines[] = '- '.$this->cardSnippet($agent->name, 36).$this->agentStatusSuffix($agent->status);
        }

        if ($agents->count() > 8) {
            $lines[] = '- plus '.($agents->count() - 8).' more';
        }

        return implode("\n", $lines);
    }

    public function approvalsText(Workspace $workspace): string
    {
        $approvals = ApprovalRequest::with('requester')
            ->where('status', 'pending')
            ->where(function ($query) use ($workspace) {
                $query->whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
                    ->orWhereHas('requester', fn ($user) => $user->where('workspace_id', $workspace->id));
            })
            ->latest()
            ->limit(5)
            ->get();

        $lines = ['Approvals', ''];
        if ($approvals->isEmpty()) {
            $lines[] = 'No pending approvals.';

            return implode("\n", $lines);
        }

        foreach ($approvals as $approval) {
            $lines[] = '- '.$this->cardSnippet($approval->title, 72).' ('.Str::limit($approval->id, 8, '').')';
            $lines[] = '  From: '.$this->cardSnippet($approval->requester?->name ?? 'unknown', 36).' | Type: '.$approval->type;
        }

        return implode("\n", $lines);
    }

    public function activityText(Workspace $workspace): string
    {
        $messages = Message::whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
            ->with(['author', 'channel'])
            ->latest('timestamp')
            ->limit(3)
            ->get();

        $activeTasks = Task::where('workspace_id', $workspace->id)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->count();

        $pendingApprovals = ApprovalRequest::where('status', 'pending')
            ->where(function ($query) use ($workspace) {
                $query->whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
                    ->orWhereHas('requester', fn ($user) => $user->where('workspace_id', $workspace->id));
            })
            ->count();

        $lines = ['Activity', '', "Open tasks: {$activeTasks}", "Pending approvals: {$pendingApprovals}"];

        if ($messages->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Recent messages';
            foreach ($messages as $message) {
                $author = (string) ($message->author?->name ?? 'System');
                $channel = (string) ($message->channel?->name ?? 'channel');
                $content = $this->activitySnippet($message->content);
                $prefix = $this->activityPrefix($author, $channel);
                $lines[] = "- {$prefix}: {$content}";
            }
        }

        return implode("\n", $lines);
    }

    public function filesText(Workspace $workspace): string
    {
        $fileCount = WorkspaceFile::where('workspace_id', $workspace->id)->where('is_folder', false)->count();
        $folderCount = WorkspaceFile::where('workspace_id', $workspace->id)->where('is_folder', true)->count();
        $recent = WorkspaceFile::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->latest()
            ->limit(5)
            ->pluck('name');

        $lines = ['Files', '', "Files: {$fileCount}", "Folders: {$folderCount}"];
        if ($recent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Recent files';
            foreach ($recent as $name) {
                $lines[] = '- '.$this->fileCardName((string) $name);
            }
        }

        return implode("\n", $lines);
    }

    public function docsText(Workspace $workspace): string
    {
        $docCount = Document::where('workspace_id', $workspace->id)->where('is_folder', false)->count();
        $folderCount = Document::where('workspace_id', $workspace->id)->where('is_folder', true)->count();
        $recent = Document::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->latest()
            ->limit(5)
            ->pluck('title')
            ->unique()
            ->values();

        $lines = ['Docs', '', "Documents: {$docCount}", "Folders: {$folderCount}"];
        if ($recent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Recent docs';
            foreach ($recent as $title) {
                $lines[] = '- '.$this->cardSnippet((string) $title, 64);
            }
        }

        return implode("\n", $lines);
    }

    public function listsText(Workspace $workspace): string
    {
        $projectCount = ListItem::where('workspace_id', $workspace->id)->where('is_folder', true)->count();
        $openCount = ListItem::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->whereNull('completed_at')
            ->where('status', '!=', 'done')
            ->count();
        $doneCount = ListItem::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->where(fn ($query) => $query->whereNotNull('completed_at')->orWhere('status', 'done'))
            ->count();
        $recent = ListItem::where('workspace_id', $workspace->id)
            ->where('is_folder', false)
            ->with(['assignee', 'parent'])
            ->latest()
            ->limit(5)
            ->get();

        $lines = ['Lists', '', "Projects: {$projectCount}", "Open items: {$openCount}", "Done items: {$doneCount}"];
        if ($recent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Recent items';
            foreach ($recent as $item) {
                $parts = [$item->status];
                if ($item->parent?->title) {
                    $parts[] = $this->cardSnippet($item->parent->title, 24);
                }
                if ($item->assignee?->name) {
                    $parts[] = $this->cardSnippet($item->assignee->name, 20);
                }
                $lines[] = '- '.$this->cardSnippet($item->title, 58).' ('.implode(', ', $parts).')';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{title: string, description: string, status: string, priority: string, project_id: ?string, project_title: string}  $draft
     */
    public function listDraftText(array $draft): string
    {
        return implode("\n", [
            'List item draft',
            '',
            'Title: '.$draft['title'],
            'Project: '.$draft['project_title'],
            'Status: '.$draft['status'],
            'Priority: '.$draft['priority'],
            '',
            'Confirm before OpenCompany creates this item.',
        ]);
    }

    public function tablesText(Workspace $workspace): string
    {
        $tables = DataTable::where('workspace_id', $workspace->id)
            ->withCount(['columns', 'rows'])
            ->orderBy('name')
            ->limit(5)
            ->get();

        $tableCount = DataTable::where('workspace_id', $workspace->id)->count();
        $rowCount = $tables->sum('rows_count');

        $lines = ['Tables', '', "Tables: {$tableCount}", 'Rows shown: '.$rowCount];
        if ($tables->isEmpty()) {
            $lines[] = '';
            $lines[] = 'No data tables yet.';

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = 'Top tables';
        foreach ($tables as $table) {
            $lines[] = '- '.$this->cardSnippet($table->name, 42).": {$table->columns_count} columns | {$table->rows_count} rows";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{table_id: string, table_name: string, data: array<string, mixed>}  $draft
     */
    public function tableRowDraftText(array $draft): string
    {
        $lines = [
            'Table row draft',
            '',
            'Table: '.$draft['table_name'],
        ];

        foreach ($draft['data'] as $key => $value) {
            $lines[] = $key.': '.Str::limit((string) $value, 120);
        }

        $lines[] = '';
        $lines[] = 'Confirm before OpenCompany creates this row.';

        return implode("\n", $lines);
    }

    public function tableClarifyText(string $rowInput): string
    {
        return implode("\n", [
            'Choose table',
            '',
            'Row data: '.Str::limit($rowInput, 160),
            '',
            'OpenCompany needs a table before it can build the row draft.',
        ]);
    }

    public function workloadText(Workspace $workspace): string
    {
        $agents = User::where('workspace_id', $workspace->id)
            ->where('type', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $openTasks = Task::where('workspace_id', $workspace->id)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
            ->get(['agent_id', 'status']);

        $failedThisWeek = Task::where('workspace_id', $workspace->id)
            ->where('status', Task::STATUS_FAILED)
            ->where('updated_at', '>=', now()->subWeek())
            ->count();

        $lines = ['Workload', '', 'Open tasks: '.$openTasks->count(), "Failed this week: {$failedThisWeek}"];

        if ($agents->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Agents';
            foreach ($agents as $agent) {
                $count = $openTasks->where('agent_id', $agent->id)->count();
                $lines[] = "- {$agent->name}: {$count} open · {$agent->status}";
            }
        }

        return implode("\n", $lines);
    }

    public function dashboardText(Workspace $workspace): string
    {
        $status = app(WorkspaceStatusService::class)->gather($workspace->id);
        $pendingApprovals = ApprovalRequest::where('status', 'pending')
            ->where(function ($query) use ($workspace) {
                $query->whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
                    ->orWhereHas('requester', fn ($user) => $user->where('workspace_id', $workspace->id));
            })
            ->count();

        $automationFailures = Automation::where('workspace_id', $workspace->id)
            ->where('consecutive_failures', '>', 0)
            ->count();

        return implode("\n", [
            'Dashboard',
            '',
            "Agents online: {$status['agents_online']}/{$status['agents_total']}",
            "Active tasks: {$status['tasks_active']}",
            "Completed today: {$status['tasks_today']}",
            "Pending approvals: {$pendingApprovals}",
            "Messages today: {$status['messages_today']}",
            "Automation failures: {$automationFailures}",
        ]);
    }

    public function automationsText(Workspace $workspace): string
    {
        $query = Automation::where('workspace_id', $workspace->id)->with(['agent', 'workspace']);
        $active = (clone $query)->where('is_active', true)->count();
        $failing = (clone $query)->where('consecutive_failures', '>', 0)->count();
        $recent = (clone $query)->latest()->limit(5)->get();

        $lines = ['Automations', '', "Active: {$active}", "Failing: {$failing}"];
        if ($recent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Recent automations';
        }
        foreach ($recent as $automation) {
            $lines[] = '- '.$this->cardSnippet($automation->name, 58).' | '.($automation->is_active ? 'active' : 'inactive');
        }

        return implode("\n", $lines);
    }

    public function automationStatusText(Automation $automation): string
    {
        $automation->loadMissing(['agent', 'workspace']);

        $lines = [
            'Automation',
            '',
            "Name: {$automation->name}",
            'Status: '.($automation->is_active ? 'active' : 'inactive'),
            'Agent: '.($automation->agent?->name ?? 'unassigned'),
            "Schedule: {$automation->cron_expression}",
            "Timezone: {$automation->timezone}",
            "Runs: {$automation->run_count}",
            'Last: '.($automation->last_run_at?->diffForHumans() ?? 'never'),
            'Next: '.($automation->next_run_at?->diffForHumans() ?? 'not scheduled'),
            "Failures: {$automation->consecutive_failures}",
        ];

        $lastResult = is_array($automation->last_result) ? $automation->last_result : [];
        $lastError = $lastResult['error'] ?? null;
        if (is_scalar($lastError) && trim((string) $lastError) !== '') {
            $lines[] = 'Last error: '.Str::limit((string) $lastError, 160);
        }

        $body = $automation->isScript() ? $automation->script : $automation->prompt;
        if (is_string($body) && trim($body) !== '') {
            $lines[] = ($automation->isScript() ? 'Script' : 'Prompt').': '.Str::limit(trim($body), 180);
        }

        $url = $this->automationWebUrl($automation);
        if ($url !== null) {
            $lines[] = '';
            $lines[] = "Open: {$url}";
        }

        return implode("\n", $lines);
    }

    public function automationHistoryText(Workspace $workspace, Automation $automation): string
    {
        $runs = Task::where('workspace_id', $workspace->id)
            ->where('source', Task::SOURCE_AUTOMATION)
            ->where(function ($taskQuery) use ($automation) {
                $taskQuery->whereJsonContains('context->automation_id', $automation->id)
                    ->orWhereJsonContains('context->scheduled_automation_id', $automation->id);
            })
            ->latest()
            ->limit(5)
            ->get();

        $lines = ["Automation history: {$automation->name}", ''];
        if ($runs->isEmpty()) {
            $lines[] = 'No runs recorded yet.';
        }
        foreach ($runs as $run) {
            $lines[] = "- {$run->status}: ".$this->cardSnippet($run->title, 68).' | '.$run->created_at->diffForHumans();
        }

        return implode("\n", $lines);
    }

    public function automationFailuresText(Workspace $workspace): string
    {
        $failingAutomations = Automation::where('workspace_id', $workspace->id)
            ->with(['agent', 'workspace'])
            ->where('consecutive_failures', '>', 0)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $lines = ['Automation failures', ''];
        if ($failingAutomations->isEmpty()) {
            $lines[] = 'No failing automations.';
        }
        foreach ($failingAutomations as $automation) {
            $lines[] = '- '.$this->cardSnippet($automation->name, 68).": {$automation->consecutive_failures} failure(s)";
        }

        return implode("\n", $lines);
    }

    private function automationWebUrl(Automation $automation): ?string
    {
        $workspace = $automation->workspace;
        if (! $workspace) {
            return null;
        }

        return rtrim((string) config('app.url'), '/')."/w/{$workspace->slug}/automation/{$automation->id}/edit";
    }

    public function calendarText(Workspace $workspace, string $argument): string
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $mode = strtolower(trim($argument)) ?: 'upcoming';
        $now = Carbon::now($timezone);

        [$title, $rangeStart, $rangeEnd] = match ($mode) {
            'today' => ['Calendar today', $now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'tomorrow' => ['Calendar tomorrow', $now->copy()->addDay()->startOfDay(), $now->copy()->addDay()->endOfDay()],
            'week' => ['Calendar next 7 days', $now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()],
            default => ['Upcoming calendar', $now->copy(), $now->copy()->addDays(14)->endOfDay()],
        };

        $events = app(ManageCalendarEvents::class)
            ->list($workspace, $rangeStart, $rangeEnd)
            ->take(5);

        $lines = [$title, ''];
        if ($events->isEmpty()) {
            $lines[] = 'No matching calendar events.';

            return implode("\n", $lines);
        }

        foreach ($events as $event) {
            $lines[] = $this->formatCalendarEvent($event, $timezone);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{title: string, start_at: string, end_at: string, timezone: string}  $draft
     */
    public function calendarDraftText(array $draft): string
    {
        $start = Carbon::parse($draft['start_at'])->timezone($draft['timezone']);
        $end = Carbon::parse($draft['end_at'])->timezone($draft['timezone']);

        return implode("\n", [
            'Calendar draft',
            '',
            'Title: '.$draft['title'],
            'When: '.$start->format('D M j H:i').' - '.$end->format('H:i'),
            'Timezone: '.$draft['timezone'],
            '',
            'Confirm before creating this event.',
        ]);
    }

    private function formatCalendarEvent(CalendarEvent $event, string $timezone): string
    {
        $start = $event->start_at?->copy()->timezone($timezone);
        $time = $event->all_day
            ? $start?->format('D M j').' all day'
            : $start?->format('D M j H:i');

        $line = '- '.($time ?: 'Unscheduled').' · '.$event->title;
        if ($event->location) {
            $line .= ' @ '.$event->location;
        }

        return $this->cardSnippet(str_replace(' · ', ' | ', $line), 120);
    }

    public function digestUsageText(): string
    {
        return 'Usage: /digest daily, /digest weekly, /digest time HH:MM, or /digest off';
    }

    public function digestTimeText(string $time): string
    {
        return "Digest delivery time set to {$time}.";
    }

    public function digestModeText(string $mode): string
    {
        return $mode === 'off'
            ? 'Daily/weekly digest disabled for this Telegram lane.'
            : "Digest enabled: {$mode}.";
    }

    public function snoozeUsageText(): string
    {
        return 'Usage: /snooze 30m, /snooze 2h, /snooze 1d, /snooze tomorrow, or /snooze off';
    }

    public function snoozeClearedText(): string
    {
        return 'Telegram notifications resumed for this lane.';
    }

    public function snoozedText(Carbon $snoozedUntil): string
    {
        return 'Telegram notifications snoozed until '.$snoozedUntil->timezone((string) config('app.timezone', 'UTC'))->format('D M j H:i').'.';
    }

    public function notifyUsageText(): string
    {
        return 'Usage: /notify <event> <severity> [immediate|silent|digest|digest-only|batched|off]';
    }

    public function notifyModeText(string $eventType, string $severity, string $mode): string
    {
        return $mode === 'off'
            ? "Notifications disabled for {$eventType} ({$severity}) in this Telegram lane."
            : "Notifications enabled for {$eventType} ({$severity}) in {$mode} mode.";
    }

    /**
     * Collapse rich OpenCompany text into a single Telegram card row.
     *
     * Operational cards often include chat messages, document titles, and file
     * names produced elsewhere in the app. This keeps those snippets readable in
     * Telegram without leaking raw Markdown syntax, multi-line agent output, or
     * database-shaped UUID filenames into the chat bubble.
     */
    private function cardSnippet(?string $value, int $limit = 80): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/(^|\s)(#{1,6}\s*|>\s*)/u', ' ', $text) ?? $text;
        $text = str_replace(['**', '__', '`'], '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return 'empty';
        }

        return Str::limit($text, $limit);
    }

    private function activityPrefix(string $author, string $channel): string
    {
        $channel = preg_replace('/\s+Automation(?:\s+Smoke(?:\s+Test)?)?.*$/iu', '', $channel) ?? $channel;
        $channel = preg_replace('/\s+Smok(?:e)?$/iu', '', $channel) ?? $channel;
        $channel = str_replace('Mini App Browser', 'Mini App', $channel);
        $channel = trim($channel);

        if ($channel === '' || strcasecmp($channel, 'channel') === 0) {
            return $this->cardSnippet($author, 18);
        }

        if (strcasecmp($author, $channel) === 0 || str_starts_with(strtolower($channel), strtolower($author).' ')) {
            return $this->cardSnippet($author, 18);
        }

        return $this->cardSnippet($author.' / '.$channel, 24);
    }

    private function activitySnippet(?string $value): string
    {
        $text = $this->cardSnippet($value, 120);
        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]+/u', '', $text) ?? $text;
        $text = preg_replace('/\\s*-\\s*/u', ' - ', $text) ?? $text;
        $text = preg_replace('/\\s+/u', ' ', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B-");

        return $this->cardSnippet($text, 38);
    }

    private function fileCardName(string $name): string
    {
        $name = $this->cardSnippet($name, 96);
        $path = pathinfo($name);
        $filename = (string) ($path['filename'] ?? $name);
        $extension = strtolower((string) ($path['extension'] ?? ''));

        if (preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $filename) === 1) {
            $kind = $extension !== '' ? strtoupper($extension).' file' : 'File';

            return $kind.': '.substr($filename, 0, 8);
        }

        return $this->cardSnippet($name, 64);
    }

    public function settingsText(IntegrationSetting $setting, TelegramConversation $conversation): string
    {
        return implode("\n", [
            'Settings',
            '',
            'Agent: '.($conversation->defaultAgent?->name ?? $setting->getConfigValue('default_agent_id', 'workspace default')),
            'Lane: '.$this->laneLabel($conversation),
            'Replies: '.$this->responseModeLabel($conversation),
        ]);
    }

    public function helpText(): string
    {
        return implode("\n", [
            'OpenCompany Telegram',
            '',
            'Send a message to the active agent.',
            '',
            'Commands',
            '- /agents switch agent',
            '- /topic change lane behavior',
            '- /status see current work',
            '- /approvals review decisions',
            '',
            'Examples',
            '- Summarize the files I sent.',
            '- Create a doc from this voice note.',
            '- Add this to the launch checklist.',
        ]);
    }

    public function healthText(Workspace $workspace, IntegrationSetting $setting): string
    {
        $profile = TelegramIntegrationProfile::where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->first();

        $receivedToday = TelegramUpdateReceipt::where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->whereDate('received_at', today())
            ->count();

        $failedReceipts = TelegramUpdateReceipt::where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->where('status', 'failed')
            ->where('received_at', '>=', now()->subDay())
            ->count();

        $failedDeliveries = TelegramDelivery::where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $lines = [
            'Telegram health',
            '',
            'Bot: '.($profile?->bot_username ? '@'.$profile->bot_username : 'unknown'),
            'Webhook: '.($profile?->health_status ?? ($setting->getConfigValue('webhook_active') ? 'configured' : 'unknown')),
            'Commands: '.($profile?->command_sync_status ?? 'unknown'),
            'Pending updates: '.($profile?->pending_update_count ?? 'unknown'),
            'Receipts today: '.$receivedToday,
            'Failed receipts 24h: '.$failedReceipts,
            'Failed deliveries 24h: '.$failedDeliveries,
            'Last check: '.($profile?->last_health_checked_at?->diffForHumans() ?? 'never'),
        ];

        $diagnostics = $profile?->capabilities['diagnostics'] ?? [];
        if (is_array($diagnostics) && $diagnostics !== []) {
            $lines[] = '';
            $lines[] = 'Diagnostics';
            foreach (array_slice($diagnostics, 0, 5) as $diagnostic) {
                if (! is_array($diagnostic)) {
                    continue;
                }

                $code = (string) ($diagnostic['code'] ?? 'telegram_warning');
                $message = (string) ($diagnostic['message'] ?? '');
                $lines[] = "- {$code}: ".Str::limit($message, 120);
            }
        }

        return implode("\n", $lines);
    }

    public function linkedIdentityText(string $telegramUserId, User $linked): string
    {
        return "Telegram identity\n\n"
            ."Linked to {$linked->name}.";
    }

    public function identityLinkText(string $telegramUserId, string $url): string
    {
        return "Telegram identity\n\n"
            ."Open this link while signed in to OpenCompany to connect Telegram to your workspace account:\n{$url}\n\n"
            .'This link expires in 15 minutes.';
    }

    public function topicStatusText(TelegramConversation $conversation): string
    {
        return implode("\n", [
            'Lane',
            '',
            $this->laneLabel($conversation),
            'Agent: '.($conversation->defaultAgent?->name ?? 'workspace default'),
            'Replies: '.$this->responseModeLabel($conversation),
            'Memory: '.($conversation->observed_context_enabled ? 'on' : 'normal'),
        ]);
    }

    public function topicHelpText(): string
    {
        return 'Use /agents to switch agent or /topic free, /topic mention, /topic ignore.';
    }

    public function topicUnknownActionText(string $action): string
    {
        return "Unknown /topic action: {$action}\n\n".$this->topicHelpText();
    }

    public function topicLinkRequiredText(): string
    {
        return 'Link your Telegram account before changing this Telegram lane.';
    }

    public function topicModeUpdatedText(string $mode): string
    {
        return match ($mode) {
            'mention' => 'This lane now replies when you ask.',
            'free' => 'This lane can reply freely.',
            'observe' => 'This lane will remember context without replying unless asked.',
            'observe_disabled' => 'Context memory is not enabled for this workspace.',
            'ignore' => 'This lane is muted.',
            'archive' => 'This lane is archived.',
            default => 'Lane updated.',
        };
    }

    public function topicAgentUsageText(): string
    {
        return 'Usage: /topic agent <agent name or id>';
    }

    public function topicAgentNotFoundText(string $target): string
    {
        return "Agent not found: {$target}";
    }

    public function topicAgentUpdatedText(User $agent): string
    {
        return "{$agent->name} is now active in this lane.";
    }

    public function cancelText(int $count, bool $hasLane): string
    {
        if (! $hasLane) {
            return 'Nothing is running here.';
        }

        return $count > 0
            ? 'Stopped current work.'
            : 'Nothing is running here.';
    }

    public function resumeText(int $count, bool $hasLane): string
    {
        if (! $hasLane) {
            return 'No paused work here.';
        }

        return $count > 0
            ? 'Resumed paused work.'
            : 'No paused work here.';
    }

    /**
     * @param  list<WorkspaceFile>  $capturedFiles
     */
    public function mediaCaptureText(array $capturedFiles): string
    {
        $names = array_map(
            fn (WorkspaceFile $file): string => $file->name,
            array_slice($capturedFiles, 0, 4)
        );
        $queuedEnrichment = collect($capturedFiles)
            ->map(fn (WorkspaceFile $file) => is_array($file->metadata) ? ($file->metadata['telegram_enrichment'] ?? null) : null)
            ->filter(fn ($enrichment) => is_array($enrichment) && ($enrichment['status'] ?? null) === 'queued' && is_string($enrichment['type'] ?? null))
            ->pluck('type')
            ->countBy();

        $lines = array_merge([
            'Added to agent',
            '',
            count($capturedFiles).' attachment'.(count($capturedFiles) === 1 ? '' : 's').' ready',
        ], array_map(fn (string $name): string => '- '.$name, $names));

        if ($queuedEnrichment->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Processing';
            foreach ($queuedEnrichment as $type => $count) {
                $label = match ((string) $type) {
                    'description' => 'Photo understanding',
                    'transcription' => 'Voice transcript',
                    default => Str::headline((string) $type),
                };
                $lines[] = '- '.$label.' queued';
            }
        }

        $lines[] = '';
        $lines[] = 'Ask the agent what to do with it.';

        return implode("\n", $lines);
    }

    /**
     * Render the destination chooser shown after a Telegram media capture card.
     *
     * Folder selection is intentionally short and button-driven because typing
     * folder paths from Telegram is error-prone on mobile and in groups. The
     * webhook pipeline owns the opaque folder tokens and workspace validation;
     * this method only explains what will move and which destinations are shown.
     *
     * @param  list<WorkspaceFile>  $capturedFiles
     * @param  iterable<WorkspaceFile>  $folders
     */
    public function mediaFolderPickerText(array $capturedFiles, iterable $folders): string
    {
        $names = array_map(
            fn (WorkspaceFile $file): string => $file->name,
            array_slice($capturedFiles, 0, 4)
        );
        $folderNames = [];
        foreach ($folders as $folder) {
            $folderNames[] = $folder->getVirtualPath();
        }

        $lines = array_merge([
            'Move Telegram files',
            '',
            'Files: '.count($capturedFiles).' selected',
        ], array_map(fn (string $name): string => '- '.$name, $names));

        $lines[] = '';
        $lines[] = 'Choose a destination folder';
        $lines[] = '- Top level';

        foreach (array_slice($folderNames, 0, 6) as $name) {
            $lines[] = '- '.$name;
        }

        if ($folderNames === []) {
            $lines[] = '';
            $lines[] = 'No folders yet. Move to Top level or create folders in OpenCompany.';
        }

        return implode("\n", $lines);
    }

    /**
     * Render a compact picker for attaching captured Telegram files to a doc.
     *
     * The card deliberately shows only a recent bounded set. Deeper browsing
     * belongs in the Mini App, while this chat-native path should cover the
     * common "I just captured this, add it to the doc I am working on" case.
     *
     * @param  list<WorkspaceFile>  $capturedFiles
     * @param  iterable<Document>  $documents
     */
    public function mediaDocumentPickerText(array $capturedFiles, iterable $documents): string
    {
        $names = array_map(
            fn (WorkspaceFile $file): string => $file->name,
            array_slice($capturedFiles, 0, 4)
        );
        $documentTitles = [];
        foreach ($documents as $document) {
            $documentTitles[] = $document->title;
        }

        $lines = array_merge([
            'Add Telegram files to doc',
            '',
            'Files: '.count($capturedFiles).' selected',
        ], array_map(fn (string $name): string => '- '.$name, $names));

        $lines[] = '';
        $lines[] = 'Choose a recent document';

        foreach (array_slice($documentTitles, 0, 6) as $title) {
            $lines[] = '- '.$this->cardSnippet((string) $title, 72);
        }

        if ($documentTitles === []) {
            $lines[] = '- No documents found. Use New doc instead.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<list<array{text: string, callback_data?: string, url?: string, web_app?: array{url: string}}>>  $rows
     * @return array{inline_keyboard: list<list<array{text: string, callback_data?: string, url?: string, web_app?: array{url: string}}>>}
     */
    public function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }
}
