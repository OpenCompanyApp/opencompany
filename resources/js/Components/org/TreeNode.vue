<template>
  <div class="org-tree-node">
    <div class="flex items-start">
      <!-- Connector lines -->
      <div v-if="depth > 0" class="flex items-center mr-2">
        <div
          v-for="i in depth"
          :key="i"
          class="w-6 h-full border-l border-neutral-200 dark:border-neutral-700"
        />
        <div class="w-6 border-t border-neutral-200 dark:border-neutral-700" />
      </div>

      <!-- Node card -->
      <div
        role="button"
        tabindex="0"
        :aria-expanded="node.children.length > 0 ? expanded : undefined"
        :aria-label="`${node.name}${node.children.length > 0 ? `, ${node.children.length} direct reports` : ''}`"
        :class="[
          'flex items-center gap-3 p-3 rounded-xl cursor-pointer',
          'transition-all duration-150 ease-out',
          'border hover:border-neutral-300 hover:shadow-sm',
          'focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 dark:focus:ring-offset-neutral-900',
          expanded ? 'bg-white dark:bg-neutral-900 border-neutral-200 dark:border-neutral-700' : 'bg-neutral-50 dark:bg-neutral-800 border-transparent',
        ]"
        @click="toggleExpand"
        @keydown.enter="toggleExpand"
        @keydown.space.prevent="toggleExpand"
      >
        <!-- Avatar -->
        <AgentAvatar
          :user="nodeAsUser"
          :src="node.avatar || undefined"
          size="md"
          :show-status="node.type === 'agent'"
          :show-tooltip="false"
        />

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <Link
              :href="`/profile/${node.id}`"
              class="font-medium text-neutral-900 dark:text-white truncate hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors duration-150"
              @click.stop
            >
              {{ node.name }}
            </Link>
            <span
              v-if="node.type === 'agent'"
              class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-700 dark:text-neutral-200"
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
          <p v-if="node.type === 'human' && node.email" class="text-xs text-neutral-500 dark:text-neutral-300 truncate">
            {{ node.email }}
          </p>
          <p v-else-if="node.currentTask" class="text-xs text-neutral-500 dark:text-neutral-300 truncate">
            {{ node.currentTask }}
          </p>
          <p v-else-if="node.type === 'agent'" class="text-xs text-neutral-400 dark:text-neutral-400">
            {{ formatStatus(node.status) }}
          </p>
        </div>

        <!-- Expand indicator -->
        <div v-if="node.children.length > 0" class="flex items-center gap-2">
          <span class="text-xs text-neutral-500 dark:text-neutral-300">
            {{ node.children.length }}
          </span>
          <Icon
            :name="expanded ? 'ph:caret-down' : 'ph:caret-right'"
            class="w-4 h-4 text-neutral-500 dark:text-neutral-300 transition-transform duration-150"
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
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'

defineOptions({ name: 'TreeNode' })

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

const toggleExpand = () => {
  if (props.node.children.length > 0) {
    expanded.value = !expanded.value
  }
}

// Convert OrgNode to User interface for AgentAvatar
const nodeAsUser = computed(() => ({
  id: props.node.id,
  name: props.node.name,
  type: props.node.type,
  agentType: props.node.agentType,
  status: props.node.status,
  currentTask: props.node.currentTask,
}))

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
