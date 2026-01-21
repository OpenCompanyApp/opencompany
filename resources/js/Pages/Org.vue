<template>
  <div class="min-h-screen bg-white p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Organization</h1>
          <p class="text-sm text-gray-500 mt-1">
            View and manage your organizational hierarchy
          </p>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
              viewMode === 'tree'
                ? 'bg-gray-900 text-white'
                : 'bg-gray-50 text-gray-500 hover:text-gray-900',
            ]"
            @click="viewMode = 'tree'"
          >
            <Icon name="ph:tree-structure" class="w-4 h-4 mr-1.5 inline" />
            Tree View
          </button>
          <button
            type="button"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
              viewMode === 'chart'
                ? 'bg-gray-900 text-white'
                : 'bg-gray-50 text-gray-500 hover:text-gray-900',
            ]"
            @click="viewMode = 'chart'"
          >
            <Icon name="ph:chart-bar-horizontal" class="w-4 h-4 mr-1.5 inline" />
            Chart View
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <Icon name="ph:spinner" class="w-8 h-8 animate-spin text-gray-900" />
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
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-8 pt-8 border-t border-gray-200">
        <div class="bg-gray-50 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
              <Icon name="ph:users" class="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.totalMembers }}</p>
              <p class="text-xs text-gray-500">Total Members</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center">
              <Icon name="ph:user" class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.humans }}</p>
              <p class="text-xs text-gray-500">Humans</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center">
              <Icon name="ph:robot" class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.agents }}</p>
              <p class="text-xs text-gray-500">Agents</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center">
              <Icon name="ph:activity" class="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-gray-900">{{ stats.activeAgents }}</p>
              <p class="text-xs text-gray-500">Active Agents</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
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
  isTemporary: boolean | null
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
    const response = await fetch('/api/org/hierarchy')
    const data = await response.json()
    hierarchy.value = data
  } catch (error) {
    console.error('Failed to fetch hierarchy:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchHierarchy()
})
</script>
