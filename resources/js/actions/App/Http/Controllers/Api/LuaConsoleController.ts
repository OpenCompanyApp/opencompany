import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\LuaConsoleController::execute
* @see app/Http/Controllers/Api/LuaConsoleController.php:22
* @route '/api/lua/execute'
*/
export const execute = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: execute.url(options),
    method: 'post',
})

execute.definition = {
    methods: ["post"],
    url: '/api/lua/execute',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\LuaConsoleController::execute
* @see app/Http/Controllers/Api/LuaConsoleController.php:22
* @route '/api/lua/execute'
*/
execute.url = (options?: RouteQueryOptions) => {
    return execute.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\LuaConsoleController::execute
* @see app/Http/Controllers/Api/LuaConsoleController.php:22
* @route '/api/lua/execute'
*/
execute.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: execute.url(options),
    method: 'post',
})

const LuaConsoleController = { execute }

export default LuaConsoleController