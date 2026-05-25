# AI Provider Runtime Architecture

OpenCompany no longer depends on relay or provider-server packages for provider/model metadata, runtime registration, prompt-cache options, model context windows, embeddings, or OpenAI-compatible gateway access. The app owns those boundaries directly so product behavior can evolve without waiting on a shared AI package.

## Runtime Shape

```text
app/Domain/Ai/
├── Catalog/
│   ├── AiCatalog.php
│   ├── ProviderInfo.php
│   ├── ModelInfo.php
│   ├── PricingInfo.php
│   ├── generated/providers.php
│   └── overrides/providers.php
├── Runtime/
│   ├── OpenCompanyAiProviderFactory.php
│   ├── OpenCompanyAiProviderRegistrar.php
│   └── OpenRouterBillingGateway.php
├── Embeddings/
│   └── EmbeddingClient.php
├── Gateway/
│   ├── AiGateway.php
│   └── AiGatewayModelRegistry.php
├── Codex/
│   ├── CodexOAuthService.php
│   ├── CodexTokenStore.php
│   └── Stores/EloquentCodexTokenStore.php
└── Usage/
    ├── CostCalculator.php
    ├── LlmUsageEvent.php
    ├── OpenRouterGenerationStore.php
    └── UsageRecorder.php

app/Ai/
├── Gateways/
│   ├── CachingTextGateway.php
│   ├── PromptCachePolicy.php
│   ├── CodexTextGateway.php
│   └── UnsupportedTextGateway.php
├── Providers/
│   └── CohereTextProvider.php
└── TextGeneration/
    └── MergedTextGenerationOptions.php
```

```text
config + DB settings
        │
        ▼
AiCatalog ──► ProviderCatalog / ModelCatalog ──► integrations UI
        │
        ▼
OpenCompanyAiProviderRegistrar ──► Laravel AI provider names
        │
        ▼
OpenCompanyAiProviderFactory ──► Laravel AI gateways
        │
        ├── PromptCachePolicy from catalog cache metadata
        └── OpenRouterBillingGateway captures provider generation metadata

External OpenAI-compatible clients call `/api/ai-gateway/v1/*`. Gateway bearer
keys bind the workspace, expose only workspace-enabled models, route through the
same OpenCompany provider runtime, and write usage rows into `llm_usage_events`.

Memory indexing uses `EmbeddingClient`, which resolves workspace provider config
through the same AI catalog path and calls Laravel AI embedding providers. Codex
subscription auth is app-local under `app/Domain/Ai/Codex`; it no longer ships as
a package provider bridge.
```

## Ownership Rules

- Providers are explicit and app-owned. Add a provider in `app/Domain/Ai/Catalog/generated/providers.php` or override local metadata in `overrides/providers.php`.
- Models are permissive. Unknown model IDs are allowed for known providers and use provider-level context, capability, and pricing fallbacks.
- Pricing is an estimate unless a provider returns actual billing. OpenRouter usage rows can be reconciled through `ReconcileOpenRouterUsage`.
- Workspace `IntegrationSetting` rows own credentials, enabled state, custom URLs, and optional model allowlists.
- OpenCompany AI Gateway owns external API access. It does not register package-owned server routes or package-owned model handlers.
- Chat streaming is catalog-gated. `AgentRun::stream()` checks `AiCatalog`
  model metadata before entering Laravel AI's streaming path; providers or
  models marked `supports_streaming=false` fall back to the durable
  non-streaming response path instead of branching in chat orchestration code.

## Usage And Cost Flow

```text
Agent response or AI Gateway request
  ├── TokenMetrics keeps task result snapshots
  └── UsageRecorder/AiGateway writes llm_usage_events
        ├── requested provider/model
        ├── resolved provider/model
        ├── token and cache-token counts
        ├── catalog estimated cost
        └── provider actual cost when available
```

`TokenAnalyticsController` reads `llm_usage_events` when the table exists and falls back to task JSON for older databases that have not migrated yet.

## Model Release Policy

OpenCompany should not update code for every provider model release. Add catalog rows only when we want first-class defaults, labels, pricing, or context-window metadata. Runtime calls should accept a custom model ID as long as the provider is known and configured.
