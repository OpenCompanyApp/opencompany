<?php

/*
|--------------------------------------------------------------------------
| Governance
|--------------------------------------------------------------------------
|
| Product Direction pillar 5 and docs/architecture/governance-model.md.
| The hard-deny floor is defined in code (ActionClassifier); this list can
| only ADD tool slugs to it for an install. Nothing here can remove a floor
| entry or relax an invariant.
|
*/

return [

    'floor_tools' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GOVERNANCE_FLOOR_TOOLS', '')),
    ))),

];
