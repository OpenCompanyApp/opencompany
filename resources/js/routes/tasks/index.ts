import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see routes/web.php:112
* @route '/w/{workspace_slug}/tasks/analytics'
*/
export const analytics = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: analytics.url(args, options),
    method: 'get',
})

analytics.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/tasks/analytics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:112
* @route '/w/{workspace_slug}/tasks/analytics'
*/
analytics.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return analytics.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:112
* @route '/w/{workspace_slug}/tasks/analytics'
*/
analytics.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: analytics.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:112
* @route '/w/{workspace_slug}/tasks/analytics'
*/
analytics.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: analytics.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:116
* @route '/w/{workspace_slug}/tasks/{id}'
*/
export const show = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/tasks/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:116
* @route '/w/{workspace_slug}/tasks/{id}'
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
* @see routes/web.php:116
* @route '/w/{workspace_slug}/tasks/{id}'
*/
show.get = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:116
* @route '/w/{workspace_slug}/tasks/{id}'
*/
show.head = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const tasks = {
    analytics: Object.assign(analytics, analytics),
    show: Object.assign(show, show),
}

export default tasks