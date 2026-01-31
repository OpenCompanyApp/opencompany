<template>
  <div class="p-4 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
    <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Quick Actions</h3>
    <div class="space-y-1">
      <button
        v-for="action in actions"
        :key="action.id"
        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150 group"
        @click="emit('actionClick', action.id)"
      >
        <Icon :name="action.icon" class="w-4 h-4 text-neutral-500 dark:text-neutral-400 group-hover:text-neutral-700 dark:group-hover:text-neutral-200" />
        <span class="text-sm text-neutral-700 dark:text-neutral-300 group-hover:text-neutral-900 dark:group-hover:text-white">{{ action.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'

interface QuickAction {
  id: string
  label: string
  icon: string
}

const actions: QuickAction[] = [
  { id: 'new-channel', label: 'New channel', icon: 'ph:hash' },
  { id: 'spawn-agent', label: 'Spawn agent', icon: 'ph:robot' },
  { id: 'create-task', label: 'Create task', icon: 'ph:check-square' },
  { id: 'new-document', label: 'New document', icon: 'ph:file-plus' },
]

const emit = defineEmits<{
  actionClick: [actionId: string]
}>()
</script>
