<template>
  <div class="contents">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRealtime } from '@/composables/useRealtime'
import { useWorkspace } from '@/composables/useWorkspace'

const { connect, isConnected, channel, privateChannel, leaveChannel, emit } = useRealtime()
const { workspace } = useWorkspace()

const subscribeToWorkspaceChannels = (workspaceId: string) => {
  if (!workspaceId) return

  // Public: agent status updates
  const agentsChannelName = `workspace.${workspaceId}.agents`
  const agentsCh = channel(agentsChannelName)
  console.log('[Realtime] Subscribed to agents channel', agentsChannelName, !!agentsCh)
  agentsCh?.listen('.AgentStatusUpdated', (data: unknown) => {
    console.log('[Realtime] AgentStatusUpdated', data)
    emit('agent:status', data)
  })

  // Private: task updates
  const tasksChannelName = `workspace.${workspaceId}.tasks`
  const tasksCh = privateChannel(tasksChannelName)
  console.log('[Realtime] Subscribed to tasks channel', tasksChannelName, !!tasksCh)
  tasksCh?.listen('.TaskUpdated', (data: unknown) => {
    console.log('[Realtime] TaskUpdated', data)
    emit('task:updated', data)
  })
}

// Auto-connect and subscribe to workspace-scoped channels
onMounted(() => {
  connect()

  if (workspace.value?.id) {
    subscribeToWorkspaceChannels(workspace.value.id)
  }
})

// Re-subscribe if workspace changes
watch(() => workspace.value?.id, (newId, oldId) => {
  if (oldId) {
    leaveChannel(`workspace.${oldId}.agents`)
    leaveChannel(`workspace.${oldId}.tasks`)
  }
  if (newId) {
    subscribeToWorkspaceChannels(newId)
  }
})

// Expose connection status for debugging
defineExpose({
  isConnected,
})
</script>
