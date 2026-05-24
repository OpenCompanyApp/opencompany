import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ProfileController::edit
* @see app/Http/Controllers/ProfileController.php:19
* @route '/w/{workspace_slug}/profile'
*/
export const edit = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/w/{workspace_slug}/profile',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ProfileController::edit
* @see app/Http/Controllers/ProfileController.php:19
* @route '/w/{workspace_slug}/profile'
*/
edit.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { workspace_slug: args }
    }

    if (Array.isArray(args)) {
        args = {
            workspace_slug: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        workspace_slug: args.workspace_slug,
    }

    return edit.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileController::edit
* @see app/Http/Controllers/ProfileController.php:19
* @route '/w/{workspace_slug}/profile'
*/
edit.get = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ProfileController::edit
* @see app/Http/Controllers/ProfileController.php:19
* @route '/w/{workspace_slug}/profile'
*/
edit.head = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ProfileController::update
* @see app/Http/Controllers/ProfileController.php:30
* @route '/w/{workspace_slug}/profile'
*/
export const update = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/w/{workspace_slug}/profile',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ProfileController::update
* @see app/Http/Controllers/ProfileController.php:30
* @route '/w/{workspace_slug}/profile'
*/
update.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { workspace_slug: args }
    }

    if (Array.isArray(args)) {
        args = {
            workspace_slug: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        workspace_slug: args.workspace_slug,
    }

    return update.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileController::update
* @see app/Http/Controllers/ProfileController.php:30
* @route '/w/{workspace_slug}/profile'
*/
update.patch = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\ProfileController::destroy
* @see app/Http/Controllers/ProfileController.php:55
* @route '/w/{workspace_slug}/profile'
*/
export const destroy = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/w/{workspace_slug}/profile',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ProfileController::destroy
* @see app/Http/Controllers/ProfileController.php:55
* @route '/w/{workspace_slug}/profile'
*/
destroy.url = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { workspace_slug: args }
    }

    if (Array.isArray(args)) {
        args = {
            workspace_slug: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        workspace_slug: args.workspace_slug,
    }

    return destroy.definition.url
            .replace('{workspace_slug}', parsedArgs.workspace_slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ProfileController::destroy
* @see app/Http/Controllers/ProfileController.php:55
* @route '/w/{workspace_slug}/profile'
*/
destroy.delete = (args: { workspace_slug: string | number } | [workspace_slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const ProfileController = { edit, update, destroy }

export default ProfileController