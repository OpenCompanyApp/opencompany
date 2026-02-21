<?php

namespace App\Observers;

use App\Jobs\SendApprovalToTelegramJob;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;
use App\Models\Workspace;

class ApprovalRequestObserver
{
    public function created(ApprovalRequest $approval): void
    {
        // Set workspace context from the approval's channel so we query the correct Telegram integration
        $workspace = $approval->channel?->workspace;
        if ($workspace && !app()->bound('currentWorkspace')) {
            app()->instance('currentWorkspace', $workspace);
        }

        $query = app()->bound('currentWorkspace')
            ? IntegrationSetting::forWorkspace()->where('integration_id', 'telegram')
            : IntegrationSetting::where('integration_id', 'telegram');

        $setting = $query->where('enabled', true)->first();

        if (!$setting) {
            return;
        }

        $notifyChatId = $setting->getConfigValue('notify_chat_id');
        if (!$notifyChatId) {
            return;
        }

        SendApprovalToTelegramJob::dispatch($approval, $notifyChatId);
    }
}
