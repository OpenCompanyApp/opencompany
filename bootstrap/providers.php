<?php

use App\Providers\AppServiceProvider;
use App\Providers\IntegrationCredentialIsolationServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    // AppServiceProvider wires first-party runtime services; Telescope stays
    // explicit so local observability can be toggled without provider discovery.
    AppServiceProvider::class,
    // Must boot after integration package providers have registered their
    // credential-bearing singletons (see the class docblock).
    IntegrationCredentialIsolationServiceProvider::class,
    TelescopeServiceProvider::class,
];
