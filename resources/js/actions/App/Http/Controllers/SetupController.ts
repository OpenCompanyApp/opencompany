import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SetupController::store
* @see app/Http/Controllers/SetupController.php:19
* @route '/setup'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/setup',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SetupController::store
* @see app/Http/Controllers/SetupController.php:19
* @route '/setup'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SetupController::store
* @see app/Http/Controllers/SetupController.php:19
* @route '/setup'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const SetupController = { store }

export default SetupController