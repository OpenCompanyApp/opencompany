<template>
  <div class="min-h-screen bg-white dark:bg-neutral-900 p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Organization</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-300 mt-1">
            View and manage your organizational hierarchy
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Button
            :variant="viewMode === 'tree' ? 'primary' : 'secondary'"
            size="sm"
            @click="viewMode = 'tree'"
          >
            <Icon name="ph:tree-structure" class="w-4 h-4 mr-1.5" />
            Tree View
          </Button>
          <Button
            :variant="viewMode === 'chart' ? 'primary' : 'secondary'"
            size="sm"
            @click="viewMode = 'chart'"
          >
            <Icon name="ph:chart-bar-horizontal" class="w-4 h-4 mr-1.5" />
            Chart View
          </Button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-8">
        <div class="space-y-4">
          <Skeleton v-for="i in 4" :key="i" preset="avatar-text" />
        </div>
      </div>

      <!-- Tree View -->
      <div v-else-if="viewMode === 'tree'" class="overflow-x-auto pb-8">
        <div class="min-w-max">
          <OrgTreeNode
            v-for="node in hierarchy"
            :key="node.id"
            :node="node"
            :depth="0"
          />
        </div>
      </div>

      <!-- Chart View (horizontal org chart) -->
      <div v-else class="overflow-x-auto pb-8">
        <div class="flex flex-col items-center min-w-max">
          <OrgChartNode
            v-for="node in hierarchy"
            :key="node.id"
            :node="node"
            :is-root="true"
          />
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-8 pt-8 border-t border-neutral-200 dark:border-neutral-700">
        <StatCard
          label="Total Members"
          :value="stats.totalMembers"
          icon="ph:users"
        />
        <StatCard
          label="Humans"
          :value="stats.humans"
          icon="ph:user"
        />
        <StatCard
          label="Agents"
          :value="stats.agents"
          icon="ph:robot"
        />
        <StatCard
          label="Active Agents"
          :value="stats.activeAgents"
          icon="ph:activity"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import StatCard from '@/Components/shared/StatCard.vue'
import OrgTreeNode from '@/Components/org/TreeNode.vue'
import OrgChartNode from '@/Components/org/ChartNode.vue'

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

const viewMode = ref<'tree' | 'chart'>('tree')
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

const fetchHierarchy = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/users')
    const users = await response.json()

    // Build hierarchy from flat user list
    // Users with managerId = null are root nodes
    const buildHierarchy = (users: any[]): OrgNode[] => {
      const nodeMap = new Map<string, OrgNode>()

      // First pass: create all nodes
      users.forEach((user: any) => {
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

      return roots
    }

    hierarchy.value = buildHierarchy(users)
  } catch (error) {
    console.error('Failed to fetch hierarchy:', error)
    hierarchy.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchHierarchy()
})
</script>
