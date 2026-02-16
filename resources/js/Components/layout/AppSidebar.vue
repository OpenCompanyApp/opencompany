<template>
  <aside
    :class="[
      'flex flex-col shrink-0 bg-neutral-100 dark:bg-neutral-950 transition-all duration-150',
      collapsed ? 'w-16' : 'w-60',
      variant === 'floating' && 'my-3 ml-3 rounded-lg border border-neutral-200 dark:border-neutral-800 shadow-md',
      variant === 'minimal' && 'bg-transparent',
      className
    ]"
  >
    <!-- Header -->
    <div :class="collapsed ? 'px-2 pt-3 pb-1' : 'px-2 pt-3 pb-2'">
      <!-- Workspace switcher + Collapse toggle -->
      <div :class="['flex items-center', collapsed ? 'justify-center' : 'justify-between']">
        <!-- Workspace switcher popover -->
        <PopoverRoot v-model:open="switcherOpen">
          <PopoverTrigger as-child>
            <button
              v-if="!collapsed"
              class="flex items-center gap-2 rounded-lg px-3 py-1.5 hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 min-w-0"
            >
              <WorkspaceIcon
                :icon="workspace?.icon"
                :color="workspace?.color"
                size="md"
              />
              <span class="font-semibold text-neutral-900 dark:text-white tracking-tight truncate text-sm">{{ workspace?.name ?? 'OpenCompany' }}</span>
              <Icon name="ph:caret-up-down" class="w-3.5 h-3.5 text-neutral-400 shrink-0" />
            </button>
            <button
              v-else
              class="p-2 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors outline-none focus-visible:ring-1 focus-visible:ring-neutral-400"
            >
              <WorkspaceIcon
                :icon="workspace?.icon"
                :color="workspace?.color"
                size="sm"
              />
            </button>
          </PopoverTrigger>

          <PopoverPortal>
            <PopoverContent
              class="z-50 w-64 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 shadow-lg p-1.5 animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
              :side="collapsed ? 'right' : 'bottom'"
              align="start"
              :side-offset="8"
              :avoid-collisions="true"
            >
              <div class="px-2 pt-1 pb-1.5">
                <span class="text-[11px] font-medium text-neutral-400 dark:text-neutral-500 uppercase tracking-wider">Workspaces</span>
              </div>

              <Link
                v-for="ws in workspaces"
                :key="ws.id"
                :href="`/w/${ws.slug}`"
                :class="[
                  'flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition-colors',
                  ws.slug === workspace?.slug
                    ? 'bg-neutral-100 dark:bg-neutral-700/50 text-neutral-900 dark:text-white'
                    : 'text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 hover:text-neutral-900 dark:hover:text-white',
                ]"
                @click="switcherOpen = false"
              >
                <WorkspaceIcon :icon="ws.icon" :color="ws.color" size="sm" />
                <span class="text-sm truncate flex-1">{{ ws.name }}</span>
                <Icon v-if="ws.slug === workspace?.slug" name="ph:check" class="w-4 h-4 text-neutral-500 shrink-0" />
              </Link>

              <template v-if="isAdmin">
                <div class="my-1.5 mx-2 border-t border-neutral-200 dark:border-neutral-700" />
                <Link
                  href="/create-workspace"
                  class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 hover:text-neutral-900 dark:hover:text-white transition-colors"
                  @click="switcherOpen = false"
                >
                  <div class="w-6 h-6 flex items-center justify-center">
                    <Icon name="ph:plus" class="w-4 h-4" />
                  </div>
                  <span class="text-sm">New workspace</span>
                </Link>
              </template>
            </PopoverContent>
          </PopoverPortal>
        </PopoverRoot>

        <!-- Collapse toggle -->
        <Tooltip
          v-if="!collapsed"
          text="Hide sidebar"
          :delay-open="300"
        >
          <button
            :class="[
              'p-1.5 rounded-lg transition-colors duration-150 outline-none shrink-0',
              'hover:bg-neutral-200 dark:hover:bg-neutral-800',
              'focus-visible:ring-1 focus-visible:ring-neutral-400',
            ]"
            @click="handleCollapse"
          >
            <Icon
              name="ph:sidebar-simple"
              class="w-4.5 h-4.5 text-neutral-600 dark:text-neutral-300"
            />
          </button>
        </Tooltip>
      </div>
    </div>

    <!-- Expand button when collapsed -->
    <div v-if="collapsed" class="px-2 mb-2">
      <Tooltip text="Expand sidebar" side="right" :delay-open="300">
        <button
          class="w-full p-2 rounded-lg hover:bg-neutral-200 dark:hover:bg-neutral-800 transition-colors duration-150 outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 flex items-center justify-center"
          @click="handleCollapse"
        >
          <Icon
            name="ph:sidebar-simple"
            class="w-5 h-5 text-neutral-600 dark:text-neutral-300"
          />
        </button>
      </Tooltip>
    </div>

    <!-- Navigation slot -->
    <slot name="navigation">
      <SidebarNav
        :collapsed="collapsed"
        size="sm"
        :show-shortcuts="false"
        :show-quick-actions="false"
        :agents="agents"
        :online-agents="onlineAgents"
        :total-agents="totalAgents"
        @spawn-agent="emit('spawnAgent')"
      />
    </slot>

    <!-- Bottom section -->
    <div class="mt-auto">
      <!-- Config: Automation, Integrations, Settings -->
      <div :class="['space-y-0.5', collapsed ? 'px-2' : 'px-2']">
        <Link
          :href="workspacePath('/automation')"
          :class="[
            'group flex items-center rounded-lg transition-colors duration-150 outline-none',
            collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
            isActive(workspacePath('/automation'))
              ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
              : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
        >
          <Icon
            :name="isActive(workspacePath('/automation')) ? 'ph:lightning-fill' : 'ph:lightning'"
            class="w-[18px] h-[18px] shrink-0"
          />
          <span v-if="!collapsed" class="text-sm truncate">Automation</span>
        </Link>
        <Link
          v-if="isAdmin"
          :href="workspacePath('/integrations')"
          :class="[
            'group flex items-center rounded-lg transition-colors duration-150 outline-none',
            collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
            isActive(workspacePath('/integrations'))
              ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
              : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
        >
          <Icon
            :name="isActive(workspacePath('/integrations')) ? 'ph:plugs-connected-fill' : 'ph:plugs-connected'"
            class="w-[18px] h-[18px] shrink-0"
          />
          <span v-if="!collapsed" class="text-sm truncate">Integrations</span>
        </Link>
        <Link
          v-if="isAdmin"
          :href="workspacePath('/settings')"
          :class="[
            'group flex items-center rounded-lg transition-colors duration-150 outline-none',
            collapsed ? 'justify-center p-2' : 'gap-2.5 px-3 py-2',
            isActive(workspacePath('/settings'))
              ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
              : 'hover:bg-neutral-200 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
            'focus-visible:ring-1 focus-visible:ring-neutral-400',
          ]"
        >
          <Icon
            :name="isActive(workspacePath('/settings')) ? 'ph:gear-fill' : 'ph:gear'"
            class="w-[18px] h-[18px] shrink-0"
          />
          <span v-if="!collapsed" class="text-sm truncate">Settings</span>
        </Link>
      </div>

      <!-- User + Branding -->
      <div :class="['border-t border-neutral-200 dark:border-neutral-800', collapsed ? 'px-2 py-2.5' : 'px-3 py-2.5']">
        <div v-if="!collapsed" class="flex items-center gap-1">
          <UserMenu compact :collapsed="true" size="sm" :user="authUser" :user-role="userRole" class="-ml-2" />
          <span class="text-sm font-semibold text-neutral-900 dark:text-white tracking-tight" style="font-family: 'Lexend', sans-serif;">OpenCompany</span>
        </div>
        <div v-else class="flex flex-col items-center gap-1.5">
          <UserMenu compact :collapsed="true" size="sm" :user="authUser" :user-role="userRole" />
          <Tooltip text="OpenCompany" side="right" :delay-open="300">
            <span class="text-[10px] font-bold text-neutral-900 dark:text-white" style="font-family: 'Lexend', sans-serif;">OC</span>
          </Tooltip>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { PopoverRoot, PopoverTrigger, PopoverPortal, PopoverContent } from 'reka-ui'
import SidebarNav from '@/Components/layout/SidebarNav.vue'
import UserMenu from '@/Components/layout/UserMenu.vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import WorkspaceIcon from '@/Components/shared/WorkspaceIcon.vue'
import { useWorkspace } from '@/composables/useWorkspace'

// Types
type SidebarVariant = 'default' | 'floating' | 'minimal'

interface SidebarAgent {
  id: string
  name: string
  status?: 'online' | 'busy' | 'idle' | 'offline'
  currentTask?: string
  isAI: boolean
}

// Props
const props = withDefaults(defineProps<{
  variant?: SidebarVariant
  class?: string
  agents?: SidebarAgent[]
  onlineAgents?: number
  totalAgents?: number
}>(), {
  variant: 'default',
  agents: () => [],
  onlineAgents: 0,
  totalAgents: 0,
})

const collapsed = defineModel<boolean>('collapsed', { default: false })

const className = computed(() => props.class)
const page = usePage()
const { workspacePath, isAdmin, workspace, workspaces } = useWorkspace()

const switcherOpen = ref(false)

const authUser = computed(() => {
  const u = (page.props.auth as any)?.user
  return u ? { name: u.name, email: u.email, avatar: u.avatar } : undefined
})

const userRole = computed(() =>
  (page.props.auth as any)?.user?.type === 'human' ? 'admin' : 'member'
)

const isActive = (path: string): boolean => {
  return page.url.startsWith(path)
}

const emit = defineEmits<{
  searchClick: []
  spawnAgent: []
}>()

const handleCollapse = () => {
  collapsed.value = !collapsed.value
}
</script>

<style scoped>
/* Minimal styling */
</style>
