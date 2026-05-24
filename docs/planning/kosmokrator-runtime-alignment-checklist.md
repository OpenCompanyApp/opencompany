# KosmoKrator Runtime Alignment Checklist

> Historical implementation checklist for aligning OpenCompany's context, compaction, pruning, and prompt-caching runtime with reusable KosmoKrator ideas. AI provider/runtime ownership was superseded on 2026-05-24 by the app-owned OpenCompany AI Runtime in `docs/architecture/ai-provider-runtime-architecture.md`.

## Status

Phases 1-5 are largely complete. Remaining open items are tracked in the [OpenCompany Plane project](https://plane.gingermedia.biz/kosmokrator/projects/ceaf5d22-612a-42bf-9cc8-0dac054cdf0c/issues/):

| Issue | Open item | Phase |
|-------|-----------|-------|
| OC-17 | Remove duplicated built-in context-window assumptions | 1 |
| OC-18 | ContextPruner: protect recent user turns and already-truncated entries | 3 |
| OC-19 | CompactionPlan: preserve protected context during compaction | 4 |
| OC-20 | Missing test coverage: compaction plan building + memory extraction | 4 |
| OC-48 | Prompt cache metrics not wired to token metrics path | 5 |

## Completed work

### Phase 1 - Catalog-Backed Context Windows
- Refactored `ModelContextRegistry` as catalog-backed adapter
- Reads defaults from app-owned `AiCatalog`
- `AppSetting` overrides as top-priority layer
- Callers pass provider + model
- Tests: relay exact match, admin override precedence, unknown model fallback

### Phase 2 — Shared Context Budget (complete)
- `ContextBudget.php` centralizes all threshold math
- Consumed by `ConversationCompactionService`, `MemoryFlushService`, `AgentRespondJob`

### Phase 3 — Context Pruning (partial)
- `ContextPruner.php` scoped to OpenCompany `read` tools
- Integrated into checkpoint/history loading path
- Minimum savings threshold enforced

### Phase 4 — Compaction Pipeline (partial)
- `CompactionPlan.php` + `CompactionMemoryExtractor.php`
- Failure counting / circuit breaker
- Durable memory extraction from summaries

### Phase 5 - Prompt Cache Planning (partial)
- Prompt splitting in `OpenCompanyAgent`
- Split stable/volatile prompts are preserved for gateway/cache policy handling
- Provider cache options are read from app-owned catalog metadata

## Notes

- Prompt splitting alone is not the same as provider-side prompt caching.
- Provider-side prompt caching is app-owned metadata and gateway behavior now, not Prism Relay behavior.
- OpenCompany pruning rules are based on OpenCompany tools, not KosmoKrator shell tools.
