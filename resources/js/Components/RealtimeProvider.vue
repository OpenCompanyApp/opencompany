<template>
  <div class="contents">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRealtime } from '@/composables/useRealtime'

const { connect, isConnected, channel, privateChannel, emit } = useRealtime()

// Auto-connect and subscribe to global channels
onMounted(() => {
  connect()

  // Public: agent status updates
  const agentsCh = channel('agents')
  console.log('[Realtime] Subscribed to agents channel', !!agentsCh)
  agentsCh?.listen('.AgentStatusUpdated', (data: unknown) => {
    console.log('[Realtime] AgentStatusUpdated', data)
    emit('agent:status', data)
  })

  // Private: task updates
  const tasksCh = privateChannel('tasks')
  console.log('[Realtime] Subscribed to tasks channel', !!tasksCh)
  tasksCh?.listen('.TaskUpdated', (data: unknown) => {
    console.log('[Realtime] TaskUpdated', data)
    emit('task:updated', data)
  })
})

// Expose connection status for debugging
defineExpose({
  isConnected,
})
</script>
