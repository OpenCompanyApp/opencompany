import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\TelegramLinkController::__invoke
* @see app/Http/Controllers/TelegramLinkController.php:23
* @route '/telegram/link/{token}'
*/
export const claim = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: claim.url(args, options),
    method: 'get',
})

claim.definition = {
    methods: ["get","head"],
    url: '/telegram/link/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\TelegramLinkController::__invoke
* @see app/Http/Controllers/TelegramLinkController.php:23
* @route '/telegram/link/{token}'
*/
claim.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return claim.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\TelegramLinkController::__invoke
* @see app/Http/Controllers/TelegramLinkController.php:23
* @route '/telegram/link/{token}'
*/
claim.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: claim.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\TelegramLinkController::__invoke
* @see app/Http/Controllers/TelegramLinkController.php:23
* @route '/telegram/link/{token}'
*/
claim.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: claim.url(args, options),
    method: 'head',
})

const link = {
    claim: Object.assign(claim, claim),
}

export default link