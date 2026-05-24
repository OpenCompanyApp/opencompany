import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::index
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:14
* @route '/api/workspace/members'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/workspace/members',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::index
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:14
* @route '/api/workspace/members'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::index
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:14
* @route '/api/workspace/members'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::index
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:14
* @route '/api/workspace/members'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::invite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:41
* @route '/api/workspace/members/invite'
*/
export const invite = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: invite.url(options),
    method: 'post',
})

invite.definition = {
    methods: ["post"],
    url: '/api/workspace/members/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::invite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:41
* @route '/api/workspace/members/invite'
*/
invite.url = (options?: RouteQueryOptions) => {
    return invite.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::invite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:41
* @route '/api/workspace/members/invite'
*/
invite.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: invite.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::updateRole
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:78
* @route '/api/workspace/members/{id}/role'
*/
export const updateRole = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateRole.url(args, options),
    method: 'patch',
})

updateRole.definition = {
    methods: ["patch"],
    url: '/api/workspace/members/{id}/role',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::updateRole
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:78
* @route '/api/workspace/members/{id}/role'
*/
updateRole.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateRole.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::updateRole
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:78
* @route '/api/workspace/members/{id}/role'
*/
updateRole.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateRole.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::remove
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:97
* @route '/api/workspace/members/{id}'
*/
export const remove = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: remove.url(args, options),
    method: 'delete',
})

remove.definition = {
    methods: ["delete"],
    url: '/api/workspace/members/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::remove
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:97
* @route '/api/workspace/members/{id}'
*/
remove.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return remove.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::remove
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:97
* @route '/api/workspace/members/{id}'
*/
remove.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: remove.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::resendInvite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:112
* @route '/api/workspace/invitations/{id}/resend'
*/
export const resendInvite = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

resendInvite.definition = {
    methods: ["post"],
    url: '/api/workspace/invitations/{id}/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::resendInvite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:112
* @route '/api/workspace/invitations/{id}/resend'
*/
resendInvite.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return resendInvite.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::resendInvite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:112
* @route '/api/workspace/invitations/{id}/resend'
*/
resendInvite.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::cancelInvite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:123
* @route '/api/workspace/invitations/{id}'
*/
export const cancelInvite = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

cancelInvite.definition = {
    methods: ["delete"],
    url: '/api/workspace/invitations/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::cancelInvite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:123
* @route '/api/workspace/invitations/{id}'
*/
cancelInvite.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return cancelInvite.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\WorkspaceMemberController::cancelInvite
* @see app/Http/Controllers/Api/WorkspaceMemberController.php:123
* @route '/api/workspace/invitations/{id}'
*/
cancelInvite.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

const WorkspaceMemberController = { index, invite, updateRole, remove, resendInvite, cancelInvite }

export default WorkspaceMemberController