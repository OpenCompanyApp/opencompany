<template>
  <div class="flex items-center justify-between px-4 py-2.5 border-t border-olympus-border text-xs text-olympus-text-muted bg-olympus-surface/50">
    <div class="flex items-center gap-4">
      <span class="flex items-center gap-1.5">
        <kbd class="px-1.5 py-0.5 bg-olympus-elevated border border-olympus-border rounded text-[10px] font-mono">↑</kbd>
        <kbd class="px-1.5 py-0.5 bg-olympus-elevated border border-olympus-border rounded text-[10px] font-mono">↓</kbd>
        <span class="text-olympus-text-subtle">navigate</span>
      </span>
      <span class="flex items-center gap-1.5">
        <kbd class="px-1.5 py-0.5 bg-olympus-elevated border border-olympus-border rounded text-[10px] font-mono">↵</kbd>
        <span class="text-olympus-text-subtle">select</span>
      </span>
      <span class="flex items-center gap-1.5">
        <kbd class="px-1.5 py-0.5 bg-olympus-elevated border border-olympus-border rounded text-[10px] font-mono">esc</kbd>
        <span class="text-olympus-text-subtle">close</span>
      </span>
    </div>
    <span class="text-olympus-text-subtle font-medium">{{ title }}</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
}>(), {
  title: 'Command Palette',
})
</script>
