import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::authorize
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:18
* @route '/api/integrations/ticktick/oauth/authorize'
*/
export const authorize = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authorize.url(options),
    method: 'get',
})

authorize.definition = {
    methods: ["get","head"],
    url: '/api/integrations/ticktick/oauth/authorize',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::authorize
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:18
* @route '/api/integrations/ticktick/oauth/authorize'
*/
authorize.url = (options?: RouteQueryOptions) => {
    return authorize.definition.url + queryParams(options)
}

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::authorize
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:18
* @route '/api/integrations/ticktick/oauth/authorize'
*/
authorize.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authorize.url(options),
    method: 'get',
})

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::authorize
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:18
* @route '/api/integrations/ticktick/oauth/authorize'
*/
authorize.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: authorize.url(options),
    method: 'head',
})

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::callback
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:63
* @route '/api/integrations/ticktick/oauth/callback'
*/
export const callback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/api/integrations/ticktick/oauth/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::callback
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:63
* @route '/api/integrations/ticktick/oauth/callback'
*/
callback.url = (options?: RouteQueryOptions) => {
    return callback.definition.url + queryParams(options)
}

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::callback
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:63
* @route '/api/integrations/ticktick/oauth/callback'
*/
callback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

/**
* @see \OpenCompany\Integrations\TickTick\TickTickOAuthController::callback
* @see Users/rutger/Sites/integrations/packages/ticktick/src/TickTickOAuthController.php:63
* @route '/api/integrations/ticktick/oauth/callback'
*/
callback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(options),
    method: 'head',
})

const TickTickOAuthController = { authorize, callback }

export default TickTickOAuthController