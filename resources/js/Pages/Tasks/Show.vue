<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-4xl mx-auto p-6">
      <!-- Back button -->
      <button
        type="button"
        class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white mb-6 transition-colors"
        @click="goBack"
      >
        <Icon name="ph:arrow-left" class="w-4 h-4" />
        Back
      </button>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <SharedSkeleton custom-class="h-20 rounded-xl" />
        <div class="grid grid-cols-4 gap-3">
          <SharedSkeleton v-for="i in 4" :key="i" custom-class="h-20 rounded-lg" />
        </div>
        <SharedSkeleton custom-class="h-32 rounded-xl" />
        <SharedSkeleton custom-class="h-64 rounded-xl" />
      </div>

      <template v-else-if="task">
        <!-- Header: Title + Badges -->
        <div class="mb-6">
          <div class="flex items-center gap-2 mb-2 flex-wrap">
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full',
                typeClasses[task.type]
              ]"
            >
              <Icon :name="typeIcons[task.type]" class="w-3 h-3" />
              {{ typeLabels[task.type] }}
            </span>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full',
                statusClasses[task.status]
              ]"
            >
              <span :class="['w-1.5 h-1.5 rounded-full', task.status === 'active' ? 'animate-pulse' : '', statusDots[task.status]]" />
              {{ statusLabels[task.status] }}
            </span>
            <span
              v-if="task.source"
              class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300"
            >
              <Icon :name="sourceIcons[task.source] || 'ph:hand'" class="w-3 h-3" />
              {{ task.source }}
            </span>
          </div>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">
            {{ task.title }}
          </h1>
        </div>

        <!-- Summary Stats Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
          <div class="p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Duration</div>
            <div class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums font-mono">
              {{ formattedDuration }}
            </div>
          </div>
          <div class="p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Input Tokens</div>
            <div class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums font-mono">
              {{ task.result?.prompt_tokens?.toLocaleString() ?? '---' }}
            </div>
            <div v-if="task.result?.total_prompt_tokens && task.result.total_prompt_tokens !== task.result.prompt_tokens" class="text-xs text-neutral-400 mt-0.5">
              {{ task.result.total_prompt_tokens.toLocaleString() }} total
            </div>
            <div v-else-if="task.result?.cache_read_tokens" class="text-xs text-neutral-400 mt-0.5">
              {{ task.result.cache_read_tokens.toLocaleString() }} cached
            </div>
          </div>
          <div class="p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Output Tokens</div>
            <div class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums font-mono">
              {{ task.result?.completion_tokens?.toLocaleString() ?? '---' }}
            </div>
            <div v-if="task.result?.total_completion_tokens && task.result.total_completion_tokens !== task.result.completion_tokens" class="text-xs text-neutral-400 mt-0.5">
              {{ task.result.total_completion_tokens.toLocaleString() }} total
            </div>
          </div>
          <div class="p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Tool Calls</div>
            <div class="text-lg font-semibold text-neutral-900 dark:text-white tabular-nums font-mono">
              {{ task.result?.tool_calls_count || toolSteps.length }}
            </div>
          </div>
        </div>

        <!-- Error Banner -->
        <div
          v-if="task.status === 'failed' && task.result?.error"
          class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
        >
          <div class="flex items-center gap-2 mb-1">
            <Icon name="ph:warning-circle-fill" class="w-5 h-5 text-red-600 dark:text-red-400" />
            <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">Task Failed</h3>
          </div>
          <p class="text-sm text-red-700 dark:text-red-400">{{ task.result.error }}</p>
        </div>

        <!-- Delegation Banner -->
        <div
          v-if="task.source === 'agent_delegation' || task.source === 'agent_ask' || task.source === 'agent_notify'"
          :class="[
            'mb-6 p-4 rounded-lg border',
            task.source === 'agent_notify'
              ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800'
              : 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800'
          ]"
        >
          <div class="flex items-center gap-3">
            <Icon :name="sourceIcons[task.source] || 'ph:users-three'" :class="['w-5 h-5 shrink-0', task.source === 'agent_notify' ? 'text-amber-600 dark:text-amber-400' : 'text-indigo-600 dark:text-indigo-400']" />
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['text-sm font-medium', task.source === 'agent_notify' ? 'text-amber-800 dark:text-amber-300' : 'text-indigo-800 dark:text-indigo-300']">
                  {{ task.source === 'agent_delegation' ? 'Delegated' : task.source === 'agent_ask' ? 'Asked' : 'Notified' }} by
                </span>
                <template v-if="task.requester">
                  <AgentAvatar :user="task.requester" size="xs" :show-status="false" />
                  <span :class="['text-sm font-medium', task.source === 'agent_notify' ? 'text-amber-700 dark:text-amber-300' : 'text-indigo-700 dark:text-indigo-300']">{{ task.requester.name }}</span>
                </template>
              </div>
              <button
                v-if="task.parentTask"
                :class="['mt-1 text-xs hover:underline flex items-center gap-1', task.source === 'agent_notify' ? 'text-amber-600 dark:text-amber-400' : 'text-indigo-600 dark:text-indigo-400']"
                @click="router.visit(workspacePath(`/tasks/${task.parentTask.id}`))"
              >
                <Icon name="ph:arrow-bend-up-left" class="w-3 h-3" />
                View parent task: {{ task.parentTask.title }}
              </button>
            </div>
          </div>
        </div>

        <!-- Metadata Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6 p-4 rounded-lg bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700">
          <div>
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Agent</label>
            <Link v-if="task.agent" :href="workspacePath(`/agent/${task.agent.id}`)" class="flex items-center gap-2 hover:underline">
              <AgentAvatar :user="task.agent" size="sm" />
              <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ task.agent.name }}</span>
            </Link>
            <span v-else class="text-sm text-neutral-400">Unassigned</span>
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Requester</label>
            <div v-if="task.requester" class="flex items-center gap-2">
              <AgentAvatar :user="task.requester" size="sm" />
              <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ task.requester.name }}</span>
            </div>
            <span v-else class="text-sm text-neutral-400">Unknown</span>
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Priority</label>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-1 text-sm font-medium rounded-full',
                priorityClasses[task.priority]
              ]"
            >
              <Icon :name="priorityIcons[task.priority]" class="w-3.5 h-3.5" />
              {{ task.priority }}
            </span>
          </div>
          <div v-if="ctx.model">
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Model</label>
            <span class="text-sm text-neutral-700 dark:text-neutral-300 font-mono">{{ ctx.model }}</span>
          </div>
          <div>
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Created</label>
            <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ formatDateTime(task.createdAt) }}</span>
          </div>
          <div v-if="task.startedAt">
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Started</label>
            <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ formatDateTime(task.startedAt) }}</span>
          </div>
          <div v-if="task.completedAt">
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Completed</label>
            <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ formatDateTime(task.completedAt) }}</span>
          </div>
          <div v-if="task.parentTask">
            <label class="block text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1">Parent Task</label>
            <button
              class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
              @click="router.visit(workspacePath(`/tasks/${task.parentTask.id}`))"
            >
              <Icon name="ph:arrow-bend-up-left" class="w-3.5 h-3.5" />
              {{ task.parentTask.title }}
            </button>
          </div>
        </div>

        <!-- Input -->
        <div v-if="task.description" class="mb-6">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-2 flex items-center gap-2">
            <Icon name="ph:arrow-right" class="w-4 h-4 text-blue-500" />
            Input
          </h3>
          <div class="p-4 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
            <p class="text-sm text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap">{{ task.description }}</p>
          </div>
        </div>

        <!-- Execution Trace -->
        <div v-if="task.steps && task.steps.length > 0" class="mb-6">
          <ExecutionTrace :steps="task.steps" />
        </div>

        <!-- Subtasks -->
        <div v-if="task.subtasks && task.subtasks.length > 0" class="mb-6">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3 flex items-center gap-2">
            <Icon name="ph:tree-structure" class="w-4 h-4 text-indigo-500" />
            Subtasks
            <span class="text-xs text-neutral-400 font-normal">{{ task.subtasks.length }}</span>
          </h3>
          <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg overflow-hidden divide-y divide-neutral-100 dark:divide-neutral-800">
            <div
              v-for="subtask in task.subtasks"
              :key="subtask.id"
              class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
              @click="router.visit(workspacePath(`/tasks/${subtask.id}`))"
            >
              <span :class="['w-2 h-2 rounded-full shrink-0', statusDots[subtask.status]]" />
              <div class="flex-1 min-w-0">
                <span class="text-sm text-neutral-900 dark:text-white truncate block">{{ subtask.title }}</span>
              </div>
              <template v-if="subtask.agent">
                <AgentAvatar :user="subtask.agent" size="xs" :show-status="false" />
                <span class="text-xs text-neutral-500 dark:text-neutral-400 shrink-0">{{ subtask.agent.name }}</span>
              </template>
              <span
                :class="[
                  'text-xs font-medium px-2 py-0.5 rounded-full shrink-0',
                  statusClasses[subtask.status]
                ]"
              >
                {{ statusLabels[subtask.status] }}
              </span>
            </div>
          </div>
        </div>

        <!-- Output -->
        <div v-if="task.result?.response" class="mb-6">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-medium text-neutral-900 dark:text-white flex items-center gap-2">
              <Icon name="ph:arrow-left" class="w-4 h-4 text-green-500" />
              Output
            </h3>
            <div class="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 rounded-md p-0.5">
              <button
                class="px-2 py-1 text-xs font-medium rounded transition-colors"
                :class="outputViewMode === 'raw' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'"
                @click="outputViewMode = 'raw'"
              >
                <Icon name="ph:code" class="w-3.5 h-3.5 inline -mt-0.5" /> Raw
              </button>
              <button
                class="px-2 py-1 text-xs font-medium rounded transition-colors"
                :class="outputViewMode === 'preview' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'"
                @click="outputViewMode = 'preview'"
              >
                <Icon name="ph:eye" class="w-3.5 h-3.5 inline -mt-0.5" /> Preview
              </button>
            </div>
          </div>
          <!-- Raw: syntax-highlighted markdown source -->
          <pre v-if="outputViewMode === 'raw'" class="text-xs bg-neutral-900 rounded-lg p-4 overflow-x-auto border border-neutral-700 max-h-[32rem] overflow-y-auto"><code class="hljs whitespace-pre-wrap break-words" v-html="highlight(task.result.response, 'markdown')" /></pre>
          <!-- Preview: rendered markdown -->
          <div v-else class="p-4 rounded-lg bg-neutral-50 dark:bg-neutral-800/50 border border-neutral-200 dark:border-neutral-700 max-h-[32rem] overflow-y-auto">
            <div class="prose prose-sm prose-neutral dark:prose-invert max-w-none" v-html="renderMarkdown(task.result.response)" />
          </div>
        </div>

        <!-- Context Panel -->
        <div v-if="ctx.system_prompt || ctx.messages?.length || ctx.tools?.length" class="mb-6">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3 flex items-center gap-2">
            <Icon name="ph:brain" class="w-4 h-4" />
            LLM Context
          </h3>
          <!-- Token Breakdown Bar -->
          <div v-if="tb" class="mb-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">
                {{ ctx.model }} <span class="text-neutral-300 dark:text-neutral-600 mx-1">&middot;</span> {{ formatTokens(tb.context_window) }} context
              </span>
              <span class="text-xs text-neutral-400 tabular-nums font-mono">
                {{ formatTokens(tb.system_prompt.total + tb.messages.total) }} / {{ formatTokens(tb.context_window) }} used
              </span>
            </div>

            <!-- Stacked bar -->
            <div class="flex h-2.5 rounded-full overflow-hidden bg-neutral-100 dark:bg-neutral-800 mb-2">
              <div
                v-for="seg in contextBarSegments"
                :key="seg.label"
                :class="[seg.color, 'transition-all duration-300']"
                :style="{ width: Math.max(seg.pct, seg.pct > 0 ? 0.5 : 0) + '%' }"
                :title="`${seg.label}: ${formatTokens(seg.tokens)} (${seg.pct.toFixed(1)}%)`"
              />
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
              <span v-for="seg in contextBarSegments.filter(s => s.pct > 0.1)" :key="seg.label" class="flex items-center gap-1.5">
                <span :class="[seg.color, 'w-2 h-2 rounded-full inline-block']" />
                {{ seg.label }}
                <span class="tabular-nums font-mono text-neutral-400">{{ formatTokens(seg.tokens) }} ({{ seg.pct.toFixed(1) }}%)</span>
              </span>
            </div>

            <!-- Compaction proximity -->
            <div class="mt-3 pt-2.5 border-t border-neutral-100 dark:border-neutral-800">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-neutral-500 dark:text-neutral-400">Compaction proximity</span>
                <span class="text-xs tabular-nums font-mono" :class="tb.compaction.pct_used >= 90 ? 'text-red-500' : tb.compaction.pct_used >= 70 ? 'text-amber-500' : 'text-green-600 dark:text-green-400'">
                  {{ tb.compaction.pct_used }}% &middot; {{ formatTokens(tb.compaction.remaining) }} remaining
                </span>
              </div>
              <div class="h-1.5 rounded-full overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                <div
                  :class="[compactionColor, 'h-full rounded-full transition-all duration-300']"
                  :style="{ width: Math.min(tb.compaction.pct_used, 100) + '%' }"
                />
              </div>
            </div>
          </div>

          <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg divide-y divide-neutral-200 dark:divide-neutral-700 overflow-hidden">
            <!-- System Prompt -->
            <div v-if="ctx.system_prompt">
              <button
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
                @click="toggleContext('system_prompt')"
              >
                <Icon name="ph:scroll" class="w-4 h-4 text-neutral-400 shrink-0" />
                <span class="flex-1 text-sm text-neutral-900 dark:text-white">System Prompt</span>
                <span class="text-xs text-neutral-400 tabular-nums font-mono shrink-0">{{ tb ? formatTokens(tb.system_prompt.total) + ' tokens' : ctx.system_prompt.length.toLocaleString() + ' chars' }}</span>
                <Icon :name="expandedContext.has('system_prompt') ? 'ph:caret-up' : 'ph:caret-down'" class="w-4 h-4 text-neutral-400 shrink-0" />
              </button>
              <div v-if="expandedContext.has('system_prompt')" class="px-4 py-3 bg-neutral-50 dark:bg-neutral-800/30 border-t border-neutral-100 dark:border-neutral-700/50">
                <!-- Section breakdown -->
                <div v-if="sectionTokens.length" class="mb-3 space-y-1.5">
                  <div
                    v-for="section in sectionTokens"
                    :key="section.label"
                    class="flex items-center gap-2"
                  >
                    <span class="text-xs text-neutral-500 dark:text-neutral-400 w-28 shrink-0 truncate" :title="section.label">{{ section.label }}</span>
                    <div class="flex-1 h-1.5 rounded-full overflow-hidden bg-neutral-200 dark:bg-neutral-700">
                      <div
                        class="h-full rounded-full bg-indigo-400 dark:bg-indigo-500 transition-all duration-300"
                        :style="{ width: (section.tokens / maxSectionTokens * 100) + '%' }"
                      />
                    </div>
                    <span class="text-xs tabular-nums font-mono text-neutral-400 w-20 text-right shrink-0">
                      {{ section.tokens.toLocaleString() }}
                      <span class="text-neutral-300 dark:text-neutral-600">({{ tb ? Math.round(section.tokens / tb.system_prompt.total * 100) : 0 }}%)</span>
                    </span>
                  </div>
                </div>

                <div class="flex justify-end mb-2">
                  <div class="flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 rounded-md p-0.5">
                    <button
                      class="px-2 py-1 text-xs font-medium rounded transition-colors"
                      :class="systemPromptViewMode === 'raw' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'"
                      @click="systemPromptViewMode = 'raw'"
                    >
                      <Icon name="ph:code" class="w-3.5 h-3.5 inline -mt-0.5" /> Raw
                    </button>
                    <button
                      class="px-2 py-1 text-xs font-medium rounded transition-colors"
                      :class="systemPromptViewMode === 'preview' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'"
                      @click="systemPromptViewMode = 'preview'"
                    >
                      <Icon name="ph:eye" class="w-3.5 h-3.5 inline -mt-0.5" /> Preview
                    </button>
                  </div>
                </div>
                <pre v-if="systemPromptViewMode === 'raw'" class="text-xs bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-700 max-h-96 overflow-y-auto"><code class="hljs whitespace-pre-wrap break-words" v-html="highlight(ctx.system_prompt!, 'markdown')" /></pre>
                <div v-else class="bg-white dark:bg-neutral-900 rounded-md p-3 overflow-x-auto border border-neutral-200 dark:border-neutral-700 max-h-96 overflow-y-auto prose prose-sm prose-neutral dark:prose-invert max-w-none" v-html="renderMarkdown(ctx.system_prompt!)" />
              </div>
            </div>

            <!-- Conversation Messages -->
            <div v-if="ctx.messages?.length">
              <button
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
                @click="toggleContext('messages')"
              >
                <Icon name="ph:chat-dots" class="w-4 h-4 text-neutral-400 shrink-0" />
                <span class="flex-1 text-sm text-neutral-900 dark:text-white">Conversation History</span>
                <span class="text-xs text-neutral-400 tabular-nums font-mono shrink-0">{{ ctx.messages.length }} messages{{ tb ? ' · ' + formatTokens(tb.messages.total) + ' tokens' : '' }}</span>
                <Icon :name="expandedContext.has('messages') ? 'ph:caret-up' : 'ph:caret-down'" class="w-4 h-4 text-neutral-400 shrink-0" />
              </button>
              <div v-if="expandedContext.has('messages')" class="px-4 py-3 bg-neutral-50 dark:bg-neutral-800/30 border-t border-neutral-100 dark:border-neutral-700/50 space-y-2 max-h-96 overflow-y-auto">
                <div
                  v-for="(msg, i) in ctx.messages"
                  :key="i"
                  class="flex gap-2"
                >
                  <span
                    :class="[
                      'shrink-0 text-xs font-mono font-medium px-1.5 py-0.5 rounded',
                      msg.role === 'assistant'
                        ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                        : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
                    ]"
                  >
                    {{ msg.role }}
                  </span>
                  <p class="text-xs text-neutral-700 dark:text-neutral-300 whitespace-pre-wrap break-words min-w-0">{{ msg.content }}</p>
                </div>
              </div>
            </div>

            <!-- Available Tools -->
            <div v-if="ctx.tools?.length">
              <button
                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50 transition-colors"
                @click="toggleContext('tools')"
              >
                <Icon name="ph:wrench" class="w-4 h-4 text-neutral-400 shrink-0" />
                <span class="flex-1 text-sm text-neutral-900 dark:text-white">Available Tools</span>
                <span class="text-xs text-neutral-400 tabular-nums font-mono shrink-0">{{ ctx.tools.length }} tools</span>
                <Icon :name="expandedContext.has('tools') ? 'ph:caret-up' : 'ph:caret-down'" class="w-4 h-4 text-neutral-400 shrink-0" />
              </button>
              <div v-if="expandedContext.has('tools')" class="px-4 py-3 bg-neutral-50 dark:bg-neutral-800/30 border-t border-neutral-100 dark:border-neutral-700/50">
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="tool in ctx.tools"
                    :key="tool"
                    class="inline-flex items-center px-2 py-1 text-xs font-mono bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-neutral-700 dark:text-neutral-300"
                  >
                    {{ tool }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-4 border-t border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center gap-2">
            <button
              v-if="task.channelId"
              class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
              @click="goToChannel"
            >
              <Icon name="ph:chat-circle" class="w-4 h-4" />
              <span>View Chat</span>
            </button>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="task.status === 'pending'"
              class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
              @click="handleStart"
            >
              <Icon name="ph:play-fill" class="w-4 h-4" />
              Start
            </button>
            <button
              v-if="task.status === 'active'"
              class="flex items-center gap-2 px-3 py-1.5 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors"
              @click="handlePause"
            >
              <Icon name="ph:pause-fill" class="w-4 h-4" />
              Pause
            </button>
            <button
              v-if="task.status === 'paused'"
              class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
              @click="handleResume"
            >
              <Icon name="ph:play-fill" class="w-4 h-4" />
              Resume
            </button>
            <button
              v-if="task.status === 'active' || task.status === 'paused'"
              class="flex items-center gap-2 px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
              @click="handleComplete"
            >
              <Icon name="ph:check-bold" class="w-4 h-4" />
              Complete
            </button>
            <button
              v-if="!['completed', 'failed', 'cancelled'].includes(task.status)"
              class="flex items-center gap-2 px-3 py-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium rounded-lg transition-colors"
              @click="handleCancel"
            >
              <Icon name="ph:x" class="w-4 h-4" />
              Cancel
            </button>
          </div>
        </div>
      </template>

      <!-- Not Found -->
      <div v-else class="text-center py-20">
        <Icon name="ph:briefcase" class="w-12 h-12 mx-auto text-neutral-300 dark:text-neutral-600 mb-3" />
        <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-1">Task not found</h2>
        <p class="text-sm text-neutral-500 dark:text-neutral-400">The task you're looking for doesn't exist.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
import type { AgentTask, TaskStatus, TaskType, Priority } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import ExecutionTrace from '@/Components/tasks/ExecutionTrace.vue'
import { useApi } from '@/composables/useApi'
import { useMarkdown } from '@/composables/useMarkdown'
import { useHighlight } from '@/composables/useHighlight'

interface PromptSection {
  label: string
  chars: number
}

interface TokenBreakdown {
  context_window: number
  output_reserve: number
  system_prompt: { total: number; sections: PromptSection[] }
  messages: { total: number; count: number }
  compaction: { threshold: number; adjusted_tokens: number; remaining: number; pct_used: number }
  actual_prompt_tokens?: number
  actual_completion_tokens?: number
}

interface TaskContext {
  system_prompt?: string
  messages?: { role: string; content: string }[]
  tools?: string[]
  model?: string
  provider?: string
  token_breakdown?: TokenBreakdown
}

const props = defineProps<{
  taskId: string
}>()

const {
  fetchAgentTask,
  startAgentTask,
  pauseAgentTask,
  resumeAgentTask,
  completeAgentTask,
  cancelAgentTask,
} = useApi()

const { workspacePath } = useWorkspace()
const { renderMarkdown } = useMarkdown()
const { highlight } = useHighlight()

// View mode toggles: 'raw' (syntax highlighted) or 'preview' (rendered markdown)
const outputViewMode = ref<'raw' | 'preview'>('raw')
const systemPromptViewMode = ref<'raw' | 'preview'>('raw')

const loading = ref(true)
const task = ref<AgentTask | null>(null)

// Context panel expand state
const expandedContext = ref(new Set<string>())

const toggleContext = (key: string) => {
  if (expandedContext.value.has(key)) {
    expandedContext.value.delete(key)
  } else {
    expandedContext.value.add(key)
  }
}

// Type styling
const typeClasses: Record<TaskType, string> = {
  ticket: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400',
  request: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  analysis: 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400',
  content: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-400',
  research: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400',
  custom: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
}

const typeIcons: Record<TaskType, string> = {
  ticket: 'ph:ticket',
  request: 'ph:paper-plane-tilt',
  analysis: 'ph:chart-bar',
  content: 'ph:note-pencil',
  research: 'ph:magnifying-glass',
  custom: 'ph:clipboard-text',
}

const typeLabels: Record<TaskType, string> = {
  ticket: 'Ticket',
  request: 'Request',
  analysis: 'Analysis',
  content: 'Content',
  research: 'Research',
  custom: 'Custom',
}

const statusClasses: Record<TaskStatus, string> = {
  pending: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  active: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  paused: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
  failed: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
  cancelled: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400',
}

const statusDots: Record<TaskStatus, string> = {
  pending: 'bg-neutral-400',
  active: 'bg-blue-500',
  paused: 'bg-amber-500',
  completed: 'bg-green-500',
  failed: 'bg-red-500',
  cancelled: 'bg-neutral-400',
}

const statusLabels: Record<TaskStatus, string> = {
  pending: 'Pending',
  active: 'Active',
  paused: 'Paused',
  completed: 'Completed',
  failed: 'Failed',
  cancelled: 'Cancelled',
}

const priorityClasses: Record<Priority, string> = {
  low: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  normal: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  medium: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  high: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  urgent: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
}

const priorityIcons: Record<Priority, string> = {
  low: 'ph:arrow-down',
  normal: 'ph:minus',
  medium: 'ph:minus',
  high: 'ph:arrow-up',
  urgent: 'ph:warning',
}

const sourceIcons: Record<string, string> = {
  chat: 'ph:chat-circle',
  manual: 'ph:hand',
  automation: 'ph:lightning',
  agent_delegation: 'ph:users-three',
  agent_ask: 'ph:question',
  agent_notify: 'ph:megaphone',
}

// Computed
const ctx = computed<TaskContext>(() => (task.value?.context as TaskContext) ?? {})

const tb = computed(() => ctx.value.token_breakdown)

const formatTokens = (n: number) => {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`
  return n.toString()
}

const contextBarSegments = computed(() => {
  if (!tb.value) return []
  const total = tb.value.context_window
  if (total <= 0) return []
  const sysPct = (tb.value.system_prompt.total / total) * 100
  const msgPct = (tb.value.messages.total / total) * 100
  const reservePct = (tb.value.output_reserve / total) * 100
  const availPct = Math.max(0, 100 - sysPct - msgPct - reservePct)
  return [
    { label: 'System', tokens: tb.value.system_prompt.total, pct: sysPct, color: 'bg-indigo-500' },
    { label: 'Conversation', tokens: tb.value.messages.total, pct: msgPct, color: 'bg-blue-500' },
    { label: 'Output Reserve', tokens: tb.value.output_reserve, pct: reservePct, color: 'bg-amber-400' },
    { label: 'Available', tokens: Math.max(0, total - tb.value.system_prompt.total - tb.value.messages.total - tb.value.output_reserve), pct: availPct, color: 'bg-neutral-200 dark:bg-neutral-700' },
  ]
})

const compactionColor = computed(() => {
  if (!tb.value) return 'bg-green-500'
  const pct = tb.value.compaction.pct_used
  if (pct >= 90) return 'bg-red-500'
  if (pct >= 70) return 'bg-amber-500'
  return 'bg-green-500'
})

// Compute per-section token allocation from char ratios
const sectionTokens = computed(() => {
  if (!tb.value?.system_prompt.sections) return []
  const totalChars = tb.value.system_prompt.sections.reduce((sum, s) => sum + s.chars, 0)
  if (totalChars <= 0) return []
  const totalTokens = tb.value.system_prompt.total
  return tb.value.system_prompt.sections
    .map(s => ({
      label: s.label,
      tokens: Math.round((s.chars / totalChars) * totalTokens),
    }))
    .filter(s => s.tokens > 0)
    .sort((a, b) => b.tokens - a.tokens)
})

const maxSectionTokens = computed(() => {
  if (!sectionTokens.value.length) return 1
  return sectionTokens.value[0].tokens
})

const toolSteps = computed(() =>
  task.value?.steps?.filter(s => s.metadata?.tool) ?? []
)

const formattedDuration = computed(() => {
  if (!task.value?.startedAt) return '---'
  const end = task.value.completedAt ? new Date(task.value.completedAt) : new Date()
  const start = new Date(task.value.startedAt)
  const ms = end.getTime() - start.getTime()
  if (ms < 1000) return `${ms}ms`
  const seconds = Math.floor(ms / 1000)
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  const remainingSecs = seconds % 60
  if (minutes < 60) return `${minutes}m ${remainingSecs}s`
  const hours = Math.floor(minutes / 60)
  return `${hours}h ${minutes % 60}m`
})

const formatDateTime = (date: Date | string | undefined) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
  })
}

// Data fetching
const fetchData = async () => {
  loading.value = true
  try {
    const result = fetchAgentTask(props.taskId)
    await result.promise
    task.value = result.data.value
  } catch (e) {
    console.error('Failed to fetch task:', e)
    task.value = null
  } finally {
    loading.value = false
  }
}

// Lifecycle actions
const handleStart = async () => {
  await startAgentTask(props.taskId)
  await fetchData()
}
const handlePause = async () => {
  await pauseAgentTask(props.taskId)
  await fetchData()
}
const handleResume = async () => {
  await resumeAgentTask(props.taskId)
  await fetchData()
}
const handleComplete = async () => {
  await completeAgentTask(props.taskId)
  await fetchData()
}
const handleCancel = async () => {
  await cancelAgentTask(props.taskId)
  await fetchData()
}

const goBack = () => window.history.back()

const goToChannel = () => {
  if (task.value?.channelId) {
    router.visit(workspacePath(`/chat?channel=${task.value.channelId}`))
  }
}

onMounted(fetchData)
watch(() => props.taskId, fetchData)
</script>
