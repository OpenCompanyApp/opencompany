import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AiGateway\ChatCompletionsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ChatCompletionsController.php:16
* @route '/api/ai-gateway/v1/chat/completions'
*/
const ChatCompletionsController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatCompletionsController.url(options),
    method: 'post',
})

ChatCompletionsController.definition = {
    methods: ["post"],
    url: '/api/ai-gateway/v1/chat/completions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AiGateway\ChatCompletionsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ChatCompletionsController.php:16
* @route '/api/ai-gateway/v1/chat/completions'
*/
ChatCompletionsController.url = (options?: RouteQueryOptions) => {
    return ChatCompletionsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGateway\ChatCompletionsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ChatCompletionsController.php:16
* @route '/api/ai-gateway/v1/chat/completions'
*/
ChatCompletionsController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatCompletionsController.url(options),
    method: 'post',
})

export default ChatCompletionsController