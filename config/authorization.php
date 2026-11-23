<?php

/*
|--------------------------------------------------------------------------
| Authorization (install-level)
|--------------------------------------------------------------------------
|
| Roles and capabilities are fixed in code (App\Domain\Authorization\Engine\
| Capabilities); nothing here can widen them. This file only names install
| operators and AI Gateway key limits.
|
| platform_operator_emails: humans who may read platform-wide diagnostics
| (the shared application log tail). Workspace owners and admins are not
| platform operators unless listed here. Comma-separated in the env form.
|
*/

return [
    'platform_operator_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PLATFORM_OPERATOR_EMAILS', '')),
    ))),

    'ai_gateway' => [
        // Every key expires. Default and maximum lifetime in days.
        'default_key_days' => (int) env('AI_GATEWAY_KEY_DEFAULT_DAYS', 90),
        'max_key_days' => (int) env('AI_GATEWAY_KEY_MAX_DAYS', 365),
    ],
];
