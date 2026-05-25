import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::index
* @see app/Http/Controllers/Api/TelegramOperationsController.php:25
* @route '/api/integrations/telegram/operations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/integrations/telegram/operations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::index
* @see app/Http/Controllers/Api/TelegramOperationsController.php:25
* @route '/api/integrations/telegram/operations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::index
* @see app/Http/Controllers/Api/TelegramOperationsController.php:25
* @route '/api/integrations/telegram/operations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::index
* @see app/Http/Controllers/Api/TelegramOperationsController.php:25
* @route '/api/integrations/telegram/operations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::repair
* @see app/Http/Controllers/Api/TelegramOperationsController.php:37
* @route '/api/integrations/telegram/operations/repair'
*/
export const repair = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: repair.url(options),
    method: 'post',
})

repair.definition = {
    methods: ["post"],
    url: '/api/integrations/telegram/operations/repair',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::repair
* @see app/Http/Controllers/Api/TelegramOperationsController.php:37
* @route '/api/integrations/telegram/operations/repair'
*/
repair.url = (options?: RouteQueryOptions) => {
    return repair.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::repair
* @see app/Http/Controllers/Api/TelegramOperationsController.php:37
* @route '/api/integrations/telegram/operations/repair'
*/
repair.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: repair.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::replayReceipt
* @see app/Http/Controllers/Api/TelegramOperationsController.php:51
* @route '/api/integrations/telegram/receipts/{receiptId}/replay'
*/
export const replayReceipt = (args: { receiptId: string | number } | [receiptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: replayReceipt.url(args, options),
    method: 'post',
})

replayReceipt.definition = {
    methods: ["post"],
    url: '/api/integrations/telegram/receipts/{receiptId}/replay',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::replayReceipt
* @see app/Http/Controllers/Api/TelegramOperationsController.php:51
* @route '/api/integrations/telegram/receipts/{receiptId}/replay'
*/
replayReceipt.url = (args: { receiptId: string | number } | [receiptId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { receiptId: args }
    }

    if (Array.isArray(args)) {
        args = {
            receiptId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        receiptId: args.receiptId,
    }

    return replayReceipt.definition.url
            .replace('{receiptId}', parsedArgs.receiptId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::replayReceipt
* @see app/Http/Controllers/Api/TelegramOperationsController.php:51
* @route '/api/integrations/telegram/receipts/{receiptId}/replay'
*/
replayReceipt.post = (args: { receiptId: string | number } | [receiptId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: replayReceipt.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::retryDelivery
* @see app/Http/Controllers/Api/TelegramOperationsController.php:75
* @route '/api/integrations/telegram/deliveries/{deliveryId}/retry'
*/
export const retryDelivery = (args: { deliveryId: string | number } | [deliveryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retryDelivery.url(args, options),
    method: 'post',
})

retryDelivery.definition = {
    methods: ["post"],
    url: '/api/integrations/telegram/deliveries/{deliveryId}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::retryDelivery
* @see app/Http/Controllers/Api/TelegramOperationsController.php:75
* @route '/api/integrations/telegram/deliveries/{deliveryId}/retry'
*/
retryDelivery.url = (args: { deliveryId: string | number } | [deliveryId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { deliveryId: args }
    }

    if (Array.isArray(args)) {
        args = {
            deliveryId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        deliveryId: args.deliveryId,
    }

    return retryDelivery.definition.url
            .replace('{deliveryId}', parsedArgs.deliveryId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TelegramOperationsController::retryDelivery
* @see app/Http/Controllers/Api/TelegramOperationsController.php:75
* @route '/api/integrations/telegram/deliveries/{deliveryId}/retry'
*/
retryDelivery.post = (args: { deliveryId: string | number } | [deliveryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retryDelivery.url(args, options),
    method: 'post',
})

const TelegramOperationsController = { index, repair, replayReceipt, retryDelivery }

export default TelegramOperationsController