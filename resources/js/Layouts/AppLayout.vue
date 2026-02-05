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
import SpawnAgentModal from '@/Components/agents/SpawnAgentModal.vue'
import { useKeyboardShortcuts, useCommandPalette } from '@/composables/useKeyboardShortcuts'
import { usePresence } from '@/composables/usePresence'
import { useApi } from '@/composables/useApi'

useKeyboardShortcuts()
const { isOpen: commandPaletteOpen } = useCommandPalette()

const sidebarCollapsed = ref(false)
const mobileMenuOpen = ref(false)
const showSpawnModal = ref(false)

// Get current user ID from Inertia page props
const page = usePage()
const userId = (page.props.auth as any)?.user?.id || 'guest'

// Load agents for sidebar
const { fetchAgents } = useApi()
const { data: agentsData, refresh: refreshAgents } = fetchAgents()

const sidebarAgents = computed(() =>
  (agentsData.value ?? []).map(a => ({
    id: a.id,
    name: a.name,
    status: a.status as 'online' | 'busy' | 'idle' | 'offline' | undefined,
    currentTask: a.currentTask,
    isAI: true,
  }))
)

const onlineAgentCount = computed(() =>
  (agentsData.value ?? []).filter(a =>
    a.status === 'idle' || a.status === 'working' || a.status === 'online'
  ).length
)

const totalAgentCount = computed(() => (agentsData.value ?? []).length)

const handleSpawnAgent = () => {
  showSpawnModal.value = true
}

const handleAgentSpawned = async (agent: { id: string }) => {
  await refreshAgents()
  router.visit(`/agent/${agent.id}`)
}

// Initialize presence tracking
const { initPresence, cleanup } = usePresence(userId)

onMounted(() => {
  initPresence()
})

onUnmounted(() => {
  cleanup()
})

// Check if a path is active
const isActive = (path: string): boolean => {
  if (path === '/') return page.url === '/'
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

          <Link href="/" class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-neutral-900 dark:bg-white flex items-center justify-center">
              <span class="text-white dark:text-neutral-900 font-bold text-xs">O</span>
            </div>
            <span class="font-semibold text-neutral-900 dark:text-white">OpenCompany</span>
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
              <Link href="/" class="flex items-center gap-2.5" @click="mobileMenuOpen = false">
                <div class="w-8 h-8 rounded-lg bg-neutral-900 dark:bg-white flex items-center justify-center">
                  <span class="text-white dark:text-neutral-900 font-bold text-sm">O</span>
                </div>
                <span class="font-semibold text-lg text-neutral-900 dark:text-white tracking-tight">OpenCompany</span>
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
                  href="/integrations"
                  :class="[
                    'flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors',
                    isActive('/integrations')
                      ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
                      : 'hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
                  ]"
                  @click="mobileMenuOpen = false"
                >
                  <Icon :name="isActive('/integrations') ? 'ph:plugs-connected-fill' : 'ph:plugs-connected'" class="w-[18px] h-[18px]" />
                  <span class="text-sm">Integrations</span>
                </Link>
                <Link
                  href="/settings"
                  :class="[
                    'flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors',
                    isActive('/settings')
                      ? 'bg-neutral-200 dark:bg-neutral-800 text-neutral-900 dark:text-white'
                      : 'hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-600 dark:text-neutral-300',
                  ]"
                  @click="mobileMenuOpen = false"
                >
                  <Icon :name="isActive('/settings') ? 'ph:gear-fill' : 'ph:gear'" class="w-[18px] h-[18px]" />
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
        <main class="flex-1 flex flex-col overflow-hidden pt-14 md:pt-0">
          <slot />
        </main>

        <CommandPalette v-model="commandPaletteOpen" />

        <!-- Spawn Agent Modal (accessible from sidebar) -->
        <SpawnAgentModal
          v-model:open="showSpawnModal"
          @spawn="handleAgentSpawned"
        />
      </div>
    </RealtimeProvider>
  </TooltipProvider>
</template>
