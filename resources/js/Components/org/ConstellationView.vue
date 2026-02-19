<template>
  <div class="constellation-wrapper relative w-full h-full" style="min-height: 500px;">
    <!-- PixiJS Canvas Container -->
    <div
      ref="canvasEl"
      class="absolute inset-0"
      :style="{ backgroundColor: isDark ? '#09090b' : '#f5f5f5' }"
    />

    <!-- Tooltip overlay -->
    <Transition name="fade">
      <div
        v-if="hoveredNode"
        :class="[
          'pointer-events-none absolute z-10 rounded-lg border backdrop-blur-sm p-3 shadow-xl max-w-xs',
          isDark
            ? 'border-neutral-700 bg-neutral-900/95'
            : 'border-neutral-200 bg-white/95',
        ]"
        :style="{
          left: `${tooltipPos.x + 12}px`,
          top: `${tooltipPos.y - 8}px`,
        }"
      >
        <div class="flex items-center gap-2 mb-1.5">
          <div
            class="w-2.5 h-2.5 rounded-full shrink-0"
            :style="{ backgroundColor: statusDotColor(hoveredNode.status) }"
          />
          <span :class="['font-medium text-sm', isDark ? 'text-white' : 'text-neutral-900']">
            {{ hoveredNode.name }}
          </span>
          <span
            v-if="hoveredNode.agentType"
            class="text-[10px] px-1.5 py-0.5 rounded font-medium"
            :class="agentTypeBadgeClass(hoveredNode.agentType)"
          >
            {{ capitalize(hoveredNode.agentType) }}
          </span>
        </div>

        <div v-if="hoveredNode.type === 'human'" :class="['text-xs', isDark ? 'text-neutral-400' : 'text-neutral-500']">
          {{ hoveredNode.email || 'Owner' }}
        </div>

        <div v-if="hoveredNode.type === 'agent'" class="space-y-1">
          <div :class="['text-xs', isDark ? 'text-neutral-400' : 'text-neutral-500']">
            Status: <span :class="isDark ? 'text-neutral-200' : 'text-neutral-800'">{{ capitalize(hoveredNode.status || 'unknown') }}</span>
          </div>
          <div v-if="hoveredNode.brain" :class="['text-xs', isDark ? 'text-neutral-400' : 'text-neutral-500']">
            Model: <span :class="isDark ? 'text-neutral-200' : 'text-neutral-800'">{{ shortenBrain(hoveredNode.brain) }}</span>
          </div>
          <div v-if="hoveredNode.currentTask" :class="['text-xs mt-1 line-clamp-2', isDark ? 'text-neutral-300' : 'text-neutral-600']">
            {{ hoveredNode.currentTask }}
          </div>
        </div>
      </div>
    </Transition>

    <!-- Loading overlay -->
    <div
      v-if="!isReady"
      class="absolute inset-0 flex items-center justify-center"
      :style="{ backgroundColor: isDark ? '#09090b' : '#f5f5f5' }"
    >
      <div class="flex flex-col items-center gap-3">
        <Icon name="ph:spinner" class="w-8 h-8 text-neutral-500 animate-spin" />
        <span class="text-sm text-neutral-500">Loading constellation...</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import { useConstellation, type ConstellationNode } from '@/composables/useConstellation'
import { shortenBrain } from '@/Components/org/constellation.config'
import { useColorMode } from '@/composables/useColorMode'
import { useWorkspace } from '@/composables/useWorkspace'

const props = defineProps<{
  nodes: ConstellationNode[]
}>()

const { isDark } = useColorMode()
const { workspacePath } = useWorkspace()
const canvasEl = ref<HTMLElement | null>(null)
const hoveredNode = ref<ConstellationNode | null>(null)
const tooltipPos = ref({ x: 0, y: 0 })
const nodesRef = ref(props.nodes)

watch(() => props.nodes, (val) => {
  nodesRef.value = val
  updateNodeData(val)
}, { deep: true })

const { init, isReady, updateNodeData } = useConstellation(
  canvasEl,
  nodesRef,
  isDark,
  (id: string) => {
    const node = nodesRef.value.find(n => n.id === id)
    router.visit(workspacePath(node?.type === 'agent' ? `/agent/${id}` : `/profile/${id}`))
  },
  (node: ConstellationNode | null, x: number, y: number) => {
    hoveredNode.value = node
    if (node) {
      tooltipPos.value = { x, y }
    }
  },
)

onMounted(() => {
  init()
})

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ')

const statusDotColor = (status: string | null): string => {
  const colors: Record<string, string> = {
    working: '#22c55e',
    idle: '#a3a3a3',
    online: '#22c55e',
    busy: '#f59e0b',
    sleeping: '#818cf8',
    awaiting_approval: '#f59e0b',
    awaiting_delegation: '#6366f1',
    paused: '#a3a3a3',
    offline: '#525252',
  }
  return colors[status || ''] || '#525252'
}

const agentTypeBadgeClass = (type: string): string => {
  const colors: Record<string, string> = {
    manager: isDark.value ? 'bg-blue-900/40 text-blue-300' : 'bg-blue-100 text-blue-700',
    coder: isDark.value ? 'bg-purple-900/40 text-purple-300' : 'bg-purple-100 text-purple-700',
    writer: isDark.value ? 'bg-green-900/40 text-green-300' : 'bg-green-100 text-green-700',
    analyst: isDark.value ? 'bg-orange-900/40 text-orange-300' : 'bg-orange-100 text-orange-700',
    creative: isDark.value ? 'bg-pink-900/40 text-pink-300' : 'bg-pink-100 text-pink-700',
    researcher: isDark.value ? 'bg-yellow-900/40 text-yellow-300' : 'bg-yellow-100 text-yellow-700',
    coordinator: isDark.value ? 'bg-cyan-900/40 text-cyan-300' : 'bg-cyan-100 text-cyan-700',
  }
  return colors[type] || (isDark.value ? 'bg-neutral-700 text-neutral-300' : 'bg-neutral-100 text-neutral-600')
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
