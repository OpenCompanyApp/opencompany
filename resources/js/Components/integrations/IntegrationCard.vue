<template>
  <div
    :class="[
      'flex items-center gap-4 p-4 rounded-lg border bg-white dark:bg-neutral-900 transition-colors cursor-pointer',
      integration.installed
        ? 'border-green-200 dark:border-green-900/50'
        : 'border-neutral-200 dark:border-neutral-800 hover:border-neutral-300 dark:hover:border-neutral-700',
    ]"
    @click="handleClick"
  >
    <div
      :class="[
        'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
        integration.installed
          ? 'bg-green-100 dark:bg-green-900/30'
          : 'bg-neutral-100 dark:bg-neutral-800',
      ]"
    >
      <Icon
        :name="integration.icon"
        :class="[
          'w-5 h-5',
          integration.installed
            ? 'text-green-600 dark:text-green-400'
            : 'text-neutral-600 dark:text-neutral-400',
        ]"
      />
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ integration.name }}</p>
        <span
          v-if="integration.popular"
          class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400"
        >
          Popular
        </span>
      </div>
      <p class="text-xs text-neutral-500 dark:text-neutral-400 truncate">{{ integration.description }}</p>
    </div>
    <button
      v-if="!integration.installed"
      type="button"
      class="px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors shrink-0"
      @click.stop="$emit('install', integration)"
    >
      Install
    </button>
    <div v-else class="flex items-center gap-2 shrink-0">
      <span class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
        <Icon name="ph:check-circle-fill" class="w-3.5 h-3.5" />
        Installed
      </span>
      <button
        type="button"
        class="p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
        title="Configure"
        @click.stop="$emit('configure', integration)"
      >
        <Icon name="ph:gear" class="w-4 h-4" />
      </button>
      <button
        type="button"
        class="p-1 text-neutral-400 hover:text-red-500 transition-colors"
        title="Uninstall"
        @click.stop="$emit('uninstall', integration)"
      >
        <Icon name="ph:trash" class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'

export interface Integration {
  id: string
  name: string
  icon: string
  description: string
  category?: string
  installed: boolean
  popular?: boolean
  configurable?: boolean
}

defineProps<{
  integration: Integration
}>()

const emit = defineEmits<{
  install: [integration: Integration]
  uninstall: [integration: Integration]
  configure: [integration: Integration]
  click: [integration: Integration]
}>()

const handleClick = () => {
  // Could open a detail modal in the future
}
</script>
