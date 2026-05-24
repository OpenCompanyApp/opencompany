import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AutomationRuleController::index
* @see app/Http/Controllers/Api/AutomationRuleController.php:15
* @route '/api/automation-rules'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/automation-rules',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::index
* @see app/Http/Controllers/Api/AutomationRuleController.php:15
* @route '/api/automation-rules'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::index
* @see app/Http/Controllers/Api/AutomationRuleController.php:15
* @route '/api/automation-rules'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::index
* @see app/Http/Controllers/Api/AutomationRuleController.php:15
* @route '/api/automation-rules'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::store
* @see app/Http/Controllers/Api/AutomationRuleController.php:26
* @route '/api/automation-rules'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/automation-rules',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::store
* @see app/Http/Controllers/Api/AutomationRuleController.php:26
* @route '/api/automation-rules'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::store
* @see app/Http/Controllers/Api/AutomationRuleController.php:26
* @route '/api/automation-rules'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::update
* @see app/Http/Controllers/Api/AutomationRuleController.php:44
* @route '/api/automation-rules/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/automation-rules/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::update
* @see app/Http/Controllers/Api/AutomationRuleController.php:44
* @route '/api/automation-rules/{id}'
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
* @see \App\Http\Controllers\Api\AutomationRuleController::update
* @see app/Http/Controllers/Api/AutomationRuleController.php:44
* @route '/api/automation-rules/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::destroy
* @see app/Http/Controllers/Api/AutomationRuleController.php:77
* @route '/api/automation-rules/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/automation-rules/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\AutomationRuleController::destroy
* @see app/Http/Controllers/Api/AutomationRuleController.php:77
* @route '/api/automation-rules/{id}'
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
* @see \App\Http\Controllers\Api\AutomationRuleController::destroy
* @see app/Http/Controllers/Api/AutomationRuleController.php:77
* @route '/api/automation-rules/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const AutomationRuleController = { index, store, update, destroy }

export default AutomationRuleController