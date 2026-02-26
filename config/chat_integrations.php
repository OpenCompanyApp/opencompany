<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat Platform Integrations
    |--------------------------------------------------------------------------
    |
    | These define the external chat platforms that can be connected to
    | workspaces via the Chatogrator package. Each platform uses the Adapter
    | pattern from Chatogrator for unified message handling.
    |
    | Note: These are separate from AI model integrations in config/integrations.php.
    | A GitHub chat adapter (for PR comment conversations) is distinct from a
    | GitHub app integration (for code, CI/CD).
    |
    */

    'telegram' => [
        'name' => 'Telegram',
        'description' => 'Telegram Bot for DMs, notifications, and approvals',
        'icon' => 'ph:telegram-logo',
        'config_fields' => [
            'api_key' => ['label' => 'Bot Token', 'type' => 'secret', 'required' => true],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'secret', 'auto' => true],
            'bot_username' => ['label' => 'Bot Username', 'type' => 'text'],
            'bot_user_id' => ['label' => 'Bot User ID', 'type' => 'text'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
            'allowed_users' => ['label' => 'Allowed User IDs', 'type' => 'array'],
        ],
    ],

    'slack' => [
        'name' => 'Slack',
        'description' => 'Connect your Slack workspace for team chat',
        'icon' => 'ph:slack-logo',
        'config_fields' => [
            'api_key' => ['label' => 'Bot Token', 'type' => 'secret', 'required' => true],
            'signing_secret' => ['label' => 'Signing Secret', 'type' => 'secret', 'required' => true],
            'team_id' => ['label' => 'Team ID', 'type' => 'text'],
            'bot_user_id' => ['label' => 'Bot User ID', 'type' => 'text'],
            'bot_name' => ['label' => 'Bot Name', 'type' => 'text'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

    'discord' => [
        'name' => 'Discord',
        'description' => 'Connect a Discord server',
        'icon' => 'ph:discord-logo',
        'config_fields' => [
            'api_key' => ['label' => 'Bot Token', 'type' => 'secret', 'required' => true],
            'public_key' => ['label' => 'Public Key', 'type' => 'secret', 'required' => true],
            'application_id' => ['label' => 'Application ID', 'type' => 'text', 'required' => true],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

];
