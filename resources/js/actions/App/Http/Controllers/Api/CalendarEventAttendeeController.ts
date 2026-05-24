import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::store
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:19
* @route '/api/calendar/events/{eventId}/attendees'
*/
export const store = (args: { eventId: string | number } | [eventId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/calendar/events/{eventId}/attendees',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::store
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:19
* @route '/api/calendar/events/{eventId}/attendees'
*/
store.url = (args: { eventId: string | number } | [eventId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { eventId: args }
    }

    if (Array.isArray(args)) {
        args = {
            eventId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        eventId: args.eventId,
    }

    return store.definition.url
            .replace('{eventId}', parsedArgs.eventId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::store
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:19
* @route '/api/calendar/events/{eventId}/attendees'
*/
store.post = (args: { eventId: string | number } | [eventId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::update
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:37
* @route '/api/calendar/events/{eventId}/attendees/{attendeeId}'
*/
export const update = (args: { eventId: string | number, attendeeId: string | number } | [eventId: string | number, attendeeId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/calendar/events/{eventId}/attendees/{attendeeId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::update
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:37
* @route '/api/calendar/events/{eventId}/attendees/{attendeeId}'
*/
update.url = (args: { eventId: string | number, attendeeId: string | number } | [eventId: string | number, attendeeId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            eventId: args[0],
            attendeeId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        eventId: args.eventId,
        attendeeId: args.attendeeId,
    }

    return update.definition.url
            .replace('{eventId}', parsedArgs.eventId.toString())
            .replace('{attendeeId}', parsedArgs.attendeeId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::update
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:37
* @route '/api/calendar/events/{eventId}/attendees/{attendeeId}'
*/
update.patch = (args: { eventId: string | number, attendeeId: string | number } | [eventId: string | number, attendeeId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::destroy
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:57
* @route '/api/calendar/events/{eventId}/attendees/{attendeeId}'
*/
export const destroy = (args: { eventId: string | number, attendeeId: string | number } | [eventId: string | number, attendeeId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/calendar/events/{eventId}/attendees/{attendeeId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::destroy
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:57
* @route '/api/calendar/events/{eventId}/attendees/{attendeeId}'
*/
destroy.url = (args: { eventId: string | number, attendeeId: string | number } | [eventId: string | number, attendeeId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            eventId: args[0],
            attendeeId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        eventId: args.eventId,
        attendeeId: args.attendeeId,
    }

    return destroy.definition.url
            .replace('{eventId}', parsedArgs.eventId.toString())
            .replace('{attendeeId}', parsedArgs.attendeeId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CalendarEventAttendeeController::destroy
* @see app/Http/Controllers/Api/CalendarEventAttendeeController.php:57
* @route '/api/calendar/events/{eventId}/attendees/{attendeeId}'
*/
destroy.delete = (args: { eventId: string | number, attendeeId: string | number } | [eventId: string | number, attendeeId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const CalendarEventAttendeeController = { store, update, destroy }

export default CalendarEventAttendeeController