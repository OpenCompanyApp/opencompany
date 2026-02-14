# AI Model Providers — Audit & Priority

Reference of all major AI providers, their compatibility with OpenCompany, and implementation priority.

> Source: [opencode provider catalog](../inspiration/opencode/) (85+ providers, 2,500+ models)

---

## Current Status in OpenCompany

### Fully Integrated
| Provider | Key | Integration Type | Models |
|---|---|---|---|
| GLM (Zhipu AI) | `glm` | IntegrationSetting + GlmPrismGateway | Dynamic (API fetch) |
| GLM Coding | `glm-coding` | IntegrationSetting + GlmPrismGateway | Dynamic (API fetch) |
| Codex (ChatGPT) | `codex` | OAuth + CodexPrismGateway | Dynamic (API fetch) |

### Partially Wired (`.env` only, hardcoded models, no config UI)
| Provider | Key | Prism Native | Default Model |
|---|---|---|---|
| Anthropic | `anthropic` | Yes | claude-sonnet-4-5-20250929 |
| OpenAI | `openai` | Yes | gpt-4o |
| Gemini | `gemini` | Yes | gemini-2.0-flash |
| DeepSeek | `deepseek` | Yes | deepseek-chat |
| Groq | `groq` | Yes | llama-3.3-70b-versatile |
| Mistral | `mistral` | Yes | mistral-large-latest |
| xAI (Grok) | `xai` | Yes | grok-2 |

### In Config Only (not in `allProviders` endpoint)
| Provider | Key | Notes |
|---|---|---|
| Ollama | `ollama` | Local models, in prism.php + resolver |
| OpenRouter | `openrouter` | In prism.php + ai.php + resolver |

---

## All Major Providers

| Provider | API Format | Prism PHP | OC Status | Base URL | Notable Models | Tier |
|---|---|---|---|---|---|---|
| **Anthropic** | Custom (Messages) | Native | Partial | `api.anthropic.com/v1` | Claude Opus 4, Sonnet 4.5, Haiku 4.5 | 1 |
| **OpenAI** | Native | Native | Partial | `api.openai.com/v1` | GPT-4o, GPT-4.1, o1, o3 | 1 |
| **Google Gemini** | Custom (Google AI) | Native | Partial | `generativelanguage.googleapis.com` | Gemini 2.5 Pro/Flash, 2.0 Flash | 1 |
| **DeepSeek** | OpenAI-compat | Native | Partial | `api.deepseek.com/v1` | DeepSeek-V3, DeepSeek-R1 | 1 |
| **Groq** | OpenAI-compat | Native | Partial | `api.groq.com/openai/v1` | Llama 3.3-70B, Qwen QwQ-32B | 1 |
| **Mistral** | OpenAI-compat | Native | Partial | `api.mistral.ai/v1` | Codestral, Mistral Large, Devstral | 1 |
| **xAI** | OpenAI-compat | Native | Partial | `api.x.ai/v1` | Grok-4, Grok-3 | 1 |
| **Ollama** | OpenAI-compat | Native | Config only | `localhost:11434` | Any local model (Llama, Qwen, etc.) | 1 |
| **OpenRouter** | OpenAI-compat | Native | Config only | `openrouter.ai/api/v1` | 149+ models from all providers | 1 |
| **Fireworks AI** | OpenAI-compat | No | None | `api.fireworks.ai/inference/v1` | DeepSeek-V3/R1, Qwen3-235B | 2 |
| **Together AI** | OpenAI-compat | No | None | `api.together.xyz/v1` | Kimi-K2, Llama 3.3, Qwen3 | 2 |
| **Cerebras** | OpenAI-compat | No | None | `api.cerebras.ai/v1` | Llama 3.3-70B (sub-second), Qwen3-235B | 2 |
| **Perplexity** | OpenAI-compat | No | None | `api.perplexity.ai` | Sonar Pro (search-augmented AI) | 2 |
| **Nvidia NIM** | OpenAI-compat | No | None | `integrate.api.nvidia.com/v1` | Nemotron, Kimi-K2.5, Llama 3.3 | 3 |
| **Hugging Face** | OpenAI-compat | No | None | `router.huggingface.co/v1` | Kimi-K2, Qwen3-235B, Llama 3.3 | 3 |
| **SiliconFlow** | OpenAI-compat | No | None | `api.siliconflow.com/v1` | DeepSeek-V3, Qwen3, Ling-2.0 | 3 |
| **Scaleway** | OpenAI-compat | No | None | `api.scaleway.ai/v1` | Qwen3-235B, Devstral, Llama 3.1 | 3 |
| **Amazon Bedrock** | AWS Sigv4 | Package (`prism-php/bedrock`) | None | AWS regional | Claude via Bedrock, Titan, Cohere | 4 |
| **Azure OpenAI** | Azure-specific | No | None | Azure deployment URLs | GPT-4o, GPT-4.1 via Azure | 4 |
| **Cohere** | Custom | No | None | `api.cohere.com/v1` | Command-R+, Command-A | 4 |
| **Cloudflare Workers AI** | OpenAI-compat | No | None | Cloudflare account URLs | Mistral-7B, Llama variants | 5 |
| **Cloudflare AI Gateway** | Proxy | No | None | Gateway URLs | Routes to other providers | 5 |
| **Poe** | Custom | No | None | `api.poe.com` | 115 models (aggregator) | 5 |
| **NovitaAI** | OpenAI-compat | No | None | NovitaAI URLs | 79 open-source models | 5 |
| **Venice AI** | OpenAI-compat | No | None | Venice URLs | Privacy-focused, open-source | 5 |
| **Deep Infra** | OpenAI-compat | No | None | `api.deepinfra.com/v1/openai` | Llama, Qwen, Mistral | 5 |

---

## Priority Tiers

### Tier 1 — Upgrade partial → full integration (already have Prism drivers)
Need: `config/integrations.php` entry + config modal + model fetch + connection test

1. **Anthropic** — #1 competitor model family, reasoning and coding
2. **OpenAI** — most widely used, largest ecosystem
3. **Gemini** — generous free tier, multimodal, strong performance
4. **DeepSeek** — cheapest quality-per-token, open-source, fast
5. **Groq** — fastest inference (>500 tok/s), free tier
6. **Mistral** — EU provider, Codestral (code-specialized), GDPR
7. **xAI** — Grok models, reasoning
8. **Ollama** — local/self-hosted, zero cost, privacy
9. **OpenRouter** — meta-aggregator (149+ models, one API key)

### Tier 2 — New OpenAI-compatible providers (easy to add)
Need: Everything from Tier 1 + `PrismManager::extend` + resolver entry

1. **Fireworks AI** — fast inference, competitive pricing, hosts popular open models
2. **Together AI** — popular platform, strong open model selection
3. **Cerebras** — fastest inference hardware (Llama-70B in <1s)
4. **Perplexity** — unique: models that search the web while reasoning

### Tier 3 — More OpenAI-compatible (same effort as Tier 2)
5. **Nvidia NIM** — enterprise credibility, free tier for testing
6. **Hugging Face** — massive model ecosystem, inference endpoints
7. **SiliconFlow** — strong for Asian market, competitive pricing
8. **Scaleway** — EU-hosted, GDPR compliance, European option

### Tier 4 — Requires more work
9. **Amazon Bedrock** — `prism-php/bedrock` package exists, AWS credentials needed
10. **Azure OpenAI** — no Prism package, needs custom driver (like GLM pattern)
11. **Cohere** — no Prism driver, custom API

### Tier 5 — Aggregators/niche (low priority)
- Cloudflare, Poe, NovitaAI, Venice, Deep Infra, Vercel AI Gateway
- Most models already accessible via OpenRouter

---

## Implementation Pattern

All OpenAI-compatible providers follow the same pattern as GLM:

```
config/integrations.php  →  IntegrationSetting  →  PrismManager::extend  →  DynamicProviderResolver
       ↓                           ↓
   Config Modal UI          API key + models stored (encrypted)
```

A **generic ProviderConfigModal** could handle all OpenAI-compatible providers with the same UI:
API key field + base URL + model dropdown + test connection + enable toggle.

This makes adding a new provider a config-only change (~5 lines in `integrations.php`).
