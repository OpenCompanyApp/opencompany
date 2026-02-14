<template>
  <div
    :class="[
      'group p-5 rounded-xl border transition-all',
      automation.isActive
        ? 'bg-white dark:bg-neutral-800/50 border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600'
        : 'bg-neutral-50 dark:bg-neutral-900/50 border-neutral-200 dark:border-neutral-800 opacity-75',
    ]"
  >
    <div class="flex items-start justify-between gap-4">
      <!-- Left: Info -->
      <div class="flex-1 min-w-0 space-y-3">
        <!-- Title row -->
        <div class="flex items-center gap-3">
          <div
            :class="[
              'w-9 h-9 rounded-lg flex items-center justify-center shrink-0',
              automation.isActive
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-400 dark:text-neutral-500',
            ]"
          >
            <Icon name="ph:clock-clockwise" class="w-4.5 h-4.5" />
          </div>
          <div class="min-w-0">
            <h3 class="font-medium text-neutral-900 dark:text-white truncate">
              {{ automation.name }}
            </h3>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 truncate">
              {{ humanSchedule }}
            </p>
          </div>
        </div>

        <!-- Agent & prompt preview -->
        <div class="flex items-center gap-3 text-xs">
          <span
            v-if="automation.agent"
            class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200"
          >
            <Icon name="ph:robot" class="w-3 h-3" />
            {{ automation.agent.name }}
          </span>
          <span class="text-neutral-400 dark:text-neutral-500 truncate max-w-[250px]">
            {{ automation.prompt }}
          </span>
        </div>

        <!-- Stats row -->
        <div class="flex items-center gap-4 text-xs text-neutral-500 dark:text-neutral-400">
          <span v-if="automation.nextRunAt" class="flex items-center gap-1">
            <Icon name="ph:clock" class="w-3.5 h-3.5" />
            Next: {{ formatRelativeDate(automation.nextRunAt) }}
          </span>
          <span class="flex items-center gap-1">
            <Icon name="ph:arrow-clockwise" class="w-3.5 h-3.5" />
            {{ automation.runCount }} runs
          </span>
          <span v-if="automation.lastRunAt" class="flex items-center gap-1">
            <Icon name="ph:check-circle" class="w-3.5 h-3.5" />
            Last: {{ formatRelativeDate(automation.lastRunAt) }}
          </span>
        </div>

        <!-- Error state -->
        <div
          v-if="automation.consecutiveFailures > 0 && automation.lastResult?.error"
          class="flex items-start gap-2 p-2.5 rounded-lg bg-red-50 dark:bg-red-900/20 text-xs"
        >
          <Icon name="ph:warning" class="w-3.5 h-3.5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <span class="text-red-600 dark:text-red-400 font-medium">
              {{ automation.consecutiveFailures }} consecutive failure{{ automation.consecutiveFailures > 1 ? 's' : '' }}
            </span>
            <p class="text-red-500/80 dark:text-red-400/60 mt-0.5 line-clamp-1">
              {{ automation.lastResult.error }}
            </p>
          </div>
        </div>

        <!-- Last success result -->
        <div
          v-else-if="automation.lastResult?.response_preview"
          class="text-xs text-neutral-400 dark:text-neutral-500 line-clamp-1 italic"
        >
          Last: "{{ automation.lastResult.response_preview }}"
        </div>
      </div>

      <!-- Right: Actions -->
      <div class="flex items-center gap-1 shrink-0">
        <!-- Toggle -->
        <button
          type="button"
          :class="[
            'relative w-11 h-6 rounded-full transition-colors',
            automation.isActive
              ? 'bg-green-500'
              : 'bg-neutral-200 dark:bg-neutral-600',
          ]"
          :title="automation.isActive ? 'Disable' : 'Enable'"
          @click="$emit('toggle', automation)"
        >
          <span
            :class="[
              'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow-sm',
              automation.isActive && 'translate-x-5',
            ]"
          />
        </button>

        <!-- Actions dropdown area -->
        <div class="relative">
          <button
            type="button"
            class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
            @click="showActions = !showActions"
          >
            <Icon name="ph:dots-three-vertical" class="w-4 h-4" />
          </button>
          <div
            v-if="showActions"
            class="absolute right-0 top-full mt-1 w-44 bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 py-1 z-10"
            @mouseleave="showActions = false"
          >
            <button
              class="w-full px-3 py-2 text-left text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 flex items-center gap-2"
              @click="showActions = false; $emit('edit', automation)"
            >
              <Icon name="ph:pencil" class="w-4 h-4" />
              Edit
            </button>
            <button
              class="w-full px-3 py-2 text-left text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 flex items-center gap-2"
              @click="showActions = false; $emit('trigger', automation)"
            >
              <Icon name="ph:play" class="w-4 h-4" />
              Run now
            </button>
            <hr class="my-1 border-neutral-200 dark:border-neutral-700" />
            <button
              class="w-full px-3 py-2 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2"
              @click="showActions = false; $emit('delete', automation)"
            >
              <Icon name="ph:trash" class="w-4 h-4" />
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ScheduledAutomation } from '@/types'
import Icon from '@/Components/shared/Icon.vue'

const props = defineProps<{
  automation: ScheduledAutomation
}>()

defineEmits<{
  toggle: [automation: ScheduledAutomation]
  edit: [automation: ScheduledAutomation]
  trigger: [automation: ScheduledAutomation]
  delete: [automation: ScheduledAutomation]
}>()

const showActions = ref(false)

const humanSchedule = computed(() => {
  const expr = props.automation.cronExpression
  if (!expr) return ''
  const [m, h, dom, , dow] = expr.split(' ')

  const timeStr = h === '*' ? `:${m.padStart(2, '0')}` : formatTime(parseInt(h), parseInt(m))

  if (h === '*') return `Every hour at :${m.padStart(2, '0')}`
  if (dow !== '*' && dom === '*') {
    const dayNames: Record<string, string> = { '0': 'Sun', '1': 'Mon', '2': 'Tue', '3': 'Wed', '4': 'Thu', '5': 'Fri', '6': 'Sat' }
    const days = dow.split(',')
    if (days.length === 5 && ['1', '2', '3', '4', '5'].every(d => days.includes(d))) {
      return `Weekdays at ${timeStr}`
    }
    return `${days.map(d => dayNames[d] ?? d).join(', ')} at ${timeStr}`
  }
  if (dom !== '*') {
    return `Monthly on the ${dom} at ${timeStr}`
  }
  return `Daily at ${timeStr}`
})

function formatTime(h: number, m: number): string {
  const period = h >= 12 ? 'PM' : 'AM'
  const hour12 = h === 0 ? 12 : h > 12 ? h - 12 : h
  return `${hour12}:${String(m).padStart(2, '0')} ${period}`
}

function formatRelativeDate(date: string): string {
  const d = new Date(date)
  const now = new Date()
  const diff = d.getTime() - now.getTime()
  const absDiff = Math.abs(diff)
  const minutes = Math.floor(absDiff / 60000)
  const hours = Math.floor(absDiff / 3600000)
  const days = Math.floor(absDiff / 86400000)

  if (diff > 0) {
    if (minutes < 60) return `in ${minutes}m`
    if (hours < 24) return `in ${hours}h`
    if (days === 1) return 'tomorrow'
    return `in ${days}d`
  } else {
    if (minutes < 60) return `${minutes}m ago`
    if (hours < 24) return `${hours}h ago`
    if (days === 1) return 'yesterday'
    return `${days}d ago`
  }
}
</script>
