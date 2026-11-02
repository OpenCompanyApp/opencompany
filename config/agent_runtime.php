<?php

return [
    // Physical overlap releases may accumulate while one long agent run owns
    // the shared lock. This is a wall-clock retry deadline, not an allowance
    // for additional provider/model attempts.
    'physical_retry_window_seconds' => (int) env('AGENT_RUN_PHYSICAL_RETRY_WINDOW', 86400),

    // Must exceed the 1,800-second model job timeout and align with the queue
    // retry_after lease. A hard-killed worker is reconciled only after Laravel
    // has had time to make its reservation eligible again.
    'stale_running_after_seconds' => (int) env('AGENT_RUN_STALE_AFTER', 2100),

    // Queued work may wait during maintenance, but it must not remain invisible
    // forever when dispatch or workers are unavailable.
    'stale_queued_after_seconds' => (int) env('AGENT_RUN_QUEUED_STALE_AFTER', 86400),

    'telegram_dispatch_claim_seconds' => (int) env('TELEGRAM_LANE_CLAIM_STALE_AFTER', 600),
];
