# Open-Source Vendor Audit

Date: 2026-04-10

Status: Historical vendor audit. `prism-php/prism` and `tightenco/ziggy` are no longer OpenCompany dependencies; those findings are retained as removal/upstream-report rationale, not current runtime risk.

Scope:
- Included: third-party packages in `vendor/`
- Excluded: `opencompany/*`, `opencompanyapp/*`, and local path/symlinked packages

Method:
- Read-through audit of the packages this app depends on most directly
- Targeted static analysis to surface suspicious runtime paths
- Manual validation of the high-signal findings only

## Ranked Findings

| Rank | Severity | Package | Finding | Evidence | Impact |
| --- | --- | --- | --- | --- | --- |
| 1 | High | `laravel/ai` `v0.1.5` | Conversation replay drops all non-text context. `DatabaseConversationStore::getLatestConversationMessages()` rehydrates every stored row as a plain `Message`, so prior user attachments and assistant tool call / tool result context are lost on continued conversations. | `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:47`, `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:73`, `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:101` | Continued conversations can become behaviorally incorrect: multimodal context disappears, tool traces disappear, and follow-up prompts can no longer rely on prior tool execution state. |
| 2 | High | `prism-php/prism` `v0.99.22` | OpenRouter tool-call mapping hard-fails on malformed JSON arguments. `ToolCallMap::map()` decodes JSON directly and passes the result into `ToolCall::__construct()`. Invalid JSON becomes `null`, which triggers a `TypeError` instead of a handled provider/tool parsing error. | `vendor/prism-php/prism/src/Providers/OpenRouter/Maps/ToolCallMap.php:15`. Repro: `php -r 'require "vendor/autoload.php"; Prism\Prism\Providers\OpenRouter\Maps\ToolCallMap::map([["id"=>"1","function"=>["name"=>"demo","arguments"=>"{"]]]);'` => `TypeError` | Any malformed tool-call payload from the model crashes the mapping layer before Prism can raise a package-level exception. This is especially bad because tool arguments are model-generated and malformed JSON is a normal failure mode. |
| 3 | Medium | `laravel/ai` `v0.1.5` | “Continue last conversation” can pick the wrong conversation. `latestConversationId()` orders by `agent_conversations.updated_at`, but that timestamp is only written when the conversation is created. Storing later messages never updates the parent conversation row. | `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:18`, `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:29`, `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:47`, `vendor/laravel/ai/src/Storage/DatabaseConversationStore.php:73` | Users with multiple conversations can resume an older conversation even after a newer one received more recent messages. |
| 4 | Medium | `tightenco/ziggy` `v2.6.1` | `ziggy:generate --types=...` does not create the target directory for a custom TypeScript output path. The command ensures the JS output directory exists, but writes the custom types path directly with no `ensureDirectoryExists()` call. | `vendor/tightenco/ziggy/src/CommandRouteGenerator.php:34`, `vendor/tightenco/ziggy/src/CommandRouteGenerator.php:53`, `vendor/tightenco/ziggy/src/CommandRouteGenerator.php:58`. Repro: `php artisan ziggy:generate --types=tmp/ziggy-audit/nested/ziggy.d.ts --types-only` => `file_put_contents(...): No such file or directory` | CI/dev scripts that emit typings to a nested custom path fail unless the directory is pre-created manually. |
| 5 | Medium | `tightenco/ziggy` `v2.6.1` | Absolute output paths are silently rebased under the Laravel app root. Both the JS path and the types path are always wrapped in `base_path(...)`, so `/tmp/ziggy.js` becomes `<project>/tmp/ziggy.js`. | `vendor/tightenco/ziggy/src/CommandRouteGenerator.php:39`, `vendor/tightenco/ziggy/src/CommandRouteGenerator.php:47`, `vendor/tightenco/ziggy/src/CommandRouteGenerator.php:58`. Repro: `php artisan ziggy:generate /tmp/ziggy-audit-exists/ziggy.js` writes into the project-local `tmp/ziggy-audit-exists/ziggy.js`, not `/tmp/ziggy-audit-exists/ziggy.js`. | Scripts that expect absolute-path output can silently write into the application tree instead of the requested destination. |

## Notes For Upstream Reports

- `laravel/ai` finding 1 and finding 3 are likely part of the same conversation-store design gap, but they are worth filing separately because they break in different ways:
  - message reconstruction is lossy
  - last-conversation selection uses stale metadata
- `prism-php/prism` finding 2 should probably be fixed by either:
  - preserving the raw JSON string, or
  - throwing a Prism exception with provider context, but not a PHP `TypeError`
- `tightenco/ziggy` findings 4 and 5 are both in `CommandRouteGenerator`, so they are good PR candidates together if maintainers agree on absolute-path semantics.

## Audit Coverage

Primary packages reviewed:
- `laravel/ai`
- `prism-php/prism`
- `tightenco/ziggy`
- `inertiajs/inertia-laravel`

I did not include weaker static-analysis-only items where I could not tie them to a concrete runtime or CLI failure mode.
