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

    'delegation' => [
        // Maximum number of delegation hops (contact_agent ask/delegate/notify
        // and synchronous subagent calls) between the originating run and the
        // agent doing the work. Depth 1 is a direct delegation.
        'max_depth' => (int) env('AGENT_DELEGATION_MAX_DEPTH', 3),
    ],

];
