import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DataTableColumnController::index
* @see app/Http/Controllers/Api/DataTableColumnController.php:15
* @route '/api/tables/{tableId}/columns'
*/
export const index = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/tables/{tableId}/columns',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::index
* @see app/Http/Controllers/Api/DataTableColumnController.php:15
* @route '/api/tables/{tableId}/columns'
*/
index.url = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tableId: args }
    }

    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
    }

    return index.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::index
* @see app/Http/Controllers/Api/DataTableColumnController.php:15
* @route '/api/tables/{tableId}/columns'
*/
index.get = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::index
* @see app/Http/Controllers/Api/DataTableColumnController.php:15
* @route '/api/tables/{tableId}/columns'
*/
index.head = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::store
* @see app/Http/Controllers/Api/DataTableColumnController.php:24
* @route '/api/tables/{tableId}/columns'
*/
export const store = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/tables/{tableId}/columns',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::store
* @see app/Http/Controllers/Api/DataTableColumnController.php:24
* @route '/api/tables/{tableId}/columns'
*/
store.url = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tableId: args }
    }

    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
    }

    return store.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::store
* @see app/Http/Controllers/Api/DataTableColumnController.php:24
* @route '/api/tables/{tableId}/columns'
*/
store.post = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::update
* @see app/Http/Controllers/Api/DataTableColumnController.php:46
* @route '/api/tables/{tableId}/columns/{columnId}'
*/
export const update = (args: { tableId: string | number, columnId: string | number } | [tableId: string | number, columnId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/tables/{tableId}/columns/{columnId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::update
* @see app/Http/Controllers/Api/DataTableColumnController.php:46
* @route '/api/tables/{tableId}/columns/{columnId}'
*/
update.url = (args: { tableId: string | number, columnId: string | number } | [tableId: string | number, columnId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            columnId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        columnId: args.columnId,
    }

    return update.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{columnId}', parsedArgs.columnId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::update
* @see app/Http/Controllers/Api/DataTableColumnController.php:46
* @route '/api/tables/{tableId}/columns/{columnId}'
*/
update.patch = (args: { tableId: string | number, columnId: string | number } | [tableId: string | number, columnId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::destroy
* @see app/Http/Controllers/Api/DataTableColumnController.php:76
* @route '/api/tables/{tableId}/columns/{columnId}'
*/
export const destroy = (args: { tableId: string | number, columnId: string | number } | [tableId: string | number, columnId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/tables/{tableId}/columns/{columnId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::destroy
* @see app/Http/Controllers/Api/DataTableColumnController.php:76
* @route '/api/tables/{tableId}/columns/{columnId}'
*/
destroy.url = (args: { tableId: string | number, columnId: string | number } | [tableId: string | number, columnId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            columnId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        columnId: args.columnId,
    }

    return destroy.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{columnId}', parsedArgs.columnId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::destroy
* @see app/Http/Controllers/Api/DataTableColumnController.php:76
* @route '/api/tables/{tableId}/columns/{columnId}'
*/
destroy.delete = (args: { tableId: string | number, columnId: string | number } | [tableId: string | number, columnId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::reorder
* @see app/Http/Controllers/Api/DataTableColumnController.php:87
* @route '/api/tables/{tableId}/columns/reorder'
*/
export const reorder = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(args, options),
    method: 'post',
})

reorder.definition = {
    methods: ["post"],
    url: '/api/tables/{tableId}/columns/reorder',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::reorder
* @see app/Http/Controllers/Api/DataTableColumnController.php:87
* @route '/api/tables/{tableId}/columns/reorder'
*/
reorder.url = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tableId: args }
    }

    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
    }

    return reorder.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableColumnController::reorder
* @see app/Http/Controllers/Api/DataTableColumnController.php:87
* @route '/api/tables/{tableId}/columns/reorder'
*/
reorder.post = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reorder.url(args, options),
    method: 'post',
})

const DataTableColumnController = { index, store, update, destroy, reorder }

export default DataTableColumnController