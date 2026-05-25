<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chat\Telegram\Application\TelegramOperationsService;
use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\TelegramDelivery;
use App\Models\TelegramUpdateReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin repair surface for the app-owned Telegram chat runtime.
 *
 * These endpoints intentionally operate on durable Telegram receipts and
 * delivery rows rather than accepting arbitrary chat IDs or payloads. That keeps
 * replay and retry actions workspace-scoped, auditable, and tied to the actual
 * webhook/delivery history OpenCompany has observed.
 */
class TelegramOperationsController extends Controller
{
    public function __construct(private readonly TelegramOperationsService $telegram) {}

    public function index(): JsonResponse
    {
        $setting = $this->telegramSetting();

        return response()->json([
            'success' => true,
            'metrics' => $this->telegram->metrics($setting),
            'logs' => $this->telegram->logs($setting, (int) request()->integer('limit', 20)),
            'repairCandidates' => $this->telegram->repairCandidates($setting),
        ]);
    }

    public function repair(Request $request): JsonResponse
    {
        $setting = $this->telegramSetting();
        $actions = $request->input('actions', []);
        $actions = is_array($actions) ? array_values(array_filter($actions, 'is_string')) : [];

        return response()->json([
            'success' => true,
            'repair' => $this->telegram->repairLocalState($setting, $actions),
            'metrics' => $this->telegram->metrics($setting),
            'repairCandidates' => $this->telegram->repairCandidates($setting),
        ]);
    }

    public function replayReceipt(string $receiptId): JsonResponse
    {
        $receipt = TelegramUpdateReceipt::forWorkspace()
            ->where('id', $receiptId)
            ->firstOrFail();

        $this->assertTelegramSetting($receipt->integration_setting_id);

        try {
            $receipt = $this->telegram->replayReceipt($receipt);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'receipt' => $receipt->fresh(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'receipt' => $receipt,
        ]);
    }

    public function retryDelivery(string $deliveryId): JsonResponse
    {
        $delivery = TelegramDelivery::forWorkspace()
            ->where('id', $deliveryId)
            ->firstOrFail();

        $this->assertTelegramSetting($delivery->integration_setting_id);

        try {
            $delivery = $this->telegram->retryDelivery($delivery);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'delivery' => $delivery->fresh(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'delivery' => $delivery,
            'metrics' => $this->telegram->metrics($this->telegramSetting()),
            'logs' => $this->telegram->logs($this->telegramSetting(), (int) request()->integer('limit', 20)),
        ]);
    }

    private function telegramSetting(): IntegrationSetting
    {
        return IntegrationSetting::forWorkspace()
            ->where('integration_id', 'telegram')
            ->where('enabled', true)
            ->firstOrFail();
    }

    private function assertTelegramSetting(string $integrationSettingId): void
    {
        IntegrationSetting::forWorkspace()
            ->where('integration_id', 'telegram')
            ->where('id', $integrationSettingId)
            ->firstOrFail();
    }
}
