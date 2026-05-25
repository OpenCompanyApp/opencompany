<?php

namespace App\Domain\Chat\Telegram\Application;

/**
 * Verifies Telegram Mini App init data against the workspace bot token.
 *
 * Telegram Mini Apps launch inside a Telegram-controlled webview and provide a
 * signed query string. The user/chat data inside that query string is only an
 * input hint until this verifier recomputes the Bot API HMAC with the workspace
 * bot token, checks freshness, and returns the parsed fields to a controller
 * that can enforce OpenCompany workspace membership.
 */
class TelegramMiniAppVerifier
{
    /**
     * @return array<string, mixed>
     */
    public function verify(string $initData, string $botToken, int $maxAgeSeconds = 86400): array
    {
        parse_str($initData, $params);

        $hash = is_string($params['hash'] ?? null) ? $params['hash'] : '';
        if ($hash === '') {
            throw new \InvalidArgumentException('Telegram Mini App init data is missing hash.');
        }

        unset($params['hash']);
        ksort($params);

        $dataCheckParts = [];
        foreach ($params as $key => $value) {
            $dataCheckParts[] = $key.'='.$value;
        }
        $dataCheckString = implode("\n", $dataCheckParts);

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expected = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expected, $hash)) {
            throw new \InvalidArgumentException('Telegram Mini App init data signature is invalid.');
        }

        $authDate = isset($params['auth_date']) ? (int) $params['auth_date'] : 0;
        if ($authDate <= 0 || $authDate < now()->subSeconds($maxAgeSeconds)->timestamp) {
            throw new \InvalidArgumentException('Telegram Mini App init data is expired.');
        }

        foreach (['user', 'chat', 'receiver'] as $jsonKey) {
            if (isset($params[$jsonKey]) && is_string($params[$jsonKey])) {
                $decoded = json_decode($params[$jsonKey], true);
                $params[$jsonKey] = is_array($decoded) ? $decoded : null;
            }
        }

        return $params;
    }
}
