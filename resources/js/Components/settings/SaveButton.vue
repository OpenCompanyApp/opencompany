<template>
  <button
    type="button"
    :class="[
      'flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors duration-150',
      saved
        ? 'text-green-600 dark:text-green-400'
        : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
    ]"
    :disabled="saving"
    @click="$emit('click')"
  >
    <!-- Spinner -->
    <svg v-if="saving" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="opacity-25" />
      <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <!-- Check -->
    <svg v-else-if="saved" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 6L9 17l-5-5" />
    </svg>
    <!-- Save icon -->
    <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
      <polyline points="17,21 17,13 7,13 7,21" />
      <polyline points="7,3 7,8 15,8" />
    </svg>
    {{ saving ? 'Saving...' : saved ? 'Saved' : 'Save' }}
  </button>
</template>

<script setup lang="ts">
defineProps<{
  saving: boolean
  saved: boolean
}>()

defineEmits<{
  click: []
}>()
</script>
