import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DocumentCommentController::index
* @see app/Http/Controllers/Api/DocumentCommentController.php:16
* @route '/api/documents/{documentId}/comments'
*/
export const index = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/documents/{documentId}/comments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::index
* @see app/Http/Controllers/Api/DocumentCommentController.php:16
* @route '/api/documents/{documentId}/comments'
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
* @see \App\Http\Controllers\Api\DocumentCommentController::index
* @see app/Http/Controllers/Api/DocumentCommentController.php:16
* @route '/api/documents/{documentId}/comments'
*/
index.get = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::index
* @see app/Http/Controllers/Api/DocumentCommentController.php:16
* @route '/api/documents/{documentId}/comments'
*/
index.head = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::store
* @see app/Http/Controllers/Api/DocumentCommentController.php:26
* @route '/api/documents/{documentId}/comments'
*/
export const store = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/documents/{documentId}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::store
* @see app/Http/Controllers/Api/DocumentCommentController.php:26
* @route '/api/documents/{documentId}/comments'
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
* @see \App\Http\Controllers\Api\DocumentCommentController::store
* @see app/Http/Controllers/Api/DocumentCommentController.php:26
* @route '/api/documents/{documentId}/comments'
*/
store.post = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::update
* @see app/Http/Controllers/Api/DocumentCommentController.php:41
* @route '/api/documents/{documentId}/comments/{commentId}'
*/
export const update = (args: { documentId: string | number, commentId: string | number } | [documentId: string | number, commentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/documents/{documentId}/comments/{commentId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::update
* @see app/Http/Controllers/Api/DocumentCommentController.php:41
* @route '/api/documents/{documentId}/comments/{commentId}'
*/
update.url = (args: { documentId: string | number, commentId: string | number } | [documentId: string | number, commentId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            documentId: args[0],
            commentId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        documentId: args.documentId,
        commentId: args.commentId,
    }

    return update.definition.url
            .replace('{documentId}', parsedArgs.documentId.toString())
            .replace('{commentId}', parsedArgs.commentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::update
* @see app/Http/Controllers/Api/DocumentCommentController.php:41
* @route '/api/documents/{documentId}/comments/{commentId}'
*/
update.patch = (args: { documentId: string | number, commentId: string | number } | [documentId: string | number, commentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::destroy
* @see app/Http/Controllers/Api/DocumentCommentController.php:70
* @route '/api/documents/{documentId}/comments/{commentId}'
*/
export const destroy = (args: { documentId: string | number, commentId: string | number } | [documentId: string | number, commentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/documents/{documentId}/comments/{commentId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::destroy
* @see app/Http/Controllers/Api/DocumentCommentController.php:70
* @route '/api/documents/{documentId}/comments/{commentId}'
*/
destroy.url = (args: { documentId: string | number, commentId: string | number } | [documentId: string | number, commentId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            documentId: args[0],
            commentId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        documentId: args.documentId,
        commentId: args.commentId,
    }

    return destroy.definition.url
            .replace('{documentId}', parsedArgs.documentId.toString())
            .replace('{commentId}', parsedArgs.commentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DocumentCommentController::destroy
* @see app/Http/Controllers/Api/DocumentCommentController.php:70
* @route '/api/documents/{documentId}/comments/{commentId}'
*/
destroy.delete = (args: { documentId: string | number, commentId: string | number } | [documentId: string | number, commentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const DocumentCommentController = { index, store, update, destroy }

export default DocumentCommentController