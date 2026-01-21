<template>
  <DialogRoot :open="isOpen" @update:open="isOpen = $event">
    <DialogPortal>
      <!-- Backdrop -->
      <DialogOverlay
        :class="[
          'fixed inset-0 z-50',
          'bg-black/50',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'duration-150'
        ]"
      />

      <!-- Dialog container -->
      <DialogContent
        :class="[
          'fixed top-[15%] left-1/2 -translate-x-1/2 z-50',
          'w-full overflow-hidden',
          sizeConfig[size].container,
          'bg-white border border-gray-200 rounded-lg',
          'shadow-lg',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'duration-150'
        ]"
        @escape-key-down="handleClose"
      >
        <!-- Search header -->
        <div
          :class="[
            'flex items-center gap-3 border-b border-gray-200 relative',
            sizeConfig[size].searchPadding
          ]"
        >
          <!-- Search icon with animation -->
          <div class="relative">
            <Icon
              name="ph:magnifying-glass"
              :class="[
                'text-gray-400 transition-colors duration-150',
                sizeConfig[size].searchIcon,
                isSearching && 'opacity-0'
              ]"
            />
            <!-- Loading spinner overlay -->
            <Transition
              enter-active-class="transition-opacity duration-150 ease-out"
              leave-active-class="transition-opacity duration-100 ease-out"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <div
                v-if="isSearching"
                class="absolute inset-0 flex items-center justify-center"
              >
                <div class="w-4 h-4 border-2 border-gray-200 border-t-gray-600 rounded-full animate-spin" />
              </div>
            </Transition>
          </div>

          <!-- Search input -->
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            :placeholder="placeholder"
            :class="[
              'flex-1 bg-transparent text-gray-900 placeholder:text-gray-400 outline-none',
              sizeConfig[size].searchInput
            ]"
            @keydown.down.prevent="selectNext"
            @keydown.up.prevent="selectPrev"
            @keydown.enter.prevent="executeSelected"
            @keydown.tab.prevent="handleTab"
            @input="handleSearch"
          />

          <!-- Search scope indicator -->
          <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            leave-active-class="transition-opacity duration-100 ease-out"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
          >
            <div
              v-if="searchScope"
              class="flex items-center gap-2 px-2 py-1 bg-gray-100 rounded-lg"
            >
              <Icon :name="searchScope.icon" class="w-3.5 h-3.5 text-gray-600" />
              <span class="text-xs text-gray-600 font-medium">{{ searchScope.label }}</span>
              <button
                class="hover:bg-gray-200 rounded p-0.5 transition-colors duration-150"
                @click="clearScope"
              >
                <Icon name="ph:x" class="w-3 h-3 text-gray-500" />
              </button>
            </div>
          </Transition>

          <!-- Close hint -->
          <kbd
            :class="[
              'bg-gray-50 border border-gray-200 rounded text-gray-400 font-mono shrink-0',
              sizeConfig[size].kbd
            ]"
          >
            esc
          </kbd>
        </div>

        <!-- Mode tabs -->
        <div
          v-if="showModeTabs"
          class="flex items-center gap-1 px-3 py-2 border-b border-gray-200 bg-gray-50"
        >
          <button
            v-for="mode in modes"
            :key="mode.id"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors duration-150',
              currentMode === mode.id
                ? 'bg-gray-900 text-white'
                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'
            ]"
            @click="setMode(mode.id)"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
            <span>{{ mode.label }}</span>
            <kbd
              v-if="mode.shortcut"
              :class="[
                'ml-1 text-[10px] font-mono px-1.5 py-0.5 rounded transition-colors duration-150',
                currentMode === mode.id
                  ? 'bg-white/20 text-white/80'
                  : 'bg-white text-gray-400'
              ]"
            >
              {{ mode.shortcut }}
            </kbd>
          </button>
        </div>

        <!-- Recent searches -->
        <Transition
          enter-active-class="transition-opacity duration-150 ease-out"
          leave-active-class="transition-opacity duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div
            v-if="showRecentSearches && recentSearches.length > 0 && !searchQuery"
            class="px-3 py-2 border-b border-gray-200"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                Recent
              </span>
              <button
                class="text-xs text-gray-400 hover:text-gray-900 transition-colors duration-150"
                @click="clearRecentSearches"
              >
                Clear all
              </button>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="(search, index) in recentSearches"
                :key="index"
                class="group/recent flex items-center gap-1.5 px-2.5 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-150"
                @click="applyRecentSearch(search)"
              >
                <Icon name="ph:clock-counter-clockwise" class="w-3.5 h-3.5 text-gray-400" />
                <span class="text-gray-500 group-hover/recent:text-gray-900 transition-colors duration-150">{{ search }}</span>
                <span
                  role="button"
                  tabindex="0"
                  class="opacity-0 group-hover/recent:opacity-100 hover:bg-gray-300 rounded p-0.5 transition-opacity duration-150 cursor-pointer"
                  @click.stop="removeRecentSearch(index)"
                  @keydown.enter.stop="removeRecentSearch(index)"
                >
                  <Icon name="ph:x" class="w-3 h-3 text-gray-400" />
                </span>
              </button>
            </div>
          </div>
        </Transition>

        <!-- Results container -->
        <div
          ref="resultsContainer"
          :class="[
            'overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent',
            sizeConfig[size].results
          ]"
        >
          <!-- Loading state -->
          <div v-if="isSearching && searchQuery" class="p-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-gray-100" />
              <div class="flex-1 space-y-2">
                <div class="h-4 w-32 bg-gray-100 rounded" />
                <div class="h-3 w-48 bg-gray-50 rounded" />
              </div>
            </div>
          </div>

          <!-- Results groups -->
          <template v-else>
            <TransitionGroup
              name="results"
              tag="div"
              class="p-2"
            >
              <CommandPaletteGroup
                v-for="(group, groupIndex) in filteredGroups"
                :key="group.label"
                :label="group.label"
                :count="group.items.length"
                :icon="group.icon"
                :collapsible="group.collapsible"
                :collapsed="collapsedGroups.has(group.label)"
                @toggle="toggleGroup(group.label)"
              >
                <TransitionGroup
                  name="items"
                  tag="div"
                  class="space-y-0.5"
                >
                  <CommandPaletteItem
                    v-for="(item, itemIndex) in group.items"
                    :key="item.id"
                    :ref="el => setItemRef(groupIndex, itemIndex, el)"
                    :icon="item.icon"
                    :icon-color="item.iconColor"
                    :label="item.label"
                    :description="item.description"
                    :shortcut="item.shortcut"
                    :badge="item.badge"
                    :badge-variant="item.badgeVariant"
                    :meta="item.meta"
                    :avatar="item.avatar"
                    :selected="isSelected(groupIndex, itemIndex)"
                    :disabled="item.disabled"
                    :highlight="searchQuery"
                    :size="size"
                    @select="executeCommand(item)"
                    @hover="setSelection(groupIndex, itemIndex)"
                  />
                </TransitionGroup>
              </CommandPaletteGroup>
            </TransitionGroup>

            <!-- Empty state -->
            <CommandPaletteEmpty
              v-if="filteredGroups.length === 0"
              :query="searchQuery"
              :suggestions="emptySuggestions"
              @suggestion="applySuggestion"
            />
          </template>
        </div>

        <!-- Preview panel (for certain items) -->
        <Transition
          enter-active-class="transition-opacity duration-150 ease-out"
          leave-active-class="transition-opacity duration-100 ease-out"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div
            v-if="showPreview && selectedItemData?.preview"
            class="absolute right-0 top-0 bottom-0 w-80 border-l border-gray-200 bg-gray-50 p-4 overflow-y-auto"
          >
            <div class="mb-3 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-500">Preview</span>
              <button
                class="p-1 hover:bg-gray-200 rounded-lg transition-colors duration-150"
                @click="showPreview = false"
              >
                <Icon name="ph:x" class="w-4 h-4 text-gray-500" />
              </button>
            </div>
            <component :is="selectedItemData.preview" v-bind="selectedItemData.previewProps" />
          </div>
        </Transition>

        <!-- Footer -->
        <CommandPaletteFooter
          :title="footerTitle"
          :show-actions="showFooterActions"
          :selected-count="selectedItems.size"
          :size="size"
          @clear-selection="clearSelection"
          @bulk-action="handleBulkAction"
        />
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, type Component, type ComponentPublicInstance } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot } from 'reka-ui'
import CommandPaletteGroup from '@/Components/layout/command-palette/CommandPaletteGroup.vue'
import CommandPaletteItem from '@/Components/layout/command-palette/CommandPaletteItem.vue'
import CommandPaletteEmpty from '@/Components/layout/command-palette/CommandPaletteEmpty.vue'
import CommandPaletteFooter from '@/Components/layout/command-palette/CommandPaletteFooter.vue'

// Types
type PaletteSize = 'sm' | 'md' | 'lg'
type PaletteMode = 'commands' | 'files' | 'channels' | 'agents'

interface CommandItem {
  id: string
  label: string
  description?: string
  icon: string
  iconColor?: string
  shortcut?: string
  badge?: string
  badgeVariant?: 'default' | 'success' | 'warning' | 'error'
  meta?: string
  avatar?: { src?: string; name: string; isAI?: boolean }
  action: () => void
  disabled?: boolean
  preview?: Component
  previewProps?: Record<string, unknown>
}

interface CommandGroup {
  label: string
  icon?: string
  items: CommandItem[]
  collapsible?: boolean
}

interface Mode {
  id: PaletteMode
  label: string
  icon: string
  shortcut?: string
}

interface SearchScope {
  type: string
  label: string
  icon: string
}

interface SizeConfig {
  container: string
  searchPadding: string
  searchIcon: string
  searchInput: string
  kbd: string
  results: string
}

// Props
const props = withDefaults(defineProps<{
  size?: PaletteSize
  placeholder?: string
  footerTitle?: string
  showModeTabs?: boolean
  showRecentSearches?: boolean
  showFooterActions?: boolean
  maxRecentSearches?: number
  modelValue?: boolean
  channels?: Array<{ id: string; name: string; description?: string; type?: string; unreadCount?: number }>
  agents?: Array<{ id: string; name: string; role?: string; status?: string }>
}>(), {
  size: 'md',
  placeholder: 'Search commands, channels, files...',
  footerTitle: 'Olympus Command Center',
  showModeTabs: true,
  showRecentSearches: true,
  showFooterActions: false,
  maxRecentSearches: 5,
  modelValue: false,
  channels: () => [],
  agents: () => [],
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

// Size configuration
const sizeConfig: Record<PaletteSize, SizeConfig> = {
  sm: {
    container: 'max-w-lg',
    searchPadding: 'px-3 py-2.5',
    searchIcon: 'w-4 h-4',
    searchInput: 'text-sm',
    kbd: 'px-1.5 py-0.5 text-[10px]',
    results: 'max-h-64 p-1.5',
  },
  md: {
    container: 'max-w-xl',
    searchPadding: 'px-4 py-3',
    searchIcon: 'w-5 h-5',
    searchInput: 'text-sm',
    kbd: 'px-2 py-1 text-xs',
    results: 'max-h-80 p-2',
  },
  lg: {
    container: 'max-w-2xl',
    searchPadding: 'px-5 py-4',
    searchIcon: 'w-5 h-5',
    searchInput: 'text-base',
    kbd: 'px-2 py-1 text-xs',
    results: 'max-h-96 p-2',
  },
}

// State
const isOpen = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value)
})
const searchQuery = ref('')
const selectedGroup = ref(0)
const selectedItem = ref(0)
const searchInput = ref<HTMLInputElement | null>(null)
const resultsContainer = ref<HTMLElement | null>(null)
const itemRefs = ref<Map<string, ComponentPublicInstance>>(new Map())
const isSearching = ref(false)
const currentMode = ref<PaletteMode>('commands')
const searchScope = ref<SearchScope | null>(null)
const recentSearches = ref<string[]>(['dashboard', 'settings', '#general'])
const collapsedGroups = ref<Set<string>>(new Set())
const selectedItems = ref<Set<string>>(new Set())
const showPreview = ref(false)

// Modes
const modes: Mode[] = [
  { id: 'commands', label: 'Commands', icon: 'ph:terminal', shortcut: 'Cmd+1' },
  { id: 'files', label: 'Files', icon: 'ph:file-text', shortcut: 'Cmd+2' },
  { id: 'channels', label: 'Channels', icon: 'ph:hash', shortcut: 'Cmd+3' },
  { id: 'agents', label: 'Agents', icon: 'ph:robot', shortcut: 'Cmd+4' },
]

// Empty state suggestions
const emptySuggestions = computed(() => [
  'Try searching for "dashboard"',
  'Search channels with "#"',
  'Search agents with "@"',
])

// Command groups
const commandGroups = computed<CommandGroup[]>(() => [
  {
    label: 'Navigation',
    icon: 'ph:compass',
    items: [
      {
        id: 'nav-home',
        label: 'Dashboard',
        description: 'Go to dashboard overview',
        icon: 'ph:house-fill',
        iconColor: 'text-gray-500',
        shortcut: 'G H',
        action: () => router.visit('/'),
      },
      {
        id: 'nav-chat',
        label: 'Chat',
        description: 'Open chat workspace',
        icon: 'ph:chat-circle-fill',
        iconColor: 'text-gray-500',
        shortcut: 'G C',
        badge: '15',
        badgeVariant: 'default',
        action: () => router.visit('/chat'),
      },
      {
        id: 'nav-tasks',
        label: 'Tasks',
        description: 'View and manage tasks',
        icon: 'ph:check-square-fill',
        iconColor: 'text-gray-500',
        shortcut: 'G T',
        action: () => router.visit('/tasks'),
      },
      {
        id: 'nav-docs',
        label: 'Documents',
        description: 'Browse documents',
        icon: 'ph:file-text-fill',
        iconColor: 'text-gray-500',
        shortcut: 'G D',
        action: () => router.visit('/docs'),
      },
    ],
  },
  {
    label: 'Channels',
    icon: 'ph:hash',
    collapsible: true,
    items: props.channels.map((channel) => ({
      id: `channel-${channel.id}`,
      label: `#${channel.name}`,
      description: channel.description,
      icon: channel.type === 'agent' ? 'ph:robot' : 'ph:hash',
      iconColor: 'text-gray-500',
      meta: channel.unreadCount ? `${channel.unreadCount} unread` : undefined,
      action: () => router.visit(`/chat?channel=${channel.id}`),
    })),
  },
  {
    label: 'Agents',
    icon: 'ph:robot',
    collapsible: true,
    items: props.agents.map((agent) => ({
      id: `agent-${agent.id}`,
      label: agent.name,
      description: agent.role || 'AI Agent',
      icon: 'ph:robot',
      avatar: { name: agent.name, isAI: true },
      badge: agent.status,
      badgeVariant: agent.status === 'online' ? 'success' : agent.status === 'busy' ? 'warning' : 'default',
      action: () => router.visit(`/chat?agent=${agent.id}`),
    })),
  },
  {
    label: 'Actions',
    icon: 'ph:lightning',
    items: [
      {
        id: 'action-task',
        label: 'New Task',
        description: 'Create a new task',
        icon: 'ph:plus-circle',
        iconColor: 'text-gray-600',
        shortcut: 'N T',
        action: () => {},
      },
      {
        id: 'action-agent',
        label: 'Spawn Agent',
        description: 'Deploy a new AI agent',
        icon: 'ph:robot',
        iconColor: 'text-gray-600',
        shortcut: 'N A',
        action: () => {},
      },
      {
        id: 'action-channel',
        label: 'New Channel',
        description: 'Create a new channel',
        icon: 'ph:chats-circle',
        iconColor: 'text-gray-500',
        action: () => {},
      },
      {
        id: 'action-doc',
        label: 'New Document',
        description: 'Create a new document',
        icon: 'ph:file-plus',
        iconColor: 'text-gray-500',
        action: () => {},
      },
    ],
  },
  {
    label: 'Settings',
    icon: 'ph:gear-six',
    items: [
      {
        id: 'settings-theme',
        label: 'Toggle Theme',
        description: 'Switch between light and dark mode',
        icon: 'ph:moon',
        iconColor: 'text-gray-500',
        action: () => {},
      },
      {
        id: 'settings-shortcuts',
        label: 'Keyboard Shortcuts',
        description: 'View all keyboard shortcuts',
        icon: 'ph:keyboard',
        shortcut: '?',
        action: () => {},
      },
      {
        id: 'settings-preferences',
        label: 'Preferences',
        description: 'Open settings panel',
        icon: 'ph:gear-six',
        action: () => {},
      },
    ],
  },
])

// Filtered groups based on search
const filteredGroups = computed(() => {
  if (!searchQuery.value.trim()) {
    // Filter by mode when no search
    if (currentMode.value === 'channels') {
      return commandGroups.value.filter((g) => g.label === 'Channels')
    }
    if (currentMode.value === 'agents') {
      return commandGroups.value.filter((g) => g.label === 'Agents')
    }
    return commandGroups.value
  }

  const query = searchQuery.value.toLowerCase()

  // Special prefix searches
  if (query.startsWith('#')) {
    const channelQuery = query.slice(1)
    return commandGroups.value
      .filter((g) => g.label === 'Channels')
      .map((g) => ({
        ...g,
        items: g.items.filter((item) => item.label.toLowerCase().includes(channelQuery)),
      }))
      .filter((g) => g.items.length > 0)
  }

  if (query.startsWith('@')) {
    const agentQuery = query.slice(1)
    return commandGroups.value
      .filter((g) => g.label === 'Agents')
      .map((g) => ({
        ...g,
        items: g.items.filter((item) => item.label.toLowerCase().includes(agentQuery)),
      }))
      .filter((g) => g.items.length > 0)
  }

  // General search
  return commandGroups.value
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) =>
          item.label.toLowerCase().includes(query) ||
          item.description?.toLowerCase().includes(query)
      ),
    }))
    .filter((group) => group.items.length > 0)
})

// Get currently selected item
const selectedItemData = computed(() => {
  const group = filteredGroups.value[selectedGroup.value]
  return group?.items[selectedItem.value]
})

// Watch for dialog open/close
watch(isOpen, (open) => {
  if (open) {
    searchQuery.value = ''
    selectedGroup.value = 0
    selectedItem.value = 0
    collapsedGroups.value.clear()
    nextTick(() => {
      searchInput.value?.focus()
    })
  }
})

// Reset selection when search changes
watch(searchQuery, () => {
  selectedGroup.value = 0
  selectedItem.value = 0
})

// Methods
const setItemRef = (groupIndex: number, itemIndex: number, el: Element | ComponentPublicInstance | null) => {
  const key = `${groupIndex}-${itemIndex}`
  if (el && '$el' in el) {
    itemRefs.value.set(key, el)
  } else {
    itemRefs.value.delete(key)
  }
}

const isSelected = (groupIndex: number, itemIndex: number): boolean => {
  return selectedGroup.value === groupIndex && selectedItem.value === itemIndex
}

const setSelection = (groupIndex: number, itemIndex: number) => {
  selectedGroup.value = groupIndex
  selectedItem.value = itemIndex
}

const selectNext = () => {
  const groups = filteredGroups.value.filter(
    (g) => !collapsedGroups.value.has(g.label)
  )
  const currentGroup = groups[selectedGroup.value]
  if (!currentGroup) return

  if (selectedItem.value < currentGroup.items.length - 1) {
    selectedItem.value++
  } else {
    // Find next non-collapsed group
    let nextGroup = selectedGroup.value + 1
    while (nextGroup < groups.length && collapsedGroups.value.has(groups[nextGroup]!.label)) {
      nextGroup++
    }
    if (nextGroup < groups.length) {
      selectedGroup.value = nextGroup
      selectedItem.value = 0
    }
  }
  scrollToSelected()
}

const selectPrev = () => {
  if (selectedItem.value > 0) {
    selectedItem.value--
  } else {
    // Find previous non-collapsed group
    const groups = filteredGroups.value.filter(
      (g) => !collapsedGroups.value.has(g.label)
    )
    let prevGroup = selectedGroup.value - 1
    while (prevGroup >= 0 && collapsedGroups.value.has(groups[prevGroup]!.label)) {
      prevGroup--
    }
    if (prevGroup >= 0) {
      selectedGroup.value = prevGroup
      const group = groups[prevGroup]
      selectedItem.value = group ? group.items.length - 1 : 0
    }
  }
  scrollToSelected()
}

const scrollToSelected = () => {
  nextTick(() => {
    const key = `${selectedGroup.value}-${selectedItem.value}`
    const el = itemRefs.value.get(key)
    ;(el?.$el as HTMLElement)?.scrollIntoView({ block: 'nearest' })
  })
}

const executeSelected = () => {
  const group = filteredGroups.value[selectedGroup.value]
  const item = group?.items[selectedItem.value]
  if (item && !item.disabled) {
    executeCommand(item)
  }
}

const executeCommand = (item: CommandItem) => {
  // Add to recent searches
  if (searchQuery.value && !recentSearches.value.includes(searchQuery.value)) {
    recentSearches.value.unshift(searchQuery.value)
    if (recentSearches.value.length > props.maxRecentSearches) {
      recentSearches.value.pop()
    }
  }

  item.action()
  isOpen.value = false
}

const handleSearch = () => {
  // Debounced search simulation
  isSearching.value = true
  setTimeout(() => {
    isSearching.value = false
  }, 200)
}

const handleTab = () => {
  // Auto-complete or cycle through suggestions
  const item = selectedItemData.value
  if (item) {
    searchQuery.value = item.label
  }
}

const handleClose = () => {
  isOpen.value = false
}

const setMode = (mode: PaletteMode) => {
  currentMode.value = mode
  searchQuery.value = ''
  selectedGroup.value = 0
  selectedItem.value = 0
}

const clearScope = () => {
  searchScope.value = null
}

const applyRecentSearch = (search: string) => {
  searchQuery.value = search
}

const removeRecentSearch = (index: number) => {
  recentSearches.value.splice(index, 1)
}

const clearRecentSearches = () => {
  recentSearches.value = []
}

const toggleGroup = (label: string) => {
  if (collapsedGroups.value.has(label)) {
    collapsedGroups.value.delete(label)
  } else {
    collapsedGroups.value.add(label)
  }
}

const applySuggestion = (suggestion: string) => {
  // Extract search term from suggestion
  const match = suggestion.match(/"([^"]+)"/)
  if (match) {
    searchQuery.value = match[1]!
  }
}

const clearSelection = () => {
  selectedItems.value.clear()
}

const handleBulkAction = (action: string) => {
  console.log('Bulk action:', action, selectedItems.value)
}
</script>

<style scoped>
/* Results transition */
.results-move,
.results-enter-active {
  transition: all 0.15s ease-out;
}

.results-leave-active {
  transition: all 0.1s ease-out;
  position: absolute;
}

.results-enter-from {
  opacity: 0;
}

.results-leave-to {
  opacity: 0;
}

/* Items transition */
.items-move,
.items-enter-active {
  transition: all 0.15s ease-out;
}

.items-leave-active {
  transition: all 0.1s ease-out;
}

.items-enter-from {
  opacity: 0;
}

.items-leave-to {
  opacity: 0;
}

/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: oklch(0.8 0 0);
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: oklch(0.7 0 0);
}
</style>
