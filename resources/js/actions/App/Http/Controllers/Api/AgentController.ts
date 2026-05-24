import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AgentController::index
* @see app/Http/Controllers/Api/AgentController.php:36
* @route '/api/agents'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AgentController::index
* @see app/Http/Controllers/Api/AgentController.php:36
* @route '/api/agents'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentController::index
* @see app/Http/Controllers/Api/AgentController.php:36
* @route '/api/agents'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AgentController::index
* @see app/Http/Controllers/Api/AgentController.php:36
* @route '/api/agents'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AgentController::store
* @see app/Http/Controllers/Api/AgentController.php:47
* @route '/api/agents'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/agents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AgentController::store
* @see app/Http/Controllers/Api/AgentController.php:47
* @route '/api/agents'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentController::store
* @see app/Http/Controllers/Api/AgentController.php:47
* @route '/api/agents'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AgentController::show
* @see app/Http/Controllers/Api/AgentController.php:82
* @route '/api/agents/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/agents/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AgentController::show
* @see app/Http/Controllers/Api/AgentController.php:82
* @route '/api/agents/{id}'
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
* @see \App\Http\Controllers\Api\AgentController::show
* @see app/Http/Controllers/Api/AgentController.php:82
* @route '/api/agents/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AgentController::show
* @see app/Http/Controllers/Api/AgentController.php:82
* @route '/api/agents/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AgentController::update
* @see app/Http/Controllers/Api/AgentController.php:90
* @route '/api/agents/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/agents/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\AgentController::update
* @see app/Http/Controllers/Api/AgentController.php:90
* @route '/api/agents/{id}'
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
* @see \App\Http\Controllers\Api\AgentController::update
* @see app/Http/Controllers/Api/AgentController.php:90
* @route '/api/agents/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\AgentController::destroy
* @see app/Http/Controllers/Api/AgentController.php:121
* @route '/api/agents/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/agents/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\AgentController::destroy
* @see app/Http/Controllers/Api/AgentController.php:121
* @route '/api/agents/{id}'
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
* @see \App\Http\Controllers\Api\AgentController::destroy
* @see app/Http/Controllers/Api/AgentController.php:121
* @route '/api/agents/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\AgentController::identityFiles
* @see app/Http/Controllers/Api/AgentController.php:132
* @route '/api/agents/{id}/identity'
*/
export const identityFiles = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: identityFiles.url(args, options),
    method: 'get',
})

identityFiles.definition = {
    methods: ["get","head"],
    url: '/api/agents/{id}/identity',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AgentController::identityFiles
* @see app/Http/Controllers/Api/AgentController.php:132
* @route '/api/agents/{id}/identity'
*/
identityFiles.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return identityFiles.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentController::identityFiles
* @see app/Http/Controllers/Api/AgentController.php:132
* @route '/api/agents/{id}/identity'
*/
identityFiles.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: identityFiles.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AgentController::identityFiles
* @see app/Http/Controllers/Api/AgentController.php:132
* @route '/api/agents/{id}/identity'
*/
identityFiles.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: identityFiles.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AgentController::updateIdentityFile
* @see app/Http/Controllers/Api/AgentController.php:151
* @route '/api/agents/{id}/identity/{fileType}'
*/
export const updateIdentityFile = (args: { id: string | number, fileType: string | number } | [id: string | number, fileType: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateIdentityFile.url(args, options),
    method: 'put',
})

updateIdentityFile.definition = {
    methods: ["put"],
    url: '/api/agents/{id}/identity/{fileType}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\AgentController::updateIdentityFile
* @see app/Http/Controllers/Api/AgentController.php:151
* @route '/api/agents/{id}/identity/{fileType}'
*/
updateIdentityFile.url = (args: { id: string | number, fileType: string | number } | [id: string | number, fileType: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            id: args[0],
            fileType: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
        fileType: args.fileType,
    }

    return updateIdentityFile.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{fileType}', parsedArgs.fileType.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentController::updateIdentityFile
* @see app/Http/Controllers/Api/AgentController.php:151
* @route '/api/agents/{id}/identity/{fileType}'
*/
updateIdentityFile.put = (args: { id: string | number, fileType: string | number } | [id: string | number, fileType: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateIdentityFile.url(args, options),
    method: 'put',
})

const AgentController = { index, store, show, update, destroy, identityFiles, updateIdentityFile }

export default AgentController