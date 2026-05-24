import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WorkloadController::index
* @see app/Http/Controllers/Api/WorkloadController.php:13
* @route '/api/workload'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/workload',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\WorkloadController::index
* @see app/Http/Controllers/Api/WorkloadController.php:13
* @route '/api/workload'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkloadController::index
* @see app/Http/Controllers/Api/WorkloadController.php:13
* @route '/api/workload'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\WorkloadController::index
* @see app/Http/Controllers/Api/WorkloadController.php:13
* @route '/api/workload'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const WorkloadController = { index }

export default WorkloadController