<?php

/*
|--------------------------------------------------------------------------
| Workspace event log and loop protection
|--------------------------------------------------------------------------
|
| docs/planning/connections-events-routines.md. Every event and run carries
| a causality chain; work whose next hop would exceed `max_depth` is refused
| (agent delegation, routines triggered by events, event-to-event chains).
|
*/

return [
    'max_depth' => (int) env('EVENTS_MAX_DEPTH', 3),
];
