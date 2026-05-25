import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AiGatewayController::config
* @see app/Http/Controllers/Api/AiGatewayController.php:21
* @route '/api/ai-gateway/config'
*/
export const config = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: config.url(options),
    method: 'get',
})

config.definition = {
    methods: ["get","head"],
    url: '/api/ai-gateway/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AiGatewayController::config
* @see app/Http/Controllers/Api/AiGatewayController.php:21
* @route '/api/ai-gateway/config'
*/
config.url = (options?: RouteQueryOptions) => {
    return config.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGatewayController::config
* @see app/Http/Controllers/Api/AiGatewayController.php:21
* @route '/api/ai-gateway/config'
*/
config.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: config.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AiGatewayController::config
* @see app/Http/Controllers/Api/AiGatewayController.php:21
* @route '/api/ai-gateway/config'
*/
config.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: config.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AiGatewayController::updateConfig
* @see app/Http/Controllers/Api/AiGatewayController.php:31
* @route '/api/ai-gateway/config'
*/
export const updateConfig = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateConfig.url(options),
    method: 'put',
})

updateConfig.definition = {
    methods: ["put"],
    url: '/api/ai-gateway/config',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AiGatewayController::updateConfig
* @see app/Http/Controllers/Api/AiGatewayController.php:31
* @route '/api/ai-gateway/config'
*/
updateConfig.url = (options?: RouteQueryOptions) => {
    return updateConfig.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGatewayController::updateConfig
* @see app/Http/Controllers/Api/AiGatewayController.php:31
* @route '/api/ai-gateway/config'
*/
updateConfig.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateConfig.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\AiGatewayController::apiKeys
* @see app/Http/Controllers/Api/AiGatewayController.php:64
* @route '/api/ai-gateway/api-keys'
*/
export const apiKeys = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: apiKeys.url(options),
    method: 'get',
})

apiKeys.definition = {
    methods: ["get","head"],
    url: '/api/ai-gateway/api-keys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AiGatewayController::apiKeys
* @see app/Http/Controllers/Api/AiGatewayController.php:64
* @route '/api/ai-gateway/api-keys'
*/
apiKeys.url = (options?: RouteQueryOptions) => {
    return apiKeys.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGatewayController::apiKeys
* @see app/Http/Controllers/Api/AiGatewayController.php:64
* @route '/api/ai-gateway/api-keys'
*/
apiKeys.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: apiKeys.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AiGatewayController::apiKeys
* @see app/Http/Controllers/Api/AiGatewayController.php:64
* @route '/api/ai-gateway/api-keys'
*/
apiKeys.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: apiKeys.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AiGatewayController::createApiKey
* @see app/Http/Controllers/Api/AiGatewayController.php:78
* @route '/api/ai-gateway/api-keys'
*/
export const createApiKey = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createApiKey.url(options),
    method: 'post',
})

createApiKey.definition = {
    methods: ["post"],
    url: '/api/ai-gateway/api-keys',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AiGatewayController::createApiKey
* @see app/Http/Controllers/Api/AiGatewayController.php:78
* @route '/api/ai-gateway/api-keys'
*/
createApiKey.url = (options?: RouteQueryOptions) => {
    return createApiKey.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGatewayController::createApiKey
* @see app/Http/Controllers/Api/AiGatewayController.php:78
* @route '/api/ai-gateway/api-keys'
*/
createApiKey.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createApiKey.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AiGatewayController::deleteApiKey
* @see app/Http/Controllers/Api/AiGatewayController.php:95
* @route '/api/ai-gateway/api-keys/{id}'
*/
export const deleteApiKey = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteApiKey.url(args, options),
    method: 'delete',
})

deleteApiKey.definition = {
    methods: ["delete"],
    url: '/api/ai-gateway/api-keys/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\AiGatewayController::deleteApiKey
* @see app/Http/Controllers/Api/AiGatewayController.php:95
* @route '/api/ai-gateway/api-keys/{id}'
*/
deleteApiKey.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return deleteApiKey.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGatewayController::deleteApiKey
* @see app/Http/Controllers/Api/AiGatewayController.php:95
* @route '/api/ai-gateway/api-keys/{id}'
*/
deleteApiKey.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteApiKey.url(args, options),
    method: 'delete',
})

const AiGatewayController = { config, updateConfig, apiKeys, createApiKey, deleteApiKey }

export default AiGatewayController