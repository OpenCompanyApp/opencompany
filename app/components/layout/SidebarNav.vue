<template>
  <nav
    :class="[
      'flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-olympus-border scrollbar-track-transparent',
      sizeConfig[size].padding,
      collapsed && 'px-2'
    ]"
  >
    <!-- Main navigation items -->
    <div :class="['space-y-1', collapsed && 'space-y-2']">
      <NuxtLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        :class="[
          'group flex items-center rounded-lg transition-all duration-200 outline-none relative overflow-hidden',
          sizeConfig[size].navItem,
          collapsed && 'justify-center p-2.5',
          isActive(item.to)
            ? 'bg-olympus-primary-muted text-olympus-text shadow-sm shadow-olympus-primary/10'
            : 'hover:bg-olympus-surface text-olympus-text-muted hover:text-olympus-text',
          'focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-sidebar'
        ]"
      >
        <!-- Active indicator bar -->
        <div
          v-if="isActive(item.to) && !collapsed"
          class="absolute left-0 top-2 bottom-2 w-0.5 bg-olympus-primary rounded-r"
        />

        <!-- Icon container -->
        <div
          :class="[
            'relative flex items-center justify-center shrink-0 transition-all duration-200',
            sizeConfig[size].icon,
            !collapsed && 'mr-3',
            collapsed && isActive(item.to) && 'bg-olympus-primary-muted rounded-lg'
          ]"
        >
          <Icon
            :name="isActive(item.to) ? item.iconActive : item.icon"
            :class="[
              'transition-all duration-200',
              sizeConfig[size].iconSize,
              isActive(item.to)
                ? 'text-olympus-primary'
                : 'text-olympus-text-muted group-hover:text-olympus-text group-hover:scale-110'
            ]"
          />

          <!-- Badge indicator for collapsed mode -->
          <span
            v-if="collapsed && item.badge && item.badge > 0"
            class="absolute -top-1 -right-1 w-2 h-2 bg-olympus-primary rounded-full ring-2 ring-olympus-sidebar"
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
              class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-olympus-accent/20 text-olympus-accent rounded"
            >
              New
            </span>
          </div>

          <div class="flex items-center gap-2">
            <!-- Badge -->
            <span
              v-if="item.badge && item.badge > 0"
              :class="[
                'px-2 py-0.5 text-xs font-semibold rounded-full transition-colors duration-200',
                isActive(item.to)
                  ? 'bg-olympus-primary/20 text-olympus-primary'
                  : 'bg-olympus-surface text-olympus-text-muted group-hover:bg-olympus-primary/10 group-hover:text-olympus-primary'
              ]"
            >
              {{ item.badge > 99 ? '99+' : item.badge }}
            </span>

            <!-- Shortcut hint -->
            <span
              v-if="item.shortcut && !item.badge && showShortcuts"
              :class="[
                'text-xs text-olympus-text-subtle font-mono opacity-0 group-hover:opacity-100 transition-opacity duration-200',
                sizeConfig[size].shortcut
              ]"
            >
              {{ item.shortcut }}
            </span>
          </div>
        </div>

        <!-- Hover effect overlay -->
        <div
          class="absolute inset-0 bg-gradient-to-r from-olympus-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none"
        />
      </NuxtLink>
    </div>

    <!-- Agents Section -->
    <CollapsibleRoot v-model:open="agentsSectionOpen" class="pt-4 mt-4 border-t border-olympus-border">
      <!-- Section header -->
      <div :class="['flex items-center justify-between mb-2', collapsed ? 'px-1' : 'px-3']">
        <CollapsibleTrigger
          v-if="!collapsed"
          class="flex items-center gap-2 text-xs font-semibold text-olympus-text-muted uppercase tracking-wider hover:text-olympus-text transition-colors group"
        >
          <Icon
            name="ph:caret-down"
            :class="[
              'w-3 h-3 transition-transform duration-200',
              !agentsSectionOpen && '-rotate-90'
            ]"
          />
          <span>Agents</span>
        </CollapsibleTrigger>

        <TooltipRoot v-if="collapsed" :delay-duration="300">
          <TooltipTrigger as-child>
            <div class="w-full flex justify-center">
              <Icon name="ph:robot" class="w-4 h-4 text-olympus-text-muted" />
            </div>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent
              side="right"
              :side-offset="12"
              class="glass px-3 py-2 rounded-lg shadow-xl animate-in fade-in-0 slide-in-from-left-2 duration-150"
            >
              <span class="font-medium text-sm">Agents</span>
              <div class="text-xs text-olympus-text-muted mt-1">
                {{ onlineAgents }}/{{ totalAgents }} online
              </div>
              <TooltipArrow class="fill-olympus-elevated" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>

        <!-- Online count -->
        <div v-if="!collapsed" class="flex items-center gap-2">
          <span class="text-xs text-olympus-text-subtle">
            {{ onlineAgents }}/{{ totalAgents }}
          </span>
          <span
            v-if="onlineAgents > 0"
            class="w-2 h-2 bg-olympus-success rounded-full animate-pulse"
          />
        </div>
      </div>

      <!-- Agent list -->
      <CollapsibleContent>
        <div :class="['space-y-0.5', collapsed && 'space-y-1']">
          <!-- Loading state -->
          <template v-if="loading">
            <AgentItemSkeleton v-for="i in 3" :key="i" :collapsed="collapsed" :size="size" />
          </template>

          <!-- Agent items -->
          <template v-else>
            <TransitionGroup
              name="agent-list"
              tag="div"
              class="space-y-0.5"
            >
              <TooltipRoot
                v-for="agent in displayedAgents"
                :key="agent.id"
                :delay-duration="300"
                :disabled="!collapsed"
              >
                <TooltipTrigger as-child>
                  <button
                    :class="[
                      'w-full flex items-center rounded-lg transition-all duration-200 text-left group outline-none relative',
                      sizeConfig[size].agentItem,
                      collapsed && 'justify-center p-2',
                      selectedAgentId === agent.id && 'bg-olympus-primary-muted',
                      'hover:bg-olympus-surface',
                      'focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-sidebar'
                    ]"
                    @click="handleAgentClick(agent)"
                  >
                    <!-- Agent avatar -->
                    <SharedAgentAvatar
                      :user="agent"
                      :size="collapsed ? 'xs' : (size === 'sm' ? 'xs' : 'sm')"
                      :show-status="!collapsed"
                    />

                    <!-- Agent info (hidden when collapsed) -->
                    <Transition
                      enter-active-class="transition-all duration-200"
                      leave-active-class="transition-all duration-150"
                      enter-from-class="opacity-0 translate-x-[-8px]"
                      leave-to-class="opacity-0 translate-x-[-8px]"
                    >
                      <div v-if="!collapsed" class="flex-1 min-w-0 ml-2.5">
                        <div class="flex items-center gap-2">
                          <span :class="[
                            'truncate transition-colors',
                            sizeConfig[size].agentName,
                            'text-olympus-text-muted group-hover:text-olympus-text'
                          ]">
                            {{ agent.name }}
                          </span>
                          <span
                            v-if="agent.status === 'busy'"
                            class="w-1.5 h-1.5 bg-olympus-warning rounded-full animate-pulse"
                          />
                        </div>
                        <p
                          v-if="agent.currentTask"
                          class="text-[11px] text-olympus-text-subtle truncate"
                        >
                          {{ agent.currentTask }}
                        </p>
                      </div>
                    </Transition>

                    <!-- Status badge -->
                    <SharedStatusBadge
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
                    class="glass px-3 py-2 rounded-lg shadow-xl animate-in fade-in-0 slide-in-from-left-2 duration-150 max-w-64"
                  >
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-sm">{{ agent.name }}</span>
                      <SharedStatusBadge v-if="agent.status" :status="agent.status" size="xs" />
                    </div>
                    <p v-if="agent.currentTask" class="text-xs text-olympus-text-muted mt-1 line-clamp-2">
                      {{ agent.currentTask }}
                    </p>
                    <p v-if="agent.role" class="text-xs text-olympus-text-subtle mt-1">
                      {{ agent.role }}
                    </p>
                    <TooltipArrow class="fill-olympus-elevated" />
                  </TooltipContent>
                </TooltipPortal>
              </TooltipRoot>
            </TransitionGroup>

            <!-- Show more button -->
            <button
              v-if="hasMoreAgents && !collapsed"
              class="w-full flex items-center justify-center gap-2 px-3 py-2 text-xs text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface rounded-lg transition-colors duration-200"
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
                class="w-8 h-8 text-olympus-text-subtle mb-2"
              />
              <p v-if="!collapsed" class="text-xs text-olympus-text-muted">No agents active</p>
            </div>
          </template>
        </div>
      </CollapsibleContent>
    </CollapsibleRoot>

    <!-- Quick actions (visible when expanded) -->
    <Transition
      enter-active-class="transition-all duration-300"
      leave-active-class="transition-all duration-200"
      enter-from-class="opacity-0 translate-y-2"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="!collapsed && showQuickActions"
        class="mt-4 pt-4 border-t border-olympus-border"
      >
        <div class="px-3 mb-2">
          <span class="text-xs font-semibold text-olympus-text-muted uppercase tracking-wider">
            Quick Actions
          </span>
        </div>
        <div class="space-y-1">
          <button
            v-for="action in quickActions"
            :key="action.id"
            :class="[
              'w-full flex items-center gap-2 px-3 py-2 rounded-lg transition-colors duration-200',
              'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50'
            ]"
            @click="$emit('quickAction', action.id)"
          >
            <Icon :name="action.icon" class="w-4 h-4" />
            <span class="text-sm">{{ action.label }}</span>
            <kbd
              v-if="action.shortcut"
              class="ml-auto text-[10px] text-olympus-text-subtle font-mono"
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
import {
  CollapsibleContent,
  CollapsibleRoot,
  CollapsibleTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'

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
}>(), {
  size: 'md',
  collapsed: false,
  loading: false,
  showShortcuts: true,
  showQuickActions: true,
  maxVisibleAgents: 5,
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
const route = useRoute()
const { agents, stats } = useMockData()

// State
const agentsSectionOpen = ref(true)
const showAllAgents = ref(false)

// Navigation items
const navItems = ref<NavItem[]>([
  { to: '/', icon: 'ph:house', iconActive: 'ph:house-fill', label: 'Dashboard', shortcut: 'G H' },
  { to: '/chat', icon: 'ph:chat-circle', iconActive: 'ph:chat-circle-fill', label: 'Chat', badge: 15, shortcut: 'G C' },
  { to: '/tasks', icon: 'ph:check-square', iconActive: 'ph:check-square-fill', label: 'Tasks', shortcut: 'G T' },
  { to: '/docs', icon: 'ph:file-text', iconActive: 'ph:file-text-fill', label: 'Docs', shortcut: 'G D' },
])

// Quick actions
const quickActions = ref<QuickAction[]>([
  { id: 'new-task', icon: 'ph:plus-circle', label: 'New Task', shortcut: 'N T' },
  { id: 'spawn-agent', icon: 'ph:robot', label: 'Spawn Agent', shortcut: 'N A' },
  { id: 'new-doc', icon: 'ph:file-plus', label: 'New Document' },
])

// Computed
const totalAgents = computed(() => stats.totalAgents)
const onlineAgents = computed(() => stats.agentsOnline)

const hasMoreAgents = computed(() => agents.length > props.maxVisibleAgents)

const displayedAgents = computed(() => {
  if (props.collapsed) {
    return agents.slice(0, 3)
  }
  return showAllAgents.value ? agents : agents.slice(0, props.maxVisibleAgents)
})

// Methods
const isActive = (path: string): boolean => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}

const handleAgentClick = (agent: Agent) => {
  // Emit to parent
}

// Agent item skeleton component
const AgentItemSkeleton = defineComponent({
  props: {
    collapsed: Boolean,
    size: {
      type: String as PropType<NavSize>,
      default: 'md',
    },
  },
  setup(props) {
    return () => h('div', {
      class: [
        'flex items-center animate-pulse',
        props.collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
      ],
    }, [
      h('div', { class: 'w-6 h-6 rounded-full bg-olympus-surface' }),
      !props.collapsed && h('div', { class: 'flex-1 space-y-1.5' }, [
        h('div', { class: 'h-3 w-24 bg-olympus-surface rounded' }),
        h('div', { class: 'h-2 w-16 bg-olympus-surface/50 rounded' }),
      ]),
    ])
  },
})
</script>

<style scoped>
.agent-list-move,
.agent-list-enter-active,
.agent-list-leave-active {
  transition: all 0.3s ease;
}

.agent-list-enter-from {
  opacity: 0;
  transform: translateX(-12px);
}

.agent-list-leave-to {
  opacity: 0;
  transform: translateX(12px);
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
  background-color: oklch(0.5 0 0 / 0.2);
  border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: oklch(0.5 0 0 / 0.3);
}
</style>
