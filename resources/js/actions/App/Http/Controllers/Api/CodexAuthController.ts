import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\CodexAuthController::status
* @see app/Http/Controllers/Api/CodexAuthController.php:18
* @route '/api/integrations/codex/auth/status'
*/
export const status = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

status.definition = {
    methods: ["get","head"],
    url: '/api/integrations/codex/auth/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\CodexAuthController::status
* @see app/Http/Controllers/Api/CodexAuthController.php:18
* @route '/api/integrations/codex/auth/status'
*/
status.url = (options?: RouteQueryOptions) => {
    return status.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CodexAuthController::status
* @see app/Http/Controllers/Api/CodexAuthController.php:18
* @route '/api/integrations/codex/auth/status'
*/
status.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: status.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\CodexAuthController::status
* @see app/Http/Controllers/Api/CodexAuthController.php:18
* @route '/api/integrations/codex/auth/status'
*/
status.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: status.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\CodexAuthController::device
* @see app/Http/Controllers/Api/CodexAuthController.php:36
* @route '/api/integrations/codex/auth/device'
*/
export const device = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: device.url(options),
    method: 'post',
})

device.definition = {
    methods: ["post"],
    url: '/api/integrations/codex/auth/device',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CodexAuthController::device
* @see app/Http/Controllers/Api/CodexAuthController.php:36
* @route '/api/integrations/codex/auth/device'
*/
device.url = (options?: RouteQueryOptions) => {
    return device.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CodexAuthController::device
* @see app/Http/Controllers/Api/CodexAuthController.php:36
* @route '/api/integrations/codex/auth/device'
*/
device.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: device.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CodexAuthController::devicePoll
* @see app/Http/Controllers/Api/CodexAuthController.php:57
* @route '/api/integrations/codex/auth/device/poll'
*/
export const devicePoll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: devicePoll.url(options),
    method: 'post',
})

devicePoll.definition = {
    methods: ["post"],
    url: '/api/integrations/codex/auth/device/poll',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CodexAuthController::devicePoll
* @see app/Http/Controllers/Api/CodexAuthController.php:57
* @route '/api/integrations/codex/auth/device/poll'
*/
devicePoll.url = (options?: RouteQueryOptions) => {
    return devicePoll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CodexAuthController::devicePoll
* @see app/Http/Controllers/Api/CodexAuthController.php:57
* @route '/api/integrations/codex/auth/device/poll'
*/
devicePoll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: devicePoll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CodexAuthController::logout
* @see app/Http/Controllers/Api/CodexAuthController.php:94
* @route '/api/integrations/codex/auth/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/api/integrations/codex/auth/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CodexAuthController::logout
* @see app/Http/Controllers/Api/CodexAuthController.php:94
* @route '/api/integrations/codex/auth/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CodexAuthController::logout
* @see app/Http/Controllers/Api/CodexAuthController.php:94
* @route '/api/integrations/codex/auth/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\CodexAuthController::test
* @see app/Http/Controllers/Api/CodexAuthController.php:104
* @route '/api/integrations/codex/test'
*/
export const test = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: test.url(options),
    method: 'post',
})

test.definition = {
    methods: ["post"],
    url: '/api/integrations/codex/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\CodexAuthController::test
* @see app/Http/Controllers/Api/CodexAuthController.php:104
* @route '/api/integrations/codex/test'
*/
test.url = (options?: RouteQueryOptions) => {
    return test.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\CodexAuthController::test
* @see app/Http/Controllers/Api/CodexAuthController.php:104
* @route '/api/integrations/codex/test'
*/
test.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: test.url(options),
    method: 'post',
})

const CodexAuthController = { status, device, devicePoll, logout, test }

export default CodexAuthController