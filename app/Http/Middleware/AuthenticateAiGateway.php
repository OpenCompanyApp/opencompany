<?php

namespace App\Http\Middleware;

use App\Models\AiGatewayApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates OpenCompany AI Gateway requests with workspace API keys.
 *
 * Successful authentication binds the key's workspace as currentWorkspace so
 * provider resolution, model exposure, integration credentials, and usage
 * logging remain scoped exactly like first-party OpenCompany runtime calls.
 */
class AuthenticateAiGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Missing API key. Provide a Bearer token in the Authorization header.');
        }

        $key = AiGatewayApiKey::findByPlainText($token);

        if (! $key) {
            return $this->unauthorized('Invalid API key.');
        }

        if ($key->isExpired()) {
            return $this->unauthorized('API key has expired.');
        }

        if ($key->workspace) {
            app()->instance('currentWorkspace', $key->workspace);
        }

        $key->timestamps = false;
        $key->update(['last_used_at' => now()]);
        $key->timestamps = true;

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ], 401);
    }
}
