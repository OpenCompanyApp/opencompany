<template>
  <div class="rounded-md border border-[var(--tg-border)] bg-[var(--tg-section)] p-3">
    <div class="text-[11px] uppercase tracking-wide text-[var(--tg-muted)]">{{ label }}</div>
    <div class="mt-1 text-lg font-semibold">{{ value }}</div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
}>()
</script>
