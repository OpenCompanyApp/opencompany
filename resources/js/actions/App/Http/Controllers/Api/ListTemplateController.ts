import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\ListTemplateController::index
* @see app/Http/Controllers/Api/ListTemplateController.php:16
* @route '/api/list-templates'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/list-templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\ListTemplateController::index
* @see app/Http/Controllers/Api/ListTemplateController.php:16
* @route '/api/list-templates'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListTemplateController::index
* @see app/Http/Controllers/Api/ListTemplateController.php:16
* @route '/api/list-templates'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\ListTemplateController::index
* @see app/Http/Controllers/Api/ListTemplateController.php:16
* @route '/api/list-templates'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\ListTemplateController::store
* @see app/Http/Controllers/Api/ListTemplateController.php:27
* @route '/api/list-templates'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/list-templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ListTemplateController::store
* @see app/Http/Controllers/Api/ListTemplateController.php:27
* @route '/api/list-templates'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListTemplateController::store
* @see app/Http/Controllers/Api/ListTemplateController.php:27
* @route '/api/list-templates'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\ListTemplateController::update
* @see app/Http/Controllers/Api/ListTemplateController.php:47
* @route '/api/list-templates/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/list-templates/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\ListTemplateController::update
* @see app/Http/Controllers/Api/ListTemplateController.php:47
* @route '/api/list-templates/{id}'
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
* @see \App\Http\Controllers\Api\ListTemplateController::update
* @see app/Http/Controllers/Api/ListTemplateController.php:47
* @route '/api/list-templates/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\ListTemplateController::destroy
* @see app/Http/Controllers/Api/ListTemplateController.php:82
* @route '/api/list-templates/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/list-templates/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\ListTemplateController::destroy
* @see app/Http/Controllers/Api/ListTemplateController.php:82
* @route '/api/list-templates/{id}'
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
* @see \App\Http\Controllers\Api\ListTemplateController::destroy
* @see app/Http/Controllers/Api/ListTemplateController.php:82
* @route '/api/list-templates/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\ListTemplateController::createListItem
* @see app/Http/Controllers/Api/ListTemplateController.php:89
* @route '/api/list-templates/{id}/create-item'
*/
export const createListItem = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createListItem.url(args, options),
    method: 'post',
})

createListItem.definition = {
    methods: ["post"],
    url: '/api/list-templates/{id}/create-item',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\ListTemplateController::createListItem
* @see app/Http/Controllers/Api/ListTemplateController.php:89
* @route '/api/list-templates/{id}/create-item'
*/
createListItem.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return createListItem.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\ListTemplateController::createListItem
* @see app/Http/Controllers/Api/ListTemplateController.php:89
* @route '/api/list-templates/{id}/create-item'
*/
createListItem.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createListItem.url(args, options),
    method: 'post',
})

const ListTemplateController = { index, store, update, destroy, createListItem }

export default ListTemplateController