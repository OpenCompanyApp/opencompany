<template>
  <div class="flex-1 overflow-x-auto p-6">
    <div class="flex gap-6 h-full min-w-max">
      <TasksTaskColumn
        v-for="column in columns"
        :key="column.status"
        :title="column.title"
        :icon="column.icon"
        :tasks="tasksByStatus[column.status]"
        :status="column.status"
        @drop="handleDrop"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Task, TaskStatus } from '~/types'

const props = defineProps<{
  tasks: Task[]
}>()

const emit = defineEmits<{
  update: [taskId: string, newStatus: TaskStatus]
}>()

const columns = [
  { status: 'backlog' as TaskStatus, title: 'Backlog', icon: 'ph:circle-dashed' },
  { status: 'in_progress' as TaskStatus, title: 'In Progress', icon: 'ph:circle-half' },
  { status: 'done' as TaskStatus, title: 'Done', icon: 'ph:check-circle' },
]

const tasksByStatus = computed(() => {
  const grouped: Record<TaskStatus, Task[]> = {
    backlog: [],
    in_progress: [],
    done: [],
  }

  props.tasks.forEach(task => {
    grouped[task.status].push(task)
  })

  return grouped
})

const handleDrop = (taskId: string, newStatus: TaskStatus) => {
  emit('update', taskId, newStatus)
}
</script>
