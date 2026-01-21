<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import AppSidebar from '@/Components/layout/AppSidebar.vue'
import CommandPalette from '@/Components/layout/CommandPalette.vue'
import RealtimeProvider from '@/Components/RealtimeProvider.vue'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'
import { usePresence } from '@/composables/usePresence'
import { usePage } from '@inertiajs/vue3'

useKeyboardShortcuts()

// Get current user ID from Inertia page props
const page = usePage()
const userId = (page.props.auth as any)?.user?.id || 'guest'

// Initialize presence tracking
const { initPresence, cleanup } = usePresence(userId)

onMounted(() => {
  initPresence()
})

onUnmounted(() => {
  cleanup()
})
</script>

<template>
  <RealtimeProvider>
    <div class="flex h-screen bg-white text-gray-900 overflow-hidden">
      <AppSidebar />
      <main class="flex-1 flex flex-col overflow-hidden">
        <slot />
      </main>
      <CommandPalette />
    </div>
  </RealtimeProvider>
</template>
