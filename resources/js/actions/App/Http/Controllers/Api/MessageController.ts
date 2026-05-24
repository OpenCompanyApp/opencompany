import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\MessageController::compact
* @see app/Http/Controllers/Api/MessageController.php:184
* @route '/api/channels/{id}/compact'
*/
export const compact = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: compact.url(args, options),
    method: 'post',
})

compact.definition = {
    methods: ["post"],
    url: '/api/channels/{id}/compact',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\MessageController::compact
* @see app/Http/Controllers/Api/MessageController.php:184
* @route '/api/channels/{id}/compact'
*/
compact.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return compact.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::compact
* @see app/Http/Controllers/Api/MessageController.php:184
* @route '/api/channels/{id}/compact'
*/
compact.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: compact.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\MessageController::index
* @see app/Http/Controllers/Api/MessageController.php:37
* @route '/api/messages'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\MessageController::index
* @see app/Http/Controllers/Api/MessageController.php:37
* @route '/api/messages'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::index
* @see app/Http/Controllers/Api/MessageController.php:37
* @route '/api/messages'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\MessageController::index
* @see app/Http/Controllers/Api/MessageController.php:37
* @route '/api/messages'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\MessageController::store
* @see app/Http/Controllers/Api/MessageController.php:58
* @route '/api/messages'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\MessageController::store
* @see app/Http/Controllers/Api/MessageController.php:58
* @route '/api/messages'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::store
* @see app/Http/Controllers/Api/MessageController.php:58
* @route '/api/messages'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\MessageController::destroy
* @see app/Http/Controllers/Api/MessageController.php:260
* @route '/api/messages/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/messages/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\MessageController::destroy
* @see app/Http/Controllers/Api/MessageController.php:260
* @route '/api/messages/{id}'
*/
destroy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return destroy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::destroy
* @see app/Http/Controllers/Api/MessageController.php:260
* @route '/api/messages/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\MessageController::uploadAttachment
* @see app/Http/Controllers/Api/MessageController.php:326
* @route '/api/messages/attachments'
*/
export const uploadAttachment = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadAttachment.url(options),
    method: 'post',
})

uploadAttachment.definition = {
    methods: ["post"],
    url: '/api/messages/attachments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\MessageController::uploadAttachment
* @see app/Http/Controllers/Api/MessageController.php:326
* @route '/api/messages/attachments'
*/
uploadAttachment.url = (options?: RouteQueryOptions) => {
    return uploadAttachment.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::uploadAttachment
* @see app/Http/Controllers/Api/MessageController.php:326
* @route '/api/messages/attachments'
*/
uploadAttachment.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadAttachment.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\MessageController::addReaction
* @see app/Http/Controllers/Api/MessageController.php:269
* @route '/api/messages/{id}/reactions'
*/
export const addReaction = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addReaction.url(args, options),
    method: 'post',
})

addReaction.definition = {
    methods: ["post"],
    url: '/api/messages/{id}/reactions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\MessageController::addReaction
* @see app/Http/Controllers/Api/MessageController.php:269
* @route '/api/messages/{id}/reactions'
*/
addReaction.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return addReaction.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::addReaction
* @see app/Http/Controllers/Api/MessageController.php:269
* @route '/api/messages/{id}/reactions'
*/
addReaction.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addReaction.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\MessageController::removeReaction
* @see app/Http/Controllers/Api/MessageController.php:284
* @route '/api/messages/{messageId}/reactions/{reactionId}'
*/
export const removeReaction = (args: { messageId: string | number, reactionId: string | number } | [messageId: string | number, reactionId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeReaction.url(args, options),
    method: 'delete',
})

removeReaction.definition = {
    methods: ["delete"],
    url: '/api/messages/{messageId}/reactions/{reactionId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\MessageController::removeReaction
* @see app/Http/Controllers/Api/MessageController.php:284
* @route '/api/messages/{messageId}/reactions/{reactionId}'
*/
removeReaction.url = (args: { messageId: string | number, reactionId: string | number } | [messageId: string | number, reactionId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            messageId: args[0],
            reactionId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        messageId: args.messageId,
        reactionId: args.reactionId,
    }

    return removeReaction.definition.url
            .replace('{messageId}', parsedArgs.messageId.toString())
            .replace('{reactionId}', parsedArgs.reactionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::removeReaction
* @see app/Http/Controllers/Api/MessageController.php:284
* @route '/api/messages/{messageId}/reactions/{reactionId}'
*/
removeReaction.delete = (args: { messageId: string | number, reactionId: string | number } | [messageId: string | number, reactionId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: removeReaction.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\MessageController::thread
* @see app/Http/Controllers/Api/MessageController.php:296
* @route '/api/messages/{id}/thread'
*/
export const thread = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: thread.url(args, options),
    method: 'get',
})

thread.definition = {
    methods: ["get","head"],
    url: '/api/messages/{id}/thread',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\MessageController::thread
* @see app/Http/Controllers/Api/MessageController.php:296
* @route '/api/messages/{id}/thread'
*/
thread.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return thread.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::thread
* @see app/Http/Controllers/Api/MessageController.php:296
* @route '/api/messages/{id}/thread'
*/
thread.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: thread.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\MessageController::thread
* @see app/Http/Controllers/Api/MessageController.php:296
* @route '/api/messages/{id}/thread'
*/
thread.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: thread.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\MessageController::pin
* @see app/Http/Controllers/Api/MessageController.php:313
* @route '/api/messages/{id}/pin'
*/
export const pin = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pin.url(args, options),
    method: 'post',
})

pin.definition = {
    methods: ["post"],
    url: '/api/messages/{id}/pin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\MessageController::pin
* @see app/Http/Controllers/Api/MessageController.php:313
* @route '/api/messages/{id}/pin'
*/
pin.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return pin.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\MessageController::pin
* @see app/Http/Controllers/Api/MessageController.php:313
* @route '/api/messages/{id}/pin'
*/
pin.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pin.url(args, options),
    method: 'post',
})

const MessageController = { compact, index, store, destroy, uploadAttachment, addReaction, removeReaction, thread, pin }

export default MessageController