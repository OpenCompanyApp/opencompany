import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../wayfinder'
/**
* @see routes/web.php:18
* @route '/welcome'
*/
export const welcome = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: welcome.url(options),
    method: 'get',
})

welcome.definition = {
    methods: ["get","head"],
    url: '/welcome',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:18
* @route '/welcome'
*/
welcome.url = (options?: RouteQueryOptions) => {
    return welcome.definition.url + queryParams(options)
}

/**
* @see routes/web.php:18
* @route '/welcome'
*/
welcome.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: welcome.url(options),
    method: 'get',
})

/**
* @see routes/web.php:18
* @route '/welcome'
*/
welcome.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: welcome.url(options),
    method: 'head',
})

/**
* @see routes/web.php:48
* @route '/setup'
*/
export const setup = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setup.url(options),
    method: 'get',
})

setup.definition = {
    methods: ["get","head"],
    url: '/setup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:48
* @route '/setup'
*/
setup.url = (options?: RouteQueryOptions) => {
    return setup.definition.url + queryParams(options)
}

/**
* @see routes/web.php:48
* @route '/setup'
*/
setup.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setup.url(options),
    method: 'get',
})

/**
* @see routes/web.php:48
* @route '/setup'
*/
setup.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: setup.url(options),
    method: 'head',
})

/**
* @see routes/web.php:77
* @route '/'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:77
* @route '/'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see routes/web.php:77
* @route '/'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see routes/web.php:77
* @route '/'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see routes/web.php:93
* @route '/w/{workspace_slug}'
*/
export const dashboard = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(args, options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:93
* @route '/w/{workspace_slug}'
*/
dashboard.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return dashboard.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:93
* @route '/w/{workspace_slug}'
*/
dashboard.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:93
* @route '/w/{workspace_slug}'
*/
dashboard.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:103
* @route '/w/{workspace_slug}/chat'
*/
export const chat = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: chat.url(args, options),
    method: 'get',
})

chat.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/chat',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:103
* @route '/w/{workspace_slug}/chat'
*/
chat.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return chat.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:103
* @route '/w/{workspace_slug}/chat'
*/
chat.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: chat.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:103
* @route '/w/{workspace_slug}/chat'
*/
chat.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: chat.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:108
* @route '/w/{workspace_slug}/tasks'
*/
export const tasks = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tasks.url(args, options),
    method: 'get',
})

tasks.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/tasks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:108
* @route '/w/{workspace_slug}/tasks'
*/
tasks.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return tasks.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:108
* @route '/w/{workspace_slug}/tasks'
*/
tasks.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tasks.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:108
* @route '/w/{workspace_slug}/tasks'
*/
tasks.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: tasks.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:123
* @route '/w/{workspace_slug}/lists'
*/
export const lists = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: lists.url(args, options),
    method: 'get',
})

lists.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/lists',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:123
* @route '/w/{workspace_slug}/lists'
*/
lists.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return lists.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:123
* @route '/w/{workspace_slug}/lists'
*/
lists.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: lists.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:123
* @route '/w/{workspace_slug}/lists'
*/
lists.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: lists.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:128
* @route '/w/{workspace_slug}/docs'
*/
export const docs = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: docs.url(args, options),
    method: 'get',
})

docs.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/docs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:128
* @route '/w/{workspace_slug}/docs'
*/
docs.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return docs.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:128
* @route '/w/{workspace_slug}/docs'
*/
docs.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: docs.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:128
* @route '/w/{workspace_slug}/docs'
*/
docs.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: docs.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:133
* @route '/w/{workspace_slug}/files'
*/
export const files = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: files.url(args, options),
    method: 'get',
})

files.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/files',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:133
* @route '/w/{workspace_slug}/files'
*/
files.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return files.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:133
* @route '/w/{workspace_slug}/files'
*/
files.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: files.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:133
* @route '/w/{workspace_slug}/files'
*/
files.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: files.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:138
* @route '/w/{workspace_slug}/activity'
*/
export const activity = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activity.url(args, options),
    method: 'get',
})

activity.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/activity',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:138
* @route '/w/{workspace_slug}/activity'
*/
activity.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return activity.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:138
* @route '/w/{workspace_slug}/activity'
*/
activity.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activity.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:138
* @route '/w/{workspace_slug}/activity'
*/
activity.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: activity.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:143
* @route '/w/{workspace_slug}/approvals'
*/
export const approvals = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: approvals.url(args, options),
    method: 'get',
})

approvals.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/approvals',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:143
* @route '/w/{workspace_slug}/approvals'
*/
approvals.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return approvals.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:143
* @route '/w/{workspace_slug}/approvals'
*/
approvals.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: approvals.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:143
* @route '/w/{workspace_slug}/approvals'
*/
approvals.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: approvals.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:163
* @route '/w/{workspace_slug}/automation'
*/
export const automation = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: automation.url(args, options),
    method: 'get',
})

automation.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/automation',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:163
* @route '/w/{workspace_slug}/automation'
*/
automation.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return automation.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:163
* @route '/w/{workspace_slug}/automation'
*/
automation.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: automation.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:163
* @route '/w/{workspace_slug}/automation'
*/
automation.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: automation.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:168
* @route '/w/{workspace_slug}/org'
*/
export const org = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: org.url(args, options),
    method: 'get',
})

org.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/org',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:168
* @route '/w/{workspace_slug}/org'
*/
org.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return org.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:168
* @route '/w/{workspace_slug}/org'
*/
org.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: org.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:168
* @route '/w/{workspace_slug}/org'
*/
org.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: org.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:173
* @route '/w/{workspace_slug}/settings'
*/
export const settings = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: settings.url(args, options),
    method: 'get',
})

settings.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:173
* @route '/w/{workspace_slug}/settings'
*/
settings.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return settings.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:173
* @route '/w/{workspace_slug}/settings'
*/
settings.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: settings.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:173
* @route '/w/{workspace_slug}/settings'
*/
settings.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: settings.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:178
* @route '/w/{workspace_slug}/workload'
*/
export const workload = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: workload.url(args, options),
    method: 'get',
})

workload.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/workload',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:178
* @route '/w/{workspace_slug}/workload'
*/
workload.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return workload.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:178
* @route '/w/{workspace_slug}/workload'
*/
workload.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: workload.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:178
* @route '/w/{workspace_slug}/workload'
*/
workload.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: workload.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:183
* @route '/w/{workspace_slug}/integrations'
*/
export const integrations = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: integrations.url(args, options),
    method: 'get',
})

integrations.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/integrations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:183
* @route '/w/{workspace_slug}/integrations'
*/
integrations.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return integrations.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:183
* @route '/w/{workspace_slug}/integrations'
*/
integrations.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: integrations.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:183
* @route '/w/{workspace_slug}/integrations'
*/
integrations.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: integrations.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:198
* @route '/w/{workspace_slug}/calendar'
*/
export const calendar = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calendar.url(args, options),
    method: 'get',
})

calendar.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:198
* @route '/w/{workspace_slug}/calendar'
*/
calendar.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return calendar.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:198
* @route '/w/{workspace_slug}/calendar'
*/
calendar.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: calendar.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:198
* @route '/w/{workspace_slug}/calendar'
*/
calendar.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: calendar.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:203
* @route '/w/{workspace_slug}/tables'
*/
export const tables = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tables.url(args, options),
    method: 'get',
})

tables.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/tables',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:203
* @route '/w/{workspace_slug}/tables'
*/
tables.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return tables.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:203
* @route '/w/{workspace_slug}/tables'
*/
tables.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tables.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:203
* @route '/w/{workspace_slug}/tables'
*/
tables.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: tables.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:23
* @route '/register'
*/
export const register = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(options),
    method: 'get',
})

register.definition = {
    methods: ["get","head"],
    url: '/register',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:23
* @route '/register'
*/
register.url = (options?: RouteQueryOptions) => {
    return register.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:23
* @route '/register'
*/
register.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:23
* @route '/register'
*/
register.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: register.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:20
* @route '/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:20
* @route '/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:20
* @route '/login'
*/
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:20
* @route '/login'
*/
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:47
* @route '/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:47
* @route '/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:47
* @route '/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})
