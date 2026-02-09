<?php

namespace App\Http\Middleware;

use App\Models\PrismApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrismServer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Missing API key. Provide a Bearer token in the Authorization header.');
        }

        $key = PrismApiKey::findByPlainText($token);

        if (! $key) {
            return $this->unauthorized('Invalid API key.');
        }

        if ($key->isExpired()) {
            return $this->unauthorized('API key has expired.');
        }

        // Touch last_used_at without triggering updated_at
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
