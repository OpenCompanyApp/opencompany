<template>
  <div class="mb-2 last:mb-0">
    <p class="px-3 py-2 text-xs font-semibold text-olympus-text-muted uppercase tracking-wider">
      {{ label }}
    </p>
    <div class="space-y-0.5">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string
}>()
</script>
