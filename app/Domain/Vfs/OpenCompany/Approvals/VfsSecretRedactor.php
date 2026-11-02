<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

/**
 * Redacts secrets from approval records shown to agents (/approvals/{id}.json)
 * while keeping ids, paths, subjects, hashes and previews readable.
 *
 * Two rules only, both precise:
 * - by key name: a string value whose key is secret-like (password, token,
 *   api key, authorization, cookie, private key, ...) is replaced. Keys are
 *   compared word by word (`author_id` is not `auth`, `max_tokens` is not
 *   `token`), and non-string values (counts, flags) are kept;
 * - by known secret pattern inside any string: quoted key/value pairs whose
 *   key is secret-like (JSON, Python/JS literals, and JSON nested inside a
 *   JSON string at any escape depth, e.g. a document body or a removed diff
 *   line in an approval preview), Authorization/Bearer/Basic credentials,
 *   `key=value` pairs with secret-like keys (also in URLs, and with quoted
 *   values as in `.env` files), and
 *   recognizable token formats (OpenAI/Stripe `sk-`/`sk_live_`, GitHub, Slack,
 *   AWS access keys, Google API keys, GitLab, JWTs, PEM private keys). Only
 *   the secret substring is replaced.
 *
 * Deliberately NOT a rule: "any long token". UUIDs, sha256 hashes and paths
 * are exactly what an agent needs to tell its approvals apart; the previous
 * 32-character heuristic redacted all of them.
 */
/*
 * Contract: redaction is applied to the copy shown to the model only
 * (MountsVfsApprovals::approvalPayload). Stored tool_execution_context
 * parameters are never rewritten; replay hashes and executes them verbatim.
 */
final class VfsSecretRedactor
{
    public const REDACTED = '[redacted]';

    /** Single key words that mark a secret. */
    private const SECRET_WORDS = [
        'secret', 'secrets', 'password', 'passwd', 'passphrase', 'pwd', 'token',
        'credential', 'credentials', 'authorization', 'auth', 'bearer', 'cookie',
        'cookies', 'apikey', 'privatekey', 'signature', 'otp',
    ];

    /** Adjacent key word pairs that mark a secret (api_key, session_id, ...). */
    private const SECRET_PAIRS = [
        ['api', 'key'], ['private', 'key'], ['access', 'key'], ['secret', 'key'],
        ['session', 'id'], ['session', 'key'], ['client', 'secret'], ['signing', 'key'],
    ];

    private const VALUE_PATTERNS = [
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s',
        '/\b(?:sk|rk|pk)[-_](?:live_|test_|proj-|ant-)?[A-Za-z0-9_-]{16,}/',
        '/\bgh[pousr]_[A-Za-z0-9]{20,}\b/',
        '/\bgithub_pat_[A-Za-z0-9_]{20,}\b/',
        '/\bglpat-[A-Za-z0-9_-]{20,}\b/',
        '/\bxox[abposr]-[A-Za-z0-9-]{10,}\b/',
        '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        '/\bAIza[0-9A-Za-z_-]{35}\b/',
        '/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/',
    ];

    public static function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $redacted[$key] = is_string($key) && self::isSecretKey($key)
                    ? self::redactWhole($item)
                    : self::redact($item);
            }

            return $redacted;
        }

        return is_string($value) ? self::redactString($value) : $value;
    }

    public static function isSecretKey(string $key): bool
    {
        $words = self::words($key);
        if (array_intersect($words, self::SECRET_WORDS) !== []) {
            return true;
        }

        foreach (self::SECRET_PAIRS as [$first, $second]) {
            foreach ($words as $index => $word) {
                if ($word === $first && ($words[$index + 1] ?? null) === $second) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function redactString(string $value): string
    {
        // Quoted key/value forms first: JSON (`"password": "x"`), Python or
        // JS literals (`'api_key': 'x'`) and the same pairs inside a JSON
        // string nested in JSON (`\"token\":\"x\"`, any escape depth). The
        // backslash run before the key's quote must repeat before the value's
        // quote and its closing quote, so the match stays at one nesting
        // level; a value quote preceded by an extra backslash is an escaped
        // quote inside the value, not its end. Keys use the same word rules as
        // array keys (isSecretKey), so `author_id` stays readable.
        $value = self::redactQuotedPairs($value);
        // Unquoted secret-like key with a quoted value (`.env`, shell, YAML):
        // PASSWORD="x", api_key: 'x'.
        $value = preg_replace(
            '/(?<![A-Za-z0-9])((?:[A-Za-z0-9]+[_-])*(?:password|passwd|secret|token|api[_-]?key|access[_-]?key|private[_-]?key|client[_-]?secret|session[_-]?id)(?:[_-][A-Za-z0-9]+)*)(\s*[:=]\s*)(\\\\*)(["\'])(?!\[redacted\])(.*?)(?<!\\\\)\3\4/is',
            '$1$2$3$4'.self::REDACTED.'$3$4',
            $value,
        ) ?? $value;
        // Header-style credentials: keep the scheme, drop the credential.
        $value = preg_replace('/\b(authorization\s*[:=]\s*)(?:(bearer|basic|token)\s+)?[^\s,;"\']+/i', '$1$2 '.self::REDACTED, $value) ?? $value;
        $value = preg_replace('/\b(bearer|basic)\s+(?!\[redacted\])[A-Za-z0-9._~+\/=-]{8,}/i', '$1 '.self::REDACTED, $value) ?? $value;
        // key=value / key: value pairs and URL query parameters.
        $value = preg_replace(
            '/(?<![A-Za-z0-9])((?:[A-Za-z0-9]+[_-])*(?:password|passwd|secret|token|api[_-]?key|access[_-]?key|private[_-]?key|client[_-]?secret|session[_-]?id)(?:[_-][A-Za-z0-9]+)*)(\s*[:=]\s*)(?!\[redacted\])([^\s,&;"\']+)/i',
            '$1$2'.self::REDACTED,
            $value,
        ) ?? $value;

        foreach (self::VALUE_PATTERNS as $pattern) {
            $value = preg_replace($pattern, self::REDACTED, $value) ?? $value;
        }

        return $value;
    }

    /**
     * Replace the values of quoted secret-like key/value pairs.
     *
     * A scan loop rather than one preg_replace: a non-secret pair's value is
     * NOT consumed, because it may itself be JSON holding a secret pair one
     * escape level deeper (`"config": "{\"token\": \"x\"}"`). Only a
     * secret pair's value is replaced and skipped.
     */
    private static function redactQuotedPairs(string $value): string
    {
        // 1 backslash run, 2 quote, 3 key, 4 separator, then the value's
        // opening quote at the same escape depth; 5 (lookahead) the value up
        // to its unescaped closing quote at that depth.
        $pattern = '/(\\\\*)(["\'])([A-Za-z0-9][A-Za-z0-9_.\- ]{0,63})\1\2(\s*[:=]\s*)\1\2(?=(.*?)(?<!\\\\)\1\2)/s';
        $offset = 0;
        while ($offset < strlen($value) && preg_match($pattern, $value, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueStart = $m[0][1] + strlen($m[0][0]);
            $secret = $m[5][0];
            if ($secret !== '' && $secret !== self::REDACTED && self::isSecretKey($m[3][0])) {
                $value = substr_replace($value, self::REDACTED, $valueStart, strlen($secret));
                $offset = $valueStart + strlen(self::REDACTED);

                continue;
            }
            $offset = $valueStart;
        }

        return $value;
    }

    private static function redactWhole(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => self::redactWhole($item), $value);
        }

        return is_string($value) && $value !== '' ? self::REDACTED : $value;
    }

    /** @return list<string> */
    private static function words(string $key): array
    {
        $spaced = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $key) ?? $key;
        $parts = preg_split('/[^A-Za-z0-9]+/', strtolower($spaced)) ?: [];

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }
}
