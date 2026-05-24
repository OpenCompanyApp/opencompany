import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ToolCatalogController::index
* @see app/Http/Controllers/Api/ToolCatalogController.php:21
* @route '/api/tools/catalog'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/tools/catalog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ToolCatalogController::index
* @see app/Http/Controllers/Api/ToolCatalogController.php:21
* @route '/api/tools/catalog'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ToolCatalogController::index
* @see app/Http/Controllers/Api/ToolCatalogController.php:21
* @route '/api/tools/catalog'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ToolCatalogController::index
* @see app/Http/Controllers/Api/ToolCatalogController.php:21
* @route '/api/tools/catalog'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const ToolCatalogController = { index }

export default ToolCatalogController