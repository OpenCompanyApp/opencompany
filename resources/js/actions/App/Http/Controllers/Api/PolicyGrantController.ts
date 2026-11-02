import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\PolicyGrantController::index
* @see app/Http/Controllers/Api/PolicyGrantController.php:25
* @route '/api/agents/{agentId}/policy-grants'
*/
export const index = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/agents/{agentId}/policy-grants',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::index
* @see app/Http/Controllers/Api/PolicyGrantController.php:25
* @route '/api/agents/{agentId}/policy-grants'
*/
index.url = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agentId: args }
    }

    if (Array.isArray(args)) {
        args = {
            agentId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agentId: args.agentId,
    }

    return index.definition.url
            .replace('{agentId}', parsedArgs.agentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::index
* @see app/Http/Controllers/Api/PolicyGrantController.php:25
* @route '/api/agents/{agentId}/policy-grants'
*/
index.get = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::index
* @see app/Http/Controllers/Api/PolicyGrantController.php:25
* @route '/api/agents/{agentId}/policy-grants'
*/
index.head = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::store
* @see app/Http/Controllers/Api/PolicyGrantController.php:35
* @route '/api/agents/{agentId}/policy-grants'
*/
export const store = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/agents/{agentId}/policy-grants',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::store
* @see app/Http/Controllers/Api/PolicyGrantController.php:35
* @route '/api/agents/{agentId}/policy-grants'
*/
store.url = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agentId: args }
    }

    if (Array.isArray(args)) {
        args = {
            agentId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agentId: args.agentId,
    }

    return store.definition.url
            .replace('{agentId}', parsedArgs.agentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::store
* @see app/Http/Controllers/Api/PolicyGrantController.php:35
* @route '/api/agents/{agentId}/policy-grants'
*/
store.post = (args: { agentId: string | number } | [agentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::destroy
* @see app/Http/Controllers/Api/PolicyGrantController.php:62
* @route '/api/agents/{agentId}/policy-grants/{grantId}'
*/
export const destroy = (args: { agentId: string | number, grantId: string | number } | [agentId: string | number, grantId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/agents/{agentId}/policy-grants/{grantId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::destroy
* @see app/Http/Controllers/Api/PolicyGrantController.php:62
* @route '/api/agents/{agentId}/policy-grants/{grantId}'
*/
destroy.url = (args: { agentId: string | number, grantId: string | number } | [agentId: string | number, grantId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agentId: args[0],
            grantId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agentId: args.agentId,
        grantId: args.grantId,
    }

    return destroy.definition.url
            .replace('{agentId}', parsedArgs.agentId.toString())
            .replace('{grantId}', parsedArgs.grantId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\PolicyGrantController::destroy
* @see app/Http/Controllers/Api/PolicyGrantController.php:62
* @route '/api/agents/{agentId}/policy-grants/{grantId}'
*/
destroy.delete = (args: { agentId: string | number, grantId: string | number } | [agentId: string | number, grantId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const PolicyGrantController = { index, store, destroy }

export default PolicyGrantController