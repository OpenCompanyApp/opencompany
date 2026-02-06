<template>
  <div class="min-h-screen bg-white dark:bg-neutral-900 p-6">
    <div class="max-w-5xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Organization</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-300 mt-1">
            Team structure and hierarchy
          </p>
        </div>

        <!-- Inline stats -->
        <div v-if="!loading" class="flex items-center gap-4 text-sm text-neutral-500 dark:text-neutral-400">
          <div class="flex items-center gap-1.5">
            <Icon name="ph:users" class="w-4 h-4" />
            <span class="font-medium text-neutral-900 dark:text-white">{{ stats.totalMembers }}</span>
            <span>members</span>
          </div>
          <span class="text-neutral-300 dark:text-neutral-600">·</span>
          <div class="flex items-center gap-1.5">
            <Icon name="ph:user" class="w-4 h-4" />
            <span class="font-medium text-neutral-900 dark:text-white">{{ stats.humans }}</span>
            <span>human{{ stats.humans !== 1 ? 's' : '' }}</span>
          </div>
          <span class="text-neutral-300 dark:text-neutral-600">·</span>
          <div class="flex items-center gap-1.5">
            <Icon name="ph:robot" class="w-4 h-4" />
            <span class="font-medium text-neutral-900 dark:text-white">{{ stats.agents }}</span>
            <span>agent{{ stats.agents !== 1 ? 's' : '' }}</span>
          </div>
          <span class="text-neutral-300 dark:text-neutral-600">·</span>
          <div class="flex items-center gap-1.5">
            <div class="w-2 h-2 rounded-full bg-green-500" />
            <span class="font-medium text-neutral-900 dark:text-white">{{ stats.activeAgents }}</span>
            <span>active</span>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-8">
        <div class="space-y-4">
          <Skeleton v-for="i in 4" :key="i" preset="avatar-text" />
        </div>
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
import { ref, computed, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import OrgTreeNode from '@/Components/org/TreeNode.vue'
import { useApi } from '@/composables/useApi'

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

const { fetchUsers } = useApi()
const loading = ref(true)
const hierarchy = ref<OrgNode[]>([])

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

onMounted(() => {
  loadHierarchy()
})
</script>
