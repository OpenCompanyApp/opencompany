import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import link from './link'
/**
* @see routes/web.php:39
* @route '/telegram/mini-app'
*/
export const miniApp = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: miniApp.url(options),
    method: 'get',
})

miniApp.definition = {
    methods: ["get","head"],
    url: '/telegram/mini-app',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:39
* @route '/telegram/mini-app'
*/
miniApp.url = (options?: RouteQueryOptions) => {
    return miniApp.definition.url + queryParams(options)
}

/**
* @see routes/web.php:39
* @route '/telegram/mini-app'
*/
miniApp.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: miniApp.url(options),
    method: 'get',
})

/**
* @see routes/web.php:39
* @route '/telegram/mini-app'
*/
miniApp.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: miniApp.url(options),
    method: 'head',
})

const telegram = {
    link: Object.assign(link, link),
    miniApp: Object.assign(miniApp, miniApp),
}

export default telegram