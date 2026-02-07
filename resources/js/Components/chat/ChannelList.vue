<template>
  <aside :class="containerClasses">
    <!-- Header -->
    <div v-if="showHeader" :class="headerClasses">
      <div class="flex items-center gap-2">
        <h2 :class="headerTitleClasses">{{ title }}</h2>
        <SharedBadge v-if="totalUnreadCount > 0" size="xs" variant="primary">
          {{ totalUnreadCount > 99 ? '99+' : totalUnreadCount }}
        </SharedBadge>
      </div>

      <div class="flex items-center gap-1">
        <!-- Filter Toggle -->
        <Tooltip v-if="showFilterButton" text="Filter channels" :delay-open="300" side="bottom" :side-offset="5">
          <button
            type="button"
            :class="headerButtonClasses(filterOpen)"
            @click="filterOpen = !filterOpen"
          >
            <Icon name="ph:funnel" class="w-4 h-4" />
          </button>
        </Tooltip>

        <!-- Create Channel -->
        <Tooltip text="Create channel" :delay-open="300" side="bottom" :side-offset="5">
          <button
            type="button"
            :class="headerButtonClasses(false)"
            @click="handleCreateChannel"
          >
            <Icon name="ph:plus" class="w-4 h-4" />
          </button>
        </Tooltip>
      </div>
    </div>

    <!-- Search -->
    <div :class="searchContainerClasses">
      <SharedSearchInput
        v-model="searchQuery"
        :placeholder="searchPlaceholder"
        :size="size === 'sm' ? 'sm' : 'md'"
        :clearable="true"
        :loading="searching"
        @focus="searchFocused = true"
        @blur="searchFocused = false"
      />

      <!-- Quick Filters (visible when search focused) -->
      <Transition name="slide-down">
        <div v-if="showQuickFilters && (searchFocused || searchQuery)" class="mt-2 flex flex-wrap gap-1">
          <button
            v-for="filter in quickFilters"
            :key="filter.value"
            type="button"
            :class="quickFilterClasses(filter.value)"
            @click="toggleQuickFilter(filter.value)"
          >
            <Icon v-if="filter.icon" :name="filter.icon" class="w-3 h-3" />
            {{ filter.label }}
          </button>
        </div>
      </Transition>
    </div>

    <!-- Filter Panel (collapsible) -->
    <Transition name="slide-down">
      <div v-if="filterOpen" :class="filterPanelClasses">
        <div class="space-y-3">
          <div>
            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider mb-2 block">
              Channel Type
            </label>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="type in channelTypeFilters"
                :key="type.value"
                type="button"
                :class="filterChipClasses(activeTypeFilters.includes(type.value))"
                @click="toggleTypeFilter(type.value)"
              >
                <Icon v-if="type.icon" :name="type.icon" class="w-3 h-3" />
                {{ type.label }}
              </button>
            </div>
          </div>

          <div>
            <label class="text-xs font-medium text-neutral-500 dark:text-neutral-300 uppercase tracking-wider mb-2 block">
              Status
            </label>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="status in statusFilters"
                :key="status.value"
                type="button"
                :class="filterChipClasses(activeStatusFilters.includes(status.value))"
                @click="toggleStatusFilter(status.value)"
              >
                {{ status.label }}
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-neutral-200 dark:border-neutral-700">
            <button
              type="button"
              class="text-xs text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white transition-colors"
              @click="clearFilters"
            >
              Clear all
            </button>
            <span class="text-xs text-neutral-400 dark:text-neutral-400">
              {{ filteredChannelsCount }} channels
            </span>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Channel List -->
    <div :class="listContainerClasses">
      <!-- Loading State -->
      <template v-if="loading">
        <ChannelListSkeleton />
      </template>

      <template v-else-if="hasChannels">
        <!-- Pinned Channels -->
        <ChannelSection
          v-if="pinnedChannels.length > 0"
          title="Pinned"
          icon="ph:push-pin"
          :channels="pinnedChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="true"
          :show-count="false"
          :size="size"
          @select="handleSelect"
          @context-action="handleContextAction"
        />

        <!-- Starred Channels -->
        <ChannelSection
          v-if="starredChannels.length > 0"
          title="Starred"
          icon="ph:star"
          :channels="starredChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="true"
          :show-count="false"
          :size="size"
          @select="handleSelect"
          @context-action="handleContextAction"
        />

        <!-- Direct Messages -->
        <ChannelSection
          title="Direct Messages"
          icon="ph:chat-circle"
          :channels="dmChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="true"
          :show-count="true"
          :show-online-count="true"
          :size="size"
          :action="{ icon: 'ph:plus', label: 'New message', onClick: () => emit('createDm') }"
          @select="handleSelect"
          @context-action="handleContextAction"
        />

        <!-- Private Channels -->
        <ChannelSection
          v-if="privateChannels.length > 0"
          title="Private Channels"
          icon="ph:lock-simple"
          :channels="privateChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="true"
          :show-count="true"
          :size="size"
          @select="handleSelect"
          @context-action="handleContextAction"
        />

        <!-- Public Channels -->
        <ChannelSection
          v-if="publicChannels.length > 0"
          title="Channels"
          icon="ph:hash"
          :channels="publicChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="true"
          :show-count="true"
          :size="size"
          :action="{ icon: 'ph:plus', label: 'Add channel', onClick: handleCreateChannel }"
          @select="handleSelect"
          @context-action="handleContextAction"
        />

        <!-- External Channels -->
        <ChannelSection
          v-if="externalChannels.length > 0"
          title="External"
          icon="ph:plug"
          :channels="externalChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="true"
          :show-count="true"
          :size="size"
          :action="{ icon: 'ph:plus', label: 'Connect integration', onClick: () => emit('createExternal') }"
          @select="handleSelect"
          @context-action="handleContextAction"
        />

        <!-- Archived (collapsed by default) -->
        <ChannelSection
          v-if="archivedChannels.length > 0 && showArchived"
          title="Archived"
          icon="ph:archive"
          :channels="archivedChannels"
          :selected-id="selectedChannel?.id"
          :collapsible="true"
          :default-open="false"
          :show-count="true"
          :size="size"
          :muted="true"
          @select="handleSelect"
          @context-action="handleContextAction"
        />
      </template>

      <!-- Empty State -->
      <template v-else>
        <!-- No Search Results -->
        <SharedEmptyState
          v-if="searchQuery || hasActiveFilters"
          icon="ph:magnifying-glass"
          title="No channels found"
          :description="emptySearchDescription"
          size="sm"
        >
          <template #action>
            <button
              type="button"
              class="text-sm text-neutral-900 dark:text-white hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
              @click="clearSearch"
            >
              Clear search
            </button>
          </template>
        </SharedEmptyState>

        <!-- No Channels At All -->
        <SharedEmptyState
          v-else
          icon="ph:chat-circle-dots"
          title="No channels yet"
          description="Create a channel to start collaborating"
          size="sm"
        >
          <template #action>
            <SharedButton size="sm" @click="handleCreateChannel">
              <Icon name="ph:plus" class="w-4 h-4" />
              Create channel
            </SharedButton>
          </template>
        </SharedEmptyState>
      </template>
    </div>

    <!-- Footer -->
    <div v-if="showFooter" :class="footerClasses">
      <!-- Browse All Channels -->
      <button
        type="button"
        :class="footerButtonClasses"
        @click="emit('browse')"
      >
        <Icon name="ph:compass" class="w-4 h-4" />
        Browse all channels
      </button>

      <!-- Show Archived Toggle -->
      <label v-if="archivedChannels.length > 0" class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-300 cursor-pointer mt-2">
        <input
          v-model="showArchived"
          type="checkbox"
          class="w-3.5 h-3.5 rounded text-neutral-900 dark:text-white bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-600 focus:ring-neutral-400/50 dark:focus:ring-neutral-500/50"
        >
        Show archived ({{ archivedChannels.length }})
      </label>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, h, defineComponent } from 'vue'
import { CollapsibleRoot, CollapsibleContent } from 'reka-ui'
import Icon from '@/Components/shared/Icon.vue'
import SharedBadge from '@/Components/shared/Badge.vue'
import Skeleton from '@/Components/shared/Skeleton.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import SharedSearchInput from '@/Components/shared/SearchInput.vue'
import SharedEmptyState from '@/Components/shared/EmptyState.vue'
import SharedButton from '@/Components/shared/Button.vue'
import ChatChannelItem from '@/Components/chat/ChannelItem.vue'
import type { Channel } from '@/types'

type ChannelListSize = 'sm' | 'md' | 'lg'
type ChannelListVariant = 'default' | 'compact' | 'floating'
type ChannelType = 'public' | 'private' | 'dm' | 'external'
type StatusFilter = 'unread' | 'muted' | 'pinned' | 'starred'
type QuickFilter = 'unread' | 'dms'

interface SectionAction {
  icon: string
  label: string
  onClick: () => void
}

const props = withDefaults(defineProps<{
  // Core
  channels: Channel[]
  selectedChannel?: Channel

  // Appearance
  size?: ChannelListSize
  variant?: ChannelListVariant
  width?: string
  title?: string

  // Features
  showHeader?: boolean
  showFooter?: boolean
  showFilterButton?: boolean
  showQuickFilters?: boolean
  searchPlaceholder?: string

  // State
  loading?: boolean
  searching?: boolean
}>(), {
  size: 'md',
  variant: 'default',
  width: 'w-60',
  title: 'Channels',
  showHeader: true,
  showFooter: true,
  showFilterButton: true,
  showQuickFilters: true,
  searchPlaceholder: 'Search channels...',
  loading: false,
  searching: false,
})

const emit = defineEmits<{
  select: [channel: Channel]
  create: []
  createDm: []
  createExternal: []
  browse: []
  contextAction: [action: string, channel: Channel]
}>()

// State
const searchQuery = ref('')
const searchFocused = ref(false)
const filterOpen = ref(false)
const showArchived = ref(false)
const activeTypeFilters = ref<ChannelType[]>([])
const activeStatusFilters = ref<StatusFilter[]>([])
const activeQuickFilters = ref<QuickFilter[]>([])

// Filter options
const channelTypeFilters: { value: ChannelType; label: string; icon: string }[] = [
  { value: 'public', label: 'Public', icon: 'ph:hash' },
  { value: 'private', label: 'Private', icon: 'ph:lock-simple' },
  { value: 'dm', label: 'DMs', icon: 'ph:chat-circle' },
  { value: 'external', label: 'External', icon: 'ph:plug' },
]

const statusFilters: { value: StatusFilter; label: string }[] = [
  { value: 'unread', label: 'Unread' },
  { value: 'muted', label: 'Muted' },
  { value: 'pinned', label: 'Pinned' },
  { value: 'starred', label: 'Starred' },
]

const quickFilters: { value: QuickFilter; label: string; icon?: string }[] = [
  { value: 'unread', label: 'Unread' },
  { value: 'dms', label: 'DMs', icon: 'ph:chat-circle' },
]

// Size configurations
const sizeConfig: Record<ChannelListSize, {
  container: string
  header: string
  search: string
  list: string
  footer: string
}> = {
  sm: {
    container: 'w-52',
    header: 'px-2 py-2',
    search: 'px-2 py-1.5',
    list: 'px-2 pb-2',
    footer: 'px-2 py-2',
  },
  md: {
    container: 'w-60',
    header: 'px-3 py-3',
    search: 'px-3 py-2',
    list: 'px-3 pb-3',
    footer: 'px-3 py-3',
  },
  lg: {
    container: 'w-72',
    header: 'px-4 py-4',
    search: 'px-4 py-3',
    list: 'px-4 pb-4',
    footer: 'px-4 py-4',
  },
}

// Filter channels
const filteredChannels = computed(() => {
  let result = props.channels

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(c =>
      c.name.toLowerCase().includes(query) ||
      c.description?.toLowerCase().includes(query),
    )
  }

  // Type filters
  if (activeTypeFilters.value.length > 0) {
    result = result.filter(c => {
      if (activeTypeFilters.value.includes('public') && c.type === 'public' && !c.private) return true
      if (activeTypeFilters.value.includes('private') && c.private) return true
      if (activeTypeFilters.value.includes('dm') && c.type === 'dm') return true
      if (activeTypeFilters.value.includes('external') && c.type === 'external') return true
      return false
    })
  }

  // Status filters
  if (activeStatusFilters.value.length > 0) {
    result = result.filter(c => {
      if (activeStatusFilters.value.includes('unread') && (c.unreadCount || 0) > 0) return true
      if (activeStatusFilters.value.includes('muted') && c.muted) return true
      if (activeStatusFilters.value.includes('pinned') && c.pinned) return true
      if (activeStatusFilters.value.includes('starred') && c.starred) return true
      return false
    })
  }

  // Quick filters
  if (activeQuickFilters.value.length > 0) {
    result = result.filter(c => {
      if (activeQuickFilters.value.includes('unread') && (c.unreadCount || 0) > 0) return true
      if (activeQuickFilters.value.includes('dms') && c.type === 'dm') return true
      return activeQuickFilters.value.length === 0
    })
  }

  return result
})

// Categorize channels
const pinnedChannels = computed(() =>
  filteredChannels.value.filter(c => c.pinned && !c.archived),
)

const starredChannels = computed(() =>
  filteredChannels.value.filter(c => c.starred && !c.pinned && !c.archived),
)

const dmChannels = computed(() =>
  filteredChannels.value.filter(c => c.type === 'dm' && !c.pinned && !c.starred && !c.archived),
)

const privateChannels = computed(() =>
  filteredChannels.value.filter(c => (c.type === 'private' || c.private) && c.type !== 'dm' && !c.pinned && !c.starred && !c.archived),
)

const publicChannels = computed(() =>
  filteredChannels.value.filter(c => c.type === 'public' && !c.private && !c.pinned && !c.starred && !c.archived),
)

const archivedChannels = computed(() =>
  filteredChannels.value.filter(c => c.archived),
)

const externalChannels = computed(() =>
  filteredChannels.value.filter(c => c.type === 'external' && !c.pinned && !c.starred && !c.archived),
)

// Computed values
const hasChannels = computed(() => filteredChannels.value.length > 0)
const filteredChannelsCount = computed(() => filteredChannels.value.length)
const hasActiveFilters = computed(() =>
  activeTypeFilters.value.length > 0 ||
  activeStatusFilters.value.length > 0 ||
  activeQuickFilters.value.length > 0,
)

const totalUnreadCount = computed(() =>
  props.channels.reduce((sum, c) => sum + (c.unreadCount || 0), 0),
)

const emptySearchDescription = computed(() => {
  if (searchQuery.value) {
    return `No results for "${searchQuery.value}"`
  }
  if (hasActiveFilters.value) {
    return 'Try adjusting your filters'
  }
  return 'No channels match your criteria'
})

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0',
    props.width || sizeConfig[props.size].container,
  ]

  if (props.variant === 'floating') {
    classes.push('absolute left-0 top-0 h-full shadow-lg z-10')
  }

  if (props.variant === 'compact') {
    classes.push('border-r-0')
  }

  return classes
})

// Header classes
const headerClasses = computed(() => [
  'flex items-center justify-between border-b border-neutral-200 dark:border-neutral-700',
  sizeConfig[props.size].header,
])

// Header title classes
const headerTitleClasses = computed(() => {
  const sizeMap: Record<ChannelListSize, string> = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-lg',
  }
  return ['font-semibold text-neutral-900 dark:text-white', sizeMap[props.size]]
})

// Header button classes
const headerButtonClasses = (active: boolean) => [
  'p-1.5 rounded-lg transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
  active
    ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
    : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800',
]

// Search container classes
const searchContainerClasses = computed(() => [
  sizeConfig[props.size].search,
])

// Quick filter classes
const quickFilterClasses = (filter: QuickFilter) => [
  'flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium',
  'transition-colors duration-150',
  activeQuickFilters.value.includes(filter)
    ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
    : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800',
]

// Filter panel classes
const filterPanelClasses = computed(() => [
  'border-b border-neutral-200 dark:border-neutral-700',
  sizeConfig[props.size].search,
])

// Filter chip classes
const filterChipClasses = (active: boolean) => [
  'flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs',
  'transition-colors duration-150',
  active
    ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
    : 'bg-neutral-50 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700',
]

// List container classes
const listContainerClasses = computed(() => [
  'flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-neutral-300 dark:scrollbar-thumb-neutral-600 scrollbar-track-transparent',
  sizeConfig[props.size].list,
])

// Footer classes
const footerClasses = computed(() => [
  'border-t border-neutral-200 dark:border-neutral-700',
  sizeConfig[props.size].footer,
])

// Footer button classes
const footerButtonClasses = computed(() => [
  'w-full flex items-center gap-2 px-3 py-2.5 rounded-lg group',
  'text-sm text-neutral-500 dark:text-neutral-300',
  'hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800',
  'transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
])

// Filter functions
const toggleTypeFilter = (type: ChannelType) => {
  const index = activeTypeFilters.value.indexOf(type)
  if (index > -1) {
    activeTypeFilters.value.splice(index, 1)
  } else {
    activeTypeFilters.value.push(type)
  }
}

const toggleStatusFilter = (status: StatusFilter) => {
  const index = activeStatusFilters.value.indexOf(status)
  if (index > -1) {
    activeStatusFilters.value.splice(index, 1)
  } else {
    activeStatusFilters.value.push(status)
  }
}

const toggleQuickFilter = (filter: QuickFilter) => {
  const index = activeQuickFilters.value.indexOf(filter)
  if (index > -1) {
    activeQuickFilters.value.splice(index, 1)
  } else {
    activeQuickFilters.value.push(filter)
  }
}

const clearFilters = () => {
  activeTypeFilters.value = []
  activeStatusFilters.value = []
  activeQuickFilters.value = []
}

const clearSearch = () => {
  searchQuery.value = ''
  clearFilters()
}

// Handlers
const handleSelect = (channel: Channel) => {
  emit('select', channel)
}

const handleCreateChannel = () => {
  emit('create')
}

const handleContextAction = (action: string, channel: Channel) => {
  emit('contextAction', action, channel)
}

// Channel Section Component
const ChannelSection = defineComponent({
  name: 'ChannelSection',
  props: {
    title: { type: String, required: true },
    icon: { type: String, default: undefined },
    channels: { type: Array as () => Channel[], required: true },
    selectedId: { type: String, default: undefined },
    collapsible: { type: Boolean, default: true },
    defaultOpen: { type: Boolean, default: true },
    showCount: { type: Boolean, default: true },
    showOnlineCount: { type: Boolean, default: false },
    size: { type: String as () => ChannelListSize, default: 'md' },
    action: { type: Object as () => SectionAction | undefined, default: undefined },
    muted: { type: Boolean, default: false },
  },
  emits: ['select', 'contextAction'],
  setup(sectionProps, { emit: sectionEmit }) {
    const isOpen = ref(sectionProps.defaultOpen)

    const unreadCount = computed(() =>
      sectionProps.channels.reduce((sum, c) => sum + (c.unreadCount || 0), 0),
    )

    const onlineCount = computed(() =>
      sectionProps.channels.filter(c => c.onlineCount && c.onlineCount > 0).length,
    )

    const headerContent = () => [
      h('div', { class: 'flex items-center gap-2' }, [
        sectionProps.icon && h(Icon, {
          name: sectionProps.icon,
          class: ['w-3.5 h-3.5', sectionProps.muted ? 'text-neutral-400 dark:text-neutral-400' : 'text-neutral-500 dark:text-neutral-300'],
        }),
        h('span', {
          class: ['text-xs font-semibold uppercase tracking-wider', sectionProps.muted ? 'text-neutral-400 dark:text-neutral-400' : 'text-neutral-500 dark:text-neutral-300'],
        }, sectionProps.title),
        sectionProps.showCount && h('span', {
          class: 'text-xs text-neutral-400 dark:text-neutral-400',
        }, `(${sectionProps.channels.length})`),
        unreadCount.value > 0 && h(SharedBadge, {
          size: 'xs',
          variant: 'primary',
        }, () => unreadCount.value > 99 ? '99+' : unreadCount.value),
        sectionProps.showOnlineCount && onlineCount.value > 0 && h('span', {
          class: 'flex items-center gap-1 text-xs text-green-600',
        }, [
          h('span', { class: 'w-1.5 h-1.5 rounded-full bg-green-600' }),
          `${onlineCount.value}`,
        ]),
      ]),
      h('div', { class: 'flex items-center gap-1' }, [
        sectionProps.action && h('button', {
          type: 'button',
          class: [
            'p-1 rounded-md transition-colors duration-150',
            'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-700',
            'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
            'opacity-0 group-hover:opacity-100',
          ],
          onClick: (e: Event) => {
            e.stopPropagation()
            sectionProps.action?.onClick()
          },
        }, [
          h(Icon, { name: sectionProps.action.icon, class: 'w-3.5 h-3.5' }),
        ]),
        sectionProps.collapsible && h(Icon, {
          name: 'ph:caret-down',
          class: [
            'w-3.5 h-3.5 text-neutral-500 dark:text-neutral-300 transition-transform duration-150',
            isOpen.value ? 'rotate-180' : '',
          ],
        }),
      ]),
    ]

    const channelList = () => h('div', { class: 'space-y-0.5 mt-1' },
      sectionProps.channels.map(channel =>
        h(ChatChannelItem, {
          key: channel.id,
          channel,
          selected: sectionProps.selectedId === channel.id,
          size: sectionProps.size,
          muted: channel.muted,
          pinned: channel.pinned,
          onClick: () => sectionEmit('select', channel),
          onContextAction: (action: string) => sectionEmit('contextAction', action, channel),
        }),
      ),
    )

    if (!sectionProps.collapsible) {
      return () => h('div', { class: 'mb-4 group' }, [
        h('div', { class: 'flex items-center justify-between mb-2' }, headerContent()),
        channelList(),
      ])
    }

    return () => h(CollapsibleRoot, {
      'open': isOpen.value,
      'onUpdate:open': (val: boolean) => { isOpen.value = val },
      'class': 'mb-4 group',
    }, () => [
      h('div', {
        class: 'w-full flex items-center justify-between py-1 cursor-pointer',
        onClick: () => { isOpen.value = !isOpen.value },
      }, headerContent()),
      h(CollapsibleContent, {}, () => channelList()),
    ])
  },
})

// Channel List Skeleton Component
const ChannelListSkeleton = defineComponent({
  name: 'ChannelListSkeleton',
  setup() {
    return () => h('div', { class: 'space-y-4' }, [
      // Section 1
      h('div', {}, [
        h(Skeleton, { class: 'h-3 w-24 mb-3 ml-2' }),
        h('div', { class: 'space-y-1' },
          [1, 2, 3].map(i =>
            h('div', { key: `agent-${i}`, class: 'flex items-center gap-2 px-2 py-2' }, [
              h(Skeleton, { class: 'w-6 h-6 rounded-full' }),
              h(Skeleton, { class: 'h-3 w-28' }),
            ]),
          ),
        ),
      ]),
      // Section 2
      h('div', {}, [
        h(Skeleton, { class: 'h-3 w-20 mb-3 ml-2' }),
        h('div', { class: 'space-y-1' },
          [1, 2, 3, 4].map(i =>
            h('div', { key: `pub-${i}`, class: 'flex items-center gap-2 px-2 py-2' }, [
              h(Skeleton, { class: 'w-5 h-5 rounded-md' }),
              h(Skeleton, { class: 'h-3 w-24' }),
            ]),
          ),
        ),
      ]),
    ])
  },
})
</script>

<style scoped>
/* Scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
  width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: rgb(209 213 219);
  border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background-color: rgb(156 163 175);
}

/* Slide down animation */
.slide-down-enter-active {
  transition: all 0.15s ease-out;
}

.slide-down-leave-active {
  transition: all 0.1s ease-out;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Collapsible animation */
@keyframes collapsible-down {
  from {
    height: 0;
    opacity: 0;
  }
  to {
    height: var(--reka-collapsible-content-height);
    opacity: 1;
  }
}

@keyframes collapsible-up {
  from {
    height: var(--reka-collapsible-content-height);
    opacity: 1;
  }
  to {
    height: 0;
    opacity: 0;
  }
}

.animate-collapsible-down {
  animation: collapsible-down 0.15s ease-out;
}

.animate-collapsible-up {
  animation: collapsible-up 0.1s ease-out;
}
</style>
