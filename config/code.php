<?php

return [
    'runtime' => 'opencompany-code-v1',
    'engine_binary' => env('RUBY_ENGINE_BINARY', base_path('bin/ruby-engine')),
    'engine_sha256' => env('RUBY_ENGINE_SHA256'),

    // Server-owned budgets. Automation wall time must remain below the current
    // 60-second worker timeout, leaving room for startup, persistence and cleanup.
    'profiles' => [
        'agent' => [
            'memory_limit' => 32 * 1024 * 1024,
            'instruction_limit' => 10_000_000,
            'wall_limit_ms' => 45_000,
            'source_limit' => 256 * 1024,
            'result_limit' => 1024 * 1024,
            'output_limit' => 64 * 1024,
            'callback_limit' => 50,
            'callback_result_limit' => 2 * 1024 * 1024,
            'callback_wall_limit' => 15.0,
            'callback_total_wall_limit' => 35.0,
        ],
        'automation' => [
            'memory_limit' => 32 * 1024 * 1024,
            'instruction_limit' => 20_000_000,
            'wall_limit_ms' => 45_000,
            'source_limit' => 512 * 1024,
            'result_limit' => 2 * 1024 * 1024,
            'output_limit' => 128 * 1024,
            'callback_limit' => 100,
            'callback_result_limit' => 4 * 1024 * 1024,
            'callback_wall_limit' => 15.0,
            'callback_total_wall_limit' => 35.0,
        ],
        'console' => [
            'memory_limit' => 32 * 1024 * 1024,
            'instruction_limit' => 10_000_000,
            'wall_limit_ms' => 5_000,
            'source_limit' => 512 * 1024,
            'result_limit' => 2 * 1024 * 1024,
            'output_limit' => 128 * 1024,
            'callback_limit' => 0,
            'callback_result_limit' => 0,
            'callback_wall_limit' => 0.0,
            'callback_total_wall_limit' => 0.0,
        ],
    ],
];
