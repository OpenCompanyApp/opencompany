import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ChannelController::index
* @see app/Http/Controllers/Api/ChannelController.php:18
* @route '/api/channels'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/channels',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::index
* @see app/Http/Controllers/Api/ChannelController.php:18
* @route '/api/channels'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::index
* @see app/Http/Controllers/Api/ChannelController.php:18
* @route '/api/channels'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::index
* @see app/Http/Controllers/Api/ChannelController.php:18
* @route '/api/channels'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::store
* @see app/Http/Controllers/Api/ChannelController.php:80
* @route '/api/channels'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/channels',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::store
* @see app/Http/Controllers/Api/ChannelController.php:80
* @route '/api/channels'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::store
* @see app/Http/Controllers/Api/ChannelController.php:80
* @route '/api/channels'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::show
* @see app/Http/Controllers/Api/ChannelController.php:67
* @route '/api/channels/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/channels/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::show
* @see app/Http/Controllers/Api/ChannelController.php:67
* @route '/api/channels/{id}'
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
* @see \App\Http\Controllers\Api\ChannelController::show
* @see app/Http/Controllers/Api/ChannelController.php:67
* @route '/api/channels/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::show
* @see app/Http/Controllers/Api/ChannelController.php:67
* @route '/api/channels/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::pinned
* @see app/Http/Controllers/Api/ChannelController.php:170
* @route '/api/channels/{id}/pinned'
*/
export const pinned = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pinned.url(args, options),
    method: 'get',
})

pinned.definition = {
    methods: ["get","head"],
    url: '/api/channels/{id}/pinned',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::pinned
* @see app/Http/Controllers/Api/ChannelController.php:170
* @route '/api/channels/{id}/pinned'
*/
pinned.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return pinned.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::pinned
* @see app/Http/Controllers/Api/ChannelController.php:170
* @route '/api/channels/{id}/pinned'
*/
pinned.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pinned.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::pinned
* @see app/Http/Controllers/Api/ChannelController.php:170
* @route '/api/channels/{id}/pinned'
*/
pinned.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pinned.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::addMember
* @see app/Http/Controllers/Api/ChannelController.php:120
* @route '/api/channels/{id}/members'
*/
export const addMember = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addMember.url(args, options),
    method: 'post',
})

addMember.definition = {
    methods: ["post"],
    url: '/api/channels/{id}/members',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::addMember
* @see app/Http/Controllers/Api/ChannelController.php:120
* @route '/api/channels/{id}/members'
*/
addMember.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return addMember.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::addMember
* @see app/Http/Controllers/Api/ChannelController.php:120
* @route '/api/channels/{id}/members'
*/
addMember.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addMember.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::removeMember
* @see app/Http/Controllers/Api/ChannelController.php:133
* @route '/api/channels/{channelId}/members/{userId}'
*/
export const removeMember = (args: { channelId: string | number, userId: string | number } | [channelId: string | number, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeMember.url(args, options),
    method: 'delete',
})

removeMember.definition = {
    methods: ["delete"],
    url: '/api/channels/{channelId}/members/{userId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::removeMember
* @see app/Http/Controllers/Api/ChannelController.php:133
* @route '/api/channels/{channelId}/members/{userId}'
*/
removeMember.url = (args: { channelId: string | number, userId: string | number } | [channelId: string | number, userId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            channelId: args[0],
            userId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        channelId: args.channelId,
        userId: args.userId,
    }

    return removeMember.definition.url
            .replace('{channelId}', parsedArgs.channelId.toString())
            .replace('{userId}', parsedArgs.userId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::removeMember
* @see app/Http/Controllers/Api/ChannelController.php:133
* @route '/api/channels/{channelId}/members/{userId}'
*/
removeMember.delete = (args: { channelId: string | number, userId: string | number } | [channelId: string | number, userId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeMember.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::markRead
* @see app/Http/Controllers/Api/ChannelController.php:144
* @route '/api/channels/{id}/read'
*/
export const markRead = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markRead.url(args, options),
    method: 'post',
})

markRead.definition = {
    methods: ["post"],
    url: '/api/channels/{id}/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::markRead
* @see app/Http/Controllers/Api/ChannelController.php:144
* @route '/api/channels/{id}/read'
*/
markRead.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return markRead.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::markRead
* @see app/Http/Controllers/Api/ChannelController.php:144
* @route '/api/channels/{id}/read'
*/
markRead.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markRead.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ChannelController::typing
* @see app/Http/Controllers/Api/ChannelController.php:155
* @route '/api/channels/{id}/typing'
*/
export const typing = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: typing.url(args, options),
    method: 'post',
})

typing.definition = {
    methods: ["post"],
    url: '/api/channels/{id}/typing',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ChannelController::typing
* @see app/Http/Controllers/Api/ChannelController.php:155
* @route '/api/channels/{id}/typing'
*/
typing.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return typing.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChannelController::typing
* @see app/Http/Controllers/Api/ChannelController.php:155
* @route '/api/channels/{id}/typing'
*/
typing.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: typing.url(args, options),
    method: 'post',
})

const ChannelController = { index, store, show, pinned, addMember, removeMember, markRead, typing }

export default ChannelController