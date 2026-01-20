<template>
  <div class="flex h-screen bg-olympus-bg text-olympus-text overflow-hidden">
    <LayoutAppSidebar />
    <main class="flex-1 flex flex-col overflow-hidden">
      <slot />
    </main>
    <LayoutCommandPalette />
  </div>
</template>

<script setup lang="ts">
import { useKeyboardShortcuts } from '~/composables/useKeyboardShortcuts'

useKeyboardShortcuts()
</script>
