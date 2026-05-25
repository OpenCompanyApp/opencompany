import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ChatWebhookController::__invoke
* @see app/Http/Controllers/Api/ChatWebhookController.php:23
* @route '/api/webhooks/chat/{adapter}'
*/
const ChatWebhookController = (args: { adapter: string | number } | [adapter: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatWebhookController.url(args, options),
    method: 'post',
})

ChatWebhookController.definition = {
    methods: ["post"],
    url: '/api/webhooks/chat/{adapter}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ChatWebhookController::__invoke
* @see app/Http/Controllers/Api/ChatWebhookController.php:23
* @route '/api/webhooks/chat/{adapter}'
*/
ChatWebhookController.url = (args: { adapter: string | number } | [adapter: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { adapter: args }
    }

    if (Array.isArray(args)) {
        args = {
            adapter: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        adapter: args.adapter,
    }

    return ChatWebhookController.definition.url
            .replace('{adapter}', parsedArgs.adapter.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ChatWebhookController::__invoke
* @see app/Http/Controllers/Api/ChatWebhookController.php:23
* @route '/api/webhooks/chat/{adapter}'
*/
ChatWebhookController.post = (args: { adapter: string | number } | [adapter: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatWebhookController.url(args, options),
    method: 'post',
})

export default ChatWebhookController