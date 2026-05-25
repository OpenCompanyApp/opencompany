import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see routes/web.php:221
* @route '/w/{workspace_slug}/messages'
*/
export const index = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:221
* @route '/w/{workspace_slug}/messages'
*/
index.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { workspace_slug: args }
    }

    if (Array.isArray(args)) {
        args = {
            workspace_slug: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        workspace_slug: args.workspace_slug,
    }

    return index.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:221
* @route '/w/{workspace_slug}/messages'
*/
index.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:221
* @route '/w/{workspace_slug}/messages'
*/
index.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:227
* @route '/w/{workspace_slug}/messages/{id}'
*/
export const show = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/messages/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:227
* @route '/w/{workspace_slug}/messages/{id}'
*/
show.url = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            workspace_slug: args[0],
            id: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        workspace_slug: args.workspace_slug,
        id: args.id,
    }

    return show.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:227
* @route '/w/{workspace_slug}/messages/{id}'
*/
show.get = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:227
* @route '/w/{workspace_slug}/messages/{id}'
*/
show.head = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const messages = {
    index: Object.assign(index, index),
    show: Object.assign(show, show),
}

export default messages