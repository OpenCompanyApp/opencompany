<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Automations</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
            Schedule agents to run automatically on a recurring basis
          </p>
        </div>
        <Button variant="primary" @click="router.visit(workspacePath('/automation/create'))">
          <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
          New Automation
        </Button>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Loading state -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
      </div>

      <!-- Empty state -->
      <div
        v-else-if="automations.length === 0"
        class="flex flex-col items-center justify-center py-20 text-center"
      >
        <div class="w-16 h-16 rounded-2xl bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center mb-5">
          <Icon name="ph:clock-clockwise" class="w-8 h-8 text-neutral-400 dark:text-neutral-500" />
        </div>
        <h2 class="text-lg font-medium text-neutral-900 dark:text-white mb-2">
          No automations yet
        </h2>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 max-w-md mb-6">
          Schedule your agents to run automatically — daily standups, weekly reports,
          hourly monitoring, or anything else you can imagine.
        </p>
        <Button variant="primary" @click="router.visit(workspacePath('/automation/create'))">
          <Icon name="ph:plus" class="w-4 h-4 mr-1.5" />
          Create your first automation
        </Button>
      </div>

      <!-- Automations list -->
      <div v-else class="space-y-4 max-w-3xl">
        <!-- Summary bar -->
        <div class="flex items-center gap-4 text-sm text-neutral-500 dark:text-neutral-400 mb-2">
          <span>{{ automations.length }} automation{{ automations.length !== 1 ? 's' : '' }}</span>
          <span class="text-neutral-300 dark:text-neutral-600">|</span>
          <span class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-green-500" />
            {{ activeCount }} active
          </span>
          <span v-if="totalRuns > 0" class="flex items-center gap-1">
            <Icon name="ph:arrow-clockwise" class="w-3.5 h-3.5" />
            {{ totalRuns }} total runs
          </span>
        </div>

        <ScheduleCard
          v-for="automation in automations"
          :key="automation.id"
          :automation="automation"
          @toggle="handleToggle"
          @edit="handleEdit"
          @trigger="handleTrigger"
          @delete="handleDelete"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import type { ScheduledAutomation } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import ScheduleCard from '@/Components/automation/ScheduleCard.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'

const { workspacePath } = useWorkspace()

const {
  fetchScheduledAutomations,
  updateScheduledAutomation,
  deleteScheduledAutomation,
  triggerScheduledAutomation,
} = useApi()

// Data fetching
const { data: automationsData, loading, refresh: refreshAutomations } = fetchScheduledAutomations()

const automations = computed<ScheduledAutomation[]>(() => automationsData.value ?? [])
const activeCount = computed(() => automations.value.filter(a => a.isActive).length)
const totalRuns = computed(() => automations.value.reduce((sum, a) => sum + (a.runCount || 0), 0))

function handleEdit(automation: ScheduledAutomation) {
  router.visit(workspacePath(`/automation/${automation.id}/edit`))
}

async function handleToggle(automation: ScheduledAutomation) {
  await updateScheduledAutomation(automation.id, { isActive: !automation.isActive })
  await refreshAutomations()
}

async function handleTrigger(automation: ScheduledAutomation) {
  await triggerScheduledAutomation(automation.id)
  await refreshAutomations()
}

async function handleDelete(automation: ScheduledAutomation) {
  if (!confirm(`Delete "${automation.name}"? This cannot be undone.`)) return
  await deleteScheduledAutomation(automation.id)
  await refreshAutomations()
}
</script>
