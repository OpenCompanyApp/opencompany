import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AiGateway\EmbeddingsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/EmbeddingsController.php:15
* @route '/api/ai-gateway/v1/embeddings'
*/
const EmbeddingsController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: EmbeddingsController.url(options),
    method: 'post',
})

EmbeddingsController.definition = {
    methods: ["post"],
    url: '/api/ai-gateway/v1/embeddings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AiGateway\EmbeddingsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/EmbeddingsController.php:15
* @route '/api/ai-gateway/v1/embeddings'
*/
EmbeddingsController.url = (options?: RouteQueryOptions) => {
    return EmbeddingsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGateway\EmbeddingsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/EmbeddingsController.php:15
* @route '/api/ai-gateway/v1/embeddings'
*/
EmbeddingsController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: EmbeddingsController.url(options),
    method: 'post',
})

export default EmbeddingsController