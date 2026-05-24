import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\UserController::index
* @see app/Http/Controllers/Api/UserController.php:12
* @route '/api/users'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/users',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\UserController::index
* @see app/Http/Controllers/Api/UserController.php:12
* @route '/api/users'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\UserController::index
* @see app/Http/Controllers/Api/UserController.php:12
* @route '/api/users'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\UserController::index
* @see app/Http/Controllers/Api/UserController.php:12
* @route '/api/users'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\UserController::agents
* @see app/Http/Controllers/Api/UserController.php:21
* @route '/api/users/agents'
*/
export const agents = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: agents.url(options),
    method: 'get',
})

agents.definition = {
    methods: ["get","head"],
    url: '/api/users/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\UserController::agents
* @see app/Http/Controllers/Api/UserController.php:21
* @route '/api/users/agents'
*/
agents.url = (options?: RouteQueryOptions) => {
    return agents.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\UserController::agents
* @see app/Http/Controllers/Api/UserController.php:21
* @route '/api/users/agents'
*/
agents.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: agents.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\UserController::agents
* @see app/Http/Controllers/Api/UserController.php:21
* @route '/api/users/agents'
*/
agents.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: agents.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\UserController::show
* @see app/Http/Controllers/Api/UserController.php:28
* @route '/api/users/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/users/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\UserController::show
* @see app/Http/Controllers/Api/UserController.php:28
* @route '/api/users/{id}'
*/
show.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return show.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\UserController::show
* @see app/Http/Controllers/Api/UserController.php:28
* @route '/api/users/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\UserController::show
* @see app/Http/Controllers/Api/UserController.php:28
* @route '/api/users/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\UserController::activity
* @see app/Http/Controllers/Api/UserController.php:96
* @route '/api/users/{id}/activity'
*/
export const activity = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activity.url(args, options),
    method: 'get',
})

activity.definition = {
    methods: ["get","head"],
    url: '/api/users/{id}/activity',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\UserController::activity
* @see app/Http/Controllers/Api/UserController.php:96
* @route '/api/users/{id}/activity'
*/
activity.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return activity.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\UserController::activity
* @see app/Http/Controllers/Api/UserController.php:96
* @route '/api/users/{id}/activity'
*/
activity.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: activity.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\UserController::activity
* @see app/Http/Controllers/Api/UserController.php:96
* @route '/api/users/{id}/activity'
*/
activity.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: activity.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\UserController::update
* @see app/Http/Controllers/Api/UserController.php:53
* @route '/api/users/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/users/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\UserController::update
* @see app/Http/Controllers/Api/UserController.php:53
* @route '/api/users/{id}'
*/
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\UserController::update
* @see app/Http/Controllers/Api/UserController.php:53
* @route '/api/users/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\UserController::updatePresence
* @see app/Http/Controllers/Api/UserController.php:80
* @route '/api/users/{id}/presence'
*/
export const updatePresence = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updatePresence.url(args, options),
    method: 'patch',
})

updatePresence.definition = {
    methods: ["patch"],
    url: '/api/users/{id}/presence',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\UserController::updatePresence
* @see app/Http/Controllers/Api/UserController.php:80
* @route '/api/users/{id}/presence'
*/
updatePresence.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return updatePresence.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\UserController::updatePresence
* @see app/Http/Controllers/Api/UserController.php:80
* @route '/api/users/{id}/presence'
*/
updatePresence.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updatePresence.url(args, options),
    method: 'patch',
})

const UserController = { index, agents, show, activity, update, updatePresence }

export default UserController