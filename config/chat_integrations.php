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
        'category' => 'chat-platforms',
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
        'category' => 'chat-platforms',
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
        'category' => 'chat-platforms',
        'config_fields' => [
            'api_key' => ['label' => 'Bot Token', 'type' => 'secret', 'required' => true],
            'public_key' => ['label' => 'Public Key', 'type' => 'secret', 'required' => true, 'hint' => 'Ed25519 public key for interaction verification'],
            'application_id' => ['label' => 'Application ID', 'type' => 'text', 'required' => true],
            'gateway_secret' => ['label' => 'Gateway Secret', 'type' => 'secret', 'hint' => 'Secret for forwarded gateway events (optional)'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

    'teams' => [
        'name' => 'Microsoft Teams',
        'description' => 'Connect a Teams channel',
        'icon' => 'ph:microsoft-teams-logo',
        'category' => 'chat-platforms',
        'config_fields' => [
            'app_id' => ['label' => 'App ID', 'type' => 'text', 'required' => true, 'hint' => 'Azure Bot registration Application (client) ID'],
            'app_password' => ['label' => 'App Password', 'type' => 'secret', 'required' => true, 'hint' => 'Azure Bot registration client secret'],
            'tenant_id' => ['label' => 'Tenant ID', 'type' => 'text', 'hint' => 'Azure AD tenant ID (for single-tenant bots)'],
            'bot_name' => ['label' => 'Bot Name', 'type' => 'text'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

    'google_chat' => [
        'name' => 'Google Chat',
        'description' => 'Google Chat space integration',
        'icon' => 'ph:google-chat-logo',
        'category' => 'chat-platforms',
        'config_fields' => [
            'service_account_json' => ['label' => 'Service Account JSON', 'type' => 'secret', 'hint' => 'GCP service account key JSON for API access'],
            'project_id' => ['label' => 'Project ID', 'type' => 'text', 'hint' => 'GCP project ID'],
            'webhook_secret' => ['label' => 'Webhook Verification Token', 'type' => 'secret', 'hint' => 'Token for verifying inbound webhooks'],
            'bot_name' => ['label' => 'Bot Name', 'type' => 'text'],
            'bot_user_id' => ['label' => 'Bot User ID', 'type' => 'text'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

    'github_chat' => [
        'name' => 'GitHub',
        'description' => 'Chat via GitHub issue/PR comments',
        'icon' => 'ph:github-logo',
        'category' => 'chat-platforms',
        'config_fields' => [
            'token' => ['label' => 'Personal Access Token', 'type' => 'secret', 'hint' => 'GitHub PAT with repo scope for posting comments'],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'secret', 'required' => true, 'hint' => 'HMAC secret from GitHub webhook settings'],
            'bot_name' => ['label' => 'Bot Name', 'type' => 'text'],
            'bot_user_id' => ['label' => 'Bot User ID', 'type' => 'text'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

    'linear_chat' => [
        'name' => 'Linear',
        'description' => 'Chat via Linear issue comments',
        'icon' => 'ph:line-segments',
        'category' => 'chat-platforms',
        'config_fields' => [
            'api_key' => ['label' => 'API Key', 'type' => 'secret', 'hint' => 'Linear personal API key for posting comments'],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'secret', 'required' => true, 'hint' => 'Signing secret from Linear webhook settings'],
            'bot_name' => ['label' => 'Bot Name', 'type' => 'text'],
            'bot_user_id' => ['label' => 'Bot User ID', 'type' => 'text'],
            'default_agent_id' => ['label' => 'Default Agent', 'type' => 'agent_select'],
        ],
    ],

];
