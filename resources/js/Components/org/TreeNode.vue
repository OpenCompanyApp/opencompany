<template>
  <div class="org-tree-node">
    <!-- Node row -->
    <div class="flex items-center gap-2 relative">
      <!-- Connector line (horizontal dash for non-root) -->
      <div
        v-if="depth > 0"
        class="w-5 h-px bg-neutral-200 dark:bg-neutral-700 shrink-0"
      />

      <!-- Node card -->
      <div
        role="button"
        tabindex="0"
        :aria-expanded="node.children.length > 0 ? expanded : undefined"
        :aria-label="`${node.name}${node.children.length > 0 ? `, ${node.children.length} direct reports` : ''}`"
        :class="[
          'flex items-center gap-3 rounded-lg cursor-pointer',
          'transition-all duration-150 ease-out',
          'focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 dark:focus:ring-offset-neutral-900',
          depth === 0
            ? 'p-3.5 border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-sm'
            : 'p-2.5 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 rounded-lg',
        ]"
        @click="toggleExpand"
        @keydown.enter="toggleExpand"
        @keydown.space.prevent="toggleExpand"
      >
        <!-- Avatar -->
        <AgentAvatar
          :user="nodeAsUser"
          :src="node.avatar || undefined"
          :size="depth === 0 ? 'lg' : 'md'"
          :show-status="node.type === 'agent'"
          :show-tooltip="false"
        />

        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <!-- Status dot -->
            <div
              v-if="node.type === 'agent'"
              :class="[
                'w-2 h-2 rounded-full shrink-0',
                node.status === 'working' ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
              ]"
            />

            <Link
              :href="workspacePath(node.type === 'agent' ? `/agent/${node.id}` : `/profile/${node.id}`)"
              :class="[
                'font-medium truncate hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors duration-150',
                depth === 0
                  ? 'text-base text-neutral-900 dark:text-white'
                  : 'text-sm text-neutral-900 dark:text-white',
              ]"
              @click.stop
            >
              {{ node.name }}
            </Link>

            <!-- Agent type badge -->
            <span
              v-if="node.type === 'agent' && node.agentType"
              :class="[
                'shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium',
                agentTypeColor(node.agentType),
              ]"
            >
              {{ formatAgentType(node.agentType) }}
            </span>

            <!-- Human badge -->
            <span
              v-if="node.type === 'human'"
              class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-medium bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300"
            >
              Owner
            </span>
          </div>

          <!-- Secondary info -->
          <p
            v-if="node.currentTask"
            class="text-xs text-neutral-500 dark:text-neutral-400 truncate mt-0.5"
          >
            {{ node.currentTask }}
          </p>
          <p
            v-else-if="node.type === 'human' && node.email"
            class="text-xs text-neutral-500 dark:text-neutral-400 truncate mt-0.5"
          >
            {{ node.email }}
          </p>
          <p
            v-else-if="node.type === 'agent'"
            class="text-xs text-neutral-400 dark:text-neutral-500 mt-0.5"
          >
            {{ formatStatus(node.status) }}
          </p>
        </div>

        <!-- Expand indicator -->
        <div v-if="node.children.length > 0" class="flex items-center gap-1.5 shrink-0">
          <span class="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums">
            {{ node.children.length }}
          </span>
          <Icon
            :name="expanded ? 'ph:caret-down' : 'ph:caret-right'"
            class="w-3.5 h-3.5 text-neutral-400 dark:text-neutral-500 transition-transform duration-150"
          />
        </div>
      </div>
    </div>

    <!-- Children -->
    <div
      v-if="expanded && node.children.length > 0"
      :class="[
        'relative',
        depth === 0 ? 'ml-6 mt-1' : 'ml-5 mt-0.5',
      ]"
    >
      <!-- Vertical connector line -->
      <div
        class="absolute left-0 top-0 bottom-3 w-px bg-neutral-200 dark:bg-neutral-700"
      />

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
import { useWorkspace } from '@/composables/useWorkspace'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'

defineOptions({ name: 'TreeNode' })

const { workspacePath } = useWorkspace()

interface OrgNode {
  id: string
  name: string
  avatar: string | null
  type: 'human' | 'agent'
  agentType: string | null
  status: string | null
  currentTask: string | null
  email: string | null
  isEphemeral: boolean | null
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

const agentTypeColor = (type: string | null): string => {
  const colors: Record<string, string> = {
    manager: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    coder: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    writer: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    analyst: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
    creative: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300',
    researcher: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
    coordinator: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  }
  return colors[type || ''] || 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300'
}

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
  margin-top: 0.125rem;
}
</style>
