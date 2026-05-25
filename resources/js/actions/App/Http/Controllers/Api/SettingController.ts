import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\SettingController::index
* @see app/Http/Controllers/Api/SettingController.php:31
* @route '/api/settings'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\SettingController::index
* @see app/Http/Controllers/Api/SettingController.php:31
* @route '/api/settings'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SettingController::index
* @see app/Http/Controllers/Api/SettingController.php:31
* @route '/api/settings'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\SettingController::index
* @see app/Http/Controllers/Api/SettingController.php:31
* @route '/api/settings'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\SettingController::update
* @see app/Http/Controllers/Api/SettingController.php:39
* @route '/api/settings'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\SettingController::update
* @see app/Http/Controllers/Api/SettingController.php:39
* @route '/api/settings'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SettingController::update
* @see app/Http/Controllers/Api/SettingController.php:39
* @route '/api/settings'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\SettingController::dangerAction
* @see app/Http/Controllers/Api/SettingController.php:85
* @route '/api/settings/danger-action'
*/
export const dangerAction = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dangerAction.url(options),
    method: 'post',
})

dangerAction.definition = {
    methods: ["post"],
    url: '/api/settings/danger-action',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\SettingController::dangerAction
* @see app/Http/Controllers/Api/SettingController.php:85
* @route '/api/settings/danger-action'
*/
dangerAction.url = (options?: RouteQueryOptions) => {
    return dangerAction.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SettingController::dangerAction
* @see app/Http/Controllers/Api/SettingController.php:85
* @route '/api/settings/danger-action'
*/
dangerAction.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dangerAction.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\SettingController::debug
* @see app/Http/Controllers/Api/SettingController.php:296
* @route '/api/settings/debug'
*/
export const debug = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: debug.url(options),
    method: 'get',
})

debug.definition = {
    methods: ["get","head"],
    url: '/api/settings/debug',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\SettingController::debug
* @see app/Http/Controllers/Api/SettingController.php:296
* @route '/api/settings/debug'
*/
debug.url = (options?: RouteQueryOptions) => {
    return debug.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\SettingController::debug
* @see app/Http/Controllers/Api/SettingController.php:296
* @route '/api/settings/debug'
*/
debug.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: debug.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\SettingController::debug
* @see app/Http/Controllers/Api/SettingController.php:296
* @route '/api/settings/debug'
*/
debug.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: debug.url(options),
    method: 'head',
})

const SettingController = { index, update, dangerAction, debug }

export default SettingController