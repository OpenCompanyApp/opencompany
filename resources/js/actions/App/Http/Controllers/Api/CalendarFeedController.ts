import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\CalendarFeedController::index
* @see app/Http/Controllers/Api/CalendarFeedController.php:20
* @route '/api/calendar/feeds'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/calendar/feeds',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::index
* @see app/Http/Controllers/Api/CalendarFeedController.php:20
* @route '/api/calendar/feeds'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::index
* @see app/Http/Controllers/Api/CalendarFeedController.php:20
* @route '/api/calendar/feeds'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::index
* @see app/Http/Controllers/Api/CalendarFeedController.php:20
* @route '/api/calendar/feeds'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::store
* @see app/Http/Controllers/Api/CalendarFeedController.php:36
* @route '/api/calendar/feeds'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/calendar/feeds',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::store
* @see app/Http/Controllers/Api/CalendarFeedController.php:36
* @route '/api/calendar/feeds'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::store
* @see app/Http/Controllers/Api/CalendarFeedController.php:36
* @route '/api/calendar/feeds'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::destroy
* @see app/Http/Controllers/Api/CalendarFeedController.php:57
* @route '/api/calendar/feeds/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/calendar/feeds/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::destroy
* @see app/Http/Controllers/Api/CalendarFeedController.php:57
* @route '/api/calendar/feeds/{id}'
*/
destroy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::destroy
* @see app/Http/Controllers/Api/CalendarFeedController.php:57
* @route '/api/calendar/feeds/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::feed
* @see app/Http/Controllers/Api/CalendarFeedController.php:68
* @route '/cal/{token}.ics'
*/
export const feed = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: feed.url(args, options),
    method: 'get',
})

feed.definition = {
    methods: ["get","head"],
    url: '/cal/{token}.ics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::feed
* @see app/Http/Controllers/Api/CalendarFeedController.php:68
* @route '/cal/{token}.ics'
*/
feed.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    if (Array.isArray(args)) {
        args = {
            token: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        token: args.token,
    }

    return feed.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::feed
* @see app/Http/Controllers/Api/CalendarFeedController.php:68
* @route '/cal/{token}.ics'
*/
feed.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: feed.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CalendarFeedController::feed
* @see app/Http/Controllers/Api/CalendarFeedController.php:68
* @route '/cal/{token}.ics'
*/
feed.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: feed.url(args, options),
    method: 'head',
})

const CalendarFeedController = { index, store, destroy, feed }

export default CalendarFeedController