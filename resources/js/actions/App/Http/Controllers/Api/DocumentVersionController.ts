import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DocumentVersionController::index
* @see app/Http/Controllers/Api/DocumentVersionController.php:14
* @route '/api/documents/{documentId}/versions'
*/
export const index = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/documents/{documentId}/versions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DocumentVersionController::index
* @see app/Http/Controllers/Api/DocumentVersionController.php:14
* @route '/api/documents/{documentId}/versions'
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
* @see \App\Http\Controllers\Api\DocumentVersionController::index
* @see app/Http/Controllers/Api/DocumentVersionController.php:14
* @route '/api/documents/{documentId}/versions'
*/
index.get = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DocumentVersionController::index
* @see app/Http/Controllers/Api/DocumentVersionController.php:14
* @route '/api/documents/{documentId}/versions'
*/
index.head = (args: { documentId: string | number } | [documentId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DocumentVersionController::restore
* @see app/Http/Controllers/Api/DocumentVersionController.php:24
* @route '/api/documents/{documentId}/versions/{versionId}/restore'
*/
export const restore = (args: { documentId: string | number, versionId: string | number } | [documentId: string | number, versionId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/api/documents/{documentId}/versions/{versionId}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DocumentVersionController::restore
* @see app/Http/Controllers/Api/DocumentVersionController.php:24
* @route '/api/documents/{documentId}/versions/{versionId}/restore'
*/
restore.url = (args: { documentId: string | number, versionId: string | number } | [documentId: string | number, versionId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            documentId: args[0],
            versionId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        documentId: args.documentId,
        versionId: args.versionId,
    }

    return restore.definition.url
            .replace('{documentId}', parsedArgs.documentId.toString())
            .replace('{versionId}', parsedArgs.versionId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DocumentVersionController::restore
* @see app/Http/Controllers/Api/DocumentVersionController.php:24
* @route '/api/documents/{documentId}/versions/{versionId}/restore'
*/
restore.post = (args: { documentId: string | number, versionId: string | number } | [documentId: string | number, versionId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

const DocumentVersionController = { index, restore }

export default DocumentVersionController