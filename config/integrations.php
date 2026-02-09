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
        'models' => [
            'glm-4-plus' => 'GLM 4 Plus (Most Capable)',
            'glm-4' => 'GLM 4',
            'glm-4-air' => 'GLM 4 Air (Balanced)',
            'glm-4-flash' => 'GLM 4 Flash (Fast)',
        ],
    ],

    'glm-coding' => [
        'name' => 'GLM Coding Plan',
        'description' => 'Specialized coding LLM via Zhipu Coding Plan',
        'icon' => 'ph:code',
        'default_url' => 'https://api.z.ai/api/coding/paas/v4',
        'models' => [
            'glm-4.7' => 'GLM 4.7 (Coding Optimized)',
        ],
    ],

    'codex' => [
        'name' => 'OpenAI Codex',
        'description' => 'Use ChatGPT Pro/Plus subscription for $0 token costs',
        'icon' => 'ph:open-ai-logo',
        'models' => [
            'gpt-5.3-codex' => 'GPT 5.3 Codex (Latest)',
            'gpt-5.2-codex' => 'GPT 5.2 Codex',
            'gpt-5.2' => 'GPT 5.2',
            'gpt-5.1-codex' => 'GPT 5.1 Codex',
            'gpt-5.1-codex-max' => 'GPT 5.1 Codex Max',
            'gpt-5.1-codex-mini' => 'GPT 5.1 Codex Mini',
            'gpt-5-codex' => 'GPT 5 Codex',
            'gpt-5-codex-mini' => 'GPT 5 Codex Mini',
            'gpt-5' => 'GPT 5',
        ],
    ],

    'telegram' => [
        'name' => 'Telegram',
        'description' => 'Telegram Bot for DMs, notifications, and approvals',
        'icon' => 'ph:telegram-logo',
    ],

];
