import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\CalendarEventController::exportMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:78
* @route '/api/calendar/events/export.ics'
*/
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/api/calendar/events/export.ics',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::exportMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:78
* @route '/api/calendar/events/export.ics'
*/
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventController::exportMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:78
* @route '/api/calendar/events/export.ics'
*/
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::exportMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:78
* @route '/api/calendar/events/export.ics'
*/
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::importMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:92
* @route '/api/calendar/events/import'
*/
export const importMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

importMethod.definition = {
    methods: ["post"],
    url: '/api/calendar/events/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::importMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:92
* @route '/api/calendar/events/import'
*/
importMethod.url = (options?: RouteQueryOptions) => {
    return importMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventController::importMethod
* @see app/Http/Controllers/Api/CalendarEventController.php:92
* @route '/api/calendar/events/import'
*/
importMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::importFromUrl
* @see app/Http/Controllers/Api/CalendarEventController.php:103
* @route '/api/calendar/events/import-url'
*/
export const importFromUrl = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importFromUrl.url(options),
    method: 'post',
})

importFromUrl.definition = {
    methods: ["post"],
    url: '/api/calendar/events/import-url',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::importFromUrl
* @see app/Http/Controllers/Api/CalendarEventController.php:103
* @route '/api/calendar/events/import-url'
*/
importFromUrl.url = (options?: RouteQueryOptions) => {
    return importFromUrl.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventController::importFromUrl
* @see app/Http/Controllers/Api/CalendarEventController.php:103
* @route '/api/calendar/events/import-url'
*/
importFromUrl.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importFromUrl.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::index
* @see app/Http/Controllers/Api/CalendarEventController.php:24
* @route '/api/calendar/events'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/calendar/events',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::index
* @see app/Http/Controllers/Api/CalendarEventController.php:24
* @route '/api/calendar/events'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventController::index
* @see app/Http/Controllers/Api/CalendarEventController.php:24
* @route '/api/calendar/events'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::index
* @see app/Http/Controllers/Api/CalendarEventController.php:24
* @route '/api/calendar/events'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::store
* @see app/Http/Controllers/Api/CalendarEventController.php:39
* @route '/api/calendar/events'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/calendar/events',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::store
* @see app/Http/Controllers/Api/CalendarEventController.php:39
* @route '/api/calendar/events'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventController::store
* @see app/Http/Controllers/Api/CalendarEventController.php:39
* @route '/api/calendar/events'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::show
* @see app/Http/Controllers/Api/CalendarEventController.php:34
* @route '/api/calendar/events/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/calendar/events/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::show
* @see app/Http/Controllers/Api/CalendarEventController.php:34
* @route '/api/calendar/events/{id}'
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
* @see \App\Http\Controllers\Api\CalendarEventController::show
* @see app/Http/Controllers/Api/CalendarEventController.php:34
* @route '/api/calendar/events/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::show
* @see app/Http/Controllers/Api/CalendarEventController.php:34
* @route '/api/calendar/events/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::update
* @see app/Http/Controllers/Api/CalendarEventController.php:53
* @route '/api/calendar/events/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/calendar/events/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::update
* @see app/Http/Controllers/Api/CalendarEventController.php:53
* @route '/api/calendar/events/{id}'
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
* @see \App\Http\Controllers\Api\CalendarEventController::update
* @see app/Http/Controllers/Api/CalendarEventController.php:53
* @route '/api/calendar/events/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventController::destroy
* @see app/Http/Controllers/Api/CalendarEventController.php:71
* @route '/api/calendar/events/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/calendar/events/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventController::destroy
* @see app/Http/Controllers/Api/CalendarEventController.php:71
* @route '/api/calendar/events/{id}'
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
* @see \App\Http\Controllers\Api\CalendarEventController::destroy
* @see app/Http/Controllers/Api/CalendarEventController.php:71
* @route '/api/calendar/events/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const CalendarEventController = { exportMethod, importMethod, importFromUrl, index, store, show, update, destroy, export: exportMethod, import: importMethod }

export default CalendarEventController