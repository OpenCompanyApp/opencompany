<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Retired Telegram webhook entrypoint kept only to give stale Telegram webhook
 * registrations a clear migration response.
 *
 * The app-owned Telegram runtime lives behind `/api/webhooks/chat/telegram` and
 * `App\Domain\Chat\Telegram`. This controller intentionally does not parse
 * updates, send replies, create channels, or resolve approval callbacks. Keeping
 * it non-mutating prevents the old direct implementation from reappearing under
 * a config flag while existing operators still get a precise 410 response if a
 * bot is pointed at the old URL.
 */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        return response('Legacy Telegram webhook retired; use /api/webhooks/chat/telegram.', 410);
    }
}
