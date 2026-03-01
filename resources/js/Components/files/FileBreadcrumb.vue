<template>
  <nav class="flex items-center gap-1 text-sm text-neutral-500 dark:text-neutral-400 min-w-0 overflow-x-auto">
    <button
      class="shrink-0 hover:text-neutral-900 dark:hover:text-white transition-colors"
      @click="$emit('navigate', null)"
    >
      <Icon name="ph:house" class="w-4 h-4" />
    </button>

    <template v-for="(segment, idx) in segments" :key="segment.id">
      <Icon name="ph:caret-right" class="w-3 h-3 shrink-0 text-neutral-300 dark:text-neutral-600" />
      <button
        :class="[
          'shrink-0 truncate max-w-32 transition-colors',
          idx === segments.length - 1
            ? 'text-neutral-900 dark:text-white font-medium'
            : 'hover:text-neutral-900 dark:hover:text-white'
        ]"
        @click="$emit('navigate', segment.id)"
      >
        {{ segment.name }}
      </button>
    </template>
  </nav>
</template>

<script setup lang="ts">
import Icon from '@/Components/shared/Icon.vue'

defineProps<{
  segments: Array<{ id: string; name: string }>
}>()

defineEmits<{
  navigate: [folderId: string | null]
}>()
</script>
