import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\FileController::index
* @see app/Http/Controllers/Api/FileController.php:28
* @route '/api/files'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/files',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\FileController::index
* @see app/Http/Controllers/Api/FileController.php:28
* @route '/api/files'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::index
* @see app/Http/Controllers/Api/FileController.php:28
* @route '/api/files'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\FileController::index
* @see app/Http/Controllers/Api/FileController.php:28
* @route '/api/files'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\FileController::tree
* @see app/Http/Controllers/Api/FileController.php:49
* @route '/api/files/tree'
*/
export const tree = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tree.url(options),
    method: 'get',
})

tree.definition = {
    methods: ["get","head"],
    url: '/api/files/tree',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\FileController::tree
* @see app/Http/Controllers/Api/FileController.php:49
* @route '/api/files/tree'
*/
tree.url = (options?: RouteQueryOptions) => {
    return tree.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::tree
* @see app/Http/Controllers/Api/FileController.php:49
* @route '/api/files/tree'
*/
tree.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tree.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\FileController::tree
* @see app/Http/Controllers/Api/FileController.php:49
* @route '/api/files/tree'
*/
tree.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: tree.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\FileController::search
* @see app/Http/Controllers/Api/FileController.php:59
* @route '/api/files/search'
*/
export const search = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/api/files/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\FileController::search
* @see app/Http/Controllers/Api/FileController.php:59
* @route '/api/files/search'
*/
search.url = (options?: RouteQueryOptions) => {
    return search.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::search
* @see app/Http/Controllers/Api/FileController.php:59
* @route '/api/files/search'
*/
search.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\FileController::search
* @see app/Http/Controllers/Api/FileController.php:59
* @route '/api/files/search'
*/
search.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\FileController::store
* @see app/Http/Controllers/Api/FileController.php:79
* @route '/api/files'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/files',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\FileController::store
* @see app/Http/Controllers/Api/FileController.php:79
* @route '/api/files'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::store
* @see app/Http/Controllers/Api/FileController.php:79
* @route '/api/files'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\FileController::createFolder
* @see app/Http/Controllers/Api/FileController.php:104
* @route '/api/files/folder'
*/
export const createFolder = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createFolder.url(options),
    method: 'post',
})

createFolder.definition = {
    methods: ["post"],
    url: '/api/files/folder',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\FileController::createFolder
* @see app/Http/Controllers/Api/FileController.php:104
* @route '/api/files/folder'
*/
createFolder.url = (options?: RouteQueryOptions) => {
    return createFolder.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::createFolder
* @see app/Http/Controllers/Api/FileController.php:104
* @route '/api/files/folder'
*/
createFolder.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createFolder.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\FileController::show
* @see app/Http/Controllers/Api/FileController.php:126
* @route '/api/files/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/files/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\FileController::show
* @see app/Http/Controllers/Api/FileController.php:126
* @route '/api/files/{id}'
*/
show.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::show
* @see app/Http/Controllers/Api/FileController.php:126
* @route '/api/files/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\FileController::show
* @see app/Http/Controllers/Api/FileController.php:126
* @route '/api/files/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\FileController::children
* @see app/Http/Controllers/Api/FileController.php:136
* @route '/api/files/{id}/children'
*/
export const children = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(args, options),
    method: 'get',
})

children.definition = {
    methods: ["get","head"],
    url: '/api/files/{id}/children',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\FileController::children
* @see app/Http/Controllers/Api/FileController.php:136
* @route '/api/files/{id}/children'
*/
children.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return children.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::children
* @see app/Http/Controllers/Api/FileController.php:136
* @route '/api/files/{id}/children'
*/
children.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\FileController::children
* @see app/Http/Controllers/Api/FileController.php:136
* @route '/api/files/{id}/children'
*/
children.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: children.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\FileController::download
* @see app/Http/Controllers/Api/FileController.php:153
* @route '/api/files/{id}/download'
*/
export const download = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/api/files/{id}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\FileController::download
* @see app/Http/Controllers/Api/FileController.php:153
* @route '/api/files/{id}/download'
*/
download.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return download.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::download
* @see app/Http/Controllers/Api/FileController.php:153
* @route '/api/files/{id}/download'
*/
download.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\FileController::download
* @see app/Http/Controllers/Api/FileController.php:153
* @route '/api/files/{id}/download'
*/
download.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\FileController::update
* @see app/Http/Controllers/Api/FileController.php:179
* @route '/api/files/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/files/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\FileController::update
* @see app/Http/Controllers/Api/FileController.php:179
* @route '/api/files/{id}'
*/
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::update
* @see app/Http/Controllers/Api/FileController.php:179
* @route '/api/files/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\FileController::destroy
* @see app/Http/Controllers/Api/FileController.php:206
* @route '/api/files/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/files/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\FileController::destroy
* @see app/Http/Controllers/Api/FileController.php:206
* @route '/api/files/{id}'
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
* @see \App\Http\Controllers\Api\FileController::destroy
* @see app/Http/Controllers/Api/FileController.php:206
* @route '/api/files/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\FileController::copy
* @see app/Http/Controllers/Api/FileController.php:218
* @route '/api/files/{id}/copy'
*/
export const copy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: copy.url(args, options),
    method: 'post',
})

copy.definition = {
    methods: ["post"],
    url: '/api/files/{id}/copy',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\FileController::copy
* @see app/Http/Controllers/Api/FileController.php:218
* @route '/api/files/{id}/copy'
*/
copy.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return copy.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\FileController::copy
* @see app/Http/Controllers/Api/FileController.php:218
* @route '/api/files/{id}/copy'
*/
copy.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: copy.url(args, options),
    method: 'post',
})

const FileController = { index, tree, search, store, createFolder, show, children, download, update, destroy, copy }

export default FileController