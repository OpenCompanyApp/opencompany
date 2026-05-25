import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::index
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:21
* @route '/api/disks'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/disks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::index
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:21
* @route '/api/disks'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::index
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:21
* @route '/api/disks'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::index
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:21
* @route '/api/disks'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::store
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:33
* @route '/api/disks'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/disks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::store
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:33
* @route '/api/disks'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::store
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:33
* @route '/api/disks'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::show
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:65
* @route '/api/disks/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/disks/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::show
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:65
* @route '/api/disks/{id}'
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
* @see \App\Http\Controllers\Api\WorkspaceDiskController::show
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:65
* @route '/api/disks/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::show
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:65
* @route '/api/disks/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::update
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:72
* @route '/api/disks/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/disks/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::update
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:72
* @route '/api/disks/{id}'
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
* @see \App\Http\Controllers\Api\WorkspaceDiskController::update
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:72
* @route '/api/disks/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::destroy
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:118
* @route '/api/disks/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/disks/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::destroy
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:118
* @route '/api/disks/{id}'
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
* @see \App\Http\Controllers\Api\WorkspaceDiskController::destroy
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:118
* @route '/api/disks/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::test
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:140
* @route '/api/disks/{id}/test'
*/
export const test = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: test.url(args, options),
    method: 'post',
})

test.definition = {
    methods: ["post"],
    url: '/api/disks/{id}/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::test
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:140
* @route '/api/disks/{id}/test'
*/
test.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return test.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::test
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:140
* @route '/api/disks/{id}/test'
*/
test.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: test.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::setDefault
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:160
* @route '/api/disks/{id}/default'
*/
export const setDefault = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setDefault.url(args, options),
    method: 'post',
})

setDefault.definition = {
    methods: ["post"],
    url: '/api/disks/{id}/default',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::setDefault
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:160
* @route '/api/disks/{id}/default'
*/
setDefault.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return setDefault.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceDiskController::setDefault
* @see app/Http/Controllers/Api/WorkspaceDiskController.php:160
* @route '/api/disks/{id}/default'
*/
setDefault.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setDefault.url(args, options),
    method: 'post',
})

const WorkspaceDiskController = { index, store, show, update, destroy, test, setDefault }

export default WorkspaceDiskController