<template>
  <div class="h-full flex flex-col bg-white dark:bg-neutral-900">
    <!-- Header -->
    <header class="h-14 px-4 md:px-6 border-b border-neutral-200 dark:border-neutral-700 flex items-center gap-4 bg-white dark:bg-neutral-900 shrink-0">
      <span class="text-lg font-semibold text-neutral-900 dark:text-white">Organization</span>

      <span v-if="!loading" class="hidden md:inline-flex items-center gap-2.5 text-xs shrink-0">
        <span class="inline-flex items-center gap-1 text-neutral-500 dark:text-neutral-400" title="Humans">
          <Icon name="ph:user" class="w-3.5 h-3.5" />{{ stats.humans }}
        </span>
        <span class="inline-flex items-center gap-1 text-blue-500 dark:text-blue-400" title="Agents">
          <Icon name="ph:robot" class="w-3.5 h-3.5" />{{ stats.agents }}
        </span>
        <span class="inline-flex items-center gap-1 text-green-500 dark:text-green-400" title="Active">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500" />{{ stats.activeAgents }}
        </span>
      </span>

      <div class="ml-auto flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 rounded-lg p-0.5">
        <button
          :class="[
            'px-2.5 py-1 text-xs font-medium rounded-md transition-colors',
            viewMode === 'constellation'
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
          ]"
          @click="viewMode = 'constellation'"
        >
          <Icon name="ph:graph" class="w-3.5 h-3.5 inline mr-1" />
          Constellation
        </button>
        <button
          :class="[
            'px-2.5 py-1 text-xs font-medium rounded-md transition-colors',
            viewMode === 'tree'
              ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
              : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
          ]"
          @click="viewMode = 'tree'"
        >
          <Icon name="ph:tree-structure" class="w-3.5 h-3.5 inline mr-1" />
          Tree
        </button>
      </div>
    </header>

    <div :class="viewMode === 'constellation' ? 'flex-1 overflow-hidden' : 'max-w-5xl mx-auto w-full p-6'">

      <!-- Loading State -->
      <div v-if="loading" class="py-8">
        <div class="space-y-4">
          <Skeleton v-for="i in 4" :key="i" preset="avatar-text" />
        </div>
      </div>

      <!-- Constellation View -->
      <div
        v-else-if="viewMode === 'constellation'"
        class="relative w-full h-full overflow-hidden"
      >
        <ConstellationView :nodes="hierarchy" />
      </div>

      <!-- Tree View -->
      <div v-else class="pb-8">
        <OrgTreeNode
          v-for="node in hierarchy"
          :key="node.id"
          :node="node"
          :depth="0"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import OrgTreeNode from '@/Components/org/TreeNode.vue'
import ConstellationView from '@/Components/org/ConstellationView.vue'
import { useApi } from '@/composables/useApi'
import { useRealtime } from '@/composables/useRealtime'

interface OrgNode {
  id: string
  name: string
  avatar: string | null
  type: 'human' | 'agent'
  agentType: string | null
  brain: string | null
  status: string | null
  currentTask: string | null
  email: string | null
  isEphemeral: boolean | null
  managerId: string | null
  children: OrgNode[]
}

const { fetchUsers } = useApi()
const loading = ref(true)
const hierarchy = ref<OrgNode[]>([])
const viewMode = ref<'constellation' | 'tree'>('constellation')

const stats = computed(() => {
  const flatten = (nodes: OrgNode[]): OrgNode[] => {
    return nodes.reduce<OrgNode[]>((acc, node) => {
      return [...acc, node, ...flatten(node.children)]
    }, [])
  }

  const allNodes = flatten(hierarchy.value)
  const humans = allNodes.filter((n) => n.type === 'human')
  const agents = allNodes.filter((n) => n.type === 'agent')
  const activeAgents = agents.filter((a) => a.status === 'working')

  return {
    totalMembers: allNodes.length,
    humans: humans.length,
    agents: agents.length,
    activeAgents: activeAgents.length,
  }
})

const loadHierarchy = async () => {
  loading.value = true
  try {
    const { data, promise } = fetchUsers()
    await promise

    if (!data.value) {
      hierarchy.value = []
      return
    }

    // Filter out ephemeral users (e.g. Telegram integrations)
    const orgUsers = data.value.filter((u: any) => !u.isEphemeral)

    // Build hierarchy from flat user list
    const nodeMap = new Map<string, OrgNode>()

    // First pass: create all nodes
    orgUsers.forEach((user: any) => {
      nodeMap.set(user.id, {
        id: user.id,
        name: user.name,
        avatar: user.avatar || null,
        type: user.type || (user.isAI ? 'agent' : 'human'),
        agentType: user.agentType || user.role || null,
        brain: user.brain || null,
        status: user.status || null,
        currentTask: user.currentTask || null,
        email: user.email || null,
        isEphemeral: user.isEphemeral || false,
        managerId: user.managerId || null,
        children: [],
      })
    })

    // Second pass: build tree
    const roots: OrgNode[] = []
    nodeMap.forEach((node) => {
      if (node.managerId && nodeMap.has(node.managerId)) {
        nodeMap.get(node.managerId)!.children.push(node)
      } else {
        roots.push(node)
      }
    })

    hierarchy.value = roots
  } catch (error) {
    console.error('Failed to fetch hierarchy:', error)
    hierarchy.value = []
  } finally {
    loading.value = false
  }
}

// Real-time status updates
const { on } = useRealtime()
const unsubStatus = on('agent:status', (data: { id: string; status: string; currentTask?: string }) => {
  const updateNode = (nodes: OrgNode[]): boolean => {
    for (const node of nodes) {
      if (node.id === data.id) {
        node.status = data.status
        if (data.currentTask !== undefined) node.currentTask = data.currentTask
        return true
      }
      if (updateNode(node.children)) return true
    }
    return false
  }
  updateNode(hierarchy.value)
})

onMounted(() => {
  loadHierarchy()
})

onUnmounted(() => {
  unsubStatus()
})
</script>
