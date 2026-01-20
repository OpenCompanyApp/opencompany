<template>
  <DialogRoot :open="isOpen" @update:open="isOpen = $event">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <DialogContent
        class="fixed top-[20%] left-1/2 -translate-x-1/2 w-full max-w-xl bg-olympus-elevated border border-olympus-border rounded-2xl shadow-2xl z-50 overflow-hidden data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 duration-200"
        @escape-key-down="isOpen = false"
      >
        <!-- Search Input -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-olympus-border">
          <Icon name="ph:magnifying-glass" class="w-5 h-5 text-olympus-text-muted" />
          <input
            ref="searchInput"
            v-model="searchQuery"
            type="text"
            placeholder="Search commands, channels, or type a command..."
            class="flex-1 bg-transparent text-olympus-text placeholder:text-olympus-text-muted outline-none text-sm"
            @keydown.down.prevent="selectNext"
            @keydown.up.prevent="selectPrev"
            @keydown.enter.prevent="executeSelected"
          />
          <kbd class="px-2 py-1 text-xs bg-olympus-surface border border-olympus-border rounded text-olympus-text-muted font-mono">
            esc
          </kbd>
        </div>

        <!-- Results -->
        <div class="max-h-80 overflow-y-auto p-2">
          <LayoutCommandPaletteGroup
            v-for="(group, groupIndex) in filteredGroups"
            :key="group.label"
            :label="group.label"
          >
            <LayoutCommandPaletteItem
              v-for="(item, itemIndex) in group.items"
              :key="item.id"
              :ref="el => setItemRef(groupIndex, itemIndex, el)"
              :icon="item.icon"
              :label="item.label"
              :description="item.description"
              :shortcut="item.shortcut"
              :selected="isSelected(groupIndex, itemIndex)"
              @select="executeCommand(item)"
              @hover="setSelection(groupIndex, itemIndex)"
            />
          </LayoutCommandPaletteGroup>

          <LayoutCommandPaletteEmpty v-if="filteredGroups.length === 0" />
        </div>

        <!-- Footer -->
        <LayoutCommandPaletteFooter title="Olympus Command Center" />
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot } from 'reka-ui'

const router = useRouter()
const { isOpen } = useCommandPalette()
const { channels } = useMockData()

const searchQuery = ref('')
const selectedGroup = ref(0)
const selectedItem = ref(0)
const searchInput = ref<HTMLInputElement | null>(null)
const itemRefs = ref<Map<string, ComponentPublicInstance>>(new Map())

watch(isOpen, (open) => {
  if (open) {
    searchQuery.value = ''
    selectedGroup.value = 0
    selectedItem.value = 0
    nextTick(() => {
      searchInput.value?.focus()
    })
  }
})

interface CommandItem {
  id: string
  label: string
  description?: string
  icon: string
  shortcut?: string
  action: () => void
}

const commandGroups = computed(() => [
  {
    label: 'Navigation',
    items: [
      { id: 'nav-home', label: 'Dashboard', description: 'Go to dashboard', icon: 'ph:house-fill', shortcut: 'G H', action: () => router.push('/') },
      { id: 'nav-chat', label: 'Chat', description: 'Go to chat', icon: 'ph:chat-circle-fill', shortcut: 'G C', action: () => router.push('/chat') },
      { id: 'nav-tasks', label: 'Tasks', description: 'Go to tasks', icon: 'ph:check-square-fill', shortcut: 'G T', action: () => router.push('/tasks') },
      { id: 'nav-docs', label: 'Docs', description: 'Go to documents', icon: 'ph:file-text-fill', shortcut: 'G D', action: () => router.push('/docs') },
    ],
  },
  {
    label: 'Channels',
    items: channels.map(channel => ({
      id: `channel-${channel.id}`,
      label: `#${channel.name}`,
      description: channel.description,
      icon: channel.type === 'agent' ? 'ph:robot' : 'ph:hash',
      shortcut: undefined as string | undefined,
      action: () => router.push(`/chat?channel=${channel.id}`),
    })),
  },
  {
    label: 'Actions',
    items: [
      { id: 'action-task', label: 'New Task', description: 'Create a new task', icon: 'ph:plus-circle', shortcut: 'N T', action: () => {} },
      { id: 'action-agent', label: 'Spawn Agent', description: 'Deploy a new AI agent', icon: 'ph:robot', shortcut: 'N A', action: () => {} },
      { id: 'action-channel', label: 'New Channel', description: 'Create a channel', icon: 'ph:chat-circle-plus', action: () => {} },
      { id: 'action-doc', label: 'New Document', description: 'Create a document', icon: 'ph:file-plus', action: () => {} },
    ],
  },
  {
    label: 'Settings',
    items: [
      { id: 'settings-theme', label: 'Toggle Theme', description: 'Switch light/dark mode', icon: 'ph:moon', action: () => {} },
      { id: 'settings-shortcuts', label: 'Keyboard Shortcuts', description: 'View all shortcuts', icon: 'ph:keyboard', shortcut: '?', action: () => {} },
      { id: 'settings-preferences', label: 'Preferences', description: 'Open settings', icon: 'ph:gear-six', action: () => {} },
    ],
  },
])

const filteredGroups = computed(() => {
  if (!searchQuery.value.trim()) {
    return commandGroups.value
  }

  const query = searchQuery.value.toLowerCase()
  return commandGroups.value
    .map(group => ({
      ...group,
      items: group.items.filter(item =>
        item.label.toLowerCase().includes(query) ||
        item.description?.toLowerCase().includes(query)
      ),
    }))
    .filter(group => group.items.length > 0)
})

const setItemRef = (groupIndex: number, itemIndex: number, el: Element | ComponentPublicInstance | null) => {
  const key = `${groupIndex}-${itemIndex}`
  if (el && '$el' in el) {
    itemRefs.value.set(key, el)
  } else {
    itemRefs.value.delete(key)
  }
}

const isSelected = (groupIndex: number, itemIndex: number) => {
  return selectedGroup.value === groupIndex && selectedItem.value === itemIndex
}

const setSelection = (groupIndex: number, itemIndex: number) => {
  selectedGroup.value = groupIndex
  selectedItem.value = itemIndex
}

const selectNext = () => {
  const currentGroup = filteredGroups.value[selectedGroup.value]
  if (!currentGroup) return
  if (selectedItem.value < currentGroup.items.length - 1) {
    selectedItem.value++
  } else if (selectedGroup.value < filteredGroups.value.length - 1) {
    selectedGroup.value++
    selectedItem.value = 0
  }
  scrollToSelected()
}

const selectPrev = () => {
  if (selectedItem.value > 0) {
    selectedItem.value--
  } else if (selectedGroup.value > 0) {
    selectedGroup.value--
    const prevGroup = filteredGroups.value[selectedGroup.value]
    selectedItem.value = prevGroup ? prevGroup.items.length - 1 : 0
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
  if (item) {
    executeCommand(item)
  }
}

const executeCommand = (item: CommandItem) => {
  item.action()
  isOpen.value = false
}
</script>
