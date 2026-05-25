import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AutomationController::index
* @see app/Http/Controllers/Api/AutomationController.php:28
* @route '/api/automations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/automations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::index
* @see app/Http/Controllers/Api/AutomationController.php:28
* @route '/api/automations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::index
* @see app/Http/Controllers/Api/AutomationController.php:28
* @route '/api/automations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::index
* @see app/Http/Controllers/Api/AutomationController.php:28
* @route '/api/automations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::previewSchedule
* @see app/Http/Controllers/Api/AutomationController.php:143
* @route '/api/automations/preview-schedule'
*/
export const previewSchedule = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: previewSchedule.url(options),
    method: 'get',
})

previewSchedule.definition = {
    methods: ["get","head"],
    url: '/api/automations/preview-schedule',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::previewSchedule
* @see app/Http/Controllers/Api/AutomationController.php:143
* @route '/api/automations/preview-schedule'
*/
previewSchedule.url = (options?: RouteQueryOptions) => {
    return previewSchedule.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::previewSchedule
* @see app/Http/Controllers/Api/AutomationController.php:143
* @route '/api/automations/preview-schedule'
*/
previewSchedule.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: previewSchedule.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::previewSchedule
* @see app/Http/Controllers/Api/AutomationController.php:143
* @route '/api/automations/preview-schedule'
*/
previewSchedule.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: previewSchedule.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::bulkDestroy
* @see app/Http/Controllers/Api/AutomationController.php:102
* @route '/api/automations/bulk-delete'
*/
export const bulkDestroy = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDestroy.url(options),
    method: 'post',
})

bulkDestroy.definition = {
    methods: ["post"],
    url: '/api/automations/bulk-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::bulkDestroy
* @see app/Http/Controllers/Api/AutomationController.php:102
* @route '/api/automations/bulk-delete'
*/
bulkDestroy.url = (options?: RouteQueryOptions) => {
    return bulkDestroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::bulkDestroy
* @see app/Http/Controllers/Api/AutomationController.php:102
* @route '/api/automations/bulk-delete'
*/
bulkDestroy.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDestroy.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::bulkTriggerRun
* @see app/Http/Controllers/Api/AutomationController.php:115
* @route '/api/automations/bulk-run'
*/
export const bulkTriggerRun = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkTriggerRun.url(options),
    method: 'post',
})

bulkTriggerRun.definition = {
    methods: ["post"],
    url: '/api/automations/bulk-run',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::bulkTriggerRun
* @see app/Http/Controllers/Api/AutomationController.php:115
* @route '/api/automations/bulk-run'
*/
bulkTriggerRun.url = (options?: RouteQueryOptions) => {
    return bulkTriggerRun.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::bulkTriggerRun
* @see app/Http/Controllers/Api/AutomationController.php:115
* @route '/api/automations/bulk-run'
*/
bulkTriggerRun.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkTriggerRun.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::store
* @see app/Http/Controllers/Api/AutomationController.php:36
* @route '/api/automations'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/automations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::store
* @see app/Http/Controllers/Api/AutomationController.php:36
* @route '/api/automations'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::store
* @see app/Http/Controllers/Api/AutomationController.php:36
* @route '/api/automations'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::show
* @see app/Http/Controllers/Api/AutomationController.php:66
* @route '/api/automations/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/automations/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::show
* @see app/Http/Controllers/Api/AutomationController.php:66
* @route '/api/automations/{id}'
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
* @see \App\Http\Controllers\Api\AutomationController::show
* @see app/Http/Controllers/Api/AutomationController.php:66
* @route '/api/automations/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::show
* @see app/Http/Controllers/Api/AutomationController.php:66
* @route '/api/automations/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::update
* @see app/Http/Controllers/Api/AutomationController.php:79
* @route '/api/automations/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/automations/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::update
* @see app/Http/Controllers/Api/AutomationController.php:79
* @route '/api/automations/{id}'
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
* @see \App\Http\Controllers\Api\AutomationController::update
* @see app/Http/Controllers/Api/AutomationController.php:79
* @route '/api/automations/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::destroy
* @see app/Http/Controllers/Api/AutomationController.php:95
* @route '/api/automations/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/automations/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::destroy
* @see app/Http/Controllers/Api/AutomationController.php:95
* @route '/api/automations/{id}'
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
* @see \App\Http\Controllers\Api\AutomationController::destroy
* @see app/Http/Controllers/Api/AutomationController.php:95
* @route '/api/automations/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::runs
* @see app/Http/Controllers/Api/AutomationController.php:131
* @route '/api/automations/{id}/runs'
*/
export const runs = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: runs.url(args, options),
    method: 'get',
})

runs.definition = {
    methods: ["get","head"],
    url: '/api/automations/{id}/runs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::runs
* @see app/Http/Controllers/Api/AutomationController.php:131
* @route '/api/automations/{id}/runs'
*/
runs.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return runs.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::runs
* @see app/Http/Controllers/Api/AutomationController.php:131
* @route '/api/automations/{id}/runs'
*/
runs.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: runs.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::runs
* @see app/Http/Controllers/Api/AutomationController.php:131
* @route '/api/automations/{id}/runs'
*/
runs.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: runs.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AutomationController::triggerRun
* @see app/Http/Controllers/Api/AutomationController.php:136
* @route '/api/automations/{id}/run'
*/
export const triggerRun = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: triggerRun.url(args, options),
    method: 'post',
})

triggerRun.definition = {
    methods: ["post"],
    url: '/api/automations/{id}/run',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AutomationController::triggerRun
* @see app/Http/Controllers/Api/AutomationController.php:136
* @route '/api/automations/{id}/run'
*/
triggerRun.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return triggerRun.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationController::triggerRun
* @see app/Http/Controllers/Api/AutomationController.php:136
* @route '/api/automations/{id}/run'
*/
triggerRun.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: triggerRun.url(args, options),
    method: 'post',
})

const AutomationController = { index, previewSchedule, bulkDestroy, bulkTriggerRun, store, show, update, destroy, runs, triggerRun }

export default AutomationController