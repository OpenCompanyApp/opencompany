import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DataTableRowController::index
* @see app/Http/Controllers/Api/DataTableRowController.php:18
* @route '/api/tables/{tableId}/rows'
*/
export const index = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/tables/{tableId}/rows',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::index
* @see app/Http/Controllers/Api/DataTableRowController.php:18
* @route '/api/tables/{tableId}/rows'
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
* @see \App\Http\Controllers\Api\DataTableRowController::index
* @see app/Http/Controllers/Api/DataTableRowController.php:18
* @route '/api/tables/{tableId}/rows'
*/
index.get = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::index
* @see app/Http/Controllers/Api/DataTableRowController.php:18
* @route '/api/tables/{tableId}/rows'
*/
index.head = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::store
* @see app/Http/Controllers/Api/DataTableRowController.php:49
* @route '/api/tables/{tableId}/rows'
*/
export const store = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/tables/{tableId}/rows',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::store
* @see app/Http/Controllers/Api/DataTableRowController.php:49
* @route '/api/tables/{tableId}/rows'
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
* @see \App\Http\Controllers\Api\DataTableRowController::store
* @see app/Http/Controllers/Api/DataTableRowController.php:49
* @route '/api/tables/{tableId}/rows'
*/
store.post = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::show
* @see app/Http/Controllers/Api/DataTableRowController.php:40
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
export const show = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/tables/{tableId}/rows/{rowId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::show
* @see app/Http/Controllers/Api/DataTableRowController.php:40
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
show.url = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            rowId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        rowId: args.rowId,
    }

    return show.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{rowId}', parsedArgs.rowId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableRowController::show
* @see app/Http/Controllers/Api/DataTableRowController.php:40
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
show.get = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::show
* @see app/Http/Controllers/Api/DataTableRowController.php:40
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
show.head = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::update
* @see app/Http/Controllers/Api/DataTableRowController.php:65
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
export const update = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/tables/{tableId}/rows/{rowId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::update
* @see app/Http/Controllers/Api/DataTableRowController.php:65
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
update.url = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            rowId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        rowId: args.rowId,
    }

    return update.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{rowId}', parsedArgs.rowId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableRowController::update
* @see app/Http/Controllers/Api/DataTableRowController.php:65
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
update.patch = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::destroy
* @see app/Http/Controllers/Api/DataTableRowController.php:84
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
export const destroy = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/tables/{tableId}/rows/{rowId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::destroy
* @see app/Http/Controllers/Api/DataTableRowController.php:84
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
destroy.url = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            rowId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        rowId: args.rowId,
    }

    return destroy.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{rowId}', parsedArgs.rowId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableRowController::destroy
* @see app/Http/Controllers/Api/DataTableRowController.php:84
* @route '/api/tables/{tableId}/rows/{rowId}'
*/
destroy.delete = (args: { tableId: string | number, rowId: string | number } | [tableId: string | number, rowId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::bulkCreate
* @see app/Http/Controllers/Api/DataTableRowController.php:98
* @route '/api/tables/{tableId}/rows/bulk'
*/
export const bulkCreate = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkCreate.url(args, options),
    method: 'post',
})

bulkCreate.definition = {
    methods: ["post"],
    url: '/api/tables/{tableId}/rows/bulk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::bulkCreate
* @see app/Http/Controllers/Api/DataTableRowController.php:98
* @route '/api/tables/{tableId}/rows/bulk'
*/
bulkCreate.url = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return bulkCreate.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableRowController::bulkCreate
* @see app/Http/Controllers/Api/DataTableRowController.php:98
* @route '/api/tables/{tableId}/rows/bulk'
*/
bulkCreate.post = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkCreate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DataTableRowController::bulkDelete
* @see app/Http/Controllers/Api/DataTableRowController.php:123
* @route '/api/tables/{tableId}/rows/bulk-delete'
*/
export const bulkDelete = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDelete.url(args, options),
    method: 'post',
})

bulkDelete.definition = {
    methods: ["post"],
    url: '/api/tables/{tableId}/rows/bulk-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DataTableRowController::bulkDelete
* @see app/Http/Controllers/Api/DataTableRowController.php:123
* @route '/api/tables/{tableId}/rows/bulk-delete'
*/
bulkDelete.url = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return bulkDelete.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableRowController::bulkDelete
* @see app/Http/Controllers/Api/DataTableRowController.php:123
* @route '/api/tables/{tableId}/rows/bulk-delete'
*/
bulkDelete.post = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDelete.url(args, options),
    method: 'post',
})

const DataTableRowController = { index, store, show, update, destroy, bulkCreate, bulkDelete }

export default DataTableRowController