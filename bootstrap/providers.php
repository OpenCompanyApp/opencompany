<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    // AppServiceProvider wires first-party runtime services; Telescope stays
    // explicit so local observability can be toggled without provider discovery.
    AppServiceProvider::class,
    TelescopeServiceProvider::class,
];
