<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chat\Telegram\Application\TelegramMiniAppVerifier;
use App\Domain\Chat\Telegram\Application\TelegramOperationsService;
use App\Http\Controllers\Controller;
use App\Jobs\RunAutomationJob;
use App\Models\ApprovalRequest;
use App\Models\Automation;
use App\Models\Document;
use App\Models\IntegrationSetting;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\TelegramConversation;
use App\Models\TelegramDelivery;
use App\Models\TelegramIntegrationProfile;
use App\Models\TelegramSubscription;
use App\Models\TelegramUpdateReceipt;
use App\Models\User;
use App\Models\UserExternalIdentity;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Telegram Mini App session boundary.
 *
 * This endpoint is intentionally unauthenticated by Laravel session middleware:
 * Telegram Mini Apps authenticate with Bot API init data. The response is only
 * a bounded OpenCompany context after the signed Telegram user is linked to a
 * user who belongs to the requested workspace.
 */
class TelegramMiniAppController extends Controller
{
    public function workspaces(Request $request, TelegramMiniAppVerifier $verifier): JsonResponse
    {
        if (! config('telegram.mini_app_enabled', true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_mini_app_disabled',
            ], 404);
        }

        $data = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $verifiedSettings = $this->verifiedMiniAppSettings($data['init_data'], $verifier);
        if ($verifiedSettings === []) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_init_data',
            ], 401);
        }

        $telegramUserId = (string) $verifiedSettings[0]['telegram_user_id'];
        $identity = UserExternalIdentity::with('user')
            ->where('provider', 'telegram')
            ->where('external_id', $telegramUserId)
            ->first();

        if (! $identity || ! $identity->user) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_user_not_linked',
                'needs_link' => true,
                'telegram_user_id' => $telegramUserId,
            ], 403);
        }

        $verifiedWorkspaceIds = collect($verifiedSettings)
            ->pluck('setting.workspace_id')
            ->filter()
            ->unique()
            ->values();
        $memberships = WorkspaceMember::where('user_id', $identity->user_id)
            ->whereIn('workspace_id', $verifiedWorkspaceIds)
            ->get()
            ->keyBy('workspace_id');
        $workspaces = Workspace::whereIn('id', $memberships->keys())
            ->orderBy('name')
            ->get();

        if ($workspaces->isEmpty()) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_user_not_linked',
                'needs_link' => true,
                'telegram_user_id' => $telegramUserId,
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'telegram' => [
                'user_id' => $telegramUserId,
            ],
            'workspaces' => $workspaces
                ->map(function (Workspace $workspace) use ($memberships, $verifiedSettings) {
                    $verified = collect($verifiedSettings)
                        ->first(fn (array $verified) => $verified['setting']->workspace_id === $workspace->id);
                    $setting = $verified['setting'] ?? null;

                    return [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'slug' => $workspace->slug,
                        'role' => $memberships[$workspace->id]?->role,
                        'bot_username' => $setting instanceof IntegrationSetting
                            ? $setting->getConfigValue('bot_username')
                            : null,
                    ];
                })
                ->values(),
        ]);
    }

    public function session(Request $request, TelegramMiniAppVerifier $verifier): JsonResponse
    {
        if (! config('telegram.mini_app_enabled', true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_mini_app_disabled',
            ], 404);
        }

        $data = $request->validate([
            'workspace_id' => ['required', 'string', 'exists:workspaces,id'],
            'init_data' => ['required', 'string'],
        ]);

        $context = $this->resolveMiniAppContext($data['workspace_id'], $data['init_data'], $verifier);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $workspace = $context['workspace'];
        $identity = $context['identity'];
        $telegramUser = $context['telegram_user'];
        $telegramUserId = $context['telegram_user_id'];
        $verified = $context['verified'];

        return response()->json([
            'ok' => true,
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
            ],
            'user' => [
                'id' => $identity->user->id,
                'name' => $identity->user->name,
                'role' => $identity->user->currentWorkspaceRole($workspace),
            ],
            'telegram' => [
                'user_id' => $telegramUserId,
                'username' => $telegramUser['username'] ?? null,
                'start_param' => $verified['start_param'] ?? null,
                'chat_type' => $verified['chat_type'] ?? null,
            ],
            'panels' => [
                'identity',
                'settings',
                'approvals',
                'tasks',
                'files',
                'docs',
                'automation',
                'notifications',
            ],
        ]);
    }

    public function panel(Request $request, TelegramMiniAppVerifier $verifier): JsonResponse
    {
        if (! config('telegram.mini_app_enabled', true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_mini_app_disabled',
            ], 404);
        }

        $data = $request->validate([
            'workspace_id' => ['required', 'string', 'exists:workspaces,id'],
            'init_data' => ['required', 'string'],
            'panel' => ['required', 'string', 'in:identity,settings,approvals,tasks,files,docs,automation,notifications'],
            'target_id' => ['nullable', 'string'],
        ]);

        $context = $this->resolveMiniAppContext($data['workspace_id'], $data['init_data'], $verifier);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $workspace = $context['workspace'];
        $setting = $context['setting'];
        $targetId = $data['target_id'] ?? null;

        $payload = match ($data['panel']) {
            'identity' => $this->identityPanel($workspace, $context),
            'settings' => $this->settingsPanel($workspace, $setting, $context['identity']),
            'approvals' => $this->approvalsPanel($workspace, $targetId),
            'tasks' => $this->tasksPanel($workspace, $targetId),
            'files' => $this->filesPanel($workspace, $targetId),
            'docs' => $this->docsPanel($workspace, $targetId),
            'automation' => $this->automationPanel($workspace, $targetId),
            'notifications' => $this->notificationsPanel($workspace, $setting),
        };

        if ($payload === null) {
            return response()->json([
                'ok' => false,
                'error' => 'panel_target_not_found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'panel' => $data['panel'],
            'data' => $payload,
        ]);
    }

    public function action(Request $request, TelegramMiniAppVerifier $verifier): JsonResponse
    {
        if (! config('telegram.mini_app_enabled', true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_mini_app_disabled',
            ], 404);
        }

        $data = $request->validate([
            'workspace_id' => ['required', 'string', 'exists:workspaces,id'],
            'init_data' => ['required', 'string'],
            'action' => ['required', 'string', 'in:set_default_agent,set_conversation_mode,set_notification,set_digest,revoke_telegram_identity,repair_telegram_state,retry_telegram_delivery,replay_telegram_receipt,pause_task,resume_task,cancel_task,approve_approval,reject_approval,run_automation,pause_automation,resume_automation'],
            'conversation_id' => ['nullable', 'string'],
            'task_id' => ['nullable', 'string'],
            'approval_id' => ['nullable', 'string'],
            'automation_id' => ['nullable', 'string'],
            'delivery_id' => ['nullable', 'string'],
            'receipt_id' => ['nullable', 'string'],
            'identity_id' => ['nullable', 'string'],
            'repair_actions' => ['nullable', 'array'],
            'repair_actions.*' => ['string', 'in:expire_interactions,repair_conversations'],
            'agent_id' => ['nullable', 'string'],
            'mode' => ['nullable', 'string'],
            'observed_context_enabled' => ['nullable', 'boolean'],
            'event_type' => ['nullable', 'string'],
            'severity' => ['nullable', 'string'],
            'notification_mode' => ['nullable', 'string'],
            'schedule' => ['nullable', 'string'],
            'time' => ['nullable', 'string'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $context = $this->resolveMiniAppContext($data['workspace_id'], $data['init_data'], $verifier);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (! str_ends_with($data['action'], '_task')
            && ! str_ends_with($data['action'], '_approval')
            && ! str_ends_with($data['action'], '_automation')
            && ! $this->canManageTelegramSettings($context)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_admin_required',
            ], 403);
        }

        $workspace = $context['workspace'];
        $setting = $context['setting'];

        $payload = match ($data['action']) {
            'set_default_agent' => $this->setMiniAppDefaultAgent($workspace, $setting, $data),
            'set_conversation_mode' => $this->setMiniAppConversationMode($workspace, $setting, $data),
            'set_notification' => $this->setMiniAppNotification($workspace, $setting, $data),
            'set_digest' => $this->setMiniAppDigest($workspace, $setting, $data),
            'revoke_telegram_identity' => $this->revokeMiniAppTelegramIdentity($workspace, $context, $data),
            'repair_telegram_state' => $this->repairMiniAppTelegramState($setting, $data),
            'retry_telegram_delivery' => $this->retryMiniAppTelegramDelivery($setting, $data),
            'replay_telegram_receipt' => $this->replayMiniAppTelegramReceipt($setting, $data),
            'pause_task', 'resume_task', 'cancel_task' => $this->setMiniAppTaskLifecycle($workspace, $data),
            'approve_approval', 'reject_approval' => $this->setMiniAppApprovalDecision($workspace, $context, $data),
            'run_automation', 'pause_automation', 'resume_automation' => $this->setMiniAppAutomationState($workspace, $data),
        };

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        return response()->json([
            'ok' => true,
            'action' => $data['action'],
            'data' => $payload,
        ]);
    }

    /**
     * Identify Telegram integration settings whose bot token signed this Mini
     * App launch. Workspace selection cannot trust a caller-supplied workspace
     * id, so it verifies the Telegram init data against enabled Telegram bot
     * tokens first and only then intersects the signed Telegram user with their
     * OpenCompany workspace memberships.
     *
     * @return list<array{setting: IntegrationSetting, telegram_user_id: string, verified: array<string, mixed>}>
     */
    private function verifiedMiniAppSettings(string $initData, TelegramMiniAppVerifier $verifier): array
    {
        $matches = [];

        foreach (IntegrationSetting::where('integration_id', 'telegram')->where('enabled', true)->get() as $setting) {
            $token = $setting->getConfigValue('api_key');
            if (! is_string($token) || $token === '') {
                continue;
            }

            try {
                $verified = $verifier->verify($initData, $token);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $telegramUser = is_array($verified['user'] ?? null) ? $verified['user'] : [];
            $telegramUserId = isset($telegramUser['id']) ? (string) $telegramUser['id'] : '';
            if ($telegramUserId === '') {
                continue;
            }

            $matches[] = [
                'setting' => $setting,
                'telegram_user_id' => $telegramUserId,
                'verified' => $verified,
            ];
        }

        return $matches;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function resolveMiniAppContext(string $workspaceId, string $initData, TelegramMiniAppVerifier $verifier): array|JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $setting = IntegrationSetting::where('workspace_id', $workspace->id)
            ->where('integration_id', 'telegram')
            ->where('enabled', true)
            ->default()
            ->first();

        if (! $setting || ! is_string($setting->getConfigValue('api_key')) || $setting->getConfigValue('api_key') === '') {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_not_configured',
            ], 422);
        }

        try {
            $verified = $verifier->verify($initData, (string) $setting->getConfigValue('api_key'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_init_data',
                'message' => $e->getMessage(),
            ], 401);
        }

        $telegramUser = is_array($verified['user'] ?? null) ? $verified['user'] : [];
        $telegramUserId = isset($telegramUser['id']) ? (string) $telegramUser['id'] : '';
        if ($telegramUserId === '') {
            return response()->json([
                'ok' => false,
                'error' => 'missing_telegram_user',
            ], 422);
        }

        $identity = UserExternalIdentity::with('user')
            ->where('provider', 'telegram')
            ->where('external_id', $telegramUserId)
            ->first();

        if (! $identity || ! $identity->user || ! $this->belongsToWorkspace($identity->user_id, $workspace)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_user_not_linked',
                'needs_link' => true,
                'telegram_user_id' => $telegramUserId,
            ], 403);
        }

        return [
            'workspace' => $workspace,
            'setting' => $setting,
            'identity' => $identity,
            'telegram_user' => $telegramUser,
            'telegram_user_id' => $telegramUserId,
            'verified' => $verified,
        ];
    }

    private function settingsPanel(Workspace $workspace, IntegrationSetting $setting, ?UserExternalIdentity $currentIdentity = null): array
    {
        $profile = TelegramIntegrationProfile::with('defaultAgent')
            ->where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->first();
        $workspaceMemberIds = WorkspaceMember::where('workspace_id', $workspace->id)->pluck('user_id');
        $operations = app(TelegramOperationsService::class);

        return [
            'type' => 'settings',
            'integration_id' => $setting->id,
            'enabled' => $setting->enabled,
            'bot_username' => $profile?->bot_username ?? $setting->getConfigValue('bot_username'),
            'bot_id' => $profile?->bot_id ?? $setting->getConfigValue('bot_user_id'),
            'webhook_url' => $profile?->webhook_url,
            'health_status' => $profile?->health_status ?? 'unknown',
            'last_health_error' => $profile?->last_health_error,
            'last_health_checked_at' => $profile?->last_health_checked_at,
            'pending_update_count' => $profile?->pending_update_count,
            'command_sync_status' => $profile?->command_sync_status,
            'profile_sync_status' => $profile?->profile_sync_status,
            'default_mode' => $profile?->default_mode ?? $setting->getConfigValue('default_mode', 'command_center'),
            'default_agent' => $profile?->defaultAgent ? [
                'id' => $profile->defaultAgent->id,
                'name' => $profile->defaultAgent->name,
            ] : null,
            'allowed_updates' => $profile?->allowed_updates ?? $setting->getConfigValue('allowed_updates', []),
            'diagnostics' => $this->profileDiagnostics($profile),
            'feature_flags' => $this->telegramFeatureFlags(),
            'linked_identities_count' => UserExternalIdentity::where('provider', 'telegram')
                ->whereIn('user_id', $workspaceMemberIds)
                ->count(),
            'linked_identities' => $this->telegramIdentitySummaries($workspace, $currentIdentity),
            'conversations' => TelegramConversation::where('workspace_id', $workspace->id)
                ->where('integration_setting_id', $setting->id)
                ->whereNull('archived_at')
                ->count(),
            'recent_lanes' => $this->conversationSummaries($workspace, $setting),
            'available_agents' => $this->availableAgents($workspace),
            'operations' => $operations->metrics($setting),
            'operations_logs' => $operations->logs($setting, 6),
            'repair_candidates' => $operations->repairCandidates($setting),
        ];
    }

    /**
     * @return list<array{id: string, name: string, status: ?string}>
     */
    private function availableAgents(Workspace $workspace): array
    {
        return User::where('workspace_id', $workspace->id)
            ->where('type', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (User $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $agent->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function profileDiagnostics(?TelegramIntegrationProfile $profile): array
    {
        $diagnostics = $profile?->capabilities['diagnostics'] ?? [];

        return collect(is_array($diagnostics) ? $diagnostics : [])
            ->filter(fn ($diagnostic) => is_array($diagnostic))
            ->map(fn (array $diagnostic) => [
                'code' => (string) ($diagnostic['code'] ?? 'unknown'),
                'severity' => (string) ($diagnostic['severity'] ?? 'info'),
                'message' => Str::limit((string) ($diagnostic['message'] ?? ''), 300),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function telegramFeatureFlags(): array
    {
        return [
            'experience_layer' => (bool) config('telegram.experience_layer'),
            'legacy_webhook_enabled' => (bool) config('telegram.legacy_webhook_enabled'),
            'private_topics_enabled' => (bool) config('telegram.private_topics_enabled'),
            'media_ingestion_enabled' => (bool) config('telegram.media_ingestion_enabled'),
            'draft_streaming_enabled' => (bool) config('telegram.draft_streaming_enabled'),
            'mini_app_enabled' => (bool) config('telegram.mini_app_enabled'),
            'group_observer_enabled' => (bool) config('telegram.group_observer_enabled'),
            'guest_mode_enabled' => (bool) config('telegram.guest_mode_enabled'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationSummaries(Workspace $workspace, IntegrationSetting $setting): array
    {
        return TelegramConversation::with(['channel:id,name', 'defaultAgent:id,name'])
            ->where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->whereNull('archived_at')
            ->latest('last_seen_at')
            ->limit(10)
            ->get()
            ->map(fn (TelegramConversation $conversation) => [
                'id' => $conversation->id,
                'chat_id' => $conversation->chat_id,
                'chat_type' => $conversation->chat_type,
                'title' => $conversation->title,
                'topic_id' => $conversation->topic_id,
                'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
                'mode' => $conversation->mode,
                'observed_context_enabled' => $conversation->observed_context_enabled,
                'channel' => $conversation->channel?->name,
                'default_agent' => $conversation->defaultAgent?->name,
                'last_seen_at' => $conversation->last_seen_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function identityPanel(Workspace $workspace, array $context): array
    {
        $identity = $context['identity'];
        $telegramUser = $context['telegram_user'];

        return [
            'type' => 'identity',
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'user' => [
                'id' => $identity->user->id,
                'name' => $identity->user->name,
                'role' => $identity->user->currentWorkspaceRole($workspace),
            ],
            'telegram' => [
                'user_id' => $identity->external_id,
                'username' => $telegramUser['username'] ?? null,
                'display_name' => $identity->display_name,
            ],
        ];
    }

    /**
     * Return Telegram identity links visible to the current workspace.
     *
     * OpenCompany's identity table is global by provider/external id, so the
     * Mini App deliberately intersects links through workspace membership
     * before showing or mutating them. This prevents an admin in one workspace
     * from discovering or revoking a Telegram link that belongs only elsewhere.
     *
     * @return list<array<string, mixed>>
     */
    private function telegramIdentitySummaries(Workspace $workspace, ?UserExternalIdentity $currentIdentity = null): array
    {
        $memberships = WorkspaceMember::where('workspace_id', $workspace->id)
            ->get(['user_id', 'role'])
            ->keyBy('user_id');

        return UserExternalIdentity::with('user:id,name')
            ->where('provider', 'telegram')
            ->whereIn('user_id', $memberships->keys())
            ->orderBy('display_name')
            ->orderBy('external_id')
            ->get()
            ->map(fn (UserExternalIdentity $identity) => [
                'id' => $identity->id,
                'telegram_user_id' => $identity->external_id,
                'display_name' => $identity->display_name,
                'user' => [
                    'id' => $identity->user_id,
                    'name' => $identity->user?->name,
                    'role' => $memberships[$identity->user_id]?->role,
                ],
                'is_current' => $currentIdentity?->id === $identity->id,
                'can_revoke' => $currentIdentity?->id !== $identity->id,
            ])
            ->sortBy(fn (array $identity) => ($identity['is_current'] ? '0' : '1').'|'.strtolower((string) ($identity['display_name'] ?? '')).'|'.$identity['telegram_user_id'])
            ->values()
            ->all();
    }

    private function approvalsPanel(Workspace $workspace, ?string $targetId): ?array
    {
        $query = $this->approvalQuery($workspace)->with(['requester', 'respondedBy']);

        if ($targetId) {
            $approval = (clone $query)->where('id', $targetId)->first();

            return $approval ? $this->approvalDetail($workspace, $approval) : null;
        }

        $approvals = $query
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get();

        return [
            'type' => 'approval_list',
            'pending_count' => $approvals->count(),
            'items' => $approvals->map(fn (ApprovalRequest $approval) => [
                'id' => $approval->id,
                'title' => $approval->title,
                'type' => $approval->type,
                'requester' => $approval->requester?->name,
                'amount' => $approval->amount,
                'audit_id' => Str::limit($approval->id, 12, ''),
            ])->values(),
        ];
    }

    private function approvalDetail(Workspace $workspace, ApprovalRequest $approval): array
    {
        $context = $approval->tool_execution_context ?? [];

        return [
            'type' => 'approval_detail',
            'id' => $approval->id,
            'title' => $approval->title,
            'description' => $approval->description,
            'status' => $approval->status,
            'requester' => $approval->requester?->name,
            'responded_by' => $approval->respondedBy?->name,
            'responded_at' => $approval->responded_at,
            'amount' => $approval->amount,
            'tool' => $this->toolName($context),
            'arguments' => $this->argumentSummary($context['parameters'] ?? []),
            'audit_id' => Str::limit($approval->id, 12, ''),
            'web_url' => "/w/{$workspace->slug}/approvals/{$approval->id}",
            'actions' => $approval->status === 'pending' ? ['approve', 'reject', 'open'] : ['open'],
        ];
    }

    private function approvalQuery(Workspace $workspace)
    {
        return ApprovalRequest::query()
            ->where(fn ($query) => $query
                ->whereHas('channel', fn ($channel) => $channel->where('workspace_id', $workspace->id))
                ->orWhereHas('requester', fn ($requester) => $requester->where('workspace_id', $workspace->id)));
    }

    /**
     * Resolve an approval from the signed Mini App session and apply the same
     * decision semantics as the web API and Telegram callback buttons. The
     * client can suggest an approval id, but the server rechecks workspace scope,
     * pending state, responder identity, and the approval execution side effects.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppApprovalDecision(Workspace $workspace, array $context, array $data): array|JsonResponse
    {
        $approvalId = (string) ($data['approval_id'] ?? '');
        if ($approvalId === '') {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_approval_not_found',
            ], 404);
        }

        $approval = $this->approvalQuery($workspace)
            ->with(['requester', 'respondedBy', 'channel'])
            ->where('id', $approvalId)
            ->first();

        if (! $approval) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_approval_not_found',
            ], 404);
        }

        if ($approval->status !== 'pending') {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_approval_already_resolved',
                'status' => $approval->status,
            ], 422);
        }

        $identity = $context['identity'];
        $status = $data['action'] === 'approve_approval' ? 'approved' : 'rejected';
        $approval->update([
            'status' => $status,
            'responded_by_id' => $identity->user_id,
            'responded_at' => now(),
        ]);

        $agent = $approval->requester;
        $agentIsWaiting = $agent
            && $agent->type === 'agent'
            && $agent->awaiting_approval_id === $approval->id;
        $approvalService = app(ApprovalExecutionService::class);

        if ($status === 'approved' && $approval->tool_execution_context) {
            if ($approval->type === 'access') {
                $approvalService->executeApprovedAccess($approval, $agentIsWaiting);
            } else {
                $approvalService->executeApprovedTool($approval, $agentIsWaiting);
            }
        } elseif ($status === 'rejected' && $agentIsWaiting) {
            $approvalService->handleRejectedTool($approval);
        }

        return [
            'approval' => $this->approvalDetail($workspace, $approval->fresh(['requester', 'respondedBy'])),
        ];
    }

    private function tasksPanel(Workspace $workspace, ?string $targetId): ?array
    {
        if ($targetId) {
            $task = Task::with(['agent', 'requester', 'channel', 'steps'])
                ->where('workspace_id', $workspace->id)
                ->where(fn ($query) => $query->where('id', $targetId)->orWhere('id', 'like', $targetId.'%'))
                ->first();

            if (! $task) {
                return null;
            }

            return $this->taskDetail($workspace, $task);
        }

        $tasks = Task::with('agent')
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->limit(12)
            ->get();

        return [
            'type' => 'task_list',
            'active_count' => Task::where('workspace_id', $workspace->id)->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PAUSED])->count(),
            'pending_count' => Task::where('workspace_id', $workspace->id)->where('status', Task::STATUS_PENDING)->count(),
            'failed_count' => Task::where('workspace_id', $workspace->id)->where('status', Task::STATUS_FAILED)->count(),
            'items' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'agent' => $task->agent?->name,
                'source' => $task->source,
                'updated_at' => $task->updated_at,
            ])->values(),
        ];
    }

    private function taskDetail(Workspace $workspace, Task $task): array
    {
        $steps = $task->steps;
        $currentStep = $steps
            ->first(fn (TaskStep $step) => $step->status === TaskStep::STATUS_IN_PROGRESS)
            ?: $steps->last();

        $startedAt = $task->started_at ?: $task->created_at;
        $finishedAt = $task->completed_at ?: now();

        return [
            'type' => 'task_detail',
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description ? Str::limit($task->description, 500) : null,
            'status' => $task->status,
            'priority' => $task->priority,
            'agent' => $task->agent?->name,
            'requester' => $task->requester?->name,
            'channel' => $task->channel?->name,
            'source' => $task->source,
            'created_at' => $task->created_at,
            'started_at' => $task->started_at,
            'completed_at' => $task->completed_at,
            'elapsed_seconds' => $startedAt ? max(0, $startedAt->diffInSeconds($finishedAt)) : null,
            'web_url' => "/w/{$workspace->slug}/tasks/{$task->id}",
            'step_counts' => [
                'total' => $steps->count(),
                'pending' => $steps->where('status', TaskStep::STATUS_PENDING)->count(),
                'in_progress' => $steps->where('status', TaskStep::STATUS_IN_PROGRESS)->count(),
                'completed' => $steps->where('status', TaskStep::STATUS_COMPLETED)->count(),
                'skipped' => $steps->where('status', TaskStep::STATUS_SKIPPED)->count(),
            ],
            'current_step' => $currentStep ? $this->taskStepSummary($currentStep) : null,
            'steps' => $steps
                ->take(20)
                ->map(fn (TaskStep $step) => $this->taskStepSummary($step))
                ->values(),
            'context' => $this->argumentSummary($task->context ?? []),
            'result' => $this->argumentSummary($task->result ?? []),
            'actions' => $this->taskActions($task),
        ];
    }

    private function taskStepSummary(TaskStep $step): array
    {
        return [
            'id' => $step->id,
            'description' => Str::limit($step->description, 280),
            'status' => $step->status,
            'type' => $step->step_type,
            'started_at' => $step->started_at,
            'completed_at' => $step->completed_at,
            'metadata' => $this->argumentSummary($step->metadata ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private function taskActions(Task $task): array
    {
        return match ($task->status) {
            Task::STATUS_ACTIVE, Task::STATUS_PENDING => ['pause', 'cancel', 'open'],
            Task::STATUS_PAUSED => ['resume', 'cancel', 'open'],
            default => ['open'],
        };
    }

    /**
     * Mini App task controls intentionally mirror the workspace task API's
     * lifecycle transitions while adding Telegram-specific scoping and state
     * guards. Telegram init data identifies a linked workspace member, but the
     * task still has to be reloaded from the requested workspace before any
     * mutation happens.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppTaskLifecycle(Workspace $workspace, array $data): array|JsonResponse
    {
        $taskId = (string) ($data['task_id'] ?? $data['target_id'] ?? '');
        if ($taskId === '') {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_task_not_found',
            ], 404);
        }

        $task = Task::with(['agent', 'requester', 'channel', 'steps'])
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $query->where('id', $taskId)->orWhere('id', 'like', $taskId.'%'))
            ->first();

        if (! $task) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_task_not_found',
            ], 404);
        }

        $action = (string) $data['action'];
        $allowed = match ($action) {
            'pause_task' => in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_ACTIVE], true),
            'resume_task' => $task->status === Task::STATUS_PAUSED,
            'cancel_task' => ! in_array($task->status, [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED], true),
            default => false,
        };

        if (! $allowed) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_invalid_task_transition',
                'status' => $task->status,
            ], 422);
        }

        match ($action) {
            'pause_task' => $task->pause(),
            'resume_task' => $task->resume(),
            'cancel_task' => $task->cancel(),
        };

        return [
            'task' => $this->taskDetail($workspace, $task->fresh(['agent', 'requester', 'channel', 'steps'])),
        ];
    }

    private function filesPanel(Workspace $workspace, ?string $targetId): ?array
    {
        if ($targetId) {
            $file = WorkspaceFile::with('parent')
                ->where('workspace_id', $workspace->id)
                ->where('id', $targetId)
                ->first();

            if (! $file) {
                return null;
            }

            return [
                'type' => 'file_detail',
                'id' => $file->id,
                'name' => $file->name,
                'path' => $file->getVirtualPath(),
                'is_folder' => $file->is_folder,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'previewable' => $file->isPreviewable(),
                'download_url' => $file->is_folder ? null : "/api/files/{$file->id}/download",
                'source' => $file->metadata['source'] ?? null,
            ];
        }

        $files = WorkspaceFile::where('workspace_id', $workspace->id)
            ->latest()
            ->limit(20)
            ->get();

        return [
            'type' => 'file_list',
            'total_count' => WorkspaceFile::where('workspace_id', $workspace->id)->count(),
            'items' => $files->map(fn (WorkspaceFile $file) => [
                'id' => $file->id,
                'name' => $file->name,
                'is_folder' => $file->is_folder,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'previewable' => $file->isPreviewable(),
                'source' => $file->metadata['source'] ?? null,
            ])->values(),
        ];
    }

    /**
     * Return a compact Mini App document picker/detail surface.
     *
     * Telegram chat cards intentionally stay terse, but the Mini App can expose
     * enough document metadata for phone-side inspection before jumping to the
     * full web editor. The query is still workspace-scoped and never returns
     * document permission internals, system flags beyond a boolean marker, or
     * content outside the selected document.
     */
    private function docsPanel(Workspace $workspace, ?string $targetId): ?array
    {
        if ($targetId) {
            $document = Document::with(['parent:id,title', 'author:id,name'])
                ->where('workspace_id', $workspace->id)
                ->where(fn ($query) => $query->where('id', $targetId)->orWhere('id', 'like', $targetId.'%'))
                ->first();

            if (! $document) {
                return null;
            }

            return [
                'type' => 'document_detail',
                'id' => $document->id,
                'title' => $document->title,
                'is_folder' => $document->is_folder,
                'is_system' => $document->is_system,
                'parent' => $document->parent?->title,
                'author' => $document->author?->name,
                'content_format' => $document->content_format ?? 'markdown',
                'content_excerpt' => $document->is_folder ? null : Str::limit(strip_tags((string) $document->content), 1200),
                'children_count' => $document->children()->count(),
                'attachments_count' => $document->attachments()->count(),
                'comments_count' => $document->comments()->count(),
                'updated_at' => $document->updated_at,
                'web_url' => "/w/{$workspace->slug}/docs?document={$document->id}",
                'actions' => ['open'],
            ];
        }

        $documents = Document::with(['parent:id,title', 'author:id,name'])
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->limit(20)
            ->get();

        return [
            'type' => 'document_list',
            'total_count' => Document::where('workspace_id', $workspace->id)->count(),
            'folder_count' => Document::where('workspace_id', $workspace->id)->where('is_folder', true)->count(),
            'items' => $documents->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'is_folder' => $document->is_folder,
                'is_system' => $document->is_system,
                'parent' => $document->parent?->title,
                'author' => $document->author?->name,
                'type' => $document->is_folder ? 'folder' : ($document->content_format ?? 'document'),
                'updated_at' => $document->updated_at,
            ])->values(),
        ];
    }

    private function automationPanel(Workspace $workspace, ?string $targetId): ?array
    {
        $query = Automation::with('agent')
            ->where('workspace_id', $workspace->id);

        if ($targetId) {
            $automation = (clone $query)
                ->where(fn ($query) => $query
                    ->where('id', $targetId)
                    ->orWhere('id', 'like', $targetId.'%')
                    ->orWhere('name', 'like', $targetId.'%'))
                ->first();

            if (! $automation) {
                return null;
            }

            return $this->automationDetail($workspace, $automation);
        }

        $automations = $query
            ->latest()
            ->limit(20)
            ->get();

        return [
            'type' => 'automation_list',
            'active_count' => Automation::where('workspace_id', $workspace->id)->where('is_active', true)->count(),
            'failed_count' => Automation::where('workspace_id', $workspace->id)->where('consecutive_failures', '>', 0)->count(),
            'items' => $automations->map(fn (Automation $automation) => [
                'id' => $automation->id,
                'name' => $automation->name,
                'agent' => $automation->agent?->name,
                'is_active' => $automation->is_active,
                'next_run_at' => $automation->next_run_at,
                'consecutive_failures' => $automation->consecutive_failures,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function automationDetail(Workspace $workspace, Automation $automation): array
    {
        return [
            'type' => 'automation_detail',
            'id' => $automation->id,
            'name' => $automation->name,
            'description' => $automation->description,
            'agent' => $automation->agent?->name,
            'trigger_type' => $automation->trigger_type,
            'execution_type' => $automation->execution_type,
            'is_active' => $automation->is_active,
            'cron_expression' => $automation->cron_expression,
            'timezone' => $automation->timezone,
            'last_run_at' => $automation->last_run_at,
            'next_run_at' => $automation->next_run_at,
            'run_count' => $automation->run_count,
            'consecutive_failures' => $automation->consecutive_failures,
            'last_error' => is_array($automation->last_result) ? Str::limit((string) ($automation->last_result['error'] ?? ''), 300) : null,
            'web_url' => "/w/{$workspace->slug}/automation/{$automation->id}/edit",
            'actions' => [
                'run',
                $automation->is_active ? 'pause' : 'resume',
                'open',
            ],
            'recent_runs' => $this->automationRuns($workspace, $automation),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function automationRuns(Workspace $workspace, Automation $automation): array
    {
        return Task::with('agent')
            ->where('workspace_id', $workspace->id)
            ->where('source', Task::SOURCE_AUTOMATION)
            ->where(fn ($query) => $query
                ->whereJsonContains('context->automation_id', $automation->id)
                ->orWhereJsonContains('context->scheduled_automation_id', $automation->id))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'agent' => $task->agent?->name,
                'created_at' => $task->created_at,
                'completed_at' => $task->completed_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Mini App automation controls intentionally expose only coarse lifecycle
     * actions. Raw scripts, prompts, and broad edits stay in the web app; the
     * Telegram surface can dispatch a run or toggle the schedule after the
     * signed Telegram user has been bound to the workspace.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppAutomationState(Workspace $workspace, array $data): array|JsonResponse
    {
        $automationId = (string) ($data['automation_id'] ?? '');
        if ($automationId === '') {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_automation_not_found',
            ], 404);
        }

        $automation = Automation::with('agent')
            ->where('workspace_id', $workspace->id)
            ->where(fn ($query) => $query
                ->where('id', $automationId)
                ->orWhere('id', 'like', $automationId.'%')
                ->orWhere('name', 'like', $automationId.'%'))
            ->first();

        if (! $automation) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_automation_not_found',
            ], 404);
        }

        match ($data['action']) {
            'run_automation' => RunAutomationJob::dispatch($automation),
            'pause_automation' => $automation->update(['is_active' => false]),
            'resume_automation' => $automation->update([
                'is_active' => true,
                'consecutive_failures' => 0,
                'next_run_at' => $automation->computeNextRunAt(),
            ]),
        };

        return [
            'automation' => $this->automationDetail($workspace, $automation->fresh('agent')),
        ];
    }

    private function notificationsPanel(Workspace $workspace, IntegrationSetting $setting): array
    {
        $subscriptions = TelegramSubscription::where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->latest()
            ->limit(30)
            ->get();

        return [
            'type' => 'notifications',
            'enabled_count' => $subscriptions->where('enabled', true)->count(),
            'items' => $subscriptions->map(fn (TelegramSubscription $subscription) => [
                'id' => $subscription->id,
                'scope_type' => $subscription->scope_type,
                'scope_id' => $subscription->scope_id,
                'event_type' => $subscription->event_type,
                'severity' => $subscription->severity,
                'schedule' => $subscription->schedule,
                'timezone' => $subscription->timezone,
                'enabled' => $subscription->enabled,
                'filters' => $subscription->filters ?? [],
            ])->values(),
        ];
    }

    /**
     * Run the Mini App's safe Telegram repair path.
     *
     * Phone-side operations deliberately exclude webhook repair because that
     * action reaches Telegram's Bot API and can replace the configured webhook.
     * The Mini App only closes expired local callback tokens and repairs local
     * conversation-to-channel mappings; full webhook resync remains in the web
     * admin operations endpoint where operators have a larger diagnostics view.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function repairMiniAppTelegramState(IntegrationSetting $setting, array $data): array
    {
        $requested = collect($data['repair_actions'] ?? [])
            ->filter(fn ($action) => is_string($action))
            ->intersect(['expire_interactions', 'repair_conversations'])
            ->values()
            ->all();
        $operations = app(TelegramOperationsService::class);

        return [
            'repair' => $operations->repairLocalState($setting, $requested),
            'metrics' => $operations->metrics($setting),
            'repair_candidates' => $operations->repairCandidates($setting),
        ];
    }

    /**
     * Revoke a workspace-scoped Telegram identity link from the Mini App.
     *
     * This is intentionally limited to non-current identities. Removing the
     * signed actor's own Telegram link would invalidate the Mini App session
     * mid-request and is better handled from the authenticated web settings
     * screen where normal browser auth can recover cleanly.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function revokeMiniAppTelegramIdentity(Workspace $workspace, array $context, array $data): array|JsonResponse
    {
        $identityId = (string) ($data['identity_id'] ?? '');
        $currentIdentity = $context['identity'];
        $memberUserIds = WorkspaceMember::where('workspace_id', $workspace->id)->pluck('user_id');
        $identity = $identityId !== ''
            ? UserExternalIdentity::where('provider', 'telegram')
                ->whereIn('user_id', $memberUserIds)
                ->where('id', $identityId)
                ->first()
            : null;

        if (! $identity) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_identity_not_found',
            ], 404);
        }

        if ($identity->id === $currentIdentity->id) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_identity_self_revoke_not_allowed',
            ], 422);
        }

        $revoked = [
            'id' => $identity->id,
            'telegram_user_id' => $identity->external_id,
            'display_name' => $identity->display_name,
        ];
        $identity->delete();

        return [
            'revoked_identity' => $revoked,
            'linked_identities' => $this->telegramIdentitySummaries($workspace, $currentIdentity),
        ];
    }

    /**
     * Retry a failed outbound Telegram delivery from a sanitized Mini App
     * action. The caller can only reference a stored delivery row for the signed
     * workspace's Telegram integration; the stored payload decides the Bot API
     * method and target.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function retryMiniAppTelegramDelivery(IntegrationSetting $setting, array $data): array|JsonResponse
    {
        $deliveryId = (string) ($data['delivery_id'] ?? '');
        $delivery = $deliveryId !== ''
            ? TelegramDelivery::where('workspace_id', $setting->workspace_id)
                ->where('integration_setting_id', $setting->id)
                ->where('id', $deliveryId)
                ->first()
            : null;

        if (! $delivery) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_delivery_not_found',
            ], 404);
        }

        $operations = app(TelegramOperationsService::class);

        try {
            $delivery = $operations->retryDelivery($delivery);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_delivery_retry_failed',
                'message' => Str::limit($e->getMessage(), 300),
                'data' => $this->telegramOperationsSnapshot($setting),
            ], 422);
        }

        return [
            'delivery' => [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'telegram_message_id' => $delivery->telegram_message_id,
            ],
            ...$this->telegramOperationsSnapshot($setting),
        ];
    }

    /**
     * Replay a failed or stale inbound Telegram receipt from durable payload
     * storage. Processed receipts intentionally stay non-replayable in the
     * domain service so the Mini App cannot duplicate already-applied events.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function replayMiniAppTelegramReceipt(IntegrationSetting $setting, array $data): array|JsonResponse
    {
        $receiptId = (string) ($data['receipt_id'] ?? '');
        $receipt = $receiptId !== ''
            ? TelegramUpdateReceipt::where('workspace_id', $setting->workspace_id)
                ->where('integration_setting_id', $setting->id)
                ->where('id', $receiptId)
                ->first()
            : null;

        if (! $receipt) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_receipt_not_found',
            ], 404);
        }

        $operations = app(TelegramOperationsService::class);

        try {
            $receipt = $operations->replayReceipt($receipt);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_receipt_replay_failed',
                'message' => Str::limit($e->getMessage(), 300),
                'data' => $this->telegramOperationsSnapshot($setting),
            ], 422);
        }

        return [
            'receipt' => [
                'id' => $receipt->id,
                'status' => $receipt->status,
                'retry_count' => $receipt->retry_count,
                'processed_at' => $receipt->processed_at,
            ],
            ...$this->telegramOperationsSnapshot($setting),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramOperationsSnapshot(IntegrationSetting $setting): array
    {
        $operations = app(TelegramOperationsService::class);

        return [
            'metrics' => $operations->metrics($setting),
            'operations_logs' => $operations->logs($setting, 6),
            'repair_candidates' => $operations->repairCandidates($setting),
        ];
    }

    /**
     * Mutating Mini App actions are intentionally narrower than the read panels.
     * Telegram init data proves only the Telegram account; every mutation still
     * rechecks OpenCompany workspace membership and requires an admin/owner role
     * because lane defaults and notification policies affect other operators in
     * shared chats and topics.
     *
     * @param  array<string, mixed>  $context
     */
    private function canManageTelegramSettings(array $context): bool
    {
        $identity = $context['identity'];
        $workspace = $context['workspace'];
        $role = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $identity->user_id)
            ->value('role');

        return in_array(strtolower((string) $role), ['owner', 'admin'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppDefaultAgent(Workspace $workspace, IntegrationSetting $setting, array $data): array|JsonResponse
    {
        $agent = $this->resolveMiniAppAgent($workspace, (string) ($data['agent_id'] ?? ''));
        if (($data['agent_id'] ?? null) !== null && ! $agent) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_agent_not_found',
            ], 404);
        }

        if (! empty($data['conversation_id'])) {
            $conversation = $this->resolveMiniAppConversation($workspace, $setting, (string) $data['conversation_id']);
            if (! $conversation) {
                return response()->json([
                    'ok' => false,
                    'error' => 'telegram_conversation_not_found',
                ], 404);
            }

            $conversation->update([
                'default_agent_id' => $agent?->id,
                'archived_at' => null,
            ]);

            return [
                'scope' => 'conversation',
                'conversation' => $this->conversationSummary($conversation->fresh(['channel:id,name', 'defaultAgent:id,name'])),
            ];
        }

        $setting->setConfigValue('default_agent_id', $agent?->id);
        $setting->save();
        $profile = $this->profileForMutation($workspace, $setting);
        $profile->update(['default_agent_id' => $agent?->id]);

        return [
            'scope' => 'workspace',
            'default_agent' => $agent ? [
                'id' => $agent->id,
                'name' => $agent->name,
            ] : null,
        ];
    }

    private function resolveMiniAppAgent(Workspace $workspace, string $agentId): ?User
    {
        if ($agentId === '') {
            return null;
        }

        return User::where('workspace_id', $workspace->id)
            ->where('type', 'agent')
            ->where('id', $agentId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppConversationMode(Workspace $workspace, IntegrationSetting $setting, array $data): array|JsonResponse
    {
        $conversation = $this->resolveMiniAppConversation($workspace, $setting, (string) ($data['conversation_id'] ?? ''));
        if (! $conversation) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_conversation_not_found',
            ], 404);
        }

        $mode = (string) ($data['mode'] ?? '');
        if (! in_array($mode, ['command_center', 'free_response', 'observed', 'ignored'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_invalid_conversation_mode',
            ], 422);
        }

        $observed = array_key_exists('observed_context_enabled', $data)
            ? (bool) $data['observed_context_enabled']
            : $mode === 'observed';

        $conversation->update([
            'mode' => $mode,
            'observed_context_enabled' => $observed,
            'archived_at' => $mode === 'ignored' ? now() : null,
        ]);

        return [
            'conversation' => $this->conversationSummary($conversation->fresh(['channel:id,name', 'defaultAgent:id,name'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppNotification(Workspace $workspace, IntegrationSetting $setting, array $data): array|JsonResponse
    {
        $conversation = $this->resolveMiniAppConversation($workspace, $setting, (string) ($data['conversation_id'] ?? ''));
        if (! $conversation) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_conversation_not_found',
            ], 404);
        }

        $eventType = trim((string) ($data['event_type'] ?? 'task_failed'));
        $severity = trim((string) ($data['severity'] ?? 'normal'));
        $mode = trim((string) ($data['notification_mode'] ?? 'immediate'));
        if ($eventType === '' || $severity === '' || ! in_array($mode, ['immediate', 'silent', 'digest', 'digest-only', 'batched', 'off'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_invalid_notification_policy',
            ], 422);
        }

        $subscription = TelegramSubscription::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => $eventType,
            'severity' => $severity,
        ]);
        $filters = $subscription->filters ?? [];
        $filters['mode'] = $mode === 'off' ? 'off' : $mode;

        $subscription->fill([
            'id' => $subscription->id ?: Str::uuid()->toString(),
            'filters' => $filters,
            'timezone' => (string) config('app.timezone', 'UTC'),
            'enabled' => $mode !== 'off' && (bool) ($data['enabled'] ?? true),
        ])->save();

        return ['subscription' => $this->subscriptionSummary($subscription->fresh())];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|JsonResponse
     */
    private function setMiniAppDigest(Workspace $workspace, IntegrationSetting $setting, array $data): array|JsonResponse
    {
        $conversation = $this->resolveMiniAppConversation($workspace, $setting, (string) ($data['conversation_id'] ?? ''));
        if (! $conversation) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_conversation_not_found',
            ], 404);
        }

        $schedule = trim((string) ($data['schedule'] ?? 'daily'));
        if (! in_array($schedule, ['daily', 'weekly', 'off'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'telegram_invalid_digest_schedule',
            ], 422);
        }

        $subscription = TelegramSubscription::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
            'scope_type' => 'telegram_conversation',
            'scope_id' => $conversation->id,
            'event_type' => 'workspace_digest',
            'severity' => 'normal',
        ]);

        $filters = $subscription->filters ?? [];
        $time = (string) ($data['time'] ?? '');
        if ($time !== '') {
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'telegram_invalid_digest_time',
                ], 422);
            }
            $filters['time'] = $time;
        }

        $subscription->fill([
            'id' => $subscription->id ?: Str::uuid()->toString(),
            'filters' => $filters,
            'schedule' => $schedule === 'off' ? null : $schedule,
            'timezone' => (string) config('app.timezone', 'UTC'),
            'enabled' => $schedule !== 'off' && (bool) ($data['enabled'] ?? true),
        ])->save();

        return ['subscription' => $this->subscriptionSummary($subscription->fresh())];
    }

    private function profileForMutation(Workspace $workspace, IntegrationSetting $setting): TelegramIntegrationProfile
    {
        $profile = TelegramIntegrationProfile::firstOrNew([
            'workspace_id' => $workspace->id,
            'integration_setting_id' => $setting->id,
        ]);

        if (! $profile->exists) {
            $profile->fill([
                'id' => Str::uuid()->toString(),
                'bot_id' => $setting->getConfigValue('bot_user_id'),
                'bot_username' => $setting->getConfigValue('bot_username'),
                'capabilities' => [],
                'allowed_updates' => $setting->getConfigValue('allowed_updates', []),
                'default_mode' => $setting->getConfigValue('default_mode', 'command_center'),
                'health_status' => 'unknown',
            ])->save();
        }

        return $profile;
    }

    private function resolveMiniAppConversation(Workspace $workspace, IntegrationSetting $setting, string $conversationId): ?TelegramConversation
    {
        if ($conversationId === '') {
            return null;
        }

        return TelegramConversation::with(['channel:id,name', 'defaultAgent:id,name'])
            ->where('workspace_id', $workspace->id)
            ->where('integration_setting_id', $setting->id)
            ->where('id', $conversationId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationSummary(TelegramConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'chat_id' => $conversation->chat_id,
            'chat_type' => $conversation->chat_type,
            'title' => $conversation->title,
            'topic_id' => $conversation->topic_id,
            'direct_messages_topic_id' => $conversation->direct_messages_topic_id,
            'mode' => $conversation->mode,
            'observed_context_enabled' => $conversation->observed_context_enabled,
            'archived_at' => $conversation->archived_at,
            'channel' => $conversation->channel?->name,
            'default_agent' => $conversation->defaultAgent ? [
                'id' => $conversation->defaultAgent->id,
                'name' => $conversation->defaultAgent->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionSummary(TelegramSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'scope_type' => $subscription->scope_type,
            'scope_id' => $subscription->scope_id,
            'event_type' => $subscription->event_type,
            'severity' => $subscription->severity,
            'schedule' => $subscription->schedule,
            'timezone' => $subscription->timezone,
            'enabled' => $subscription->enabled,
            'filters' => $subscription->filters ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function toolName(array $context): string
    {
        $tool = $context['tool_slug'] ?? $context['tool'] ?? null;

        return is_string($tool) && $tool !== '' ? $tool : 'opencompany_action';
    }

    private function argumentSummary(mixed $parameters): array
    {
        if (! is_array($parameters) || $parameters === []) {
            return [];
        }

        return collect($parameters)
            ->take(12)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->summarizeArgumentValue((string) $key, $value)])
            ->all();
    }

    private function summarizeArgumentValue(string $key, mixed $value): string
    {
        if (preg_match('/(secret|token|password|credential|api[_-]?key)/i', $key)) {
            return '[redacted]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return Str::limit((string) $value, 200);
        }

        return is_array($value) ? '[array:'.count($value).']' : '['.get_debug_type($value).']';
    }

    private function belongsToWorkspace(string $userId, Workspace $workspace): bool
    {
        return WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $userId)
            ->exists();
    }
}
