import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\DataTableViewController::index
* @see app/Http/Controllers/Api/DataTableViewController.php:15
* @route '/api/tables/{tableId}/views'
*/
export const index = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/tables/{tableId}/views',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\DataTableViewController::index
* @see app/Http/Controllers/Api/DataTableViewController.php:15
* @route '/api/tables/{tableId}/views'
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
* @see \App\Http\Controllers\Api\DataTableViewController::index
* @see app/Http/Controllers/Api/DataTableViewController.php:15
* @route '/api/tables/{tableId}/views'
*/
index.get = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\DataTableViewController::index
* @see app/Http/Controllers/Api/DataTableViewController.php:15
* @route '/api/tables/{tableId}/views'
*/
index.head = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\DataTableViewController::store
* @see app/Http/Controllers/Api/DataTableViewController.php:24
* @route '/api/tables/{tableId}/views'
*/
export const store = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/tables/{tableId}/views',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\DataTableViewController::store
* @see app/Http/Controllers/Api/DataTableViewController.php:24
* @route '/api/tables/{tableId}/views'
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
* @see \App\Http\Controllers\Api\DataTableViewController::store
* @see app/Http/Controllers/Api/DataTableViewController.php:24
* @route '/api/tables/{tableId}/views'
*/
store.post = (args: { tableId: string | number } | [tableId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\DataTableViewController::update
* @see app/Http/Controllers/Api/DataTableViewController.php:45
* @route '/api/tables/{tableId}/views/{viewId}'
*/
export const update = (args: { tableId: string | number, viewId: string | number } | [tableId: string | number, viewId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/tables/{tableId}/views/{viewId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\DataTableViewController::update
* @see app/Http/Controllers/Api/DataTableViewController.php:45
* @route '/api/tables/{tableId}/views/{viewId}'
*/
update.url = (args: { tableId: string | number, viewId: string | number } | [tableId: string | number, viewId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            viewId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        viewId: args.viewId,
    }

    return update.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{viewId}', parsedArgs.viewId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableViewController::update
* @see app/Http/Controllers/Api/DataTableViewController.php:45
* @route '/api/tables/{tableId}/views/{viewId}'
*/
update.patch = (args: { tableId: string | number, viewId: string | number } | [tableId: string | number, viewId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\DataTableViewController::destroy
* @see app/Http/Controllers/Api/DataTableViewController.php:78
* @route '/api/tables/{tableId}/views/{viewId}'
*/
export const destroy = (args: { tableId: string | number, viewId: string | number } | [tableId: string | number, viewId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/tables/{tableId}/views/{viewId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\DataTableViewController::destroy
* @see app/Http/Controllers/Api/DataTableViewController.php:78
* @route '/api/tables/{tableId}/views/{viewId}'
*/
destroy.url = (args: { tableId: string | number, viewId: string | number } | [tableId: string | number, viewId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tableId: args[0],
            viewId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tableId: args.tableId,
        viewId: args.viewId,
    }

    return destroy.definition.url
            .replace('{tableId}', parsedArgs.tableId.toString())
            .replace('{viewId}', parsedArgs.viewId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\DataTableViewController::destroy
* @see app/Http/Controllers/Api/DataTableViewController.php:78
* @route '/api/tables/{tableId}/views/{viewId}'
*/
destroy.delete = (args: { tableId: string | number, viewId: string | number } | [tableId: string | number, viewId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const DataTableViewController = { index, store, update, destroy }

export default DataTableViewController