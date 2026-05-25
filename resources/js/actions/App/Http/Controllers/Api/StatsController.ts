import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\StatsController::index
* @see app/Http/Controllers/Api/StatsController.php:11
* @route '/api/stats'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\StatsController::index
* @see app/Http/Controllers/Api/StatsController.php:11
* @route '/api/stats'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\StatsController::index
* @see app/Http/Controllers/Api/StatsController.php:11
* @route '/api/stats'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\StatsController::index
* @see app/Http/Controllers/Api/StatsController.php:11
* @route '/api/stats'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:29
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
* @see app/Http/Controllers/Api/StatsController.php:29
* @route '/api/stats/status'
*/
status.url = (options?: RouteQueryOptions) => {
    return status.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:29
* @route '/api/stats/status'
*/
status.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\StatsController::status
* @see app/Http/Controllers/Api/StatsController.php:29
* @route '/api/stats/status'
*/
status.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\StatsController::update
* @see app/Http/Controllers/Api/StatsController.php:0
* @route '/api/stats'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/stats',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\StatsController::update
* @see app/Http/Controllers/Api/StatsController.php:0
* @route '/api/stats'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\StatsController::update
* @see app/Http/Controllers/Api/StatsController.php:0
* @route '/api/stats'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const StatsController = { index, status, update }

export default StatsController