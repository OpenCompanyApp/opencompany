<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { TooltipProvider } from 'reka-ui'
import AppSidebar from '@/Components/layout/AppSidebar.vue'
import CommandPalette from '@/Components/layout/CommandPalette.vue'
import RealtimeProvider from '@/Components/RealtimeProvider.vue'
import Slideover from '@/Components/shared/Slideover.vue'
import SidebarNav from '@/Components/layout/SidebarNav.vue'
import UserMenu from '@/Components/layout/UserMenu.vue'
import Icon from '@/Components/shared/Icon.vue'
import WorkspaceIcon from '@/Components/shared/WorkspaceIcon.vue'
import SpawnAgentModal from '@/Components/agents/SpawnAgentModal.vue'
import { useKeyboardShortcuts, useCommandPalette } from '@/composables/useKeyboardShortcuts'
import { usePresence } from '@/composables/usePresence'
import { useChannelListener } from '@/composables/useRealtime'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'
import type { AgentStatus } from '@/types'

useKeyboardShortcuts()
const { isOpen: commandPaletteOpen } = useCommandPalette()
const { workspacePath, workspace, isAdmin } = useWorkspace()

const sidebarCollapsed = ref(false)
const mobileMenuOpen = ref(false)
const showSpawnModal = ref(false)

// Get current user ID from Inertia page props
const page = usePage()
const userId = (page.props.auth as any)?.user?.id || 'guest'

// Load agents and channels
const { fetchAgents, fetchChannels } = useApi()
const { data: agentsData, refresh: refreshAgents } = fetchAgents()
const { data: channelsData } = fetchChannels()

const sidebarAgents = computed(() => agentsData.value ?? [])

const onlineAgentCount = computed(() =>
  (agentsData.value ?? []).filter(a =>
    a.status === 'idle' || a.status === 'working' || a.status === 'online'
  ).length
)

const totalAgentCount = computed(() => (agentsData.value ?? []).length)

// Real-time agent status updates (workspace-scoped)
useChannelListener<{ id: string; status: string }>(
  `workspace.${workspace.value?.id}.agents`,
  '.AgentStatusUpdated',
  (data) => {
    if (!agentsData.value) return
    const agent = agentsData.value.find(a => a.id === data.id)
    if (agent) {
      agent.status = data.status as AgentStatus
    }
  }
)

// Map data for command palette
const channelsForPalette = computed(() =>
  (channelsData.value ?? []).map(c => ({
    id: c.id,
    name: c.name,
    description: c.description,
    type: c.type,
    unreadCount: c.unreadCount,
  }))
)

const agentsForPalette = computed(() =>
  (agentsData.value ?? []).map(a => ({
    id: a.id,
    name: a.name,
    role: (a as any).agentType,
    status: a.status,
  }))
)

const handleSpawnAgent = () => {
  showSpawnModal.value = true
}

const handlePaletteAction = (type: string) => {
  switch (type) {
    case 'spawn-agent':
      showSpawnModal.value = true
      break
    case 'new-task':
      router.visit(workspacePath('/tasks?action=new'))
      break
    case 'new-channel':
      router.visit(workspacePath('/chat?action=new-channel'))
      break
    case 'new-document':
      router.visit(workspacePath('/docs?action=new'))
      break
  }
}

const handleAgentSpawned = async (agent: { id: string }) => {
  await refreshAgents()
  router.visit(workspacePath(`/agent/${agent.id}`))
}

// Initialize presence tracking
const { initPresence, cleanup } = usePresence(userId, workspace.value?.id)

onMounted(() => {
  initPresence()
})

onUnmounted(() => {
  cleanup()
})

// Check if a path is active
const isActive = (path: string): boolean => {
  if (path === workspacePath('/')) return page.url === workspacePath('/')
  return page.url.startsWith(path)
}
</script>

<template>
  <TooltipProvider :delay-duration="300">
    <RealtimeProvider>
      <div class="flex h-screen bg-white dark:bg-neutral-900 text-neutral-900 dark:text-white overflow-hidden">
        <!-- Mobile Header -->
        <div class="md:hidden fixed top-0 left-0 right-0 z-40 h-14 bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between px-4">
          <button
            class="p-2 -ml-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
            @click="mobileMenuOpen = true"
          >
            <Icon name="ph:list" class="w-6 h-6 text-neutral-700 dark:text-neutral-200" />
          </button>

          <Link :href="workspacePath('/')" class="flex items-center gap-2">
            <WorkspaceIcon
              :icon="workspace?.icon"
              :color="workspace?.color"
              size="sm"
            />
            <span class="font-semibold text-neutral-900 dark:text-white">{{ workspace?.name ?? 'OpenCompany' }}</span>
          </Link>

          <div class="w-10" /> <!-- Spacer for centering -->
        </div>

        <!-- Desktop Sidebar -->
        <AppSidebar
          class="hidden md:flex"
          v-model:collapsed="sidebarCollapsed"
          :agents="sidebarAgents"
          :online-agents="onlineAgentCount"
          :total-agents="totalAgentCount"
          @spawn-agent="handleSpawnAgent"
        />

        <!-- Mobile Sidebar Drawer -->
        <Slideover v-model:open="mobileMenuOpen" side="left" size="sm" :show-close="false">
          <template #header>
            <div class="flex items-center justify-between w-full">
              <Link :href="workspacePath('/')" class="flex items-center gap-2.5" @click="mobileMenuOpen = false">
                <WorkspaceIcon
                  :icon="workspace?.icon"
                  :color="workspace?.color"
                  size="md"
                />
                <span class="font-semibold text-lg text-neutral-900 dark:text-white tracking-tight">{{ workspace?.name ?? 'OpenCompany' }}</span>
              </Link>
              <button
                class="p-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
                @click="mobileMenuOpen = false"
              >
                <Icon name="ph:x" class="w-5 h-5 text-neutral-500 dark:text-neutral-300" />
              </button>
            </div>
          </template>

          <template #body>
            <div class="flex flex-col h-full -mx-6 -my-4">
              <SidebarNav :collapsed="false" />

              <!-- Bottom links -->
              <div class="mt-auto border-t border-neutral-200 dark:border-neutral-700 px-2 py-2 space-y-0.5">
                <Link
                  v-if="isAdmin"
                  :href="workspacePath('/integrations')"
                  :class="[
                    'flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors',
                    isActive(workspacePath('/integrations'))
                      ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
                      : 'hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
                  ]"
                  @click="mobileMenuOpen = false"
                >
                  <Icon :name="isActive(workspacePath('/integrations')) ? 'ph:plugs-connected-fill' : 'ph:plugs-connected'" class="w-[18px] h-[18px]" />
                  <span class="text-sm">Integrations</span>
                </Link>
                <Link
                  v-if="isAdmin"
                  :href="workspacePath('/settings')"
                  :class="[
                    'flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors',
                    isActive(workspacePath('/settings'))
                      ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
                      : 'hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
                  ]"
                  @click="mobileMenuOpen = false"
                >
                  <Icon :name="isActive(workspacePath('/settings')) ? 'ph:gear-fill' : 'ph:gear'" class="w-[18px] h-[18px]" />
                  <span class="text-sm">Settings</span>
                </Link>
              </div>

              <!-- User menu -->
              <div class="border-t border-neutral-200 dark:border-neutral-700">
                <UserMenu :collapsed="false" size="sm" />
              </div>
            </div>
          </template>
        </Slideover>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden pt-14 md:pt-0">
          <slot />
        </main>

        <CommandPalette
          v-model="commandPaletteOpen"
          :channels="channelsForPalette"
          :agents="agentsForPalette"
          @action="handlePaletteAction"
        />

        <!-- Spawn Agent Modal (accessible from sidebar) -->
        <SpawnAgentModal
          v-model:open="showSpawnModal"
          @spawn="handleAgentSpawned"
        />
      </div>
    </RealtimeProvider>
  </TooltipProvider>
</template>
