import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::index
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:13
* @route '/api/documents/{documentId}/attachments'
*/
export const index = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/documents/{documentId}/attachments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::index
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:13
* @route '/api/documents/{documentId}/attachments'
*/
index.url = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { documentId: args }
    }

    if (Array.isArray(args)) {
        args = {
            documentId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        documentId: args.documentId,
    }

    return index.definition.url
            .replace('{documentId}', parsedArgs.documentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::index
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:13
* @route '/api/documents/{documentId}/attachments'
*/
index.get = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::index
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:13
* @route '/api/documents/{documentId}/attachments'
*/
index.head = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::store
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:23
* @route '/api/documents/{documentId}/attachments'
*/
export const store = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/documents/{documentId}/attachments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::store
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:23
* @route '/api/documents/{documentId}/attachments'
*/
store.url = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { documentId: args }
    }

    if (Array.isArray(args)) {
        args = {
            documentId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        documentId: args.documentId,
    }

    return store.definition.url
            .replace('{documentId}', parsedArgs.documentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::store
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:23
* @route '/api/documents/{documentId}/attachments'
*/
store.post = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::destroy
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:45
* @route '/api/documents/{documentId}/attachments/{attachmentId}'
*/
export const destroy = (args: { documentId: string | number, attachmentId: string | number } | [documentId: string | number, attachmentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/documents/{documentId}/attachments/{attachmentId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::destroy
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:45
* @route '/api/documents/{documentId}/attachments/{attachmentId}'
*/
destroy.url = (args: { documentId: string | number, attachmentId: string | number } | [documentId: string | number, attachmentId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            documentId: args[0],
            attachmentId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        documentId: args.documentId,
        attachmentId: args.attachmentId,
    }

    return destroy.definition.url
            .replace('{documentId}', parsedArgs.documentId.toString())
            .replace('{attachmentId}', parsedArgs.attachmentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DocumentAttachmentController::destroy
* @see app/Http/Controllers/Api/DocumentAttachmentController.php:45
* @route '/api/documents/{documentId}/attachments/{attachmentId}'
*/
destroy.delete = (args: { documentId: string | number, attachmentId: string | number } | [documentId: string | number, attachmentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const DocumentAttachmentController = { index, store, destroy }

export default DocumentAttachmentController