<template>
  <div class="org-chart-node flex flex-col items-center">
    <!-- Node card -->
    <div
      :class="[
        'relative px-4 py-3 rounded-xl border cursor-pointer min-w-[180px]',
        'transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]',
        'bg-white hover:border-gray-900/50 hover:shadow-md hover:shadow-gray-900/10',
        'hover:scale-[1.02] hover:-translate-y-0.5 active:scale-[0.99]',
        isRoot ? 'border-gray-900' : 'border-gray-200',
      ]"
    >
      <div class="flex items-center gap-3">
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
          <!-- Status indicator -->
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
          <div class="flex items-center gap-1.5">
            <Icon
              :name="node.type === 'human' ? 'ph:user' : 'ph:robot'"
              class="w-3.5 h-3.5 text-gray-500 shrink-0"
            />
            <Link
              :href="`/profile/${node.id}`"
              class="font-medium text-gray-900 truncate text-sm hover:text-gray-900 transition-colors duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
              @click.stop
            >
              {{ node.name }}
            </Link>
          </div>
          <p v-if="node.type === 'agent' && node.agentType" class="text-xs text-gray-500 capitalize">
            {{ node.agentType }}
          </p>
          <p v-else-if="node.email" class="text-xs text-gray-500 truncate">
            {{ node.email }}
          </p>
        </div>
      </div>

      <!-- Temporary badge -->
      <span
        v-if="node.isTemporary"
        class="absolute -top-2 -right-2 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30"
      >
        Temp
      </span>
    </div>

    <!-- Connector line down to children -->
    <div
      v-if="node.children.length > 0"
      class="w-0.5 h-6 bg-gray-200"
    />

    <!-- Children row -->
    <div
      v-if="node.children.length > 0"
      class="relative flex items-start gap-4"
    >
      <!-- Horizontal connector line -->
      <div
        v-if="node.children.length > 1"
        class="absolute top-0 h-0.5 bg-gray-200"
        :style="{
          left: `${100 / (node.children.length * 2)}%`,
          right: `${100 / (node.children.length * 2)}%`,
        }"
      />

      <div
        v-for="child in node.children"
        :key="child.id"
        class="flex flex-col items-center"
      >
        <!-- Vertical connector to child -->
        <div class="w-0.5 h-6 bg-gray-200" />
        <ChartNode :node="child" :is-root="false" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
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
  isRoot: boolean
}>()

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
</script>
