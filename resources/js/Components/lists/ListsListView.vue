<template>
  <div class="flex-1 overflow-auto">
    <!-- Status Groups -->
    <div class="max-w-4xl mx-auto px-4 md:px-6 py-4 space-y-2">
      <div v-for="group in statusGroups" :key="group.slug">
        <!-- Group Header -->
        <button
          class="flex items-center gap-2 w-full py-2 text-left group/header"
          @click="toggleGroup(group.slug)"
        >
          <Icon
            name="ph:caret-right-bold"
            :class="[
              'w-3 h-3 text-neutral-400 transition-transform duration-150',
              expandedGroups[group.slug] && 'rotate-90'
            ]"
          />
          <Icon :name="group.icon" :class="['w-3.5 h-3.5', colorMap[group.color]?.text ?? 'text-neutral-500']" />
          <span class="text-xs font-semibold uppercase tracking-wider" :class="colorMap[group.color]?.text ?? 'text-neutral-500 dark:text-neutral-400'">
            {{ group.name }}
          </span>
          <span class="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums">
            {{ groupedItems[group.slug]?.length || 0 }}
          </span>
        </button>

        <!-- Group Items -->
        <div v-if="expandedGroups[group.slug]" class="space-y-px">
          <div
            v-for="item in (groupedItems[group.slug] || [])"
            :key="item.id"
            class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 cursor-pointer group/row transition-colors duration-75"
            @click="$emit('select', item)"
          >
            <!-- Status Checkbox -->
            <button
              class="shrink-0 flex items-center justify-center"
              @click.stop="cycleStatus(item)"
            >
              <!-- Done status: filled check circle -->
              <span
                v-if="getItemStatus(item)?.isDone"
                :class="['w-[18px] h-[18px] rounded-full flex items-center justify-center', colorMap[getItemStatus(item)?.color ?? 'green']?.bg ?? 'bg-green-500']"
              >
                <Icon name="ph:check-bold" class="w-2.5 h-2.5 text-white" />
              </span>
              <!-- Non-done status: icon-based indicator -->
              <span
                v-else
                :class="['w-[18px] h-[18px] flex items-center justify-center', colorMap[getItemStatus(item)?.color ?? 'neutral']?.text ?? 'text-neutral-400']"
              >
                <Icon :name="getItemStatus(item)?.icon ?? 'ph:circle-dashed'" class="w-[18px] h-[18px]" />
              </span>
            </button>

            <!-- Priority Dot -->
            <span
              :class="[
                'w-2 h-2 rounded-full shrink-0',
                priorityDotColors[item.priority]
              ]"
            />

            <!-- Title -->
            <span
              :class="[
                'flex-1 text-sm truncate min-w-0',
                getItemStatus(item)?.isDone
                  ? 'line-through text-neutral-400 dark:text-neutral-500'
                  : 'text-neutral-900 dark:text-white'
              ]"
            >
              {{ item.title }}
            </span>

            <!-- Due Date -->
            <span
              v-if="item.dueDate"
              :class="[
                'shrink-0 text-xs tabular-nums',
                dueDateStyle(item.dueDate, item.status)
              ]"
            >
              {{ formatDueDate(item.dueDate) }}
            </span>

            <!-- Assignee Avatar -->
            <Tooltip v-if="item.assignee" :text="item.assignee.name" :delay-open="400">
              <AgentAvatar :user="item.assignee" size="xs" class="shrink-0" />
            </Tooltip>
            <span v-else class="w-5 shrink-0" />
          </div>

          <!-- Inline Quick Add (not for Done groups, requires project selected) -->
          <div v-if="!group.isDone && props.canCreate !== false" class="px-2 py-1">
            <div
              v-if="addingInGroup === group.slug"
              class="flex items-center gap-3"
            >
              <span class="w-[18px] shrink-0" />
              <span class="w-2 shrink-0" />
              <input
                ref="quickAddInput"
                v-model="quickAddTitle"
                type="text"
                :placeholder="`Add item...`"
                class="flex-1 text-sm bg-transparent border-none outline-none text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 py-0.5"
                @keydown.enter="submitQuickAdd(group.slug)"
                @keydown.escape="cancelQuickAdd"
                @blur="handleQuickAddBlur(group.slug)"
              />
            </div>
            <button
              v-else
              class="flex items-center gap-3 w-full py-1 text-neutral-400 dark:text-neutral-500 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
              @click="startQuickAdd(group.slug)"
            >
              <Icon name="ph:plus" class="w-[18px] h-[18px]" />
              <span class="w-2" />
              <span class="text-sm">Add item</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="items.length === 0" class="text-center py-16">
        <Icon name="ph:list-checks" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
        <p class="text-neutral-500 dark:text-neutral-400">No items yet</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, reactive, watch } from 'vue'
import type { ListItem, ListItemStatus, ListStatus, Priority } from '@/types'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import Tooltip from '@/Components/shared/Tooltip.vue'

const props = defineProps<{
  items: ListItem[]
  statuses: ListStatus[]
  canCreate?: boolean
}>()

const emit = defineEmits<{
  select: [item: ListItem]
  update: [id: string, data: Record<string, unknown>]
  create: [data: { title: string; status: ListItemStatus }]
}>()

// Color mapping from status color name to Tailwind classes
const colorMap: Record<string, { text: string; bg: string }> = {
  neutral: { text: 'text-neutral-500 dark:text-neutral-400', bg: 'bg-neutral-500' },
  blue: { text: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-500' },
  green: { text: 'text-green-600 dark:text-green-400', bg: 'bg-green-500' },
  yellow: { text: 'text-yellow-600 dark:text-yellow-400', bg: 'bg-yellow-500' },
  orange: { text: 'text-orange-600 dark:text-orange-400', bg: 'bg-orange-500' },
  red: { text: 'text-red-600 dark:text-red-400', bg: 'bg-red-500' },
  purple: { text: 'text-purple-600 dark:text-purple-400', bg: 'bg-purple-500' },
  pink: { text: 'text-pink-600 dark:text-pink-400', bg: 'bg-pink-500' },
}

// Dynamic status groups from prop
const statusGroups = computed(() =>
  [...props.statuses].sort((a, b) => a.position - b.position)
)

// Group expansion state — done statuses collapsed by default
const expandedGroups = reactive<Record<string, boolean>>({})

// Initialize expansion state when statuses load
watch(() => props.statuses, (statuses) => {
  for (const s of statuses) {
    if (!(s.slug in expandedGroups)) {
      expandedGroups[s.slug] = !s.isDone
    }
  }
}, { immediate: true })

const toggleGroup = (slug: string) => {
  expandedGroups[slug] = !expandedGroups[slug]
}

// Get the status object for an item
const getItemStatus = (item: ListItem) => {
  return props.statuses.find(s => s.slug === item.status)
}

// Group items by status
const groupedItems = computed(() => {
  const groups: Record<string, ListItem[]> = {}
  for (const s of props.statuses) {
    groups[s.slug] = []
  }
  for (const item of props.items) {
    if (groups[item.status]) {
      groups[item.status].push(item)
    }
  }
  return groups
})

// Status cycling: cycle through statuses in position order
const cycleStatus = (item: ListItem) => {
  const sorted = [...props.statuses].sort((a, b) => a.position - b.position)
  const currentIdx = sorted.findIndex(s => s.slug === item.status)
  const nextIdx = (currentIdx + 1) % sorted.length
  emit('update', item.id, { status: sorted[nextIdx].slug })
}

// Priority dot colors
const priorityDotColors: Record<Priority, string> = {
  urgent: 'bg-red-500',
  high: 'bg-orange-500',
  medium: 'bg-blue-500',
  low: 'bg-neutral-300 dark:bg-neutral-600',
  normal: 'bg-neutral-300 dark:bg-neutral-600',
}

// Due date formatting
const formatDueDate = (dateStr: string): string => {
  const date = new Date(dateStr + 'T00:00:00')
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const target = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const diffDays = Math.round((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))

  if (diffDays < 0) return 'Overdue'
  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return 'Tomorrow'
  if (diffDays < 7) {
    return date.toLocaleDateString('en-US', { weekday: 'short' })
  }
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const dueDateStyle = (dateStr: string, status: ListItemStatus): string => {
  const statusObj = props.statuses.find(s => s.slug === status)
  if (statusObj?.isDone) return 'text-neutral-400 dark:text-neutral-500'
  const date = new Date(dateStr + 'T00:00:00')
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const target = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const diffDays = Math.round((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))

  if (diffDays < 0) return 'text-red-600 dark:text-red-400 font-medium'
  if (diffDays === 0) return 'text-orange-600 dark:text-orange-400 font-medium'
  if (diffDays === 1) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-neutral-400 dark:text-neutral-500'
}

// Quick add
const addingInGroup = ref<string | null>(null)
const quickAddTitle = ref('')
const quickAddInput = ref<HTMLInputElement[] | null>(null)

const startQuickAdd = async (status: string) => {
  addingInGroup.value = status
  quickAddTitle.value = ''
  await nextTick()
  quickAddInput.value?.[0]?.focus()
}

const submitQuickAdd = (status: string) => {
  const title = quickAddTitle.value.trim()
  if (!title) {
    cancelQuickAdd()
    return
  }
  emit('create', { title, status })
  quickAddTitle.value = ''
  // Keep input open for rapid entry
}

const cancelQuickAdd = () => {
  addingInGroup.value = null
  quickAddTitle.value = ''
}

const handleQuickAddBlur = (status: string) => {
  // Small delay to allow clicking submit
  setTimeout(() => {
    if (addingInGroup.value === status && !quickAddTitle.value.trim()) {
      cancelQuickAdd()
    }
  }, 150)
}
</script>
