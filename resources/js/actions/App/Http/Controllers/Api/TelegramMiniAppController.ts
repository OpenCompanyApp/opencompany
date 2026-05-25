import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::workspaces
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:40
* @route '/api/telegram/mini-app/workspaces'
*/
export const workspaces = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: workspaces.url(options),
    method: 'post',
})

workspaces.definition = {
    methods: ["post"],
    url: '/api/telegram/mini-app/workspaces',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::workspaces
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:40
* @route '/api/telegram/mini-app/workspaces'
*/
workspaces.url = (options?: RouteQueryOptions) => {
    return workspaces.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::workspaces
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:40
* @route '/api/telegram/mini-app/workspaces'
*/
workspaces.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: workspaces.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::session
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:123
* @route '/api/telegram/mini-app/session'
*/
export const session = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: session.url(options),
    method: 'post',
})

session.definition = {
    methods: ["post"],
    url: '/api/telegram/mini-app/session',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::session
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:123
* @route '/api/telegram/mini-app/session'
*/
session.url = (options?: RouteQueryOptions) => {
    return session.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::session
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:123
* @route '/api/telegram/mini-app/session'
*/
session.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: session.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::panel
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:179
* @route '/api/telegram/mini-app/panel'
*/
export const panel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: panel.url(options),
    method: 'post',
})

panel.definition = {
    methods: ["post"],
    url: '/api/telegram/mini-app/panel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::panel
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:179
* @route '/api/telegram/mini-app/panel'
*/
panel.url = (options?: RouteQueryOptions) => {
    return panel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::panel
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:179
* @route '/api/telegram/mini-app/panel'
*/
panel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: panel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::action
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:229
* @route '/api/telegram/mini-app/action'
*/
export const action = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(options),
    method: 'post',
})

action.definition = {
    methods: ["post"],
    url: '/api/telegram/mini-app/action',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::action
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:229
* @route '/api/telegram/mini-app/action'
*/
action.url = (options?: RouteQueryOptions) => {
    return action.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramMiniAppController::action
* @see app/Http/Controllers/Api/TelegramMiniAppController.php:229
* @route '/api/telegram/mini-app/action'
*/
action.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(options),
    method: 'post',
})

const TelegramMiniAppController = { workspaces, session, panel, action }

export default TelegramMiniAppController