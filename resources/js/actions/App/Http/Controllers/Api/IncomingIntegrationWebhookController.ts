import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\IncomingIntegrationWebhookController::__invoke
* @see app/Http/Controllers/Api/IncomingIntegrationWebhookController.php:19
* @route '/api/webhooks/{webhook}'
*/
const IncomingIntegrationWebhookController = (args: { webhook: string | number } | [webhook: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: IncomingIntegrationWebhookController.url(args, options),
    method: 'post',
})

IncomingIntegrationWebhookController.definition = {
    methods: ["post"],
    url: '/api/webhooks/{webhook}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IncomingIntegrationWebhookController::__invoke
* @see app/Http/Controllers/Api/IncomingIntegrationWebhookController.php:19
* @route '/api/webhooks/{webhook}'
*/
IncomingIntegrationWebhookController.url = (args: { webhook: string | number } | [webhook: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { webhook: args }
    }

    if (Array.isArray(args)) {
        args = {
            webhook: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        webhook: args.webhook,
    }

    return IncomingIntegrationWebhookController.definition.url
            .replace('{webhook}', parsedArgs.webhook.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IncomingIntegrationWebhookController::__invoke
* @see app/Http/Controllers/Api/IncomingIntegrationWebhookController.php:19
* @route '/api/webhooks/{webhook}'
*/
IncomingIntegrationWebhookController.post = (args: { webhook: string | number } | [webhook: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: IncomingIntegrationWebhookController.url(args, options),
    method: 'post',
})

export default IncomingIntegrationWebhookController