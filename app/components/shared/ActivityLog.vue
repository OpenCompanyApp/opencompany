<template>
  <CollapsibleRoot v-model:open="isOpen" class="w-full">
    <CollapsibleTrigger
      class="w-full flex items-center gap-2 text-xs text-olympus-text-muted hover:text-olympus-text transition-colors group"
    >
      <Icon
        name="ph:caret-right"
        class="w-3 h-3 transition-transform duration-200"
        :class="{ 'rotate-90': isOpen }"
      />
      <span>Activity log</span>
      <span class="text-olympus-text-subtle">({{ steps.length }} steps)</span>
    </CollapsibleTrigger>

    <CollapsibleContent class="mt-2 pl-5 border-l-2 border-olympus-border space-y-2">
      <div
        v-for="(step, index) in steps"
        :key="step.id"
        class="flex items-start gap-2 text-xs"
      >
        <div
          :class="[
            'w-4 h-4 rounded-full flex items-center justify-center shrink-0 mt-0.5',
            stepStatusClasses[step.status]
          ]"
        >
          <Icon
            v-if="step.status === 'completed'"
            name="ph:check"
            class="w-2.5 h-2.5"
          />
          <Icon
            v-else-if="step.status === 'in_progress'"
            name="ph:spinner"
            class="w-2.5 h-2.5 animate-spin"
          />
          <span v-else class="w-1.5 h-1.5 rounded-full bg-current" />
        </div>
        <div class="flex-1 min-w-0">
          <p
            :class="[
              step.status === 'completed' ? 'text-olympus-text-muted' : 'text-olympus-text'
            ]"
          >
            {{ step.description }}
          </p>
          <p class="text-olympus-text-subtle mt-0.5">
            {{ formatDuration(step) }}
          </p>
        </div>
      </div>
    </CollapsibleContent>
  </CollapsibleRoot>
</template>

<script setup lang="ts">
import { CollapsibleContent, CollapsibleRoot, CollapsibleTrigger } from 'reka-ui'
import type { ActivityStep } from '~/types'

const props = defineProps<{
  steps: ActivityStep[]
}>()

const isOpen = ref(false)

const stepStatusClasses = {
  completed: 'bg-green-500/20 text-green-400',
  in_progress: 'bg-olympus-primary/20 text-olympus-primary',
  pending: 'bg-olympus-surface text-olympus-text-muted',
}

const formatDuration = (step: ActivityStep) => {
  const start = new Date(step.startedAt)
  const end = step.completedAt ? new Date(step.completedAt) : new Date()
  const diffMs = end.getTime() - start.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffSecs = Math.floor((diffMs % 60000) / 1000)

  if (step.status === 'in_progress') {
    return `${diffMins}m ${diffSecs}s elapsed`
  }
  return `${diffMins}m ${diffSecs}s`
}
</script>
