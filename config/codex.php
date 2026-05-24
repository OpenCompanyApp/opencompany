<?php

return [
    'url' => env('CODEX_URL', 'https://chatgpt.com/backend-api/codex'),
    'oauth_port' => env('CODEX_OAUTH_PORT', 9876),
    'callback_route' => env('CODEX_CALLBACK_ROUTE', '/auth/codex/callback'),
    'table' => env('CODEX_TOKEN_TABLE', 'codex_tokens'),
    'id_token_add_organizations' => env('CODEX_ID_TOKEN_ADD_ORGANIZATIONS', true),
    'originator' => env('CODEX_ORIGINATOR', 'opencompany'),
    'user_agent' => env('CODEX_USER_AGENT', 'opencompany'),
];
