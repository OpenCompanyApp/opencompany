<template>
  <div class="h-full flex flex-col bg-white dark:bg-neutral-900">
    <!-- Header -->
    <header class="h-14 px-4 md:px-6 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-4 bg-white dark:bg-neutral-900 shrink-0">
      <span class="text-lg font-semibold text-neutral-900 dark:text-white">Automations</span>

      <!-- Status tabs -->
      <div v-if="!loading" class="hidden md:flex items-center gap-0.5 bg-neutral-100 dark:bg-neutral-800 rounded-lg p-0.5">
        <button
          v-for="s in statusTabs"
          :key="s.value"
          :class="[
            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors',
            statusFilter === s.value
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
          ]"
          @click="statusFilter = s.value"
        >
          <span v-if="s.dot" :class="['w-1.5 h-1.5 rounded-full', s.dot]" />
          {{ s.label }}
          <span v-if="s.count > 0" class="text-[10px] opacity-60">{{ s.count }}</span>
        </button>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <SearchInput
          v-model="searchQuery"
          placeholder="Search..."
          variant="ghost"
          size="sm"
          :clearable="true"
          :debounce="300"
          class="w-36 lg:w-48 shrink-0"
        />
        <Button variant="primary" size="sm" icon-left="ph:plus" @click="router.visit(workspacePath('/automation/create'))">
          New
        </Button>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-4xl mx-auto p-4 md:p-6">
        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
          <div v-for="i in 4" :key="i" class="h-20 rounded-lg bg-neutral-100 dark:bg-neutral-800 animate-pulse" />
        </div>

        <!-- Empty -->
        <div v-else-if="automations.length === 0" class="py-16 text-center">
          <div class="w-16 h-16 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mx-auto mb-5">
            <Icon name="ph:lightning" class="w-8 h-8 text-neutral-400 dark:text-neutral-500" />
          </div>
          <p class="text-sm font-medium text-neutral-900 dark:text-white mb-1">No automations yet</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto mb-6">
            Schedule agents to run automatically — daily standups, weekly reports,
            hourly monitoring, or anything you can imagine.
          </p>
          <Button variant="primary" icon-left="ph:plus" @click="router.visit(workspacePath('/automation/create'))">
            Create your first automation
          </Button>
        </div>

        <!-- No results for filter -->
        <div v-else-if="filteredAutomations.length === 0" class="py-16 text-center">
          <Icon name="ph:magnifying-glass" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <p class="text-sm font-medium text-neutral-900 dark:text-white mb-1">No matches</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400">Try adjusting your search or filters.</p>
        </div>

        <!-- Automations list -->
        <div v-else class="rounded-lg border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
          <div
            v-for="automation in filteredAutomations"
            :key="automation.id"
            :class="[
              'px-4 py-3.5 transition-colors',
              automation.isActive
                ? 'hover:bg-neutral-50 dark:hover:bg-neutral-800/50'
                : 'opacity-60 hover:opacity-80',
            ]"
          >
            <div class="flex items-start gap-3">
              <!-- Status icon badge -->
              <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5', statusBg(automation)]">
                <Icon name="ph:lightning" :class="['w-4 h-4', statusIconColor(automation)]" />
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <!-- Title + schedule -->
                <div class="flex items-center gap-2">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ automation.name }}</p>
                  <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400 shrink-0">
                    {{ humanSchedule(automation) }}
                  </span>
                </div>

                <!-- Agent + prompt -->
                <div class="flex items-center gap-2 mt-1 text-xs">
                  <span
                    v-if="automation.agent"
                    class="inline-flex items-center gap-1 text-neutral-600 dark:text-neutral-300"
                  >
                    <Icon name="ph:robot" class="w-3 h-3" />
                    {{ automation.agent.name }}
                  </span>
                  <span class="text-neutral-400 dark:text-neutral-500 truncate">
                    {{ automation.prompt }}
                  </span>
                </div>

                <!-- Stats row -->
                <div class="flex items-center gap-3 mt-1.5 text-[11px] text-neutral-400 dark:text-neutral-500">
                  <span v-if="automation.nextRunAt" class="flex items-center gap-1">
                    <Icon name="ph:clock" class="w-3 h-3" />
                    {{ formatRelativeDate(automation.nextRunAt) }}
                  </span>
                  <span class="flex items-center gap-1">
                    <Icon name="ph:arrow-clockwise" class="w-3 h-3" />
                    {{ automation.runCount }} runs
                  </span>
                  <span v-if="automation.lastRunAt" class="flex items-center gap-1">
                    <Icon name="ph:check-circle" class="w-3 h-3" />
                    {{ formatRelativeDate(automation.lastRunAt) }}
                  </span>
                </div>

                <!-- Error state -->
                <div
                  v-if="automation.consecutiveFailures > 0 && automation.lastResult?.error"
                  class="flex items-center gap-1.5 mt-2 text-[11px] text-red-500 dark:text-red-400"
                >
                  <Icon name="ph:warning" class="w-3 h-3 shrink-0" />
                  <span class="font-medium">{{ automation.consecutiveFailures }} failure{{ automation.consecutiveFailures > 1 ? 's' : '' }}</span>
                  <span class="text-red-400/70 dark:text-red-400/50 truncate">{{ automation.lastResult.error }}</span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 shrink-0">
                <!-- Toggle -->
                <button
                  type="button"
                  :class="[
                    'relative w-9 h-5 rounded-full transition-colors',
                    automation.isActive ? 'bg-green-500' : 'bg-neutral-200 dark:bg-neutral-600',
                  ]"
                  :title="automation.isActive ? 'Disable' : 'Enable'"
                  @click="handleToggle(automation)"
                >
                  <span
                    :class="[
                      'absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform shadow-sm',
                      automation.isActive && 'translate-x-4',
                    ]"
                  />
                </button>

                <!-- Actions menu -->
                <div class="relative">
                  <button
                    type="button"
                    class="p-1.5 rounded-md hover:bg-neutral-100 dark:hover:bg-neutral-700 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                    @click="toggleActions(automation.id)"
                  >
                    <Icon name="ph:dots-three-vertical" class="w-4 h-4" />
                  </button>
                  <div
                    v-if="openMenuId === automation.id"
                    class="absolute right-0 top-full mt-1 w-40 bg-white dark:bg-neutral-800 rounded-xl shadow-lg border border-neutral-200 dark:border-neutral-700 py-1 z-10"
                    @mouseleave="openMenuId = null"
                  >
                    <button
                      class="w-full px-3 py-1.5 text-left text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 flex items-center gap-2"
                      @click="openMenuId = null; handleEdit(automation)"
                    >
                      <Icon name="ph:pencil" class="w-3.5 h-3.5" />
                      Edit
                    </button>
                    <button
                      class="w-full px-3 py-1.5 text-left text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 flex items-center gap-2"
                      @click="openMenuId = null; handleTrigger(automation)"
                    >
                      <Icon name="ph:play" class="w-3.5 h-3.5" />
                      Run now
                    </button>
                    <hr class="my-1 border-neutral-200 dark:border-neutral-700" />
                    <button
                      class="w-full px-3 py-1.5 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2"
                      @click="openMenuId = null; handleDelete(automation)"
                    >
                      <Icon name="ph:trash" class="w-3.5 h-3.5" />
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { Automation } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()
const {
  fetchAutomations,
  updateAutomation,
  deleteAutomation,
  triggerAutomation,
} = useApi()

// Data
const { data: automationsData, loading, refresh: refreshAutomations } = fetchAutomations()
const automations = computed<Automation[]>(() => automationsData.value ?? [])

// Filters
const statusFilter = ref<'all' | 'active' | 'inactive' | 'failed'>('all')
const searchQuery = ref('')
const openMenuId = ref<string | null>(null)

// Status tabs
const statusTabs = computed(() => {
  const all = automations.value
  return [
    { label: 'All', value: 'all' as const, count: all.length, dot: undefined },
    { label: 'Active', value: 'active' as const, dot: 'bg-green-500', count: all.filter(a => a.isActive && a.consecutiveFailures === 0).length },
    { label: 'Inactive', value: 'inactive' as const, dot: 'bg-neutral-400', count: all.filter(a => !a.isActive).length },
    { label: 'Failing', value: 'failed' as const, dot: 'bg-red-500', count: all.filter(a => a.consecutiveFailures > 0).length },
  ]
})

// Filtered automations
const filteredAutomations = computed(() => {
  let result = automations.value
  if (statusFilter.value === 'active') result = result.filter(a => a.isActive && a.consecutiveFailures === 0)
  if (statusFilter.value === 'inactive') result = result.filter(a => !a.isActive)
  if (statusFilter.value === 'failed') result = result.filter(a => a.consecutiveFailures > 0)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(a =>
      a.name.toLowerCase().includes(q) ||
      a.agent?.name?.toLowerCase().includes(q) ||
      a.prompt.toLowerCase().includes(q)
    )
  }
  return result
})

// Status helpers
const statusBg = (a: Automation): string => {
  if (a.consecutiveFailures > 0) return 'bg-red-100 dark:bg-red-900/30'
  if (!a.isActive) return 'bg-neutral-100 dark:bg-neutral-700'
  return 'bg-emerald-100 dark:bg-emerald-900/30'
}

const statusIconColor = (a: Automation): string => {
  if (a.consecutiveFailures > 0) return 'text-red-600 dark:text-red-400'
  if (!a.isActive) return 'text-neutral-400 dark:text-neutral-500'
  return 'text-emerald-600 dark:text-emerald-400'
}

// Schedule helpers
function humanSchedule(automation: Automation): string {
  const expr = automation.cronExpression
  if (!expr) return ''
  const [m, h, dom, , dow] = expr.split(' ')

  const timeStr = h === '*' ? `:${m.padStart(2, '0')}` : formatTime(parseInt(h), parseInt(m))

  if (h === '*') return `Every hour at :${m.padStart(2, '0')}`
  if (dow !== '*' && dom === '*') {
    const dayNames: Record<string, string> = { '0': 'Sun', '1': 'Mon', '2': 'Tue', '3': 'Wed', '4': 'Thu', '5': 'Fri', '6': 'Sat' }
    const days = dow.split(',')
    if (days.length === 5 && ['1', '2', '3', '4', '5'].every(d => days.includes(d))) {
      return `Weekdays ${timeStr}`
    }
    return `${days.map(d => dayNames[d] ?? d).join(', ')} ${timeStr}`
  }
  if (dom !== '*') return `Monthly ${dom}${ordinal(dom)} ${timeStr}`
  return `Daily ${timeStr}`
}

function formatTime(h: number, m: number): string {
  const period = h >= 12 ? 'PM' : 'AM'
  const hour12 = h === 0 ? 12 : h > 12 ? h - 12 : h
  return `${hour12}:${String(m).padStart(2, '0')} ${period}`
}

function ordinal(n: string): string {
  const num = parseInt(n)
  if (num >= 11 && num <= 13) return 'th'
  switch (num % 10) {
    case 1: return 'st'
    case 2: return 'nd'
    case 3: return 'rd'
    default: return 'th'
  }
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

// Actions
function toggleActions(id: string) {
  openMenuId.value = openMenuId.value === id ? null : id
}

function handleEdit(automation: Automation) {
  router.visit(workspacePath(`/automation/${automation.id}/edit`))
}

async function handleToggle(automation: Automation) {
  await updateAutomation(automation.id, { isActive: !automation.isActive })
  await refreshAutomations()
}

async function handleTrigger(automation: Automation) {
  await triggerAutomation(automation.id)
  await refreshAutomations()
}

async function handleDelete(automation: Automation) {
  if (!confirm(`Delete "${automation.name}"? This cannot be undone.`)) return
  await deleteAutomation(automation.id)
  await refreshAutomations()
}
</script>
