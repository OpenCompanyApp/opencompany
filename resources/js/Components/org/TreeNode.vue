<template>
  <div class="org-tree-node">
    <div class="flex items-start">
      <!-- Connector lines -->
      <div v-if="depth > 0" class="flex items-center mr-2">
        <div
          v-for="i in depth"
          :key="i"
          class="w-6 h-full border-l border-gray-200"
        />
        <div class="w-6 border-t border-gray-200" />
      </div>

      <!-- Node card -->
      <div
        :class="[
          'flex items-center gap-3 p-3 rounded-xl cursor-pointer',
          'transition-all duration-150 ease-out',
          'border hover:border-gray-300 hover:shadow-sm',
          expanded ? 'bg-white border-gray-200' : 'bg-gray-50 border-transparent',
        ]"
        @click="expanded = !expanded"
      >
        <!-- Avatar -->
        <div class="relative">
          <div
            v-if="node.avatar"
            class="w-10 h-10 rounded-full overflow-hidden"
          >
            <img :src="node.avatar" :alt="node.name" class="w-full h-full object-cover" />
          </div>
          <div
            v-else
            :class="[
              'w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold',
              node.type === 'human' ? 'bg-blue-500' : agentColorClass,
            ]"
          >
            {{ node.name.charAt(0) }}
          </div>
          <!-- Status indicator for agents -->
          <span
            v-if="node.type === 'agent'"
            :class="[
              'absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white',
              statusColorClass,
            ]"
          />
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <Link
              :href="`/profile/${node.id}`"
              class="font-medium text-gray-900 truncate hover:text-gray-700 transition-colors duration-150"
              @click.stop
            >
              {{ node.name }}
            </Link>
            <span
              v-if="node.type === 'agent'"
              class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700"
            >
              {{ formatAgentType(node.agentType) }}
            </span>
            <span
              v-if="node.isTemporary"
              class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/20 text-amber-400"
            >
              Temporary
            </span>
          </div>
          <p v-if="node.type === 'human' && node.email" class="text-xs text-gray-500 truncate">
            {{ node.email }}
          </p>
          <p v-else-if="node.currentTask" class="text-xs text-gray-500 truncate">
            {{ node.currentTask }}
          </p>
          <p v-else-if="node.type === 'agent'" class="text-xs text-gray-400">
            {{ formatStatus(node.status) }}
          </p>
        </div>

        <!-- Expand indicator -->
        <div v-if="node.children.length > 0" class="flex items-center gap-2">
          <span class="text-xs text-gray-500">
            {{ node.children.length }}
          </span>
          <Icon
            :name="expanded ? 'ph:caret-down' : 'ph:caret-right'"
            class="w-4 h-4 text-gray-500 transition-transform duration-150"
          />
        </div>
      </div>
    </div>

    <!-- Children -->
    <div v-if="expanded && node.children.length > 0" class="mt-2 ml-4">
      <TreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'

interface OrgNode {
  id: string
  name: string
  avatar: string | null
  type: 'human' | 'agent'
  agentType: string | null
  status: string | null
  currentTask: string | null
  email: string | null
  isTemporary: boolean | null
  managerId: string | null
  children: OrgNode[]
}

const props = defineProps<{
  node: OrgNode
  depth: number
}>()

const expanded = ref(props.depth < 2) // Auto-expand first 2 levels

const agentColorClass = computed(() => {
  const colors: Record<string, string> = {
    manager: 'bg-purple-500',
    writer: 'bg-green-500',
    analyst: 'bg-cyan-500',
    creative: 'bg-pink-500',
    researcher: 'bg-amber-500',
    coder: 'bg-indigo-500',
    coordinator: 'bg-teal-500',
  }
  return colors[props.node.agentType || ''] || 'bg-gray-500'
})

const statusColorClass = computed(() => {
  const colors: Record<string, string> = {
    working: 'bg-green-400',
    idle: 'bg-amber-400',
    offline: 'bg-gray-400',
  }
  return colors[props.node.status || ''] || 'bg-gray-400'
})

const formatAgentType = (type: string | null) => {
  if (!type) return 'Agent'
  return type.charAt(0).toUpperCase() + type.slice(1)
}

const formatStatus = (status: string | null) => {
  if (!status) return 'Unknown'
  return status.charAt(0).toUpperCase() + status.slice(1)
}
</script>

<style scoped>
.org-tree-node + .org-tree-node {
  margin-top: 0.5rem;
}
</style>
