<template>
  <DialogRoot :open="isOpen" @update:open="isOpen = $event">
    <DialogPortal>
      <!-- Backdrop -->
      <DialogOverlay
        :class="[
          'fixed inset-0 z-50',
          'bg-black/60 backdrop-blur-sm',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0'
        ]"
      />

      <!-- Dialog container -->
      <DialogContent
        :class="[
          'fixed top-[15%] left-1/2 -translate-x-1/2 z-50',
          'w-full overflow-hidden',
          sizeConfig[size].container,
          'bg-olympus-elevated border border-olympus-border rounded-2xl shadow-2xl',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          'duration-200'
        ]"
        @escape-key-down="handleClose"
      >
        <!-- Search header -->
        <div
          :class="[
            'flex items-center gap-3 border-b border-olympus-border relative',
            sizeConfig[size].searchPadding
          ]"
        >
          <!-- Search icon with animation -->
          <div class="relative">
            <Icon
              name="ph:magnifying-glass"
              :class="[
                'text-olympus-text-muted transition-all duration-200',
                sizeConfig[size].searchIcon,
                isSearching && 'animate-pulse'
              ]"
            />
            <!-- Loading spinner overlay -->
            <div
              v-if="isSearching"
              class="absolute inset-0 flex items-center justify-center"
            >
              <div class="w-4 h-4 border-2 border-olympus-primary/30 border-t-olympus-primary rounded-full animate-spin" />
            </div>
          </div>

          <!-- Search input -->
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            :placeholder="placeholder"
            :class="[
              'flex-1 bg-transparent text-olympus-text placeholder:text-olympus-text-muted outline-none',
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
            enter-active-class="transition-all duration-200"
            leave-active-class="transition-all duration-150"
            enter-from-class="opacity-0 scale-95"
            leave-to-class="opacity-0 scale-95"
          >
            <div
              v-if="searchScope"
              class="flex items-center gap-2 px-2 py-1 bg-olympus-primary/10 rounded-lg"
            >
              <Icon :name="searchScope.icon" class="w-3.5 h-3.5 text-olympus-primary" />
              <span class="text-xs text-olympus-primary font-medium">{{ searchScope.label }}</span>
              <button
                class="hover:bg-olympus-primary/20 rounded p-0.5 transition-colors"
                @click="clearScope"
              >
                <Icon name="ph:x" class="w-3 h-3 text-olympus-primary" />
              </button>
            </div>
          </Transition>

          <!-- Close hint -->
          <kbd
            :class="[
              'bg-olympus-surface border border-olympus-border rounded text-olympus-text-muted font-mono shrink-0',
              sizeConfig[size].kbd
            ]"
          >
            esc
          </kbd>
        </div>

        <!-- Mode tabs -->
        <div
          v-if="showModeTabs"
          class="flex items-center gap-1 px-3 py-2 border-b border-olympus-border bg-olympus-surface/30"
        >
          <button
            v-for="mode in modes"
            :key="mode.id"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200',
              currentMode === mode.id
                ? 'bg-olympus-primary text-white shadow-sm shadow-olympus-primary/30'
                : 'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface'
            ]"
            @click="setMode(mode.id)"
          >
            <Icon :name="mode.icon" class="w-4 h-4" />
            <span>{{ mode.label }}</span>
            <kbd
              v-if="mode.shortcut"
              :class="[
                'ml-1 text-[10px] font-mono px-1.5 py-0.5 rounded',
                currentMode === mode.id
                  ? 'bg-white/20 text-white/80'
                  : 'bg-olympus-surface text-olympus-text-subtle'
              ]"
            >
              {{ mode.shortcut }}
            </kbd>
          </button>
        </div>

        <!-- Recent searches -->
        <Transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 -translate-y-2"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div
            v-if="showRecentSearches && recentSearches.length > 0 && !searchQuery"
            class="px-3 py-2 border-b border-olympus-border"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-medium text-olympus-text-muted uppercase tracking-wider">
                Recent
              </span>
              <button
                class="text-xs text-olympus-text-subtle hover:text-olympus-text transition-colors"
                @click="clearRecentSearches"
              >
                Clear all
              </button>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="(search, index) in recentSearches"
                :key="index"
                class="flex items-center gap-1.5 px-2.5 py-1 text-sm bg-olympus-surface hover:bg-olympus-border rounded-lg transition-colors group"
                @click="applyRecentSearch(search)"
              >
                <Icon name="ph:clock-counter-clockwise" class="w-3.5 h-3.5 text-olympus-text-subtle" />
                <span class="text-olympus-text-muted">{{ search }}</span>
                <button
                  class="opacity-0 group-hover:opacity-100 hover:bg-olympus-surface rounded p-0.5 transition-all"
                  @click.stop="removeRecentSearch(index)"
                >
                  <Icon name="ph:x" class="w-3 h-3 text-olympus-text-subtle" />
                </button>
              </button>
            </div>
          </div>
        </Transition>

        <!-- Results container -->
        <div
          ref="resultsContainer"
          :class="[
            'overflow-y-auto scrollbar-thin scrollbar-thumb-olympus-border scrollbar-track-transparent',
            sizeConfig[size].results
          ]"
        >
          <!-- Loading state -->
          <div v-if="isSearching && searchQuery" class="p-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-olympus-surface animate-pulse" />
              <div class="flex-1 space-y-2">
                <div class="h-4 w-32 bg-olympus-surface rounded animate-pulse" />
                <div class="h-3 w-48 bg-olympus-surface/50 rounded animate-pulse" />
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
              <LayoutCommandPaletteGroup
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
                  <LayoutCommandPaletteItem
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
              </LayoutCommandPaletteGroup>
            </TransitionGroup>

            <!-- Empty state -->
            <LayoutCommandPaletteEmpty
              v-if="filteredGroups.length === 0"
              :query="searchQuery"
              :suggestions="emptySuggestions"
              @suggestion="applySuggestion"
            />
          </template>
        </div>

        <!-- Preview panel (for certain items) -->
        <Transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-150"
          enter-from-class="opacity-0 translate-x-4"
          leave-to-class="opacity-0 translate-x-4"
        >
          <div
            v-if="showPreview && selectedItem?.preview"
            class="absolute right-0 top-0 bottom-0 w-80 border-l border-olympus-border bg-olympus-surface/50 p-4 overflow-y-auto"
          >
            <div class="mb-3 flex items-center justify-between">
              <span class="text-sm font-medium text-olympus-text-muted">Preview</span>
              <button
                class="p-1 hover:bg-olympus-border rounded transition-colors"
                @click="showPreview = false"
              >
                <Icon name="ph:x" class="w-4 h-4 text-olympus-text-muted" />
              </button>
            </div>
            <component :is="selectedItem.preview" v-bind="selectedItem.previewProps" />
          </div>
        </Transition>

        <!-- Footer -->
        <LayoutCommandPaletteFooter
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
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot } from 'reka-ui'

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
}>(), {
  size: 'md',
  placeholder: 'Search commands, channels, files...',
  footerTitle: 'Olympus Command Center',
  showModeTabs: true,
  showRecentSearches: true,
  showFooterActions: false,
  maxRecentSearches: 5,
})

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

// Composables
const router = useRouter()
const { isOpen } = useCommandPalette()
const { channels, agents } = useMockData()

// State
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
  { id: 'commands', label: 'Commands', icon: 'ph:terminal', shortcut: '⌘1' },
  { id: 'files', label: 'Files', icon: 'ph:file-text', shortcut: '⌘2' },
  { id: 'channels', label: 'Channels', icon: 'ph:hash', shortcut: '⌘3' },
  { id: 'agents', label: 'Agents', icon: 'ph:robot', shortcut: '⌘4' },
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
        iconColor: 'text-blue-400',
        shortcut: 'G H',
        action: () => router.push('/'),
      },
      {
        id: 'nav-chat',
        label: 'Chat',
        description: 'Open chat workspace',
        icon: 'ph:chat-circle-fill',
        iconColor: 'text-green-400',
        shortcut: 'G C',
        badge: '15',
        badgeVariant: 'default',
        action: () => router.push('/chat'),
      },
      {
        id: 'nav-tasks',
        label: 'Tasks',
        description: 'View and manage tasks',
        icon: 'ph:check-square-fill',
        iconColor: 'text-purple-400',
        shortcut: 'G T',
        action: () => router.push('/tasks'),
      },
      {
        id: 'nav-docs',
        label: 'Documents',
        description: 'Browse documents',
        icon: 'ph:file-text-fill',
        iconColor: 'text-orange-400',
        shortcut: 'G D',
        action: () => router.push('/docs'),
      },
    ],
  },
  {
    label: 'Channels',
    icon: 'ph:hash',
    collapsible: true,
    items: channels.map((channel) => ({
      id: `channel-${channel.id}`,
      label: `#${channel.name}`,
      description: channel.description,
      icon: channel.type === 'agent' ? 'ph:robot' : 'ph:hash',
      iconColor: channel.type === 'agent' ? 'text-olympus-accent' : 'text-olympus-text-muted',
      meta: channel.unreadCount ? `${channel.unreadCount} unread` : undefined,
      action: () => router.push(`/chat?channel=${channel.id}`),
    })),
  },
  {
    label: 'Agents',
    icon: 'ph:robot',
    collapsible: true,
    items: agents.map((agent) => ({
      id: `agent-${agent.id}`,
      label: agent.name,
      description: agent.role || 'AI Agent',
      icon: 'ph:robot',
      avatar: { name: agent.name, isAI: true },
      badge: agent.status,
      badgeVariant: agent.status === 'online' ? 'success' : agent.status === 'busy' ? 'warning' : 'default',
      action: () => router.push(`/chat?agent=${agent.id}`),
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
        iconColor: 'text-olympus-primary',
        shortcut: 'N T',
        action: () => {},
      },
      {
        id: 'action-agent',
        label: 'Spawn Agent',
        description: 'Deploy a new AI agent',
        icon: 'ph:robot',
        iconColor: 'text-olympus-accent',
        shortcut: 'N A',
        action: () => {},
      },
      {
        id: 'action-channel',
        label: 'New Channel',
        description: 'Create a new channel',
        icon: 'ph:chat-circle-plus',
        iconColor: 'text-green-400',
        action: () => {},
      },
      {
        id: 'action-doc',
        label: 'New Document',
        description: 'Create a new document',
        icon: 'ph:file-plus',
        iconColor: 'text-orange-400',
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
        iconColor: 'text-yellow-400',
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
.results-enter-active,
.results-leave-active {
  transition: all 0.3s ease;
}

.results-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.results-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

.results-leave-active {
  position: absolute;
}

/* Items transition */
.items-move,
.items-enter-active,
.items-leave-active {
  transition: all 0.2s ease;
}

.items-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.items-leave-to {
  opacity: 0;
  transform: translateX(10px);
}

/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: oklch(0.5 0 0 / 0.2);
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: oklch(0.5 0 0 / 0.3);
}
</style>
