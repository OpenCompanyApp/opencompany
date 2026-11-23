import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::index
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:30
* @route '/api/authorization/ledger'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/authorization/ledger',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::index
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:30
* @route '/api/authorization/ledger'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::index
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:30
* @route '/api/authorization/ledger'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::index
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:30
* @route '/api/authorization/ledger'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::verify
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:68
* @route '/api/authorization/ledger/verify'
*/
export const verify = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/api/authorization/ledger/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::verify
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:68
* @route '/api/authorization/ledger/verify'
*/
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::verify
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:68
* @route '/api/authorization/ledger/verify'
*/
verify.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AuthorizationLedgerController::verify
* @see app/Http/Controllers/Api/AuthorizationLedgerController.php:68
* @route '/api/authorization/ledger/verify'
*/
verify.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(options),
    method: 'head',
})

const AuthorizationLedgerController = { index, verify }

export default AuthorizationLedgerController