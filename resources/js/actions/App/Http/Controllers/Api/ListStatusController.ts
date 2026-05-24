import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ListStatusController::index
* @see app/Http/Controllers/Api/ListStatusController.php:16
* @route '/api/list-statuses'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/list-statuses',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ListStatusController::index
* @see app/Http/Controllers/Api/ListStatusController.php:16
* @route '/api/list-statuses'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListStatusController::index
* @see app/Http/Controllers/Api/ListStatusController.php:16
* @route '/api/list-statuses'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ListStatusController::index
* @see app/Http/Controllers/Api/ListStatusController.php:16
* @route '/api/list-statuses'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\ListStatusController::store
* @see app/Http/Controllers/Api/ListStatusController.php:21
* @route '/api/list-statuses'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/list-statuses',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ListStatusController::store
* @see app/Http/Controllers/Api/ListStatusController.php:21
* @route '/api/list-statuses'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListStatusController::store
* @see app/Http/Controllers/Api/ListStatusController.php:21
* @route '/api/list-statuses'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ListStatusController::reorder
* @see app/Http/Controllers/Api/ListStatusController.php:88
* @route '/api/list-statuses/reorder'
*/
export const reorder = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(options),
    method: 'post',
})

reorder.definition = {
    methods: ["post"],
    url: '/api/list-statuses/reorder',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ListStatusController::reorder
* @see app/Http/Controllers/Api/ListStatusController.php:88
* @route '/api/list-statuses/reorder'
*/
reorder.url = (options?: RouteQueryOptions) => {
    return reorder.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListStatusController::reorder
* @see app/Http/Controllers/Api/ListStatusController.php:88
* @route '/api/list-statuses/reorder'
*/
reorder.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ListStatusController::update
* @see app/Http/Controllers/Api/ListStatusController.php:57
* @route '/api/list-statuses/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/list-statuses/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\ListStatusController::update
* @see app/Http/Controllers/Api/ListStatusController.php:57
* @route '/api/list-statuses/{id}'
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
* @see \App\Http\Controllers\Api\ListStatusController::update
* @see app/Http/Controllers/Api/ListStatusController.php:57
* @route '/api/list-statuses/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\ListStatusController::destroy
* @see app/Http/Controllers/Api/ListStatusController.php:99
* @route '/api/list-statuses/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/list-statuses/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\ListStatusController::destroy
* @see app/Http/Controllers/Api/ListStatusController.php:99
* @route '/api/list-statuses/{id}'
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
* @see \App\Http\Controllers\Api\ListStatusController::destroy
* @see app/Http/Controllers/Api/ListStatusController.php:99
* @route '/api/list-statuses/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const ListStatusController = { index, store, reorder, update, destroy }

export default ListStatusController