<?php

/*
|--------------------------------------------------------------------------
| Agent collaboration limits
|--------------------------------------------------------------------------
|
| Server-owned bounds for agent-to-agent work. These are safety limits, not
| model hints: they are enforced by the runtime regardless of what a prompt
| asks for.
|
*/

return [

    'subagents' => [
        // Peer agents exposed as synchronous `subagent_*` tools to one agent.
        'max_tools' => (int) env('AGENT_SUBAGENT_MAX_TOOLS', 8),
    ],

    // Delegation depth is the workspace causality chain limit:
    // config/events.php `max_depth` (EVENTS_MAX_DEPTH).

];
