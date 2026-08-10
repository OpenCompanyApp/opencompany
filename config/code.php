<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Script runtime
    |--------------------------------------------------------------------------
    |
    | Persisted automations pin this identifier. A future engine or contract
    | change must use a new value instead of silently changing old semantics.
    |
    */
    'runtime' => 'quickjs-v1',

    /*
    |--------------------------------------------------------------------------
    | Host-owned resource profiles
    |--------------------------------------------------------------------------
    |
    | Agents and HTTP clients choose a workflow, never raw resource limits.
    | Callback wall time is separate because QuickJS correctly excludes time
    | spent in PHP/provider calls from its JavaScript CPU budget.
    |
    */
    'profiles' => [
        'agent' => [
            'memory_limit' => 32 * 1024 * 1024,
            'cpu_limit' => 5.0,
            'stack_limit' => 512 * 1024,
            'source_limit' => 256 * 1024,
            'result_limit' => 1024 * 1024,
            'output_limit' => 64 * 1024,
            'log_limit' => 200,
            'callback_limit' => 50,
            'callback_result_limit' => 2 * 1024 * 1024,
            'callback_wall_limit' => 30.0,
            'callback_total_wall_limit' => 90.0,
        ],
        'automation' => [
            'memory_limit' => 32 * 1024 * 1024,
            'cpu_limit' => 10.0,
            'stack_limit' => 512 * 1024,
            'source_limit' => 512 * 1024,
            'result_limit' => 2 * 1024 * 1024,
            'output_limit' => 128 * 1024,
            'log_limit' => 500,
            'callback_limit' => 100,
            'callback_result_limit' => 4 * 1024 * 1024,
            'callback_wall_limit' => 60.0,
            'callback_total_wall_limit' => 300.0,
        ],
        'console' => [
            'memory_limit' => 32 * 1024 * 1024,
            'cpu_limit' => 5.0,
            'stack_limit' => 512 * 1024,
            'source_limit' => 512 * 1024,
            'result_limit' => 2 * 1024 * 1024,
            'output_limit' => 128 * 1024,
            'log_limit' => 500,
            'callback_limit' => 0,
            'callback_result_limit' => 0,
            'callback_wall_limit' => 0.0,
            'callback_total_wall_limit' => 0.0,
        ],
    ],
];
