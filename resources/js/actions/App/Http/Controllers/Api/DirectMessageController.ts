import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DirectMessageController::index
* @see app/Http/Controllers/Api/DirectMessageController.php:18
* @route '/api/direct-messages'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/direct-messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DirectMessageController::index
* @see app/Http/Controllers/Api/DirectMessageController.php:18
* @route '/api/direct-messages'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DirectMessageController::index
* @see app/Http/Controllers/Api/DirectMessageController.php:18
* @route '/api/direct-messages'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::index
* @see app/Http/Controllers/Api/DirectMessageController.php:18
* @route '/api/direct-messages'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::store
* @see app/Http/Controllers/Api/DirectMessageController.php:38
* @route '/api/direct-messages'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/direct-messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DirectMessageController::store
* @see app/Http/Controllers/Api/DirectMessageController.php:38
* @route '/api/direct-messages'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DirectMessageController::store
* @see app/Http/Controllers/Api/DirectMessageController.php:38
* @route '/api/direct-messages'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::unreadCount
* @see app/Http/Controllers/Api/DirectMessageController.php:98
* @route '/api/direct-messages/unread-count'
*/
export const unreadCount = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unreadCount.url(options),
    method: 'get',
})

unreadCount.definition = {
    methods: ["get","head"],
    url: '/api/direct-messages/unread-count',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DirectMessageController::unreadCount
* @see app/Http/Controllers/Api/DirectMessageController.php:98
* @route '/api/direct-messages/unread-count'
*/
unreadCount.url = (options?: RouteQueryOptions) => {
    return unreadCount.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DirectMessageController::unreadCount
* @see app/Http/Controllers/Api/DirectMessageController.php:98
* @route '/api/direct-messages/unread-count'
*/
unreadCount.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: unreadCount.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::unreadCount
* @see app/Http/Controllers/Api/DirectMessageController.php:98
* @route '/api/direct-messages/unread-count'
*/
unreadCount.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: unreadCount.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::show
* @see app/Http/Controllers/Api/DirectMessageController.php:31
* @route '/api/direct-messages/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/direct-messages/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DirectMessageController::show
* @see app/Http/Controllers/Api/DirectMessageController.php:31
* @route '/api/direct-messages/{id}'
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
* @see \App\Http\Controllers\Api\DirectMessageController::show
* @see app/Http/Controllers/Api/DirectMessageController.php:31
* @route '/api/direct-messages/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::show
* @see app/Http/Controllers/Api/DirectMessageController.php:31
* @route '/api/direct-messages/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DirectMessageController::markRead
* @see app/Http/Controllers/Api/DirectMessageController.php:86
* @route '/api/direct-messages/{id}/read'
*/
export const markRead = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markRead.url(args, options),
    method: 'post',
})

markRead.definition = {
    methods: ["post"],
    url: '/api/direct-messages/{id}/read',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DirectMessageController::markRead
* @see app/Http/Controllers/Api/DirectMessageController.php:86
* @route '/api/direct-messages/{id}/read'
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
* @see \App\Http\Controllers\Api\DirectMessageController::markRead
* @see app/Http/Controllers/Api/DirectMessageController.php:86
* @route '/api/direct-messages/{id}/read'
*/
markRead.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: markRead.url(args, options),
    method: 'post',
})

const DirectMessageController = { index, store, unreadCount, show, markRead }

export default DirectMessageController