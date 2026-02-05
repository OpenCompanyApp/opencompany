<template>
  <nav
    :class="[
      'flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-neutral-300 scrollbar-track-transparent',
      collapsed ? 'px-2 py-2' : 'px-2 py-1'
    ]"
  >
    <!-- Dashboard (always at top) -->
    <div class="space-y-0.5">
      <Link
        href="/"
        :class="[
          'group flex items-center rounded-lg transition-colors duration-150 outline-none',
          collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
          isActive('/')
            ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
            : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-700 dark:text-neutral-200',
          'focus-visible:ring-1 focus-visible:ring-neutral-400',
        ]"
      >
        <Icon
          :name="isActive('/') ? 'ph:house-fill' : 'ph:house'"
          class="w-[18px] h-[18px] shrink-0"
        />
        <span v-if="!collapsed" class="text-sm truncate flex-1">Dashboard</span>
      </Link>
    </div>

    <!-- Separator -->
    <div class="my-2.5 mx-3 border-t border-neutral-200 dark:border-neutral-800" />

    <!-- Agent Work Items -->
    <div class="space-y-0.5">
      <NavItem v-for="item in agentWorkItems" :key="item.to" :item="item" :collapsed="collapsed" />
    </div>

    <!-- Separator -->
    <div class="my-2.5 mx-3 border-t border-neutral-200 dark:border-neutral-800" />

    <!-- Office Items -->
    <div class="space-y-0.5">
      <NavItem v-for="item in officeItems" :key="item.to" :item="item" :collapsed="collapsed" />
    </div>

    <!-- Separator -->
    <div class="my-2.5 mx-3 border-t border-neutral-200 dark:border-neutral-800" />

    <!-- Monitoring Items -->
    <div class="space-y-0.5">
      <NavItem v-for="item in monitoringItems" :key="item.to" :item="item" :collapsed="collapsed" />
    </div>

    <!-- Agents Section (collapsible) -->
    <CollapsibleRoot v-model:open="agentsSectionOpen" class="mt-3 mb-3">
      <div v-if="!collapsed" class="flex items-center gap-1 px-3">
        <CollapsibleTrigger
          class="flex-1 flex items-center gap-2 py-1.5 text-xs font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors duration-150"
        >
          <Icon
            name="ph:caret-down"
            :class="[
              'w-3 h-3 transition-transform duration-150',
              !agentsSectionOpen && '-rotate-90'
            ]"
          />
          <span>Agents</span>
          <span
            v-if="onlineAgents > 0"
            class="ml-auto flex items-center gap-1.5 text-neutral-400 dark:text-neutral-500"
          >
            <span class="w-1.5 h-1.5 bg-green-500 rounded-full" />
            {{ onlineAgents }}
          </span>
        </CollapsibleTrigger>
        <button
          type="button"
          class="p-1 rounded-md text-neutral-400 dark:text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-200 hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors duration-150"
          title="Spawn new agent"
          @click.stop="emit('spawnAgent')"
        >
          <Icon name="ph:plus" class="w-3.5 h-3.5" />
        </button>
      </div>

      <CollapsibleContent>
        <div class="mt-1 space-y-0.5">
          <template v-if="loading">
            <div
              v-for="i in 2"
              :key="i"
              class="flex items-center gap-2 px-3 py-1.5 animate-pulse"
            >
              <div class="w-5 h-5 rounded-full bg-neutral-200 dark:bg-neutral-800" />
              <div class="h-3 w-20 bg-neutral-200 dark:bg-neutral-800 rounded" />
            </div>
          </template>
          <template v-else>
            <button
              v-for="agent in displayedAgents"
              :key="agent.id"
              class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-neutral-600 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-white transition-colors duration-150 text-left"
              @click="handleAgentClick(agent)"
            >
              <AgentAvatar :user="agent" size="xs" :show-status="true" />
              <span class="truncate flex-1">{{ agent.name }}</span>
              <span
                v-if="agent.status === 'busy'"
                class="w-1.5 h-1.5 bg-amber-500 rounded-full shrink-0"
              />
            </button>
          </template>
        </div>
      </CollapsibleContent>
    </CollapsibleRoot>
  </nav>
</template>

<script setup lang="ts">
import { ref, computed, h, defineComponent } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { CollapsibleRoot, CollapsibleTrigger, CollapsibleContent } from 'reka-ui'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Icon from '@/Components/shared/Icon.vue'

// Types
interface NavItemType {
  to: string
  icon: string
  iconActive: string
  label: string
  badge?: number
}

interface Agent {
  id: string
  name: string
  status?: 'online' | 'busy' | 'idle' | 'offline'
  currentTask?: string
  role?: string
  avatar?: string
  isAI: boolean
}

// Props
const props = withDefaults(defineProps<{
  collapsed?: boolean
  loading?: boolean
  showShortcuts?: boolean
  showQuickActions?: boolean
  maxVisibleAgents?: number
  agents?: Agent[]
  totalAgents?: number
  onlineAgents?: number
}>(), {
  collapsed: false,
  loading: false,
  showShortcuts: false,
  showQuickActions: false,
  maxVisibleAgents: 4,
  agents: () => [],
  totalAgents: 0,
  onlineAgents: 0,
})

// Emits
const emit = defineEmits<{
  spawnAgent: []
}>()

// Composables
const page = usePage()

// State
const agentsSectionOpen = ref(false)

// Agent Work - Tasks, approvals, organization
const agentWorkItems = ref<NavItemType[]>([
  { to: '/tasks', icon: 'ph:check-square', iconActive: 'ph:check-square-fill', label: 'Tasks' },
  { to: '/approvals', icon: 'ph:seal-check', iconActive: 'ph:seal-check-fill', label: 'Approvals', badge: 3 },
  { to: '/org', icon: 'ph:tree-structure', iconActive: 'ph:tree-structure-fill', label: 'Organization' },
])

// Office - Daily productivity tools
const officeItems = ref<NavItemType[]>([
  { to: '/chat', icon: 'ph:chat-circle', iconActive: 'ph:chat-circle-fill', label: 'Chat', badge: 15 },
  { to: '/docs', icon: 'ph:file-text', iconActive: 'ph:file-text-fill', label: 'Docs' },
  { to: '/tables', icon: 'ph:table', iconActive: 'ph:table-fill', label: 'Tables' },
  { to: '/calendar', icon: 'ph:calendar', iconActive: 'ph:calendar-fill', label: 'Calendar' },
  { to: '/lists', icon: 'ph:kanban', iconActive: 'ph:kanban-fill', label: 'Lists' },
])

// Monitoring - Activity feed
const monitoringItems = ref<NavItemType[]>([
  { to: '/activity', icon: 'ph:activity', iconActive: 'ph:activity-fill', label: 'Activity' },
])


// Computed
const agents = computed(() => props.agents)
const onlineAgents = computed(() => props.onlineAgents)

const displayedAgents = computed(() => {
  return agents.value.slice(0, props.maxVisibleAgents)
})

// Methods
const isActive = (path: string): boolean => {
  const currentUrl = page.url
  if (path === '/') return currentUrl === '/'
  return currentUrl.startsWith(path)
}

const handleAgentClick = (agent: Agent) => {
  if (agent.isAI) {
    router.visit(`/agent/${agent.id}`)
  } else {
    router.visit(`/profile/${agent.id}`)
  }
}

// NavItem subcomponent
const NavItem = defineComponent({
  name: 'NavItem',
  props: {
    item: { type: Object as () => NavItemType, required: true },
    collapsed: { type: Boolean, default: false },
  },
  setup(itemProps) {
    return () => h(Link, {
      href: itemProps.item.to,
      class: [
        'group flex items-center rounded-lg transition-colors duration-150 outline-none',
        itemProps.collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
        isActive(itemProps.item.to)
          ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
          : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
        'focus-visible:ring-1 focus-visible:ring-neutral-400',
      ],
    }, () => [
      h(Icon, {
        name: isActive(itemProps.item.to) ? itemProps.item.iconActive : itemProps.item.icon,
        class: 'w-[18px] h-[18px] shrink-0',
      }),
      !itemProps.collapsed && h('span', { class: 'text-sm truncate flex-1' }, itemProps.item.label),
      !itemProps.collapsed && itemProps.item.badge && itemProps.item.badge > 0 && h('span', {
        class: 'text-xs px-1.5 py-0.5 rounded-full bg-neutral-300 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300 font-medium',
      }, itemProps.item.badge > 99 ? '99+' : itemProps.item.badge),
    ])
  },
})

</script>

<style scoped>
/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: rgb(163 163 163 / 0.5);
  border-radius: 4px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: rgb(163 163 163 / 0.8);
}
</style>
