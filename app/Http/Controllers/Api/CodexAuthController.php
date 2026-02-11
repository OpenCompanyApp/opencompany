<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use OpenCompany\PrismCodex\CodexOAuthService;
use OpenCompany\PrismCodex\CodexTokenStore;

class CodexAuthController extends Controller
{
    /**
     * Get current Codex auth status.
     */
    public function status(CodexOAuthService $oauthService)
    {
        $stored = CodexTokenStore::current();

        return response()->json([
            'authenticated' => $stored !== null,
            'email' => $stored?->email,
            'account_id' => $stored?->account_id,
            'expires_at' => $stored?->expires_at?->toDateTimeString(),
            'is_expired' => $stored?->isExpired() ?? true,
            'updated_at' => $stored?->updated_at?->toDateTimeString(),
            'models' => IntegrationSetting::getAvailableIntegrations()['codex']['models'] ?? [],
        ]);
    }

    /**
     * Initiate device authorization flow.
     */
    public function device(CodexOAuthService $oauthService)
    {
        try {
            $result = $oauthService->initiateDeviceAuth();

            return response()->json([
                'user_code' => $result['user_code'],
                'verification_url' => 'https://auth.openai.com/codex/device',
                'device_auth_id' => $result['device_auth_id'],
                'interval' => $result['interval'] + 3,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to initiate device authorization: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Poll device authorization status.
     */
    public function devicePoll(CodexOAuthService $oauthService, Request $request)
    {
        $request->validate([
            'device_auth_id' => 'required|string',
            'user_code' => 'required|string',
        ]);

        try {
            $tokens = $oauthService->pollDeviceAuth(
                $request->input('device_auth_id'),
                $request->input('user_code'),
            );

            if ($tokens === null) {
                return response()->json(['status' => 'pending']);
            }

            $oauthService->storeTokens($tokens);

            $stored = CodexTokenStore::current();

            return response()->json([
                'status' => 'complete',
                'email' => $stored?->email,
                'account_id' => $stored?->account_id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear stored Codex tokens.
     */
    public function logout()
    {
        CodexTokenStore::clear();

        return response()->json(['success' => true]);
    }

    /**
     * Test the Codex connection by sending a minimal request.
     */
    public function test(CodexOAuthService $oauthService)
    {
        $token = $oauthService->getAccessToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => 'Not authenticated. Please log in first.',
            ], 400);
        }

        try {
            $available = IntegrationSetting::getAvailableIntegrations();
            $model = array_key_first($available['codex']['models'] ?? []) ?? 'gpt-5.3-codex';

            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->withHeaders(array_filter([
                    'ChatGPT-Account-Id' => $oauthService->getAccountId(),
                ]))
                ->timeout(30)
                ->post(config('codex.url', 'https://chatgpt.com/backend-api/codex').'/responses', [
                    'model' => $model,
                    'input' => 'Say "Connection successful" in exactly those two words.',
                    'max_output_tokens' => 20,
                ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful — Codex API is working.',
                    'model' => $model,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'API returned '.$response->status().': '.($response->json('error.message') ?? $response->body()),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Request failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
