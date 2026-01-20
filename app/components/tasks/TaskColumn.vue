<template>
  <div
    class="w-80 flex flex-col shrink-0"
    @dragover.prevent="isDragOver = true"
    @dragleave="isDragOver = false"
    @drop="handleDrop"
  >
    <!-- Column Header -->
    <div class="flex items-center gap-2 mb-4 px-1">
      <Icon
        :name="icon"
        :class="[
          'w-5 h-5',
          status === 'done' ? 'text-green-400' : status === 'in_progress' ? 'text-olympus-primary' : 'text-olympus-text-muted'
        ]"
      />
      <h3 class="font-semibold">{{ title }}</h3>
      <span class="text-sm text-olympus-text-muted">({{ tasks.length }})</span>
    </div>

    <!-- Cards Container -->
    <div
      :class="[
        'flex-1 space-y-3 overflow-y-auto rounded-2xl p-1 -m-1 transition-colors duration-200',
        isDragOver ? 'bg-olympus-primary/10 ring-2 ring-olympus-primary/30 ring-inset' : ''
      ]"
    >
      <TasksTaskCard
        v-for="task in tasks"
        :key="task.id"
        :task="task"
        draggable="true"
        @dragstart="handleDragStart($event, task)"
        @dragend="handleDragEnd"
      />

      <!-- Empty State -->
      <div
        v-if="tasks.length === 0"
        class="flex flex-col items-center justify-center py-12 text-olympus-text-muted"
      >
        <Icon name="ph:tray" class="w-10 h-10 mb-2 opacity-50" />
        <p class="text-sm">No tasks</p>
      </div>
    </div>

    <!-- Add Task Button -->
    <button
      class="mt-4 flex items-center justify-center gap-2 w-full py-3 rounded-xl border-2 border-dashed border-olympus-border hover:border-olympus-primary hover:bg-olympus-primary/5 transition-colors text-olympus-text-muted hover:text-olympus-primary"
    >
      <Icon name="ph:plus" class="w-4 h-4" />
      <span class="text-sm font-medium">Add task</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import type { Task, TaskStatus } from '~/types'

const props = defineProps<{
  title: string
  icon: string
  tasks: Task[]
  status: TaskStatus
}>()

const emit = defineEmits<{
  drop: [taskId: string, newStatus: TaskStatus]
}>()

const isDragOver = ref(false)
const draggedTaskId = ref<string | null>(null)

const handleDragStart = (event: DragEvent, task: Task) => {
  if (event.dataTransfer) {
    event.dataTransfer.setData('taskId', task.id)
    event.dataTransfer.effectAllowed = 'move'
  }
  draggedTaskId.value = task.id
}

const handleDragEnd = () => {
  draggedTaskId.value = null
  isDragOver.value = false
}

const handleDrop = (event: DragEvent) => {
  isDragOver.value = false
  const taskId = event.dataTransfer?.getData('taskId')
  if (taskId) {
    emit('drop', taskId, props.status)
  }
}
</script>
