import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:22
* @route '/api/stats/status'
*/
export const status = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

status.definition = {
    methods: ["get","head"],
    url: '/api/stats/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:22
* @route '/api/stats/status'
*/
status.url = (options?: RouteQueryOptions) => {
    return status.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:22
* @route '/api/stats/status'
*/
status.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:22
* @route '/api/stats/status'
*/
status.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(options),
    method: 'head',
})

const StatsController = { status }

export default StatsController