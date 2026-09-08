<?php

return [
    'runtime' => 'opencompany-code-v1',
    'engine_binary' => env('RUBY_ENGINE_BINARY', base_path('bin/ruby-engine')),
    // Container builds write the artifact checksum here after copying the
    // executable. An explicit environment value still takes precedence for
    // immutable release deployments and tests; missing metadata remains
    // backward compatible for local development but never weakens a supplied
    // expected digest.
    'engine_sha256' => env('RUBY_ENGINE_SHA256') ?: (
        is_readable('/usr/local/share/ruby-engine.sha256')
            ? strtok((string) file_get_contents('/usr/local/share/ruby-engine.sha256'), " \t\r\n")
            : null
    ),

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
            'require_task_receipt_for_writes' => true,
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
            'require_task_receipt_for_writes' => true,
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
            'require_task_receipt_for_writes' => false,
        ],
    ],
];
