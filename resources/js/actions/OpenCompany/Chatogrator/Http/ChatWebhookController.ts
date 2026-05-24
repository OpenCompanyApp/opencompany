import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \OpenCompany\Chatogrator\Http\ChatWebhookController::__invoke
* @see vendor/opencompany/chatogrator/src/Http/ChatWebhookController.php:11
* @route '/internal/chatogrator/webhooks/{adapter}'
*/
const ChatWebhookController = (args: { adapter: string | number } | [adapter: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatWebhookController.url(args, options),
    method: 'post',
})

ChatWebhookController.definition = {
    methods: ["post"],
    url: '/internal/chatogrator/webhooks/{adapter}',
} satisfies RouteDefinition<["post"]>

/**
* @see \OpenCompany\Chatogrator\Http\ChatWebhookController::__invoke
* @see vendor/opencompany/chatogrator/src/Http/ChatWebhookController.php:11
* @route '/internal/chatogrator/webhooks/{adapter}'
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
* @see \OpenCompany\Chatogrator\Http\ChatWebhookController::__invoke
* @see vendor/opencompany/chatogrator/src/Http/ChatWebhookController.php:11
* @route '/internal/chatogrator/webhooks/{adapter}'
*/
ChatWebhookController.post = (args: { adapter: string | number } | [adapter: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatWebhookController.url(args, options),
    method: 'post',
})

export default ChatWebhookController