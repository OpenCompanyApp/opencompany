<?php

/*
| Connection secret vault (docs/planning/connections-events-routines.md).
| Optional dedicated key (base64:…); falls back to APP_KEY when unset.
*/

return [
    'key' => env('VAULT_KEY'),
];
