import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\McpServerController::index
* @see app/Http/Controllers/Api/McpServerController.php:24
* @route '/api/mcp-servers'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/mcp-servers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::index
* @see app/Http/Controllers/Api/McpServerController.php:24
* @route '/api/mcp-servers'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\McpServerController::index
* @see app/Http/Controllers/Api/McpServerController.php:24
* @route '/api/mcp-servers'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::index
* @see app/Http/Controllers/Api/McpServerController.php:24
* @route '/api/mcp-servers'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::store
* @see app/Http/Controllers/Api/McpServerController.php:32
* @route '/api/mcp-servers'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/mcp-servers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::store
* @see app/Http/Controllers/Api/McpServerController.php:32
* @route '/api/mcp-servers'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\McpServerController::store
* @see app/Http/Controllers/Api/McpServerController.php:32
* @route '/api/mcp-servers'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::testNewConnection
* @see app/Http/Controllers/Api/McpServerController.php:105
* @route '/api/mcp-servers/test-new'
*/
export const testNewConnection = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testNewConnection.url(options),
    method: 'post',
})

testNewConnection.definition = {
    methods: ["post"],
    url: '/api/mcp-servers/test-new',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::testNewConnection
* @see app/Http/Controllers/Api/McpServerController.php:105
* @route '/api/mcp-servers/test-new'
*/
testNewConnection.url = (options?: RouteQueryOptions) => {
    return testNewConnection.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\McpServerController::testNewConnection
* @see app/Http/Controllers/Api/McpServerController.php:105
* @route '/api/mcp-servers/test-new'
*/
testNewConnection.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testNewConnection.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::show
* @see app/Http/Controllers/Api/McpServerController.php:52
* @route '/api/mcp-servers/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/mcp-servers/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::show
* @see app/Http/Controllers/Api/McpServerController.php:52
* @route '/api/mcp-servers/{id}'
*/
show.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\McpServerController::show
* @see app/Http/Controllers/Api/McpServerController.php:52
* @route '/api/mcp-servers/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::show
* @see app/Http/Controllers/Api/McpServerController.php:52
* @route '/api/mcp-servers/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::update
* @see app/Http/Controllers/Api/McpServerController.php:60
* @route '/api/mcp-servers/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/mcp-servers/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::update
* @see app/Http/Controllers/Api/McpServerController.php:60
* @route '/api/mcp-servers/{id}'
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
* @see \App\Http\Controllers\Api\McpServerController::update
* @see app/Http/Controllers/Api/McpServerController.php:60
* @route '/api/mcp-servers/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::destroy
* @see app/Http/Controllers/Api/McpServerController.php:83
* @route '/api/mcp-servers/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/mcp-servers/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::destroy
* @see app/Http/Controllers/Api/McpServerController.php:83
* @route '/api/mcp-servers/{id}'
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
* @see \App\Http\Controllers\Api\McpServerController::destroy
* @see app/Http/Controllers/Api/McpServerController.php:83
* @route '/api/mcp-servers/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::testConnection
* @see app/Http/Controllers/Api/McpServerController.php:93
* @route '/api/mcp-servers/{id}/test'
*/
export const testConnection = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testConnection.url(args, options),
    method: 'post',
})

testConnection.definition = {
    methods: ["post"],
    url: '/api/mcp-servers/{id}/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::testConnection
* @see app/Http/Controllers/Api/McpServerController.php:93
* @route '/api/mcp-servers/{id}/test'
*/
testConnection.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return testConnection.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\McpServerController::testConnection
* @see app/Http/Controllers/Api/McpServerController.php:93
* @route '/api/mcp-servers/{id}/test'
*/
testConnection.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testConnection.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\McpServerController::discoverTools
* @see app/Http/Controllers/Api/McpServerController.php:122
* @route '/api/mcp-servers/{id}/discover'
*/
export const discoverTools = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: discoverTools.url(args, options),
    method: 'post',
})

discoverTools.definition = {
    methods: ["post"],
    url: '/api/mcp-servers/{id}/discover',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\McpServerController::discoverTools
* @see app/Http/Controllers/Api/McpServerController.php:122
* @route '/api/mcp-servers/{id}/discover'
*/
discoverTools.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return discoverTools.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\McpServerController::discoverTools
* @see app/Http/Controllers/Api/McpServerController.php:122
* @route '/api/mcp-servers/{id}/discover'
*/
discoverTools.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: discoverTools.url(args, options),
    method: 'post',
})

const McpServerController = { index, store, testNewConnection, show, update, destroy, testConnection, discoverTools }

export default McpServerController