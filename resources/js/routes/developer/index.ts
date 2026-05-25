import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see routes/web.php:188
* @route '/w/{workspace_slug}/developer/tools'
*/
export const tools = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tools.url(args, options),
    method: 'get',
})

tools.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/developer/tools',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:188
* @route '/w/{workspace_slug}/developer/tools'
*/
tools.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return tools.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:188
* @route '/w/{workspace_slug}/developer/tools'
*/
tools.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tools.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:188
* @route '/w/{workspace_slug}/developer/tools'
*/
tools.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: tools.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:193
* @route '/w/{workspace_slug}/developer/lua-console'
*/
export const luaConsole = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: luaConsole.url(args, options),
    method: 'get',
})

luaConsole.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/developer/lua-console',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:193
* @route '/w/{workspace_slug}/developer/lua-console'
*/
luaConsole.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return luaConsole.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:193
* @route '/w/{workspace_slug}/developer/lua-console'
*/
luaConsole.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: luaConsole.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:193
* @route '/w/{workspace_slug}/developer/lua-console'
*/
luaConsole.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: luaConsole.url(args, options),
    method: 'head',
})

const developer = {
    tools: Object.assign(tools, tools),
    luaConsole: Object.assign(luaConsole, luaConsole),
}

export default developer