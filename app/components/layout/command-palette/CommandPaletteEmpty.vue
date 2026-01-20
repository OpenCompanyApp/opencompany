<template>
  <SharedEmptyState
    icon="ph:magnifying-glass"
    :title="title"
    :description="description"
    size="sm"
  />
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  description?: string
}>(), {
  title: 'No results found',
  description: 'Try searching with different keywords',
})
</script>
