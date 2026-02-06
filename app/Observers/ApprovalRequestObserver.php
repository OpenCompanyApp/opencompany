<?php

namespace App\Observers;

use App\Jobs\SendApprovalToTelegramJob;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;

class ApprovalRequestObserver
{
    public function created(ApprovalRequest $approval): void
    {
        $setting = IntegrationSetting::where('integration_id', 'telegram')
            ->where('enabled', true)
            ->first();

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
