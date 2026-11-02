<?php

return [
    // App-local overrides live here when a model release needs better metadata
    // before the generated catalog is refreshed. Runtime accepts unknown model
    // IDs, so this file is for labels, pricing, context windows, and defaults.

    'z' => [
        // Older agent brains used the synthetic `glm-coding` provider key with
        // GLM 4.x models that the coding plan no longer serves. Brains stored
        // under that alias are upgraded by model-ID prefix; brains that name
        // `z` directly are left untouched.
        'alias_model_replacements' => [
            'glm-coding' => ['glm-4.' => 'glm-5.1'],
        ],
    ],
];
