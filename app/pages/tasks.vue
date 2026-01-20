<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="h-14 px-6 border-b border-olympus-border flex items-center justify-between bg-olympus-sidebar shrink-0">
      <div class="flex items-center gap-4">
        <h1 class="text-xl font-bold">Tasks</h1>
        <div class="flex items-center gap-2 text-sm text-olympus-text-muted">
          <span>{{ taskCounts.total }} tasks</span>
          <span class="text-olympus-border">/</span>
          <span class="text-green-400">{{ taskCounts.done }} done</span>
        </div>
      </div>
      <TasksTaskFilters v-model:filter="currentFilter" />
    </header>

    <!-- Board -->
    <TasksTaskBoard :tasks="filteredTasks" class="flex-1" @update="handleTaskUpdate" />
  </div>
</template>

<script setup lang="ts">
import type { Task, TaskStatus } from '~/types'

const { tasks: initialTasks } = useMockData()

const tasks = ref<Task[]>([...initialTasks])
const currentFilter = ref('all')

const filteredTasks = computed(() => {
  if (currentFilter.value === 'all') return tasks.value
  if (currentFilter.value === 'agents') {
    return tasks.value.filter(t => t.assignee.type === 'agent')
  }
  if (currentFilter.value === 'humans') {
    return tasks.value.filter(t => t.assignee.type === 'human')
  }
  return tasks.value
})

const taskCounts = computed(() => ({
  total: tasks.value.length,
  done: tasks.value.filter(t => t.status === 'done').length,
}))

const handleTaskUpdate = (taskId: string, newStatus: TaskStatus) => {
  const task = tasks.value.find(t => t.id === taskId)
  if (task) {
    task.status = newStatus
    if (newStatus === 'done') {
      task.completedAt = new Date()
    }
  }
}
</script>
