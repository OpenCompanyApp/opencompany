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

    'glm' => [
        'category' => 'ai-models',
        'name' => 'GLM (Zhipu AI)',
        'description' => 'General-purpose Chinese LLM',
        'icon' => 'ph:brain',
        'default_url' => 'https://open.bigmodel.cn/api/paas/v4',
        'api_format' => 'openai_compat',
        'api_key_url' => 'https://open.bigmodel.cn/',
    ],

    'glm-coding' => [
        'category' => 'ai-models',
        'name' => 'GLM Coding Plan',
        'description' => 'Specialized coding LLM via Zhipu Coding Plan',
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
    | Communication Integrations
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'name' => 'Telegram',
        'description' => 'Telegram Bot for DMs, notifications, and approvals',
        'icon' => 'ph:telegram-logo',
    ],


];
