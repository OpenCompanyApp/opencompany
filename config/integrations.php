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

    'glm' => [
        'name' => 'GLM (Zhipu AI)',
        'description' => 'General-purpose Chinese LLM',
        'icon' => 'ph:brain',
        'default_url' => 'https://open.bigmodel.cn/api/paas/v4',
    ],

    'glm-coding' => [
        'name' => 'GLM Coding Plan',
        'description' => 'Specialized coding LLM via Zhipu Coding Plan',
        'icon' => 'ph:code',
        'default_url' => 'https://api.z.ai/api/coding/paas/v4',
    ],

    'codex' => [
        'name' => 'OpenAI Codex',
        'description' => 'Use ChatGPT Pro/Plus subscription for $0 token costs',
        'icon' => 'ph:open-ai-logo',
    ],

    'telegram' => [
        'name' => 'Telegram',
        'description' => 'Telegram Bot for DMs, notifications, and approvals',
        'icon' => 'ph:telegram-logo',
    ],


];
