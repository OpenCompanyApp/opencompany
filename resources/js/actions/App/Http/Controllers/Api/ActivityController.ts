import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ActivityController::index
* @see app/Http/Controllers/Api/ActivityController.php:16
* @route '/api/activities'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/activities',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ActivityController::index
* @see app/Http/Controllers/Api/ActivityController.php:16
* @route '/api/activities'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ActivityController::index
* @see app/Http/Controllers/Api/ActivityController.php:16
* @route '/api/activities'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ActivityController::index
* @see app/Http/Controllers/Api/ActivityController.php:16
* @route '/api/activities'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const ActivityController = { index }

export default ActivityController