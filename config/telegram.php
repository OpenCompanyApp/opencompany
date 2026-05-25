<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenCompany Telegram Runtime Flags
    |--------------------------------------------------------------------------
    |
    | Telegram is app-owned under the Chat domain. These switches make rollout
    | and rollback explicit while keeping legacy behavior opt-in and isolating
    | optional Telegram-only surfaces behind named flags.
    |
    */

    'experience_layer' => env('TELEGRAM_EXPERIENCE_LAYER', true),
    'legacy_webhook_enabled' => env('TELEGRAM_LEGACY_WEBHOOK_ENABLED', false),
    'private_topics_enabled' => env('TELEGRAM_PRIVATE_TOPICS_ENABLED', true),

    /*
    | Legacy product-module commands are intentionally opt-in. Telegram's
    | default UX is an agent shell; files, docs, lists, tables, calendar, and
    | automation work should normally be requested from the current agent.
    */
    'direct_resource_commands_enabled' => env('TELEGRAM_DIRECT_RESOURCE_COMMANDS_ENABLED', false),

    'media_ingestion_enabled' => env('TELEGRAM_MEDIA_INGESTION_ENABLED', true),
    'media_enrichment_enabled' => env('TELEGRAM_MEDIA_ENRICHMENT_ENABLED', true),
    'media_enrichment_timeout' => env('TELEGRAM_MEDIA_ENRICHMENT_TIMEOUT', 120),
    'media_transcription_provider' => env('TELEGRAM_MEDIA_TRANSCRIPTION_PROVIDER', env('AI_DEFAULT_FOR_TRANSCRIPTION', 'openai')),
    'media_transcription_model' => env('TELEGRAM_MEDIA_TRANSCRIPTION_MODEL'),
    'media_description_provider' => env('TELEGRAM_MEDIA_DESCRIPTION_PROVIDER', env('AI_DEFAULT_FOR_IMAGES', 'gemini')),
    'media_description_model' => env('TELEGRAM_MEDIA_DESCRIPTION_MODEL'),
    'draft_streaming_enabled' => env('TELEGRAM_DRAFT_STREAMING_ENABLED', false),
    'text_batching_enabled' => env('TELEGRAM_TEXT_BATCHING_ENABLED', true),
    'text_batch_window_seconds' => env('TELEGRAM_TEXT_BATCH_WINDOW_SECONDS', 3),
    'mini_app_enabled' => env('TELEGRAM_MINI_APP_ENABLED', true),
    'group_observer_enabled' => env('TELEGRAM_GROUP_OBSERVER_ENABLED', true),
    'guest_mode_enabled' => env('TELEGRAM_GUEST_MODE_ENABLED', false),
    'guest_rate_limit_seconds' => env('TELEGRAM_GUEST_RATE_LIMIT_SECONDS', 60),
    'guest_reply_text' => env(
        'TELEGRAM_GUEST_REPLY_TEXT',
        'OpenCompany received your guest message. To work with workspace agents, open this bot directly and run /link from your OpenCompany account.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot API Transport
    |--------------------------------------------------------------------------
    |
    | Keep the public Telegram Bot API as the default, but allow operators to
    | route requests and file downloads through a self-hosted/local Bot API
    | server. The file base defaults to "{bot_api_base_url}/file", matching
    | Telegram's hosted URL shape while still supporting split API/file origins.
    |
    */

    'bot_api_base_url' => env('TELEGRAM_BOT_API_BASE_URL', 'https://api.telegram.org'),
    'bot_api_file_base_url' => env('TELEGRAM_BOT_API_FILE_BASE_URL'),

];
