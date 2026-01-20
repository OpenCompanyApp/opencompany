<template>
  <div class="card-gradient bg-olympus-surface rounded-xl p-4">
    <h2 class="font-semibold text-sm mb-4 text-gradient">Quick Actions</h2>

    <div class="space-y-1.5">
      <button
        v-for="action in actions"
        :key="action.label"
        class="w-full flex items-center gap-3 p-3 rounded-lg bg-olympus-bg hover:bg-olympus-elevated border border-olympus-border-subtle hover:border-olympus-border transition-all duration-200 text-left group outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-surface active:scale-[0.99] hover:shadow-lg hover:shadow-olympus-primary/5"
        @click="action.action"
      >
        <div
          :class="[
            'w-10 h-10 rounded-lg flex items-center justify-center transition-transform duration-150 group-hover:scale-105',
            action.bgClass
          ]"
        >
          <Icon :name="action.icon" :class="['w-5 h-5', action.iconClass]" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <p class="font-medium text-sm">{{ action.label }}</p>
            <span v-if="action.shortcut" class="text-[10px] text-olympus-text-subtle bg-olympus-surface px-1.5 py-0.5 rounded border border-olympus-border font-mono opacity-0 group-hover:opacity-100 transition-opacity">
              {{ action.shortcut }}
            </span>
          </div>
          <p class="text-xs text-olympus-text-muted">{{ action.description }}</p>
        </div>
        <Icon
          name="ph:caret-right"
          class="w-4 h-4 text-olympus-text-subtle opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all duration-150"
        />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const actions = [
  {
    label: 'New Channel',
    description: 'Create a collaboration space',
    icon: 'ph:chat-circle-plus-fill',
    bgClass: 'bg-blue-500/20',
    iconClass: 'text-blue-400',
    shortcut: '⌘N',
    action: () => {},
  },
  {
    label: 'Spawn Agent',
    description: 'Deploy a new AI worker',
    icon: 'ph:robot-fill',
    bgClass: 'bg-cyan-500/20',
    iconClass: 'text-cyan-400',
    shortcut: '⌘A',
    action: () => {},
  },
  {
    label: 'Create Task',
    description: 'Assign work to your team',
    icon: 'ph:check-square-fill',
    bgClass: 'bg-green-500/20',
    iconClass: 'text-green-400',
    shortcut: '⌘T',
    action: () => {},
  },
  {
    label: 'New Document',
    description: 'Write a new document',
    icon: 'ph:file-plus-fill',
    bgClass: 'bg-amber-500/20',
    iconClass: 'text-amber-400',
    shortcut: '⌘D',
    action: () => {},
  },
]
</script>
