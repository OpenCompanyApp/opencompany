<template>
  <nav
    :class="[
      'flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent',
      sizeConfig[size].padding,
      collapsed && 'px-2'
    ]"
  >
    <!-- Main navigation items -->
    <div :class="['space-y-1', collapsed && 'space-y-2']">
      <Link
        v-for="item in navItems"
        :key="item.to"
        :href="item.to"
        :class="[
          'group flex items-center rounded-lg transition-colors duration-150 outline-none relative',
          sizeConfig[size].navItem,
          collapsed && 'justify-center p-2.5',
          isActive(item.to)
            ? 'bg-gray-100 text-gray-900'
            : 'hover:bg-gray-50 text-gray-500 hover:text-gray-900',
          'focus-visible:ring-1 focus-visible:ring-gray-400',
        ]"
      >
        <!-- Active indicator bar -->
        <div
          v-if="isActive(item.to) && !collapsed"
          class="absolute left-0 top-2 bottom-2 w-1 bg-gray-900 rounded-r transition-all duration-150"
        />

        <!-- Icon container -->
        <div
          :class="[
            'relative flex items-center justify-center shrink-0 transition-colors duration-150',
            sizeConfig[size].icon,
            !collapsed && 'mr-3',
            collapsed && isActive(item.to) && 'bg-gray-100 rounded-lg'
          ]"
        >
          <Icon
            :name="isActive(item.to) ? item.iconActive : item.icon"
            :class="[
              sizeConfig[size].iconSize,
              isActive(item.to)
                ? 'text-gray-900'
                : 'text-gray-500 group-hover:text-gray-900'
            ]"
          />

          <!-- Badge indicator for collapsed mode -->
          <span
            v-if="collapsed && item.badge && item.badge > 0"
            class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-gray-900 rounded-full ring-2 ring-white"
          />
        </div>

        <!-- Label and metadata (hidden when collapsed) -->
        <div v-if="!collapsed" class="flex-1 flex items-center justify-between min-w-0">
          <div class="flex items-center gap-2">
            <span :class="['font-medium truncate', sizeConfig[size].label]">
              {{ item.label }}
            </span>
            <span
              v-if="item.isNew"
              class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-gray-100 text-gray-600 rounded"
            >
              New
            </span>
          </div>

          <div class="flex items-center gap-2">
            <!-- Badge -->
            <span
              v-if="item.badge && item.badge > 0"
              :class="[
                'px-2 py-0.5 text-xs font-semibold rounded-full transition-colors duration-150',
                isActive(item.to)
                  ? 'bg-gray-200 text-gray-900'
                  : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200 group-hover:text-gray-900'
              ]"
            >
              {{ item.badge > 99 ? '99+' : item.badge }}
            </span>

            <!-- Shortcut hint -->
            <span
              v-if="item.shortcut && !item.badge && showShortcuts"
              :class="[
                'text-xs text-gray-400 font-mono opacity-0 group-hover:opacity-100 transition-opacity duration-150',
                sizeConfig[size].shortcut
              ]"
            >
              {{ item.shortcut }}
            </span>
          </div>
        </div>
      </Link>
    </div>

    <!-- Agents Section -->
    <CollapsibleRoot v-model:open="agentsSectionOpen" class="pt-4 mt-4 border-t border-gray-200">
      <!-- Section header -->
      <div :class="['flex items-center justify-between mb-2', collapsed ? 'px-1' : 'px-3']">
        <CollapsibleTrigger
          v-if="!collapsed"
          class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-900 transition-colors duration-150 group"
        >
          <Icon
            name="ph:caret-down"
            :class="[
              'w-3 h-3 transition-transform duration-150 ease-out',
              !agentsSectionOpen && '-rotate-90'
            ]"
          />
          <span>Agents</span>
        </CollapsibleTrigger>

        <TooltipProvider v-if="collapsed" :delay-duration="300">
          <TooltipRoot>
            <TooltipTrigger as-child>
              <div class="w-full flex justify-center group cursor-pointer">
                <Icon name="ph:robot" class="w-4 h-4 text-gray-500 group-hover:text-gray-900 transition-colors duration-150" />
              </div>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                side="right"
                :side-offset="12"
                class="bg-white border border-gray-200 px-3 py-2.5 rounded-lg shadow-md animate-in fade-in-0 duration-150"
              >
                <span class="font-semibold text-sm text-gray-900">Agents</span>
                <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                  <span class="w-2 h-2 bg-green-500 rounded-full" />
                  {{ onlineAgents }}/{{ totalAgents }} online
                </div>
                <TooltipArrow class="fill-white" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>

        <!-- Online count -->
        <div v-if="!collapsed" class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-gray-50 transition-colors duration-150">
          <span class="text-xs text-gray-400 font-medium tabular-nums">
            {{ onlineAgents }}/{{ totalAgents }}
          </span>
          <span
            v-if="onlineAgents > 0"
            class="w-2 h-2 bg-green-500 rounded-full"
          />
        </div>
      </div>

      <!-- Agent list -->
      <CollapsibleContent>
        <div :class="['space-y-0.5', collapsed && 'space-y-1']">
          <!-- Loading state -->
          <template v-if="loading">
            <div
              v-for="i in 3"
              :key="i"
              :class="[
                'flex items-center animate-pulse',
                collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
              ]"
            >
              <div class="w-6 h-6 rounded-full bg-gray-100" />
              <div v-if="!collapsed" class="flex-1 space-y-1.5">
                <div class="h-3 w-24 bg-gray-100 rounded" />
                <div class="h-2 w-16 bg-gray-100 rounded" />
              </div>
            </div>
          </template>

          <!-- Agent items -->
          <template v-else>
            <TransitionGroup
              name="agent-list"
              tag="div"
              class="space-y-0.5"
            >
              <TooltipProvider
                v-for="agent in displayedAgents"
                :key="agent.id"
                :delay-duration="300"
              >
                <TooltipRoot :disabled="!collapsed">
                  <TooltipTrigger as-child>
                    <button
                      :class="[
                        'w-full flex items-center rounded-lg transition-colors duration-150 text-left group outline-none',
                        sizeConfig[size].agentItem,
                        collapsed && 'justify-center p-2',
                        selectedAgentId === agent.id && 'bg-gray-100',
                        'hover:bg-gray-50',
                        'focus-visible:ring-1 focus-visible:ring-gray-400'
                      ]"
                      @click="handleAgentClick(agent)"
                    >
                      <!-- Agent avatar -->
                      <AgentAvatar
                        :user="agent"
                        :size="collapsed ? 'xs' : (size === 'sm' ? 'xs' : 'sm')"
                        :show-status="!collapsed"
                      />

                      <!-- Agent info (hidden when collapsed) -->
                      <Transition
                        enter-active-class="transition-all duration-150 ease-out"
                        leave-active-class="transition-all duration-100 ease-in"
                        enter-from-class="opacity-0"
                        leave-to-class="opacity-0"
                      >
                        <div v-if="!collapsed" class="flex-1 min-w-0 ml-2.5">
                          <div class="flex items-center gap-2">
                            <span :class="[
                              'truncate transition-colors duration-150',
                              sizeConfig[size].agentName,
                              'text-gray-500 group-hover:text-gray-900'
                            ]">
                              {{ agent.name }}
                            </span>
                            <span
                              v-if="agent.status === 'busy'"
                              class="w-1.5 h-1.5 bg-amber-500 rounded-full"
                            />
                          </div>
                          <p
                            v-if="agent.currentTask"
                            class="text-[11px] text-gray-400 truncate"
                          >
                            {{ agent.currentTask }}
                          </p>
                        </div>
                      </Transition>

                      <!-- Status badge -->
                      <StatusBadge
                        v-if="!collapsed && agent.status"
                        :status="agent.status"
                        size="xs"
                        class="opacity-0 group-hover:opacity-100 transition-opacity"
                      />
                    </button>
                  </TooltipTrigger>
                  <TooltipPortal>
                    <TooltipContent
                      side="right"
                      :side-offset="12"
                      class="bg-white border border-gray-200 px-4 py-3 rounded-lg shadow-md animate-in fade-in-0 duration-150 max-w-64"
                    >
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-sm text-gray-900">{{ agent.name }}</span>
                        <StatusBadge v-if="agent.status" :status="agent.status" size="xs" />
                      </div>
                      <p v-if="agent.currentTask" class="text-xs text-gray-500 mt-1.5 line-clamp-2 italic">
                        "{{ agent.currentTask }}"
                      </p>
                      <p v-if="agent.role" class="text-xs text-gray-400 mt-1.5 flex items-center gap-1.5">
                        <Icon name="ph:briefcase" class="w-3 h-3" />
                        {{ agent.role }}
                      </p>
                      <TooltipArrow class="fill-white" />
                    </TooltipContent>
                  </TooltipPortal>
                </TooltipRoot>
              </TooltipProvider>
            </TransitionGroup>

            <!-- Show more button -->
            <button
              v-if="hasMoreAgents && !collapsed"
              class="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-xs text-gray-500 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors duration-150 group mt-1"
              @click="showAllAgents = !showAllAgents"
            >
              <Icon
                :name="showAllAgents ? 'ph:caret-up' : 'ph:caret-down'"
                class="w-3 h-3"
              />
              <span>{{ showAllAgents ? 'Show less' : `Show ${agents.length - maxVisibleAgents} more` }}</span>
            </button>

            <!-- Empty state -->
            <div
              v-if="agents.length === 0"
              :class="[
                'flex flex-col items-center justify-center text-center py-4',
                collapsed && 'py-2'
              ]"
            >
              <Icon
                v-if="!collapsed"
                name="ph:robot"
                class="w-8 h-8 text-gray-400 mb-2"
              />
              <p v-if="!collapsed" class="text-xs text-gray-500">No agents active</p>
            </div>
          </template>
        </div>
      </CollapsibleContent>
    </CollapsibleRoot>

    <!-- Quick actions (visible when expanded) -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out"
      leave-active-class="transition-all duration-100 ease-in"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="!collapsed && showQuickActions"
        class="mt-4 pt-4 border-t border-gray-200"
      >
        <div class="px-3 mb-2">
          <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Quick Actions
          </span>
        </div>
        <div class="space-y-1">
          <button
            v-for="action in quickActions"
            :key="action.id"
            :class="[
              'w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-colors duration-150 group',
              'text-gray-500 hover:text-gray-900 hover:bg-gray-50',
              'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-gray-400'
            ]"
            @click="$emit('quickAction', action.id)"
          >
            <Icon :name="action.icon" class="w-4 h-4 group-hover:text-gray-900 transition-colors duration-150" />
            <span class="text-sm">{{ action.label }}</span>
            <kbd
              v-if="action.shortcut"
              class="ml-auto text-[10px] text-gray-400 font-mono px-1.5 py-0.5 rounded bg-gray-100 opacity-0 group-hover:opacity-100 transition-opacity duration-150"
            >
              {{ action.shortcut }}
            </kbd>
          </button>
        </div>
      </div>
    </Transition>
  </nav>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import {
  CollapsibleContent,
  CollapsibleRoot,
  CollapsibleTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import StatusBadge from '@/Components/shared/StatusBadge.vue'

// Types
type NavSize = 'sm' | 'md' | 'lg'

interface NavItem {
  to: string
  icon: string
  iconActive: string
  label: string
  badge?: number
  shortcut?: string
  isNew?: boolean
}

interface QuickAction {
  id: string
  icon: string
  label: string
  shortcut?: string
}

interface NavSizeConfig {
  padding: string
  navItem: string
  icon: string
  iconSize: string
  label: string
  shortcut: string
  agentItem: string
  agentName: string
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
  size?: NavSize
  collapsed?: boolean
  loading?: boolean
  showShortcuts?: boolean
  showQuickActions?: boolean
  selectedAgentId?: string
  maxVisibleAgents?: number
  agents?: Agent[]
  totalAgents?: number
  onlineAgents?: number
}>(), {
  size: 'md',
  collapsed: false,
  loading: false,
  showShortcuts: true,
  showQuickActions: true,
  maxVisibleAgents: 5,
  agents: () => [],
  totalAgents: 0,
  onlineAgents: 0,
})

// Emits
defineEmits<{
  agentClick: [agent: Agent]
  quickAction: [actionId: string]
}>()

// Size configuration
const sizeConfig: Record<NavSize, NavSizeConfig> = {
  sm: {
    padding: 'p-3',
    navItem: 'gap-2 px-2.5 py-2',
    icon: 'w-4 h-4',
    iconSize: 'w-4 h-4',
    label: 'text-xs',
    shortcut: 'text-[10px]',
    agentItem: 'gap-2 px-2.5 py-1.5',
    agentName: 'text-xs',
  },
  md: {
    padding: 'p-4',
    navItem: 'gap-3 px-3 py-2.5',
    icon: 'w-5 h-5',
    iconSize: 'w-5 h-5',
    label: 'text-sm',
    shortcut: 'text-xs',
    agentItem: 'gap-2.5 px-3 py-2',
    agentName: 'text-sm',
  },
  lg: {
    padding: 'p-5',
    navItem: 'gap-3 px-4 py-3',
    icon: 'w-6 h-6',
    iconSize: 'w-5 h-5',
    label: 'text-base',
    shortcut: 'text-xs',
    agentItem: 'gap-3 px-4 py-2.5',
    agentName: 'text-sm',
  },
}

// Composables
const page = usePage()

// State
const agentsSectionOpen = ref(true)
const showAllAgents = ref(false)

// Navigation items
const navItems = ref<NavItem[]>([
  { to: '/', icon: 'ph:house', iconActive: 'ph:house-fill', label: 'Dashboard', shortcut: 'G H' },
  { to: '/chat', icon: 'ph:chat-circle', iconActive: 'ph:chat-circle-fill', label: 'Chat', badge: 15, shortcut: 'G C' },
  { to: '/messages', icon: 'ph:envelope-simple', iconActive: 'ph:envelope-simple-fill', label: 'Messages', shortcut: 'G M' },
  { to: '/tasks', icon: 'ph:check-square', iconActive: 'ph:check-square-fill', label: 'Tasks', shortcut: 'G T' },
  { to: '/activity', icon: 'ph:activity', iconActive: 'ph:activity-fill', label: 'Activity', shortcut: 'G F' },
  { to: '/approvals', icon: 'ph:seal-check', iconActive: 'ph:seal-check-fill', label: 'Approvals', badge: 3, shortcut: 'G A' },
  { to: '/org', icon: 'ph:tree-structure', iconActive: 'ph:tree-structure-fill', label: 'Organization', shortcut: 'G O' },
  { to: '/workload', icon: 'ph:chart-bar', iconActive: 'ph:chart-bar-fill', label: 'Workload', shortcut: 'G W' },
  { to: '/credits', icon: 'ph:coins', iconActive: 'ph:coins-fill', label: 'Credits', shortcut: 'G B' },
  { to: '/docs', icon: 'ph:file-text', iconActive: 'ph:file-text-fill', label: 'Docs', shortcut: 'G D' },
  { to: '/automation', icon: 'ph:lightning', iconActive: 'ph:lightning-fill', label: 'Automation', shortcut: 'G U', isNew: true },
  { to: '/settings', icon: 'ph:gear', iconActive: 'ph:gear-fill', label: 'Settings', shortcut: 'G S' },
])

// Quick actions
const quickActions = ref<QuickAction[]>([
  { id: 'new-task', icon: 'ph:plus-circle', label: 'New Task', shortcut: 'N T' },
  { id: 'spawn-agent', icon: 'ph:robot', label: 'Spawn Agent', shortcut: 'N A' },
  { id: 'new-doc', icon: 'ph:file-plus', label: 'New Document' },
])

// Computed
const agents = computed(() => props.agents)
const totalAgents = computed(() => props.totalAgents)
const onlineAgents = computed(() => props.onlineAgents)

const hasMoreAgents = computed(() => agents.value.length > props.maxVisibleAgents)

const displayedAgents = computed(() => {
  if (props.collapsed) {
    return agents.value.slice(0, 3)
  }
  return showAllAgents.value ? agents.value : agents.value.slice(0, props.maxVisibleAgents)
})

// Methods
const isActive = (path: string): boolean => {
  const currentUrl = page.url
  if (path === '/') return currentUrl === '/'
  return currentUrl.startsWith(path)
}

const handleAgentClick = (agent: Agent) => {
  // Navigate to agent detail page for agents, profile for humans
  if (agent.isAI) {
    router.visit(`/agent/${agent.id}`)
  } else {
    router.visit(`/profile/${agent.id}`)
  }
}
</script>

<style scoped>
/* Agent list animations */
.agent-list-move,
.agent-list-enter-active,
.agent-list-leave-active {
  transition: all 0.15s ease-out;
}

.agent-list-enter-from {
  opacity: 0;
  transform: translateX(-8px);
}

.agent-list-leave-to {
  opacity: 0;
  transform: translateX(8px);
}

.agent-list-leave-active {
  position: absolute;
}

/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: oklch(0.8 0 0);
  border-radius: 4px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: oklch(0.7 0 0);
}
</style>
