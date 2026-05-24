<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Search And Fetch Runtime
    |--------------------------------------------------------------------------
    |
    | Static catalog and conservative defaults for app-owned web tools.
    | Workspace-specific provider credentials live in encrypted
    | IntegrationSetting rows, while this file keeps provider IDs, base URLs,
    | and safe runtime limits in one predictable app-owned place.
    |
    */

    'cache' => [
        'enabled' => env('WEB_CACHE_ENABLED', true),
        'ttl_seconds' => env('WEB_CACHE_TTL_SECONDS', 900),
    ],

    'search' => [
        'default_provider' => env('WEB_SEARCH_PROVIDER', 'tavily'),
        'fallback_providers' => array_values(array_filter(explode(',', env('WEB_SEARCH_FALLBACK_PROVIDERS', '')))),
        'max_results' => env('WEB_SEARCH_MAX_RESULTS', 8),
        'timeout_seconds' => env('WEB_SEARCH_TIMEOUT_SECONDS', 30),
        'output_limit_chars' => env('WEB_SEARCH_OUTPUT_LIMIT_CHARS', 60000),
    ],

    'fetch' => [
        'default_provider' => env('WEB_FETCH_PROVIDER', 'direct'),
        'fallback_providers' => array_values(array_filter(explode(',', env('WEB_FETCH_FALLBACK_PROVIDERS', 'jina')))),
        'allow_external' => env('WEB_FETCH_ALLOW_EXTERNAL', false),
        'allowed_private_hosts' => array_values(array_filter(array_map('trim', explode(',', env('WEB_FETCH_ALLOWED_PRIVATE_HOSTS', ''))))),
        'timeout_seconds' => env('WEB_FETCH_TIMEOUT_SECONDS', 30),
        'max_bytes' => env('WEB_FETCH_MAX_BYTES', 10485760),
        'max_chars' => env('WEB_FETCH_MAX_CHARS', 12000),
        'output_limit_chars' => env('WEB_FETCH_OUTPUT_LIMIT_CHARS', 100000),
        'user_agent' => env('WEB_FETCH_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'),
    ],

    'policy' => [
        'allowed_domains' => array_values(array_filter(explode(',', env('WEB_ALLOWED_DOMAINS', '')))),
        'blocked_domains' => array_values(array_filter(explode(',', env('WEB_BLOCKED_DOMAINS', '')))),
    ],

    'providers' => [
        'direct' => [
            'label' => 'Direct Fetch',
            'capabilities' => ['fetch'],
            'enabled' => true,
            'integration_id' => null,
        ],
        'tavily' => [
            'label' => 'Tavily',
            'capabilities' => ['search', 'fetch', 'crawl'],
            'enabled' => env('WEB_PROVIDER_TAVILY_ENABLED', true),
            'integration_id' => 'web.tavily',
            'api_key_env' => 'TAVILY_API_KEY',
            'api_key' => env('TAVILY_API_KEY'),
            'base_url' => env('TAVILY_API_URL', 'https://api.tavily.com'),
            'api_key_url' => 'https://app.tavily.com/home',
        ],
        'zai' => [
            'label' => 'Z.AI',
            'capabilities' => ['search', 'fetch'],
            'enabled' => env('WEB_PROVIDER_ZAI_ENABLED', true),
            'integration_id' => 'web.zai',
            'api_key_env' => 'ZAI_API_KEY',
            'api_key' => env('ZAI_API_KEY', env('GLM_API_KEY')),
            'remote_url' => env('ZAI_WEB_SEARCH_MCP_URL', 'https://api.z.ai/api/mcp/web_search_prime/mcp'),
            'base_url' => env('ZAI_CODING_BASE_URL', env('GLM_URL', 'https://api.z.ai/api/coding/paas/v4')),
            'chat_model' => env('ZAI_WEB_SEARCH_MODEL', 'glm-5.1'),
            'api_key_url' => 'https://api.z.ai/',
        ],
        'firecrawl' => [
            'label' => 'Firecrawl',
            'capabilities' => ['search', 'fetch', 'crawl'],
            'enabled' => env('WEB_PROVIDER_FIRECRAWL_ENABLED', true),
            'integration_id' => 'web.firecrawl',
            'api_key_env' => 'FIRECRAWL_API_KEY',
            'api_key' => env('FIRECRAWL_API_KEY'),
            'base_url' => env('FIRECRAWL_API_URL', 'https://api.firecrawl.dev'),
            'api_key_url' => 'https://www.firecrawl.dev/app/api-keys',
        ],
        'exa' => [
            'label' => 'Exa',
            'capabilities' => ['search', 'fetch'],
            'enabled' => env('WEB_PROVIDER_EXA_ENABLED', true),
            'integration_id' => 'web.exa',
            'api_key_env' => 'EXA_API_KEY',
            'api_key' => env('EXA_API_KEY'),
            'base_url' => env('EXA_API_URL', 'https://api.exa.ai'),
            'api_key_url' => 'https://dashboard.exa.ai/api-keys',
        ],
        'brave' => [
            'label' => 'Brave Search',
            'capabilities' => ['search'],
            'enabled' => env('WEB_PROVIDER_BRAVE_ENABLED', true),
            'integration_id' => 'web.brave',
            'api_key_env' => 'BRAVE_SEARCH_API_KEY',
            'api_key' => env('BRAVE_SEARCH_API_KEY'),
            'base_url' => env('BRAVE_SEARCH_API_URL', 'https://api.search.brave.com'),
            'api_key_url' => 'https://api.search.brave.com/app/keys',
        ],
        'parallel' => [
            'label' => 'Parallel',
            'capabilities' => ['search', 'fetch'],
            'enabled' => env('WEB_PROVIDER_PARALLEL_ENABLED', true),
            'integration_id' => 'web.parallel',
            'api_key_env' => 'PARALLEL_API_KEY',
            'api_key' => env('PARALLEL_API_KEY'),
            'base_url' => env('PARALLEL_API_URL', 'https://api.parallel.ai'),
        ],
        'jina' => [
            'label' => 'Jina Reader',
            'capabilities' => ['search', 'fetch'],
            'enabled' => env('WEB_PROVIDER_JINA_ENABLED', true),
            'integration_id' => 'web.jina',
            'api_key_env' => 'JINA_API_KEY',
            'api_key' => env('JINA_API_KEY'),
            'search_url' => env('JINA_SEARCH_URL', 'https://s.jina.ai'),
            'reader_url' => env('JINA_READER_URL', 'https://r.jina.ai'),
            'api_key_url' => 'https://jina.ai/reader',
        ],
        'searxng' => [
            'label' => 'SearXNG',
            'capabilities' => ['search'],
            'enabled' => env('WEB_PROVIDER_SEARXNG_ENABLED', false),
            'integration_id' => 'web.searxng',
            'base_url' => env('SEARXNG_BASE_URL'),
        ],
        'perplexity' => [
            'label' => 'Perplexity Search',
            'capabilities' => ['search'],
            'enabled' => env('WEB_PROVIDER_PERPLEXITY_ENABLED', true),
            'integration_id' => 'web.perplexity',
            'api_key_env' => 'PERPLEXITY_API_KEY',
            'api_key' => env('PERPLEXITY_API_KEY'),
            'base_url' => env('PERPLEXITY_API_URL', 'https://api.perplexity.ai'),
            'api_key_url' => 'https://www.perplexity.ai/settings/api',
        ],
        'openai_native' => [
            'label' => 'OpenAI Native Web Search',
            'capabilities' => ['search'],
            'enabled' => env('WEB_PROVIDER_OPENAI_NATIVE_ENABLED', true),
            'integration_id' => 'web.openai_native',
            'api_key_env' => 'OPENAI_API_KEY',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'model' => env('WEB_OPENAI_NATIVE_MODEL', env('OPENAI_MODEL', 'gpt-5')),
        ],
        'anthropic_native' => [
            'label' => 'Anthropic Native Web Search',
            'capabilities' => ['search'],
            'enabled' => env('WEB_PROVIDER_ANTHROPIC_NATIVE_ENABLED', true),
            'integration_id' => 'web.anthropic_native',
            'api_key_env' => 'ANTHROPIC_API_KEY',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com'),
            'model' => env('WEB_ANTHROPIC_NATIVE_MODEL', 'claude-sonnet-4-20250514'),
            'max_uses' => env('WEB_ANTHROPIC_NATIVE_MAX_USES', 5),
        ],
    ],
];
