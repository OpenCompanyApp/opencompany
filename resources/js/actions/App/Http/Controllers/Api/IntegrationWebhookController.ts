import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::index
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:20
* @route '/api/integration-webhooks'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/integration-webhooks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::index
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:20
* @route '/api/integration-webhooks'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::index
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:20
* @route '/api/integration-webhooks'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::index
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:20
* @route '/api/integration-webhooks'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::store
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:30
* @route '/api/integration-webhooks'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/integration-webhooks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::store
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:30
* @route '/api/integration-webhooks'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::store
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:30
* @route '/api/integration-webhooks'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::update
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:55
* @route '/api/integration-webhooks/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/integration-webhooks/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::update
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:55
* @route '/api/integration-webhooks/{id}'
*/
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::update
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:55
* @route '/api/integration-webhooks/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::destroy
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:83
* @route '/api/integration-webhooks/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/integration-webhooks/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::destroy
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:83
* @route '/api/integration-webhooks/{id}'
*/
destroy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return destroy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationWebhookController::destroy
* @see app/Http/Controllers/Api/IntegrationWebhookController.php:83
* @route '/api/integration-webhooks/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const IntegrationWebhookController = { index, store, update, destroy }

export default IntegrationWebhookController