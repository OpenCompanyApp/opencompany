import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WorkspaceController::store
* @see app/Http/Controllers/Api/WorkspaceController.php:27
* @route '/api/workspaces'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/workspaces',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceController::store
* @see app/Http/Controllers/Api/WorkspaceController.php:27
* @route '/api/workspaces'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceController::store
* @see app/Http/Controllers/Api/WorkspaceController.php:27
* @route '/api/workspaces'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceController::show
* @see app/Http/Controllers/Api/WorkspaceController.php:95
* @route '/api/workspace'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/workspace',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceController::show
* @see app/Http/Controllers/Api/WorkspaceController.php:95
* @route '/api/workspace'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceController::show
* @see app/Http/Controllers/Api/WorkspaceController.php:95
* @route '/api/workspace'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceController::show
* @see app/Http/Controllers/Api/WorkspaceController.php:95
* @route '/api/workspace'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceController::update
* @see app/Http/Controllers/Api/WorkspaceController.php:104
* @route '/api/workspace'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/workspace',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceController::update
* @see app/Http/Controllers/Api/WorkspaceController.php:104
* @route '/api/workspace'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceController::update
* @see app/Http/Controllers/Api/WorkspaceController.php:104
* @route '/api/workspace'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const WorkspaceController = { store, show, update }

export default WorkspaceController