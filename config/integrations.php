<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Static AI Model Integrations
    |--------------------------------------------------------------------------
    |
    | These are the built-in AI provider integrations that use API keys or
    | OAuth tokens. Each entry defines the provider's display metadata and
    | available models. This is the single source of truth for model lists.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'category' => 'ai-models',
        'name' => 'Anthropic',
        'description' => 'Claude models — advanced reasoning and coding',
        'icon' => 'ph:chat-circle-dots',
        'default_url' => 'https://api.anthropic.com/v1',
        'api_format' => 'anthropic',
        'api_key_url' => 'https://console.anthropic.com/settings/keys',
    ],

    'openai' => [
        'category' => 'ai-models',
        'name' => 'OpenAI',
        'description' => 'GPT models — the most widely used AI platform',
        'icon' => 'ph:open-ai-logo',
        'default_url' => 'https://api.openai.com/v1',
        'api_format' => 'openai',
        'api_key_url' => 'https://platform.openai.com/api-keys',
    ],

    'gemini' => [
        'category' => 'ai-models',
        'name' => 'Google Gemini',
        'description' => 'Gemini models — multimodal AI with generous free tier',
        'icon' => 'ph:google-logo',
        'default_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'api_format' => 'gemini',
        'api_key_url' => 'https://aistudio.google.com/apikey',
    ],

    'deepseek' => [
        'category' => 'ai-models',
        'name' => 'DeepSeek',
        'description' => 'Cost-effective reasoning and coding models',
        'icon' => 'ph:magnifying-glass',
        'default_url' => 'https://api.deepseek.com/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://platform.deepseek.com/api_keys',
    ],

    'groq' => [
        'category' => 'ai-models',
        'name' => 'Groq',
        'description' => 'Ultra-fast inference — 500+ tokens/sec',
        'icon' => 'ph:lightning',
        'default_url' => 'https://api.groq.com/openai/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://console.groq.com/keys',
    ],

    'mistral' => [
        'category' => 'ai-models',
        'name' => 'Mistral',
        'description' => 'EU-based provider with Codestral for code',
        'icon' => 'ph:wind',
        'default_url' => 'https://api.mistral.ai/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://console.mistral.ai/api-keys',
    ],

    'xai' => [
        'category' => 'ai-models',
        'name' => 'xAI',
        'description' => 'Grok models — reasoning and real-time knowledge',
        'icon' => 'ph:x-logo',
        'default_url' => 'https://api.x.ai/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://console.x.ai/',
    ],

    'ollama' => [
        'category' => 'ai-models',
        'name' => 'Ollama',
        'description' => 'Run models locally — zero cost, full privacy',
        'icon' => 'ph:desktop-tower',
        'default_url' => 'http://localhost:11434/v1',
        'api_format' => 'ollama',
        'api_key_url' => null,
    ],

    'openrouter' => [
        'category' => 'ai-models',
        'name' => 'OpenRouter',
        'description' => 'Access 200+ models from all providers with one API key',
        'icon' => 'ph:arrows-split',
        'default_url' => 'https://openrouter.ai/api/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://openrouter.ai/keys',
    ],

    'perplexity' => [
        'category' => 'ai-models',
        'name' => 'Perplexity',
        'description' => 'Search-native reasoning models',
        'icon' => 'ph:compass',
        'default_url' => 'https://api.perplexity.ai',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://www.perplexity.ai/settings/api',
    ],

    'minimax' => [
        'category' => 'ai-models',
        'name' => 'MiniMax Coding Plan',
        'description' => 'MiniMax coding models via Anthropic-compatible API',
        'icon' => 'ph:cube',
        'default_url' => 'https://api.minimax.io/anthropic/v1',
        'api_format' => 'anthropic',
        'api_key_url' => 'https://platform.minimax.io/docs/coding-plan/intro',
    ],

    'minimax-cn' => [
        'category' => 'ai-models',
        'name' => 'MiniMax Coding Plan (CN)',
        'description' => 'MiniMax coding models — China region endpoint',
        'icon' => 'ph:cube',
        'default_url' => 'https://api.minimaxi.com/anthropic/v1',
        'api_format' => 'anthropic',
        'api_key_url' => 'https://platform.minimaxi.com/docs/coding-plan/intro',
    ],

    'kimi' => [
        'category' => 'ai-models',
        'name' => 'Kimi (Moonshot AI)',
        'description' => 'Kimi K2 models — large context coding and reasoning',
        'icon' => 'ph:moon-stars',
        'default_url' => 'https://api.moonshot.ai/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://platform.moonshot.ai/console',
    ],

    'kimi-coding' => [
        'category' => 'ai-models',
        'name' => 'Kimi Coding Plan',
        'description' => 'Coding-focused Kimi models via Moonshot Coding Plan',
        'icon' => 'ph:code',
        'default_url' => 'https://api.moonshot.ai/v1',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://platform.moonshot.ai/console',
    ],

    'z-api' => [
        'category' => 'ai-models',
        'name' => 'Z.AI API',
        'description' => 'Zhipu AI standard API endpoint',
        'icon' => 'ph:brain',
        'default_url' => 'https://open.bigmodel.cn/api/paas/v4',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://open.bigmodel.cn/',
    ],

    'z' => [
        'category' => 'ai-models',
        'name' => 'Z.AI Coding Plan',
        'description' => 'Zhipu AI coding-plan endpoint',
        'icon' => 'ph:code',
        'default_url' => 'https://api.z.ai/api/coding/paas/v4',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://api.z.ai/',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    */

    'codex' => [
        'category' => 'ai-models',
        'name' => 'OpenAI Codex',
        'description' => 'Use ChatGPT Pro/Plus subscription for $0 token costs',
        'icon' => 'ph:open-ai-logo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Search / Fetch Providers
    |--------------------------------------------------------------------------
    */

    'web.tavily' => [
        'category' => 'web-providers',
        'name' => 'Tavily Web',
        'description' => 'Search and extract web pages through Tavily.',
        'icon' => 'ph:magnifying-glass',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true, 'hint' => 'Used by web_search and provider-backed web_fetch.'],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.tavily.com'],
        ],
    ],

    'web.zai' => [
        'category' => 'web-providers',
        'name' => 'Z.AI Web',
        'description' => 'Z.AI remote MCP search, coding-plan chat search, and reader fetch.',
        'icon' => 'ph:code',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'remote_url' => ['label' => 'Remote MCP URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.z.ai/api/mcp/web_search_prime/mcp'],
            'base_url' => ['label' => 'Coding PaaS URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.z.ai/api/coding/paas/v4'],
        ],
    ],

    'web.firecrawl' => [
        'category' => 'web-providers',
        'name' => 'Firecrawl Web',
        'description' => 'Search and scrape web pages through Firecrawl.',
        'icon' => 'ph:flame',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.firecrawl.dev'],
        ],
    ],

    'web.exa' => [
        'category' => 'web-providers',
        'name' => 'Exa Web',
        'description' => 'Neural web search and page contents through Exa.',
        'icon' => 'ph:sparkle',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.exa.ai'],
        ],
    ],

    'web.brave' => [
        'category' => 'web-providers',
        'name' => 'Brave Search',
        'description' => 'Search results from Brave Search API.',
        'icon' => 'ph:compass',
        'config_fields' => [
            'api_key' => ['label' => 'Subscription Token', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.search.brave.com'],
        ],
    ],

    'web.parallel' => [
        'category' => 'web-providers',
        'name' => 'Parallel Web',
        'description' => 'Search and extract web content through Parallel.',
        'icon' => 'ph:git-branch',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.parallel.ai'],
        ],
    ],

    'web.jina' => [
        'category' => 'web-providers',
        'name' => 'Jina Reader',
        'description' => 'Reader and search endpoints for LLM-friendly web text. API key is optional for free-tier use.',
        'icon' => 'ph:article',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => false],
            'search_url' => ['label' => 'Search URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://s.jina.ai'],
            'reader_url' => ['label' => 'Reader URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://r.jina.ai'],
        ],
    ],

    'web.searxng' => [
        'category' => 'web-providers',
        'name' => 'SearXNG',
        'description' => 'Self-hosted metasearch provider.',
        'icon' => 'ph:binoculars',
        'config_fields' => [
            'base_url' => ['label' => 'Base URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://search.example.com'],
        ],
    ],

    'web.perplexity' => [
        'category' => 'web-providers',
        'name' => 'Perplexity Search',
        'description' => 'Search API from Perplexity.',
        'icon' => 'ph:compass-tool',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.perplexity.ai'],
        ],
    ],

    'web.openai_native' => [
        'category' => 'web-providers',
        'name' => 'OpenAI Native Web Search',
        'description' => 'OpenAI Responses API native web search provider.',
        'icon' => 'ph:open-ai-logo',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.openai.com/v1'],
        ],
    ],

    'web.anthropic_native' => [
        'category' => 'web-providers',
        'name' => 'Anthropic Native Web Search',
        'description' => 'Anthropic Messages API native web search provider.',
        'icon' => 'ph:chat-circle-dots',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'required' => true],
            'base_url' => ['label' => 'API URL', 'type' => 'url', 'required' => false, 'placeholder' => 'https://api.anthropic.com'],
        ],
    ],

    // Chat platform integrations (Telegram, Slack, Discord) have been moved
    // to config/chat_integrations.php to keep them separate from AI model providers.

];
