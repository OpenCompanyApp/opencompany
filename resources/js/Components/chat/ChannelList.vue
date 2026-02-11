<template>
  <aside :class="containerClasses">
    <!-- Header -->
    <div v-if="showHeader" class="flex items-center justify-between px-4 py-3 border-b border-neutral-200 dark:border-neutral-700">
      <div class="flex items-center gap-2">
        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Chats</h2>
        <span
          v-if="totalUnreadCount > 0"
          class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-semibold bg-neutral-900 dark:bg-white text-white dark:text-neutral-900"
        >
          {{ totalUnreadCount > 99 ? '99+' : totalUnreadCount }}
        </span>
      </div>

      <div class="flex items-center gap-1">
        <!-- Search Toggle -->
        <Tooltip text="Search" :delay-open="300" side="bottom" :side-offset="5">
          <button
            type="button"
            :class="headerButtonClasses(searchOpen)"
            @click="searchOpen = !searchOpen"
          >
            <Icon name="ph:magnifying-glass" class="w-4 h-4" />
          </button>
        </Tooltip>

        <!-- Compose -->
        <div class="relative">
          <Tooltip text="New conversation" :delay-open="300" side="bottom" :side-offset="5">
            <button
              type="button"
              :class="headerButtonClasses(composeOpen)"
              @click="composeOpen = !composeOpen"
            >
              <Icon name="ph:pencil-simple-line" class="w-4 h-4" />
            </button>
          </Tooltip>

          <!-- Compose Dropdown -->
          <Transition name="fade-scale">
            <div
              v-if="composeOpen"
              class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl shadow-lg py-1 z-20"
            >
              <button
                type="button"
                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
                @click="handleCompose('dm')"
              >
                <Icon name="ph:chat-circle" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
                New Message
              </button>
              <button
                type="button"
                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
                @click="handleCompose('channel')"
              >
                <Icon name="ph:hash" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
                New Channel
              </button>
              <button
                type="button"
                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-neutral-700 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors"
                @click="handleCompose('external')"
              >
                <Icon name="ph:plug" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
                Connect External
              </button>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <!-- Search Bar (expandable) -->
    <Transition name="slide-down">
      <div v-if="searchOpen" class="px-3 py-2 border-b border-neutral-200 dark:border-neutral-700">
        <SearchInput
          v-model="searchQuery"
          placeholder="Search conversations..."
          size="sm"
          :clearable="true"
          autofocus
        />
      </div>
    </Transition>

    <!-- Filter Chips -->
    <div class="flex gap-1.5 px-3 py-2 overflow-x-auto scrollbar-none border-b border-neutral-100 dark:border-neutral-800">
      <button
        v-for="filter in filters"
        :key="filter.value"
        type="button"
        :class="filterChipClasses(filter.value === activeFilter)"
        @click="activeFilter = filter.value"
      >
        <Icon v-if="filter.icon" :name="filter.icon" class="w-3 h-3" />
        {{ filter.label }}
        <span v-if="filter.value === 'unread' && totalUnreadCount > 0" class="ml-0.5 opacity-70">
          {{ totalUnreadCount }}
        </span>
      </button>
    </div>

    <!-- Channel List -->
    <div class="flex-1 overflow-y-auto scrollbar-thin">
      <!-- Loading State -->
      <template v-if="loading">
        <div class="p-2 space-y-1">
          <div v-for="i in 8" :key="i" class="flex items-center gap-3 px-3 py-3 animate-pulse">
            <div class="w-10 h-10 rounded-full bg-neutral-200 dark:bg-neutral-700 shrink-0" />
            <div class="flex-1 space-y-2">
              <div class="flex justify-between">
                <div class="h-3.5 rounded bg-neutral-200 dark:bg-neutral-700" :style="{ width: `${80 + (i * 13) % 60}px` }" />
                <div class="h-3 w-8 rounded bg-neutral-200 dark:bg-neutral-700" />
              </div>
              <div class="h-3 rounded bg-neutral-100 dark:bg-neutral-800" :style="{ width: `${120 + (i * 17) % 80}px` }" />
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="hasChannels">
        <div class="p-2 space-y-0.5">
          <!-- Pinned Chats -->
          <template v-if="pinnedChannels.length > 0">
            <ChatChannelItem
              v-for="channel in pinnedChannels"
              :key="channel.id"
              :channel="channel"
              :selected="selectedChannel?.id === channel.id"
              :muted="channel.muted"
              :pinned="channel.pinned"
              @click="handleSelect"
              @context-action="handleContextAction"
            />
            <!-- Pinned divider -->
            <div class="mx-3 my-1.5 border-b border-neutral-200 dark:border-neutral-700" />
          </template>

          <!-- Unpinned Chats (sorted by latest message) -->
          <ChatChannelItem
            v-for="channel in unpinnedChannels"
            :key="channel.id"
            :channel="channel"
            :selected="selectedChannel?.id === channel.id"
            :muted="channel.muted"
            :pinned="channel.pinned"
            @click="handleSelect"
            @context-action="handleContextAction"
          />
        </div>
      </template>

      <!-- Empty States -->
      <template v-else>
        <!-- No Search Results -->
        <div v-if="searchQuery" class="flex flex-col items-center justify-center py-12 px-4 text-center">
          <Icon name="ph:magnifying-glass" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mb-3" />
          <p class="text-sm font-medium text-neutral-900 dark:text-white">No results</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            No conversations match "{{ searchQuery }}"
          </p>
          <button
            type="button"
            class="mt-3 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
            @click="searchQuery = ''"
          >
            Clear search
          </button>
        </div>

        <!-- No Channels With Active Filter -->
        <div v-else-if="activeFilter !== 'all'" class="flex flex-col items-center justify-center py-12 px-4 text-center">
          <Icon name="ph:funnel" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mb-3" />
          <p class="text-sm font-medium text-neutral-900 dark:text-white">Nothing here</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            No {{ activeFilter === 'unread' ? 'unread' : activeFilter === 'dms' ? 'direct message' : activeFilter === 'channels' ? 'channel' : 'external' }} conversations
          </p>
          <button
            type="button"
            class="mt-3 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
            @click="activeFilter = 'all'"
          >
            Show all chats
          </button>
        </div>

        <!-- No Channels At All -->
        <div v-else class="flex flex-col items-center justify-center py-12 px-4 text-center">
          <Icon name="ph:chat-circle-dots" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mb-3" />
          <p class="text-sm font-medium text-neutral-900 dark:text-white">No conversations</p>
          <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
            Start a new conversation to get going
          </p>
        </div>
      </template>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'
import SearchInput from '@/Components/shared/SearchInput.vue'
import ChatChannelItem from '@/Components/chat/ChannelItem.vue'
import type { Channel } from '@/types'

type FilterTab = 'all' | 'unread' | 'dms' | 'channels' | 'external'

const props = withDefaults(defineProps<{
  channels: Channel[]
  selectedChannel?: Channel
  showHeader?: boolean
  loading?: boolean
}>(), {
  showHeader: true,
  loading: false,
})

const emit = defineEmits<{
  select: [channel: Channel]
  create: []
  createDm: []
  createExternal: []
  contextAction: [action: string, channel: Channel]
}>()

// State
const searchQuery = ref('')
const searchOpen = ref(false)
const composeOpen = ref(false)
const activeFilter = ref<FilterTab>('all')

// Close compose dropdown when clicking outside
const handleClickOutside = (e: MouseEvent) => {
  if (composeOpen.value) {
    composeOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Filter options
const filters: { value: FilterTab; label: string; icon?: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'unread', label: 'Unread' },
  { value: 'dms', label: 'DMs', icon: 'ph:chat-circle' },
  { value: 'channels', label: 'Channels', icon: 'ph:hash' },
  { value: 'external', label: 'External', icon: 'ph:plug' },
]

// Filtered channels
const filteredChannels = computed(() => {
  let result = props.channels.filter(c => !c.archived)

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(c =>
      c.name.toLowerCase().includes(query) ||
      c.description?.toLowerCase().includes(query) ||
      c.latestMessage?.content?.toLowerCase().includes(query),
    )
  }

  // Tab filter
  switch (activeFilter.value) {
    case 'unread':
      result = result.filter(c => (c.unreadCount || 0) > 0)
      break
    case 'dms':
      result = result.filter(c => c.type === 'dm')
      break
    case 'channels':
      result = result.filter(c => c.type === 'public' || c.type === 'private')
      break
    case 'external':
      result = result.filter(c => c.type === 'external')
      break
  }

  return result
})

// Sort helper
function sortByLastMessage(a: Channel, b: Channel): number {
  const aTime = getMessageTime(a)
  const bTime = getMessageTime(b)
  return bTime - aTime // descending (most recent first)
}

function getMessageTime(c: Channel): number {
  if (c.latestMessage?.timestamp) return new Date(c.latestMessage.timestamp).getTime()
  if (c.lastMessageAt) return new Date(c.lastMessageAt).getTime()
  if (c.createdAt) return new Date(c.createdAt).getTime()
  return 0
}

// Pinned channels (at top)
const pinnedChannels = computed(() =>
  filteredChannels.value
    .filter(c => c.pinned)
    .sort(sortByLastMessage),
)

// Unpinned channels (sorted by latest message)
const unpinnedChannels = computed(() =>
  filteredChannels.value
    .filter(c => !c.pinned)
    .sort(sortByLastMessage),
)

// Computed values
const hasChannels = computed(() => filteredChannels.value.length > 0)

const totalUnreadCount = computed(() =>
  props.channels.reduce((sum, c) => sum + (c.unreadCount || 0), 0),
)

// Container classes
const containerClasses = computed(() => [
  'bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-700 flex flex-col shrink-0 w-80',
])

// Header button classes
const headerButtonClasses = (active: boolean) => [
  'p-1.5 rounded-lg transition-colors duration-150',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
  active
    ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
    : 'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-50 dark:hover:bg-neutral-800',
]

// Filter chip classes
const filterChipClasses = (active: boolean) => [
  'flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap',
  'transition-all duration-150 shrink-0',
  active
    ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
    : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700',
]

// Handlers
const handleSelect = (channel: Channel) => {
  emit('select', channel)
}

const handleContextAction = (action: string, channel: Channel) => {
  emit('contextAction', action, channel)
}

const handleCompose = (type: 'dm' | 'channel' | 'external') => {
  composeOpen.value = false
  switch (type) {
    case 'dm':
      emit('createDm')
      break
    case 'channel':
      emit('create')
      break
    case 'external':
      emit('createExternal')
      break
  }
}
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

.scrollbar-none::-webkit-scrollbar {
  display: none;
}

.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
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

/* Fade scale animation for compose dropdown */
.fade-scale-enter-active {
  transition: all 0.15s ease-out;
}

.fade-scale-leave-active {
  transition: all 0.1s ease-in;
}

.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(-4px);
}
</style>
