import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::authorize
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:32
* @route '/api/integrations/google/oauth/authorize'
*/
export const authorize = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authorize.url(options),
    method: 'get',
})

authorize.definition = {
    methods: ["get","head"],
    url: '/api/integrations/google/oauth/authorize',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::authorize
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:32
* @route '/api/integrations/google/oauth/authorize'
*/
authorize.url = (options?: RouteQueryOptions) => {
    return authorize.definition.url + queryParams(options)
}

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::authorize
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:32
* @route '/api/integrations/google/oauth/authorize'
*/
authorize.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authorize.url(options),
    method: 'get',
})

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::authorize
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:32
* @route '/api/integrations/google/oauth/authorize'
*/
authorize.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: authorize.url(options),
    method: 'head',
})

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::callback
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:83
* @route '/api/integrations/google/oauth/callback'
*/
export const callback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/api/integrations/google/oauth/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::callback
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:83
* @route '/api/integrations/google/oauth/callback'
*/
callback.url = (options?: RouteQueryOptions) => {
    return callback.definition.url + queryParams(options)
}

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::callback
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:83
* @route '/api/integrations/google/oauth/callback'
*/
callback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

/**
* @see \OpenCompany\Integrations\Google\GoogleOAuthController::callback
* @see vendor/opencompanyapp/integration-google/src/GoogleOAuthController.php:83
* @route '/api/integrations/google/oauth/callback'
*/
callback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(options),
    method: 'head',
})

const GoogleOAuthController = { authorize, callback }

export default GoogleOAuthController