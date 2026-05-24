import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AgentPermissionController::index
* @see app/Http/Controllers/Api/AgentPermissionController.php:21
* @route '/api/agents/{id}/permissions'
*/
export const index = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/agents/{id}/permissions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::index
* @see app/Http/Controllers/Api/AgentPermissionController.php:21
* @route '/api/agents/{id}/permissions'
*/
index.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::index
* @see app/Http/Controllers/Api/AgentPermissionController.php:21
* @route '/api/agents/{id}/permissions'
*/
index.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::index
* @see app/Http/Controllers/Api/AgentPermissionController.php:21
* @route '/api/agents/{id}/permissions'
*/
index.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateTools
* @see app/Http/Controllers/Api/AgentPermissionController.php:36
* @route '/api/agents/{id}/permissions/tools'
*/
export const updateTools = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateTools.url(args, options),
    method: 'put',
})

updateTools.definition = {
    methods: ["put"],
    url: '/api/agents/{id}/permissions/tools',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateTools
* @see app/Http/Controllers/Api/AgentPermissionController.php:36
* @route '/api/agents/{id}/permissions/tools'
*/
updateTools.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateTools.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateTools
* @see app/Http/Controllers/Api/AgentPermissionController.php:36
* @route '/api/agents/{id}/permissions/tools'
*/
updateTools.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateTools.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateChannels
* @see app/Http/Controllers/Api/AgentPermissionController.php:71
* @route '/api/agents/{id}/permissions/channels'
*/
export const updateChannels = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateChannels.url(args, options),
    method: 'put',
})

updateChannels.definition = {
    methods: ["put"],
    url: '/api/agents/{id}/permissions/channels',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateChannels
* @see app/Http/Controllers/Api/AgentPermissionController.php:71
* @route '/api/agents/{id}/permissions/channels'
*/
updateChannels.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateChannels.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateChannels
* @see app/Http/Controllers/Api/AgentPermissionController.php:71
* @route '/api/agents/{id}/permissions/channels'
*/
updateChannels.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateChannels.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateFolders
* @see app/Http/Controllers/Api/AgentPermissionController.php:174
* @route '/api/agents/{id}/permissions/folders'
*/
export const updateFolders = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateFolders.url(args, options),
    method: 'put',
})

updateFolders.definition = {
    methods: ["put"],
    url: '/api/agents/{id}/permissions/folders',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateFolders
* @see app/Http/Controllers/Api/AgentPermissionController.php:174
* @route '/api/agents/{id}/permissions/folders'
*/
updateFolders.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateFolders.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateFolders
* @see app/Http/Controllers/Api/AgentPermissionController.php:174
* @route '/api/agents/{id}/permissions/folders'
*/
updateFolders.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateFolders.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateIntegrations
* @see app/Http/Controllers/Api/AgentPermissionController.php:104
* @route '/api/agents/{id}/permissions/integrations'
*/
export const updateIntegrations = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateIntegrations.url(args, options),
    method: 'put',
})

updateIntegrations.definition = {
    methods: ["put"],
    url: '/api/agents/{id}/permissions/integrations',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateIntegrations
* @see app/Http/Controllers/Api/AgentPermissionController.php:104
* @route '/api/agents/{id}/permissions/integrations'
*/
updateIntegrations.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateIntegrations.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateIntegrations
* @see app/Http/Controllers/Api/AgentPermissionController.php:104
* @route '/api/agents/{id}/permissions/integrations'
*/
updateIntegrations.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateIntegrations.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateFileFolders
* @see app/Http/Controllers/Api/AgentPermissionController.php:139
* @route '/api/agents/{id}/permissions/file-folders'
*/
export const updateFileFolders = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateFileFolders.url(args, options),
    method: 'put',
})

updateFileFolders.definition = {
    methods: ["put"],
    url: '/api/agents/{id}/permissions/file-folders',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateFileFolders
* @see app/Http/Controllers/Api/AgentPermissionController.php:139
* @route '/api/agents/{id}/permissions/file-folders'
*/
updateFileFolders.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateFileFolders.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentPermissionController::updateFileFolders
* @see app/Http/Controllers/Api/AgentPermissionController.php:139
* @route '/api/agents/{id}/permissions/file-folders'
*/
updateFileFolders.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateFileFolders.url(args, options),
    method: 'put',
})

const AgentPermissionController = { index, updateTools, updateChannels, updateFolders, updateIntegrations, updateFileFolders }

export default AgentPermissionController