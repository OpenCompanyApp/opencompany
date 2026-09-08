import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\CodeConsoleController::execute
* @see app/Http/Controllers/Api/CodeConsoleController.php:22
* @route '/api/code/execute'
*/
export const execute = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: execute.url(options),
    method: 'post',
})

execute.definition = {
    methods: ["post"],
    url: '/api/code/execute',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CodeConsoleController::execute
* @see app/Http/Controllers/Api/CodeConsoleController.php:22
* @route '/api/code/execute'
*/
execute.url = (options?: RouteQueryOptions) => {
    return execute.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CodeConsoleController::execute
* @see app/Http/Controllers/Api/CodeConsoleController.php:22
* @route '/api/code/execute'
*/
execute.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: execute.url(options),
    method: 'post',
})

const CodeConsoleController = { execute }

export default CodeConsoleController