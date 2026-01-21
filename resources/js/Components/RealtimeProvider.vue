<template>
  <div class="contents">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRealtime } from '@/composables/useRealtime'

const { connect, isConnected } = useRealtime()

// Auto-connect on mount
onMounted(() => {
  connect()
})

// Expose connection status for debugging
defineExpose({
  isConnected,
})
</script>
