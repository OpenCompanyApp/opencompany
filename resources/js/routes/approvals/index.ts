import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see routes/web.php:146
* @route '/w/{workspace_slug}/approvals/{id}'
*/
export const show = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/approvals/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:146
* @route '/w/{workspace_slug}/approvals/{id}'
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
* @see routes/web.php:146
* @route '/w/{workspace_slug}/approvals/{id}'
*/
show.get = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:146
* @route '/w/{workspace_slug}/approvals/{id}'
*/
show.head = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const approvals = {
    show: Object.assign(show, show),
}

export default approvals