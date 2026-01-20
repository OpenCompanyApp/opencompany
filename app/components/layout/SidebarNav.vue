<template>
  <nav class="flex-1 overflow-y-auto p-4 space-y-1">
    <NuxtLink
      v-for="item in navItems"
      :key="item.to"
      :to="item.to"
      class="group flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-sidebar"
      :class="[
        isActive(item.to)
          ? 'bg-olympus-primary-muted text-olympus-text border-l-2 border-olympus-primary pl-[10px] shadow-sm shadow-olympus-primary/10'
          : 'hover:bg-olympus-surface text-olympus-text-muted hover:text-olympus-text'
      ]"
    >
      <Icon
        :name="isActive(item.to) ? item.iconActive : item.icon"
        :class="[
          'w-5 h-5 shrink-0 transition-colors duration-150',
          isActive(item.to) ? 'text-olympus-primary' : 'text-olympus-text-muted group-hover:text-olympus-text'
        ]"
      />
      <span class="font-medium text-sm">{{ item.label }}</span>
      <span
        v-if="item.badge && item.badge > 0"
        class="ml-auto px-2 py-0.5 text-xs font-semibold rounded-full bg-olympus-primary/20 text-olympus-primary"
      >
        {{ item.badge > 99 ? '99+' : item.badge }}
      </span>
      <span
        v-if="item.shortcut && !item.badge"
        class="ml-auto text-xs text-olympus-text-subtle opacity-0 group-hover:opacity-100 transition-opacity"
      >
        {{ item.shortcut }}
      </span>
    </NuxtLink>

    <!-- Agents Section -->
    <div class="pt-4 mt-4 border-t border-olympus-border">
      <div class="px-3 mb-2 flex items-center justify-between">
        <span class="text-xs font-semibold text-olympus-text-muted uppercase tracking-wider">
          Agents
        </span>
        <span class="text-xs text-olympus-text-subtle">
          {{ onlineAgents }}/{{ totalAgents }}
        </span>
      </div>

      <div class="space-y-0.5">
        <button
          v-for="agent in agents.slice(0, 5)"
          :key="agent.id"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 text-left group outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-sidebar"
        >
          <SharedAgentAvatar :user="agent" size="xs" />
          <span class="flex-1 truncate text-sm text-olympus-text-muted group-hover:text-olympus-text transition-colors">
            {{ agent.name }}
          </span>
          <SharedStatusBadge v-if="agent.status" :status="agent.status" size="xs" />
        </button>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
const route = useRoute()
const { agents, stats } = useMockData()

const totalAgents = computed(() => stats.totalAgents)
const onlineAgents = computed(() => stats.agentsOnline)

const navItems = [
  { to: '/', icon: 'ph:house', iconActive: 'ph:house-fill', label: 'Dashboard', shortcut: 'G H' },
  { to: '/chat', icon: 'ph:chat-circle', iconActive: 'ph:chat-circle-fill', label: 'Chat', badge: 15, shortcut: 'G C' },
  { to: '/tasks', icon: 'ph:check-square', iconActive: 'ph:check-square-fill', label: 'Tasks', shortcut: 'G T' },
  { to: '/docs', icon: 'ph:file-text', iconActive: 'ph:file-text-fill', label: 'Docs', shortcut: 'G D' },
]

const isActive = (path: string) => {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>
