<?php

namespace App\Http\Controllers;

use App\Domain\Integrations\Application\ManageIntegrationSettings;
use App\Models\TelegramInteraction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Claims short-lived Telegram identity-link interactions from authenticated web
 * sessions.
 *
 * The link starts in Telegram, but the actual identity binding is completed in
 * OpenCompany after normal web authentication and workspace membership checks.
 * Telegram user IDs are treated as external hints until this controller proves
 * the signed URL, token freshness, and user membership.
 */
class TelegramLinkController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ManageIntegrationSettings $settings
    ): RedirectResponse {
        $interaction = TelegramInteraction::where('token', $token)
            ->where('interaction_type', 'identity_link')
            ->firstOrFail();

        $workspace = Workspace::findOrFail($interaction->workspace_id);
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->belongsToWorkspace($user, $workspace), 403);

        if ($interaction->resolved_at) {
            return redirect("/w/{$workspace->slug}/integrations")
                ->with('status', 'Telegram is already linked for this request.');
        }

        if (! $interaction->hasValidPayloadChecksum()) {
            $interaction->update([
                'resolved_at' => now(),
                'resolved_by_id' => $user->id,
                'final_status' => 'tampered',
            ]);

            return redirect("/w/{$workspace->slug}/integrations")
                ->with('error', 'That Telegram link is no longer valid. Send /link again in Telegram.');
        }

        if ($interaction->expires_at && $interaction->expires_at->isPast()) {
            $interaction->update([
                'resolved_at' => now(),
                'resolved_by_id' => $user->id,
                'final_status' => 'expired',
            ]);

            return redirect("/w/{$workspace->slug}/integrations")
                ->with('error', 'That Telegram link expired. Send /link again in Telegram.');
        }

        $payload = $interaction->payload ?? [];
        $telegramUserId = (string) ($payload['telegram_user_id'] ?? '');
        abort_if($telegramUserId === '', 404);

        $result = $settings->linkExternalIdentity([
            'userId' => $user->id,
            'provider' => 'telegram',
            'externalId' => $telegramUserId,
            'displayName' => $payload['display_name'] ?? null,
        ]);

        if (isset($result['conflict'])) {
            return redirect("/w/{$workspace->slug}/integrations")
                ->with('error', $result['conflict']);
        }

        $interaction->update([
            'resolved_at' => now(),
            'resolved_by_id' => $user->id,
            'final_status' => 'linked',
        ]);

        return redirect("/w/{$workspace->slug}/integrations")
            ->with('status', 'Telegram linked to your OpenCompany account.');
    }

    private function belongsToWorkspace(User $user, Workspace $workspace): bool
    {
        if ($user->type === 'agent') {
            return $user->workspace_id === $workspace->id;
        }

        return $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->exists();
    }
}
