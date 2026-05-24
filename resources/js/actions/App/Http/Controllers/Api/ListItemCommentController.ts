import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ListItemCommentController::index
* @see app/Http/Controllers/Api/ListItemCommentController.php:20
* @route '/api/list-items/{listItemId}/comments'
*/
export const index = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/list-items/{listItemId}/comments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::index
* @see app/Http/Controllers/Api/ListItemCommentController.php:20
* @route '/api/list-items/{listItemId}/comments'
*/
index.url = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { listItemId: args }
    }

    if (Array.isArray(args)) {
        args = {
            listItemId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        listItemId: args.listItemId,
    }

    return index.definition.url
            .replace('{listItemId}', parsedArgs.listItemId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::index
* @see app/Http/Controllers/Api/ListItemCommentController.php:20
* @route '/api/list-items/{listItemId}/comments'
*/
index.get = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::index
* @see app/Http/Controllers/Api/ListItemCommentController.php:20
* @route '/api/list-items/{listItemId}/comments'
*/
index.head = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::store
* @see app/Http/Controllers/Api/ListItemCommentController.php:33
* @route '/api/list-items/{listItemId}/comments'
*/
export const store = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/list-items/{listItemId}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::store
* @see app/Http/Controllers/Api/ListItemCommentController.php:33
* @route '/api/list-items/{listItemId}/comments'
*/
store.url = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { listItemId: args }
    }

    if (Array.isArray(args)) {
        args = {
            listItemId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        listItemId: args.listItemId,
    }

    return store.definition.url
            .replace('{listItemId}', parsedArgs.listItemId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::store
* @see app/Http/Controllers/Api/ListItemCommentController.php:33
* @route '/api/list-items/{listItemId}/comments'
*/
store.post = (args: { listItemId: string | number } | [listItemId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::destroy
* @see app/Http/Controllers/Api/ListItemCommentController.php:50
* @route '/api/list-items/{listItemId}/comments/{commentId}'
*/
export const destroy = (args: { listItemId: string | number, commentId: string | number } | [listItemId: string | number, commentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/list-items/{listItemId}/comments/{commentId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::destroy
* @see app/Http/Controllers/Api/ListItemCommentController.php:50
* @route '/api/list-items/{listItemId}/comments/{commentId}'
*/
destroy.url = (args: { listItemId: string | number, commentId: string | number } | [listItemId: string | number, commentId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            listItemId: args[0],
            commentId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        listItemId: args.listItemId,
        commentId: args.commentId,
    }

    return destroy.definition.url
            .replace('{listItemId}', parsedArgs.listItemId.toString())
            .replace('{commentId}', parsedArgs.commentId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListItemCommentController::destroy
* @see app/Http/Controllers/Api/ListItemCommentController.php:50
* @route '/api/list-items/{listItemId}/comments/{commentId}'
*/
destroy.delete = (args: { listItemId: string | number, commentId: string | number } | [listItemId: string | number, commentId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const ListItemCommentController = { index, store, destroy }

export default ListItemCommentController