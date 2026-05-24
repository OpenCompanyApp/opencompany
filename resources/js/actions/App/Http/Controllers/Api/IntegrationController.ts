import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\IntegrationController::index
* @see app/Http/Controllers/Api/IntegrationController.php:36
* @route '/api/integrations'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/integrations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::index
* @see app/Http/Controllers/Api/IntegrationController.php:36
* @route '/api/integrations'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::index
* @see app/Http/Controllers/Api/IntegrationController.php:36
* @route '/api/integrations'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::index
* @see app/Http/Controllers/Api/IntegrationController.php:36
* @route '/api/integrations'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::enabledModels
* @see app/Http/Controllers/Api/IntegrationController.php:309
* @route '/api/integrations/models'
*/
export const enabledModels = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: enabledModels.url(options),
    method: 'get',
})

enabledModels.definition = {
    methods: ["get","head"],
    url: '/api/integrations/models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::enabledModels
* @see app/Http/Controllers/Api/IntegrationController.php:309
* @route '/api/integrations/models'
*/
enabledModels.url = (options?: RouteQueryOptions) => {
    return enabledModels.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::enabledModels
* @see app/Http/Controllers/Api/IntegrationController.php:309
* @route '/api/integrations/models'
*/
enabledModels.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: enabledModels.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::enabledModels
* @see app/Http/Controllers/Api/IntegrationController.php:309
* @route '/api/integrations/models'
*/
enabledModels.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: enabledModels.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::allProviders
* @see app/Http/Controllers/Api/IntegrationController.php:320
* @route '/api/integrations/all-providers'
*/
export const allProviders = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: allProviders.url(options),
    method: 'get',
})

allProviders.definition = {
    methods: ["get","head"],
    url: '/api/integrations/all-providers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::allProviders
* @see app/Http/Controllers/Api/IntegrationController.php:320
* @route '/api/integrations/all-providers'
*/
allProviders.url = (options?: RouteQueryOptions) => {
    return allProviders.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::allProviders
* @see app/Http/Controllers/Api/IntegrationController.php:320
* @route '/api/integrations/all-providers'
*/
allProviders.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: allProviders.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::allProviders
* @see app/Http/Controllers/Api/IntegrationController.php:320
* @route '/api/integrations/all-providers'
*/
allProviders.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: allProviders.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::embeddingModels
* @see app/Http/Controllers/Api/IntegrationController.php:328
* @route '/api/integrations/embedding-models'
*/
export const embeddingModels = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: embeddingModels.url(options),
    method: 'get',
})

embeddingModels.definition = {
    methods: ["get","head"],
    url: '/api/integrations/embedding-models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::embeddingModels
* @see app/Http/Controllers/Api/IntegrationController.php:328
* @route '/api/integrations/embedding-models'
*/
embeddingModels.url = (options?: RouteQueryOptions) => {
    return embeddingModels.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::embeddingModels
* @see app/Http/Controllers/Api/IntegrationController.php:328
* @route '/api/integrations/embedding-models'
*/
embeddingModels.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: embeddingModels.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::embeddingModels
* @see app/Http/Controllers/Api/IntegrationController.php:328
* @route '/api/integrations/embedding-models'
*/
embeddingModels.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: embeddingModels.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::rerankingModels
* @see app/Http/Controllers/Api/IntegrationController.php:336
* @route '/api/integrations/reranking-models'
*/
export const rerankingModels = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: rerankingModels.url(options),
    method: 'get',
})

rerankingModels.definition = {
    methods: ["get","head"],
    url: '/api/integrations/reranking-models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::rerankingModels
* @see app/Http/Controllers/Api/IntegrationController.php:336
* @route '/api/integrations/reranking-models'
*/
rerankingModels.url = (options?: RouteQueryOptions) => {
    return rerankingModels.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::rerankingModels
* @see app/Http/Controllers/Api/IntegrationController.php:336
* @route '/api/integrations/reranking-models'
*/
rerankingModels.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: rerankingModels.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::rerankingModels
* @see app/Http/Controllers/Api/IntegrationController.php:336
* @route '/api/integrations/reranking-models'
*/
rerankingModels.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: rerankingModels.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::showAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:92
* @route '/api/ai/providers/{id}/config'
*/
export const showAiProviderConfig = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showAiProviderConfig.url(args, options),
    method: 'get',
})

showAiProviderConfig.definition = {
    methods: ["get","head"],
    url: '/api/ai/providers/{id}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::showAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:92
* @route '/api/ai/providers/{id}/config'
*/
showAiProviderConfig.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return showAiProviderConfig.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::showAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:92
* @route '/api/ai/providers/{id}/config'
*/
showAiProviderConfig.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showAiProviderConfig.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::showAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:92
* @route '/api/ai/providers/{id}/config'
*/
showAiProviderConfig.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showAiProviderConfig.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:109
* @route '/api/ai/providers/{id}/config'
*/
export const updateAiProviderConfig = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateAiProviderConfig.url(args, options),
    method: 'put',
})

updateAiProviderConfig.definition = {
    methods: ["put"],
    url: '/api/ai/providers/{id}/config',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:109
* @route '/api/ai/providers/{id}/config'
*/
updateAiProviderConfig.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateAiProviderConfig.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateAiProviderConfig
* @see app/Http/Controllers/Api/IntegrationController.php:109
* @route '/api/ai/providers/{id}/config'
*/
updateAiProviderConfig.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateAiProviderConfig.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::testAiProviderConnection
* @see app/Http/Controllers/Api/IntegrationController.php:148
* @route '/api/ai/providers/{id}/test'
*/
export const testAiProviderConnection = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testAiProviderConnection.url(args, options),
    method: 'post',
})

testAiProviderConnection.definition = {
    methods: ["post"],
    url: '/api/ai/providers/{id}/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::testAiProviderConnection
* @see app/Http/Controllers/Api/IntegrationController.php:148
* @route '/api/ai/providers/{id}/test'
*/
testAiProviderConnection.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return testAiProviderConnection.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::testAiProviderConnection
* @see app/Http/Controllers/Api/IntegrationController.php:148
* @route '/api/ai/providers/{id}/test'
*/
testAiProviderConnection.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testAiProviderConnection.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::fetchModels
* @see app/Http/Controllers/Api/IntegrationController.php:362
* @route '/api/ai/providers/{id}/fetch-models'
*/
const fetchModels48ccd281c1ab96a10992ce5add21fba3 = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchModels48ccd281c1ab96a10992ce5add21fba3.url(args, options),
    method: 'post',
})

fetchModels48ccd281c1ab96a10992ce5add21fba3.definition = {
    methods: ["post"],
    url: '/api/ai/providers/{id}/fetch-models',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::fetchModels
* @see app/Http/Controllers/Api/IntegrationController.php:362
* @route '/api/ai/providers/{id}/fetch-models'
*/
fetchModels48ccd281c1ab96a10992ce5add21fba3.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return fetchModels48ccd281c1ab96a10992ce5add21fba3.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::fetchModels
* @see app/Http/Controllers/Api/IntegrationController.php:362
* @route '/api/ai/providers/{id}/fetch-models'
*/
fetchModels48ccd281c1ab96a10992ce5add21fba3.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchModels48ccd281c1ab96a10992ce5add21fba3.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::fetchModels
* @see app/Http/Controllers/Api/IntegrationController.php:362
* @route '/api/integrations/{id}/fetch-models'
*/
const fetchModels6d99e0501fcbf1902765d26b2ad9836d = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchModels6d99e0501fcbf1902765d26b2ad9836d.url(args, options),
    method: 'post',
})

fetchModels6d99e0501fcbf1902765d26b2ad9836d.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/fetch-models',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::fetchModels
* @see app/Http/Controllers/Api/IntegrationController.php:362
* @route '/api/integrations/{id}/fetch-models'
*/
fetchModels6d99e0501fcbf1902765d26b2ad9836d.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return fetchModels6d99e0501fcbf1902765d26b2ad9836d.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::fetchModels
* @see app/Http/Controllers/Api/IntegrationController.php:362
* @route '/api/integrations/{id}/fetch-models'
*/
fetchModels6d99e0501fcbf1902765d26b2ad9836d.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchModels6d99e0501fcbf1902765d26b2ad9836d.url(args, options),
    method: 'post',
})

/**
* Multiple routes resolve to \App\Http\Controllers\Api\IntegrationController::fetchModels, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `fetchModels['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
export const fetchModels = {
    '/api/ai/providers/{id}/fetch-models': fetchModels48ccd281c1ab96a10992ce5add21fba3,
    '/api/integrations/{id}/fetch-models': fetchModels6d99e0501fcbf1902765d26b2ad9836d,
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::externalIdentities
* @see app/Http/Controllers/Api/IntegrationController.php:301
* @route '/api/integrations/external-identities'
*/
export const externalIdentities = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: externalIdentities.url(options),
    method: 'get',
})

externalIdentities.definition = {
    methods: ["get","head"],
    url: '/api/integrations/external-identities',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::externalIdentities
* @see app/Http/Controllers/Api/IntegrationController.php:301
* @route '/api/integrations/external-identities'
*/
externalIdentities.url = (options?: RouteQueryOptions) => {
    return externalIdentities.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::externalIdentities
* @see app/Http/Controllers/Api/IntegrationController.php:301
* @route '/api/integrations/external-identities'
*/
externalIdentities.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: externalIdentities.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::externalIdentities
* @see app/Http/Controllers/Api/IntegrationController.php:301
* @route '/api/integrations/external-identities'
*/
externalIdentities.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: externalIdentities.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::linkExternalUser
* @see app/Http/Controllers/Api/IntegrationController.php:259
* @route '/api/integrations/link-user'
*/
export const linkExternalUser = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: linkExternalUser.url(options),
    method: 'post',
})

linkExternalUser.definition = {
    methods: ["post"],
    url: '/api/integrations/link-user',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::linkExternalUser
* @see app/Http/Controllers/Api/IntegrationController.php:259
* @route '/api/integrations/link-user'
*/
linkExternalUser.url = (options?: RouteQueryOptions) => {
    return linkExternalUser.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::linkExternalUser
* @see app/Http/Controllers/Api/IntegrationController.php:259
* @route '/api/integrations/link-user'
*/
linkExternalUser.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: linkExternalUser.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::unlinkExternalUser
* @see app/Http/Controllers/Api/IntegrationController.php:291
* @route '/api/integrations/link-user/{identityId}'
*/
export const unlinkExternalUser = (args: { identityId: string | number } | [identityId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: unlinkExternalUser.url(args, options),
    method: 'delete',
})

unlinkExternalUser.definition = {
    methods: ["delete"],
    url: '/api/integrations/link-user/{identityId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::unlinkExternalUser
* @see app/Http/Controllers/Api/IntegrationController.php:291
* @route '/api/integrations/link-user/{identityId}'
*/
unlinkExternalUser.url = (args: { identityId: string | number } | [identityId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { identityId: args }
    }

    if (Array.isArray(args)) {
        args = {
            identityId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        identityId: args.identityId,
    }

    return unlinkExternalUser.definition.url
            .replace('{identityId}', parsedArgs.identityId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::unlinkExternalUser
* @see app/Http/Controllers/Api/IntegrationController.php:291
* @route '/api/integrations/link-user/{identityId}'
*/
unlinkExternalUser.delete = (args: { identityId: string | number } | [identityId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: unlinkExternalUser.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::showConfig
* @see app/Http/Controllers/Api/IntegrationController.php:44
* @route '/api/integrations/{id}/config'
*/
export const showConfig = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showConfig.url(args, options),
    method: 'get',
})

showConfig.definition = {
    methods: ["get","head"],
    url: '/api/integrations/{id}/config',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::showConfig
* @see app/Http/Controllers/Api/IntegrationController.php:44
* @route '/api/integrations/{id}/config'
*/
showConfig.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return showConfig.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::showConfig
* @see app/Http/Controllers/Api/IntegrationController.php:44
* @route '/api/integrations/{id}/config'
*/
showConfig.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showConfig.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::showConfig
* @see app/Http/Controllers/Api/IntegrationController.php:44
* @route '/api/integrations/{id}/config'
*/
showConfig.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showConfig.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateConfig
* @see app/Http/Controllers/Api/IntegrationController.php:61
* @route '/api/integrations/{id}/config'
*/
export const updateConfig = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateConfig.url(args, options),
    method: 'put',
})

updateConfig.definition = {
    methods: ["put"],
    url: '/api/integrations/{id}/config',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateConfig
* @see app/Http/Controllers/Api/IntegrationController.php:61
* @route '/api/integrations/{id}/config'
*/
updateConfig.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return updateConfig.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateConfig
* @see app/Http/Controllers/Api/IntegrationController.php:61
* @route '/api/integrations/{id}/config'
*/
updateConfig.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateConfig.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::toggle
* @see app/Http/Controllers/Api/IntegrationController.php:123
* @route '/api/integrations/{id}/toggle'
*/
export const toggle = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: toggle.url(args, options),
    method: 'post',
})

toggle.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/toggle',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::toggle
* @see app/Http/Controllers/Api/IntegrationController.php:123
* @route '/api/integrations/{id}/toggle'
*/
toggle.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return toggle.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::toggle
* @see app/Http/Controllers/Api/IntegrationController.php:123
* @route '/api/integrations/{id}/toggle'
*/
toggle.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: toggle.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::testConnection
* @see app/Http/Controllers/Api/IntegrationController.php:137
* @route '/api/integrations/{id}/test'
*/
export const testConnection = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testConnection.url(args, options),
    method: 'post',
})

testConnection.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/test',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::testConnection
* @see app/Http/Controllers/Api/IntegrationController.php:137
* @route '/api/integrations/{id}/test'
*/
testConnection.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return testConnection.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::testConnection
* @see app/Http/Controllers/Api/IntegrationController.php:137
* @route '/api/integrations/{id}/test'
*/
testConnection.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testConnection.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::disconnect
* @see app/Http/Controllers/Api/IntegrationController.php:158
* @route '/api/integrations/{id}/disconnect'
*/
export const disconnect = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disconnect.url(args, options),
    method: 'post',
})

disconnect.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/disconnect',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::disconnect
* @see app/Http/Controllers/Api/IntegrationController.php:158
* @route '/api/integrations/{id}/disconnect'
*/
disconnect.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return disconnect.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::disconnect
* @see app/Http/Controllers/Api/IntegrationController.php:158
* @route '/api/integrations/{id}/disconnect'
*/
disconnect.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disconnect.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::setupWebhook
* @see app/Http/Controllers/Api/IntegrationController.php:175
* @route '/api/integrations/{id}/setup-webhook'
*/
export const setupWebhook = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setupWebhook.url(args, options),
    method: 'post',
})

setupWebhook.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/setup-webhook',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::setupWebhook
* @see app/Http/Controllers/Api/IntegrationController.php:175
* @route '/api/integrations/{id}/setup-webhook'
*/
setupWebhook.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return setupWebhook.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::setupWebhook
* @see app/Http/Controllers/Api/IntegrationController.php:175
* @route '/api/integrations/{id}/setup-webhook'
*/
setupWebhook.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setupWebhook.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::listAccounts
* @see app/Http/Controllers/Api/IntegrationController.php:403
* @route '/api/integrations/{id}/accounts'
*/
export const listAccounts = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listAccounts.url(args, options),
    method: 'get',
})

listAccounts.definition = {
    methods: ["get","head"],
    url: '/api/integrations/{id}/accounts',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::listAccounts
* @see app/Http/Controllers/Api/IntegrationController.php:403
* @route '/api/integrations/{id}/accounts'
*/
listAccounts.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return listAccounts.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::listAccounts
* @see app/Http/Controllers/Api/IntegrationController.php:403
* @route '/api/integrations/{id}/accounts'
*/
listAccounts.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: listAccounts.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::listAccounts
* @see app/Http/Controllers/Api/IntegrationController.php:403
* @route '/api/integrations/{id}/accounts'
*/
listAccounts.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: listAccounts.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::createAccount
* @see app/Http/Controllers/Api/IntegrationController.php:411
* @route '/api/integrations/{id}/accounts'
*/
export const createAccount = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createAccount.url(args, options),
    method: 'post',
})

createAccount.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/accounts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::createAccount
* @see app/Http/Controllers/Api/IntegrationController.php:411
* @route '/api/integrations/{id}/accounts'
*/
createAccount.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return createAccount.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::createAccount
* @see app/Http/Controllers/Api/IntegrationController.php:411
* @route '/api/integrations/{id}/accounts'
*/
createAccount.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createAccount.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateAccount
* @see app/Http/Controllers/Api/IntegrationController.php:440
* @route '/api/integrations/{id}/accounts/{alias}'
*/
export const updateAccount = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateAccount.url(args, options),
    method: 'put',
})

updateAccount.definition = {
    methods: ["put"],
    url: '/api/integrations/{id}/accounts/{alias}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateAccount
* @see app/Http/Controllers/Api/IntegrationController.php:440
* @route '/api/integrations/{id}/accounts/{alias}'
*/
updateAccount.url = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            id: args[0],
            alias: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
        alias: args.alias,
    }

    return updateAccount.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{alias}', parsedArgs.alias.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::updateAccount
* @see app/Http/Controllers/Api/IntegrationController.php:440
* @route '/api/integrations/{id}/accounts/{alias}'
*/
updateAccount.put = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateAccount.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::deleteAccount
* @see app/Http/Controllers/Api/IntegrationController.php:452
* @route '/api/integrations/{id}/accounts/{alias}'
*/
export const deleteAccount = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteAccount.url(args, options),
    method: 'delete',
})

deleteAccount.definition = {
    methods: ["delete"],
    url: '/api/integrations/{id}/accounts/{alias}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::deleteAccount
* @see app/Http/Controllers/Api/IntegrationController.php:452
* @route '/api/integrations/{id}/accounts/{alias}'
*/
deleteAccount.url = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            id: args[0],
            alias: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
        alias: args.alias,
    }

    return deleteAccount.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{alias}', parsedArgs.alias.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::deleteAccount
* @see app/Http/Controllers/Api/IntegrationController.php:452
* @route '/api/integrations/{id}/accounts/{alias}'
*/
deleteAccount.delete = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteAccount.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::setDefaultAccount
* @see app/Http/Controllers/Api/IntegrationController.php:469
* @route '/api/integrations/{id}/accounts/{alias}/default'
*/
export const setDefaultAccount = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setDefaultAccount.url(args, options),
    method: 'post',
})

setDefaultAccount.definition = {
    methods: ["post"],
    url: '/api/integrations/{id}/accounts/{alias}/default',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::setDefaultAccount
* @see app/Http/Controllers/Api/IntegrationController.php:469
* @route '/api/integrations/{id}/accounts/{alias}/default'
*/
setDefaultAccount.url = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            id: args[0],
            alias: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
        alias: args.alias,
    }

    return setDefaultAccount.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace('{alias}', parsedArgs.alias.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::setDefaultAccount
* @see app/Http/Controllers/Api/IntegrationController.php:469
* @route '/api/integrations/{id}/accounts/{alias}/default'
*/
setDefaultAccount.post = (args: { id: string | number, alias: string | number } | [id: string | number, alias: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: setDefaultAccount.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaModelStatus
* @see app/Http/Controllers/Api/IntegrationController.php:344
* @route '/api/integrations/ollama/status'
*/
export const ollamaModelStatus = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ollamaModelStatus.url(options),
    method: 'get',
})

ollamaModelStatus.definition = {
    methods: ["get","head"],
    url: '/api/integrations/ollama/status',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaModelStatus
* @see app/Http/Controllers/Api/IntegrationController.php:344
* @route '/api/integrations/ollama/status'
*/
ollamaModelStatus.url = (options?: RouteQueryOptions) => {
    return ollamaModelStatus.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaModelStatus
* @see app/Http/Controllers/Api/IntegrationController.php:344
* @route '/api/integrations/ollama/status'
*/
ollamaModelStatus.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ollamaModelStatus.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaModelStatus
* @see app/Http/Controllers/Api/IntegrationController.php:344
* @route '/api/integrations/ollama/status'
*/
ollamaModelStatus.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ollamaModelStatus.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaPullModel
* @see app/Http/Controllers/Api/IntegrationController.php:352
* @route '/api/integrations/ollama/pull'
*/
export const ollamaPullModel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ollamaPullModel.url(options),
    method: 'post',
})

ollamaPullModel.definition = {
    methods: ["post"],
    url: '/api/integrations/ollama/pull',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaPullModel
* @see app/Http/Controllers/Api/IntegrationController.php:352
* @route '/api/integrations/ollama/pull'
*/
ollamaPullModel.url = (options?: RouteQueryOptions) => {
    return ollamaPullModel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\IntegrationController::ollamaPullModel
* @see app/Http/Controllers/Api/IntegrationController.php:352
* @route '/api/integrations/ollama/pull'
*/
ollamaPullModel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ollamaPullModel.url(options),
    method: 'post',
})

const IntegrationController = { index, enabledModels, allProviders, embeddingModels, rerankingModels, showAiProviderConfig, updateAiProviderConfig, testAiProviderConnection, fetchModels, externalIdentities, linkExternalUser, unlinkExternalUser, showConfig, updateConfig, toggle, testConnection, disconnect, setupWebhook, listAccounts, createAccount, updateAccount, deleteAccount, setDefaultAccount, ollamaModelStatus, ollamaPullModel }

export default IntegrationController