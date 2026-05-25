import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AiGateway\ModelsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ModelsController.php:14
* @route '/api/ai-gateway/v1/models'
*/
const ModelsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ModelsController.url(options),
    method: 'get',
})

ModelsController.definition = {
    methods: ["get","head"],
    url: '/api/ai-gateway/v1/models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\AiGateway\ModelsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ModelsController.php:14
* @route '/api/ai-gateway/v1/models'
*/
ModelsController.url = (options?: RouteQueryOptions) => {
    return ModelsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AiGateway\ModelsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ModelsController.php:14
* @route '/api/ai-gateway/v1/models'
*/
ModelsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ModelsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\AiGateway\ModelsController::__invoke
* @see app/Http/Controllers/Api/AiGateway/ModelsController.php:14
* @route '/api/ai-gateway/v1/models'
*/
ModelsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ModelsController.url(options),
    method: 'head',
})

export default ModelsController