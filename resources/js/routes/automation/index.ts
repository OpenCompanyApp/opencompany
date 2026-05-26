import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see routes/web.php:153
* @route '/w/{workspace_slug}/automation/create'
*/
export const create = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/automation/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:153
* @route '/w/{workspace_slug}/automation/create'
*/
create.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return create.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:153
* @route '/w/{workspace_slug}/automation/create'
*/
create.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:153
* @route '/w/{workspace_slug}/automation/create'
*/
create.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:157
* @route '/w/{workspace_slug}/automation/{id}/edit'
*/
export const edit = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/automation/{id}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:157
* @route '/w/{workspace_slug}/automation/{id}/edit'
*/
edit.url = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:157
* @route '/w/{workspace_slug}/automation/{id}/edit'
*/
edit.get = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:157
* @route '/w/{workspace_slug}/automation/{id}/edit'
*/
edit.head = (args: { workspace_slug: string | number, id: string | number } | [workspace_slug: string | number, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

