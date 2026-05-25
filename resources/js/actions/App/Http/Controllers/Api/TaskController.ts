import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Api\TaskController::index
* @see app/Http/Controllers/Api/TaskController.php:27
* @route '/api/tasks'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/tasks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\TaskController::index
* @see app/Http/Controllers/Api/TaskController.php:27
* @route '/api/tasks'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::index
* @see app/Http/Controllers/Api/TaskController.php:27
* @route '/api/tasks'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\TaskController::index
* @see app/Http/Controllers/Api/TaskController.php:27
* @route '/api/tasks'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\TaskController::store
* @see app/Http/Controllers/Api/TaskController.php:37
* @route '/api/tasks'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/tasks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::store
* @see app/Http/Controllers/Api/TaskController.php:37
* @route '/api/tasks'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::store
* @see app/Http/Controllers/Api/TaskController.php:37
* @route '/api/tasks'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::show
* @see app/Http/Controllers/Api/TaskController.php:32
* @route '/api/tasks/{id}'
*/
export const show = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/tasks/{id}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\TaskController::show
* @see app/Http/Controllers/Api/TaskController.php:32
* @route '/api/tasks/{id}'
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
* @see \App\Http\Controllers\Api\TaskController::show
* @see app/Http/Controllers/Api/TaskController.php:32
* @route '/api/tasks/{id}'
*/
show.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\TaskController::show
* @see app/Http/Controllers/Api/TaskController.php:32
* @route '/api/tasks/{id}'
*/
show.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\TaskController::update
* @see app/Http/Controllers/Api/TaskController.php:42
* @route '/api/tasks/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/api/tasks/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\TaskController::update
* @see app/Http/Controllers/Api/TaskController.php:42
* @route '/api/tasks/{id}'
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
* @see \App\Http\Controllers\Api\TaskController::update
* @see app/Http/Controllers/Api/TaskController.php:42
* @route '/api/tasks/{id}'
*/
update.patch = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\TaskController::destroy
* @see app/Http/Controllers/Api/TaskController.php:47
* @route '/api/tasks/{id}'
*/
export const destroy = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/tasks/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Api\TaskController::destroy
* @see app/Http/Controllers/Api/TaskController.php:47
* @route '/api/tasks/{id}'
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
* @see \App\Http\Controllers\Api\TaskController::destroy
* @see app/Http/Controllers/Api/TaskController.php:47
* @route '/api/tasks/{id}'
*/
destroy.delete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Api\TaskController::start
* @see app/Http/Controllers/Api/TaskController.php:54
* @route '/api/tasks/{id}/start'
*/
export const start = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

start.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/start',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::start
* @see app/Http/Controllers/Api/TaskController.php:54
* @route '/api/tasks/{id}/start'
*/
start.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return start.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::start
* @see app/Http/Controllers/Api/TaskController.php:54
* @route '/api/tasks/{id}/start'
*/
start.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::pause
* @see app/Http/Controllers/Api/TaskController.php:59
* @route '/api/tasks/{id}/pause'
*/
export const pause = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

pause.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::pause
* @see app/Http/Controllers/Api/TaskController.php:59
* @route '/api/tasks/{id}/pause'
*/
pause.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return pause.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::pause
* @see app/Http/Controllers/Api/TaskController.php:59
* @route '/api/tasks/{id}/pause'
*/
pause.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pause.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::resume
* @see app/Http/Controllers/Api/TaskController.php:64
* @route '/api/tasks/{id}/resume'
*/
export const resume = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::resume
* @see app/Http/Controllers/Api/TaskController.php:64
* @route '/api/tasks/{id}/resume'
*/
resume.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return resume.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::resume
* @see app/Http/Controllers/Api/TaskController.php:64
* @route '/api/tasks/{id}/resume'
*/
resume.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::complete
* @see app/Http/Controllers/Api/TaskController.php:69
* @route '/api/tasks/{id}/complete'
*/
export const complete = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::complete
* @see app/Http/Controllers/Api/TaskController.php:69
* @route '/api/tasks/{id}/complete'
*/
complete.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return complete.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::complete
* @see app/Http/Controllers/Api/TaskController.php:69
* @route '/api/tasks/{id}/complete'
*/
complete.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::fail
* @see app/Http/Controllers/Api/TaskController.php:74
* @route '/api/tasks/{id}/fail'
*/
export const fail = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fail.url(args, options),
    method: 'post',
})

fail.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/fail',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::fail
* @see app/Http/Controllers/Api/TaskController.php:74
* @route '/api/tasks/{id}/fail'
*/
fail.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return fail.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::fail
* @see app/Http/Controllers/Api/TaskController.php:74
* @route '/api/tasks/{id}/fail'
*/
fail.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fail.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::cancel
* @see app/Http/Controllers/Api/TaskController.php:79
* @route '/api/tasks/{id}/cancel'
*/
export const cancel = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::cancel
* @see app/Http/Controllers/Api/TaskController.php:79
* @route '/api/tasks/{id}/cancel'
*/
cancel.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return cancel.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::cancel
* @see app/Http/Controllers/Api/TaskController.php:79
* @route '/api/tasks/{id}/cancel'
*/
cancel.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::steps
* @see app/Http/Controllers/Api/TaskController.php:87
* @route '/api/tasks/{id}/steps'
*/
export const steps = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: steps.url(args, options),
    method: 'get',
})

steps.definition = {
    methods: ["get","head"],
    url: '/api/tasks/{id}/steps',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Api\TaskController::steps
* @see app/Http/Controllers/Api/TaskController.php:87
* @route '/api/tasks/{id}/steps'
*/
steps.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return steps.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::steps
* @see app/Http/Controllers/Api/TaskController.php:87
* @route '/api/tasks/{id}/steps'
*/
steps.get = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: steps.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Api\TaskController::steps
* @see app/Http/Controllers/Api/TaskController.php:87
* @route '/api/tasks/{id}/steps'
*/
steps.head = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: steps.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Api\TaskController::addStep
* @see app/Http/Controllers/Api/TaskController.php:92
* @route '/api/tasks/{id}/steps'
*/
export const addStep = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addStep.url(args, options),
    method: 'post',
})

addStep.definition = {
    methods: ["post"],
    url: '/api/tasks/{id}/steps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::addStep
* @see app/Http/Controllers/Api/TaskController.php:92
* @route '/api/tasks/{id}/steps'
*/
addStep.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return addStep.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::addStep
* @see app/Http/Controllers/Api/TaskController.php:92
* @route '/api/tasks/{id}/steps'
*/
addStep.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: addStep.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Api\TaskController::updateStep
* @see app/Http/Controllers/Api/TaskController.php:97
* @route '/api/tasks/{taskId}/steps/{stepId}'
*/
export const updateStep = (args: { taskId: string | number, stepId: string | number } | [taskId: string | number, stepId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateStep.url(args, options),
    method: 'patch',
})

updateStep.definition = {
    methods: ["patch"],
    url: '/api/tasks/{taskId}/steps/{stepId}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Api\TaskController::updateStep
* @see app/Http/Controllers/Api/TaskController.php:97
* @route '/api/tasks/{taskId}/steps/{stepId}'
*/
updateStep.url = (args: { taskId: string | number, stepId: string | number } | [taskId: string | number, stepId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            taskId: args[0],
            stepId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        taskId: args.taskId,
        stepId: args.stepId,
    }

    return updateStep.definition.url
            .replace('{taskId}', parsedArgs.taskId.toString())
            .replace('{stepId}', parsedArgs.stepId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::updateStep
* @see app/Http/Controllers/Api/TaskController.php:97
* @route '/api/tasks/{taskId}/steps/{stepId}'
*/
updateStep.patch = (args: { taskId: string | number, stepId: string | number } | [taskId: string | number, stepId: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateStep.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Api\TaskController::completeStep
* @see app/Http/Controllers/Api/TaskController.php:102
* @route '/api/tasks/{taskId}/steps/{stepId}/complete'
*/
export const completeStep = (args: { taskId: string | number, stepId: string | number } | [taskId: string | number, stepId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: completeStep.url(args, options),
    method: 'post',
})

completeStep.definition = {
    methods: ["post"],
    url: '/api/tasks/{taskId}/steps/{stepId}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Api\TaskController::completeStep
* @see app/Http/Controllers/Api/TaskController.php:102
* @route '/api/tasks/{taskId}/steps/{stepId}/complete'
*/
completeStep.url = (args: { taskId: string | number, stepId: string | number } | [taskId: string | number, stepId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            taskId: args[0],
            stepId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        taskId: args.taskId,
        stepId: args.stepId,
    }

    return completeStep.definition.url
            .replace('{taskId}', parsedArgs.taskId.toString())
            .replace('{stepId}', parsedArgs.stepId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Api\TaskController::completeStep
* @see app/Http/Controllers/Api/TaskController.php:102
* @route '/api/tasks/{taskId}/steps/{stepId}/complete'
*/
completeStep.post = (args: { taskId: string | number, stepId: string | number } | [taskId: string | number, stepId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: completeStep.url(args, options),
    method: 'post',
})

const TaskController = { index, store, show, update, destroy, start, pause, resume, complete, fail, cancel, steps, addStep, updateStep, completeStep }

export default TaskController