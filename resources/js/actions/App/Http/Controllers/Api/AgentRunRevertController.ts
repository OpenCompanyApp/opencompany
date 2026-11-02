import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\AgentRunRevertController::__invoke
* @see app/Http/Controllers/Api/AgentRunRevertController.php:22
* @route '/api/agent-runs/{runId}/revert'
*/
const AgentRunRevertController = (args: { runId: string | number } | [runId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentRunRevertController.url(args, options),
    method: 'post',
})

AgentRunRevertController.definition = {
    methods: ["post"],
    url: '/api/agent-runs/{runId}/revert',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\AgentRunRevertController::__invoke
* @see app/Http/Controllers/Api/AgentRunRevertController.php:22
* @route '/api/agent-runs/{runId}/revert'
*/
AgentRunRevertController.url = (args: { runId: string | number } | [runId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { runId: args }
    }

    if (Array.isArray(args)) {
        args = {
            runId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        runId: args.runId,
    }

    return AgentRunRevertController.definition.url
            .replace('{runId}', parsedArgs.runId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\AgentRunRevertController::__invoke
* @see app/Http/Controllers/Api/AgentRunRevertController.php:22
* @route '/api/agent-runs/{runId}/revert'
*/
AgentRunRevertController.post = (args: { runId: string | number } | [runId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentRunRevertController.url(args, options),
    method: 'post',
})

export default AgentRunRevertController