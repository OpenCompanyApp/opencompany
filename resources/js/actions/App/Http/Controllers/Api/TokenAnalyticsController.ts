import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\TokenAnalyticsController::index
* @see app/Http/Controllers/Api/TokenAnalyticsController.php:13
* @route '/api/tasks/analytics/tokens'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/tasks/analytics/tokens',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\TokenAnalyticsController::index
* @see app/Http/Controllers/Api/TokenAnalyticsController.php:13
* @route '/api/tasks/analytics/tokens'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TokenAnalyticsController::index
* @see app/Http/Controllers/Api/TokenAnalyticsController.php:13
* @route '/api/tasks/analytics/tokens'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\TokenAnalyticsController::index
* @see app/Http/Controllers/Api/TokenAnalyticsController.php:13
* @route '/api/tasks/analytics/tokens'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const TokenAnalyticsController = { index }

export default TokenAnalyticsController