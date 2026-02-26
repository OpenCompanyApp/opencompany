<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Route Prefix
    |--------------------------------------------------------------------------
    |
    | The chatogrator package registers its own webhook route at this prefix.
    | We use our own ChatWebhookController at /api/webhooks/chat/{adapter}
    | for workspace resolution, so this is set to a non-conflicting prefix.
    |
    */

    'route_prefix' => 'internal/chatogrator/webhooks',

    /*
    |--------------------------------------------------------------------------
    | Webhook Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [],

];
