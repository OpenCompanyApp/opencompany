# Open-Source Vendor Audit: Critical Follow-Up

Date: 2026-04-10

Status: Historical vendor audit. `prism-php/prism` is no longer an OpenCompany dependency; findings are retained as removal rationale, not current runtime risk.

Scope:
- Included: third-party packages in `vendor/`
- Excluded: `opencompany/*`, `opencompanyapp/*`, and local path/symlinked packages
- Goal: only high-impact findings, not style or static-analysis noise

Method:
- manual source audit
- targeted local repros
- only retained findings that produce either hard crashes or state loss

## Ranked Findings

| Rank | Severity | Package | Area | Finding | Evidence | Impact |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Critical | `prism-php/prism` `v0.99.22` | OpenAI provider | `OpenAI\Maps\ToolCallMap` passes `data_get(..., 'arguments')` straight into `ToolCall`. Missing `arguments` becomes `null`, which throws a raw `TypeError`. | `vendor/prism-php/prism/src/Providers/OpenAI/Maps/ToolCallMap.php:22` | A malformed or partial tool-call payload crashes the request instead of producing a handled provider error. |
| 2 | Critical | `prism-php/prism` `v0.99.22` | Gemini provider | `Gemini\Maps\ToolCallMap` passes `functionCall.args` directly into `ToolCall`. Missing args become `null` and hard-fail with `TypeError`. | `vendor/prism-php/prism/src/Providers/Gemini/Maps/ToolCallMap.php:23` | Empty-argument or malformed Gemini tool-call payloads can crash generation instead of failing cleanly. |
| 3 | Critical | `prism-php/prism` `v0.99.22` | DeepSeek provider | `DeepSeek\Maps\ToolCallMap` does the same direct pass-through from `function.arguments` to `ToolCall`; missing args produce `TypeError`. | `vendor/prism-php/prism/src/Providers/DeepSeek/Maps/ToolCallMap.php:17` | A single malformed tool-call block can take down the whole text response path. |
| 4 | Critical | `prism-php/prism` `v0.99.22` | Groq text handler | `Groq\Handlers\Text::mapToolCalls()` forwards nullable `function.arguments` directly into `ToolCall`. Repro in app container: missing arguments => `TypeError`. | `vendor/prism-php/prism/src/Providers/Groq/Handlers/Text.php:151` | Provider response drift or malformed tool payloads crash the non-streaming Groq path. |
| 5 | Critical | `prism-php/prism` `v0.99.22` | Mistral text handler | `Mistral\Handlers\Text::mapToolCalls()` forwards nullable `function.arguments` directly into `ToolCall`. Repro: missing arguments => `TypeError`. | `vendor/prism-php/prism/src/Providers/Mistral/Handlers/Text.php:165` | The non-streaming Mistral tool-call path is crashable by incomplete tool payloads. |
| 6 | Critical | `prism-php/prism` `v0.99.22` | XAI text handler | `XAI\Handlers\Text::mapToolCalls()` forwards nullable `function.arguments` directly into `ToolCall`. Repro: missing arguments => `TypeError`. | `vendor/prism-php/prism/src/Providers/XAI/Handlers/Text.php:141` | The non-streaming xAI path can hard-crash on malformed tool-call payloads. |
| 7 | Critical | `prism-php/prism` `v0.99.22` | Ollama text handler | `Ollama\Handlers\Text::mapToolCalls()` forwards `function.name` and `function.arguments` directly. Missing `function.name` already causes a `TypeError` on constructor arg 2 before any recovery is possible. | `vendor/prism-php/prism/src/Providers/Ollama/Handlers/Text.php:166` | Malformed Ollama tool-call payloads can crash even earlier than the argument-type failures in other providers. |
| 8 | Critical | `prism-php/prism` `v0.99.22` | Groq stream handler | `Groq\Handlers\Stream::mapToolCalls()` JSON-decodes string arguments, but does not verify the decoded type. Valid scalar JSON like `"1"` becomes `int(1)` and is passed to `ToolCall`, causing `TypeError`. Repro confirmed in `artisan tinker`. | `vendor/prism-php/prism/src/Providers/Groq/Handlers/Stream.php:321`, `vendor/prism-php/prism/src/Providers/Groq/Handlers/Stream.php:329` | Even valid JSON can crash the streaming path if it decodes to a scalar instead of an object/array. |
| 9 | Critical | `prism-php/prism` `v0.99.22` | OpenRouter stream handler | `OpenRouter\Handlers\Stream::mapToolCalls()` has the same scalar-JSON bug: `"1"` decodes to `int(1)` and then triggers `TypeError` in `ToolCall`. Repro confirmed in `artisan tinker`. | `vendor/prism-php/prism/src/Providers/OpenRouter/Handlers/Stream.php:432`, `vendor/prism-php/prism/src/Providers/OpenRouter/Handlers/Stream.php:439` | Streaming tool calls can hard-fail even when the incoming `arguments` field contains syntactically valid JSON. |
| 10 | Critical | `prism-php/prism` `v0.99.22` | Mistral stream handler | `Mistral\Handlers\Stream::mapToolCalls()` repeats the same scalar-JSON failure mode as Groq/OpenRouter. Repro confirmed in `artisan tinker`. | `vendor/prism-php/prism/src/Providers/Mistral/Handlers/Stream.php:362`, `vendor/prism-php/prism/src/Providers/Mistral/Handlers/Stream.php:369` | The streaming Mistral path can crash on scalar JSON arguments instead of degrading to a raw-string or provider exception. |

## Repro Notes

Representative local repros that were validated:

1. Missing args in static map:

```php
Prism\Prism\Providers\OpenAI\Maps\ToolCallMap::map([
    ['id' => '1', 'name' => 'demo'],
]);
```

Result:

```text
TypeError: Prism\Prism\ValueObjects\ToolCall::__construct(): Argument #3 ($arguments) must be of type array|string, null given
```

2. Scalar JSON in stream path:

```php
$client = Http::baseUrl('https://example.com');
$handler = new class($client) extends \Prism\Prism\Providers\OpenRouter\Handlers\Stream {
    public function expose(array $payload) { return $this->mapToolCalls($payload); }
};

$handler->expose([
    ['id' => '1', 'function' => ['name' => 'demo', 'arguments' => '1']],
]);
```

Result:

```text
TypeError: Prism\Prism\ValueObjects\ToolCall::__construct(): Argument #3 ($arguments) must be of type array|string, int given
```

## Takeaway

This is not an isolated one-off bug. `prism-php/prism` has a systemic tool-call normalization problem:

- multiple providers assume tool arguments always arrive as an array or object-like JSON
- multiple code paths pass nullable or scalar-decoded values directly into `ToolCall`
- the failure mode is a raw PHP `TypeError`, not a package-level provider/parsing error

If I continue on this path, the next useful step is to turn this into:

- one systemic upstream issue describing the normalization bug class
- plus provider/path-specific follow-up issues or PRs for each affected mapper / handler
