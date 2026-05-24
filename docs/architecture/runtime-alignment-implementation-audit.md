# Runtime Alignment Implementation Audit

Date: 2026-04-09
Status: Historical audit, superseded for AI provider/runtime ownership by `docs/architecture/ai-provider-runtime-architecture.md`.

## Findings

All findings from this audit are now tracked as issues in the [OpenCompany Plane project](https://plane.gingermedia.biz/kosmokrator/projects/ceaf5d22-612a-42bf-9cc8-0dac054cdf0c/issues/):

| Issue | Finding | Severity |
|-------|---------|----------|
| OC-1 | ~~Prompt caching depends on ignored `vendor` patches~~ - **Superseded**: current runtime uses app-owned `PromptCachePolicy` and `AiCatalog` metadata, with no Prism Relay or vendor patch dependency | ~~High~~ |
| OC-3 | ~~`planPromptCache()` never called in request flow~~ - **Superseded**: provider cache options now flow through OpenCompany runtime/catalog metadata | ~~High~~ |
| OC-4 | `ContextBudget` undercounts retry context pressure | High |
| OC-5 | `ModelContextRegistry` regressed prefix-style admin overrides | Medium |
| OC-6 | Durable-memory extraction re-logs same facts on later compactions | Medium |

## Verification Notes

- The workspace requires a filtered `APP_PACKAGES_CACHE` during tests because several `opencompanyapp/integration-*` packages are absent from `vendor`.
- Prompt-cache metrics have not been verified end-to-end through production observability.
