<template>
  <div class="org-chart-node flex flex-col items-center">
    <!-- Node card -->
    <div
      tabindex="0"
      :class="[
        'relative px-4 py-3 rounded-xl border cursor-pointer min-w-[180px]',
        'transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]',
        'bg-white dark:bg-neutral-900 hover:border-neutral-900/50 hover:shadow-md hover:shadow-neutral-900/10',
        'hover:scale-[1.02] hover:-translate-y-0.5 active:scale-[0.99]',
        'focus:outline-none focus:ring-2 focus:ring-neutral-400 focus:ring-offset-2 dark:focus:ring-offset-neutral-900',
        isRoot ? 'border-neutral-900 dark:border-white' : 'border-neutral-200 dark:border-neutral-700',
      ]"
    >
      <div class="flex items-center gap-3">
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
          <div class="flex items-center gap-1.5">
            <Icon
              :name="node.type === 'human' ? 'ph:user' : 'ph:robot'"
              class="w-3.5 h-3.5 text-neutral-500 dark:text-neutral-300 shrink-0"
            />
            <Link
              :href="workspacePath(node.type === 'agent' ? `/agent/${node.id}` : `/profile/${node.id}`)"
              class="font-medium text-neutral-900 dark:text-white truncate text-sm hover:text-neutral-900 dark:hover:text-white transition-colors duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
              @click.stop
            >
              {{ node.name }}
            </Link>
          </div>
          <p v-if="node.type === 'agent' && node.agentType" class="text-xs text-neutral-500 dark:text-neutral-300 capitalize">
            {{ node.agentType }}
          </p>
          <p v-else-if="node.email" class="text-xs text-neutral-500 dark:text-neutral-300 truncate">
            {{ node.email }}
          </p>
        </div>
      </div>

      <!-- Ephemeral badge -->
      <span
        v-if="node.isEphemeral"
        class="absolute -top-2 -right-2 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-500/20 text-amber-400 border border-amber-500/30"
      >
        Ephemeral
      </span>
    </div>

    <!-- Connector line down to children -->
    <div
      v-if="node.children.length > 0"
      class="w-0.5 h-6 bg-neutral-200 dark:bg-neutral-600"
    />

    <!-- Children row -->
    <div
      v-if="node.children.length > 0"
      class="relative flex items-start gap-4"
    >
      <!-- Horizontal connector line -->
      <div
        v-if="node.children.length > 1"
        class="absolute top-0 h-0.5 bg-neutral-200 dark:bg-neutral-600"
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
        <div class="w-0.5 h-6 bg-neutral-200 dark:bg-neutral-600" />
        <ChartNode :node="child" :is-root="false" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useWorkspace } from '@/composables/useWorkspace'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'

defineOptions({ name: 'ChartNode' })

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
  isRoot: boolean
}>()

// Convert OrgNode to User interface for AgentAvatar
const nodeAsUser = computed(() => ({
  id: props.node.id,
  name: props.node.name,
  type: props.node.type,
  agentType: props.node.agentType,
  status: props.node.status,
  currentTask: props.node.currentTask,
}))
</script>
