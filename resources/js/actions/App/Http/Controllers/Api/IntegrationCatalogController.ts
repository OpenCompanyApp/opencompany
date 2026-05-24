import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::index
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:16
* @route '/api/integrations/catalog'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/integrations/catalog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::index
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:16
* @route '/api/integrations/catalog'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::index
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:16
* @route '/api/integrations/catalog'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::index
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:16
* @route '/api/integrations/catalog'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::show
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:65
* @route '/api/integrations/catalog/{slug}'
*/
export const show = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/integrations/catalog/{slug}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::show
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:65
* @route '/api/integrations/catalog/{slug}'
*/
show.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { slug: args }
    }

    if (Array.isArray(args)) {
        args = {
            slug: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        slug: args.slug,
    }

    return show.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::show
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:65
* @route '/api/integrations/catalog/{slug}'
*/
show.get = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationCatalogController::show
* @see app/Http/Controllers/Api/IntegrationCatalogController.php:65
* @route '/api/integrations/catalog/{slug}'
*/
show.head = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const IntegrationCatalogController = { index, show }

export default IntegrationCatalogController