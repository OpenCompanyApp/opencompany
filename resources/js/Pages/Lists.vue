<template>
  <div class="h-full flex">
    <!-- Project Sidebar (hidden on mobile) -->
    <div class="hidden md:block w-64 shrink-0">
      <ListsProjectList
        :tasks="listItems"
        :selected-id="selectedProjectId"
        @select="handleProjectSelect"
        @create-project="openCreateProjectModal"
        @rename-project="handleRenameProject"
        @delete-project="handleDeleteProject"
      />
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <header class="px-4 md:px-6 py-3 md:py-0 md:h-14 border-b border-neutral-200 dark:border-neutral-700 flex flex-col md:flex-row md:items-center gap-3 bg-white dark:bg-neutral-900 shrink-0">
      <div class="flex items-center justify-between md:gap-4">
        <div class="flex items-center gap-3 md:gap-4">
          <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Lists</h1>
          <div class="hidden md:flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-300">
            <span>{{ itemCounts.total }} items</span>
            <span class="text-neutral-200 dark:text-neutral-600">/</span>
            <span class="text-green-400">{{ itemCounts.done }} done</span>
          </div>
        </div>
        <!-- Mobile: New Task button -->
        <button
          class="md:hidden flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg"
          @click="openCreateModal()"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New</span>
        </button>
      </div>
      <div class="flex items-center gap-3 overflow-x-auto md:overflow-visible md:ml-auto pb-1 md:pb-0 -mx-4 px-4 md:mx-0 md:px-0">
        <ListsItemFilters
          v-model:filter="currentFilter"
          v-model:view="currentView"
        />
        <button
          class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-sm font-medium rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors shrink-0"
          @click="openCreateModal()"
        >
          <Icon name="ph:plus-bold" class="w-4 h-4" />
          <span>New Item</span>
        </button>
      </div>
    </header>

    <!-- Board View -->
    <ListsItemBoard
      v-if="currentView === 'board'"
      :tasks="filteredItems"
      class="flex-1"
      @update="handleItemUpdate"
      @task-click="openItemDetail"
      @add-task="openCreateModal"
    />

    <!-- List View -->
    <div v-else-if="currentView === 'list'" class="flex-1 overflow-auto p-4 md:p-6">
      <!-- Mobile Card View -->
      <div class="md:hidden space-y-3">
        <div
          v-for="item in filteredItems"
          :key="item.id"
          class="p-4 bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 cursor-pointer active:bg-neutral-50 dark:active:bg-neutral-700/50"
          @click="openItemDetail(item)"
        >
          <div class="flex items-start justify-between gap-3 mb-2">
            <h4 :class="['font-medium text-neutral-900 dark:text-white', item.status === 'done' && 'line-through text-neutral-500 dark:text-neutral-400']">
              {{ item.title }}
            </h4>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full shrink-0',
                statusClasses[item.status]
              ]"
            >
              <span :class="['w-1.5 h-1.5 rounded-full', statusDots[item.status]]" />
              {{ statusLabels[item.status] }}
            </span>
          </div>
          <p v-if="item.description" class="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-2 mb-3">
            {{ item.description }}
          </p>
          <div class="flex items-center gap-3 text-sm">
            <span
              :class="[
                'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full',
                priorityClasses[item.priority]
              ]"
            >
              {{ item.priority }}
            </span>
            <div v-if="item.assignee" class="flex items-center gap-1.5">
              <AgentAvatar :user="item.assignee" size="xs" />
              <span class="text-neutral-600 dark:text-neutral-300">{{ item.assignee.name }}</span>
            </div>
            <CostBadge
              v-if="item.cost || item.estimatedCost"
              :cost="item.cost || item.estimatedCost!"
              :variant="item.cost ? 'actual' : 'estimated'"
              size="xs"
              class="ml-auto"
            />
          </div>
        </div>
        <!-- Mobile Empty State -->
        <div v-if="filteredItems.length === 0" class="text-center py-12">
          <Icon name="ph:list-checks" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
          <p class="text-neutral-500 dark:text-neutral-400">No items found</p>
        </div>
      </div>

      <!-- Desktop Table View -->
      <div class="hidden md:block bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden">
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-4 py-3 bg-neutral-50 dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-700 text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">
          <div class="col-span-5">Item</div>
          <div class="col-span-2">Status</div>
          <div class="col-span-2">Priority</div>
          <div class="col-span-2">Assignee</div>
          <div class="col-span-1 text-right">Cost</div>
        </div>

        <!-- Item Rows -->
        <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
          <div
            v-for="item in filteredItems"
            :key="item.id"
            class="grid grid-cols-12 gap-4 px-4 py-3 hover:bg-neutral-50 dark:hover:bg-neutral-700/50 cursor-pointer transition-colors"
            @click="openItemDetail(item)"
          >
            <!-- Item Title & Description -->
            <div class="col-span-5">
              <h4 :class="['font-medium text-neutral-900 dark:text-white truncate', item.status === 'done' && 'line-through text-neutral-500 dark:text-neutral-400']">
                {{ item.title }}
              </h4>
              <p v-if="item.description" class="text-sm text-neutral-500 dark:text-neutral-400 truncate mt-0.5">
                {{ item.description }}
              </p>
            </div>

            <!-- Status -->
            <div class="col-span-2 flex items-center">
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full',
                  statusClasses[item.status]
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', statusDots[item.status]]" />
                {{ statusLabels[item.status] }}
              </span>
            </div>

            <!-- Priority -->
            <div class="col-span-2 flex items-center">
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full',
                  priorityClasses[item.priority]
                ]"
              >
                <span :class="['w-1.5 h-1.5 rounded-full', priorityDots[item.priority]]" />
                {{ item.priority }}
              </span>
            </div>

            <!-- Assignee -->
            <div class="col-span-2 flex items-center">
              <div v-if="item.assignee" class="flex items-center gap-2">
                <AgentAvatar :user="item.assignee" size="xs" />
                <span class="text-sm text-neutral-700 dark:text-neutral-300 truncate">{{ item.assignee.name }}</span>
              </div>
              <span v-else class="text-sm text-neutral-400 dark:text-neutral-500">Unassigned</span>
            </div>

            <!-- Cost -->
            <div class="col-span-1 flex items-center justify-end">
              <CostBadge
                v-if="item.cost || item.estimatedCost"
                :cost="item.cost || item.estimatedCost!"
                :variant="item.cost ? 'actual' : 'estimated'"
                size="xs"
              />
              <span v-else class="text-sm text-neutral-400 dark:text-neutral-500">-</span>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="filteredItems.length === 0" class="px-4 py-12 text-center">
            <Icon name="ph:list-checks" class="w-12 h-12 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
            <p class="text-neutral-500 dark:text-neutral-400">No items found</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline View (placeholder) -->
    <div v-else-if="currentView === 'timeline'" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <Icon name="ph:chart-line" class="w-16 h-16 text-neutral-300 dark:text-neutral-600 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-neutral-900 dark:text-white mb-2">Timeline View</h3>
        <p class="text-neutral-500 dark:text-neutral-400">Coming soon</p>
      </div>
    </div>

    <!-- Item Detail Drawer -->
    <ListsItemDetail
      v-if="selectedItem"
      v-model:open="itemDetailOpen"
      :task="selectedItem"
      @update="handleItemDetailUpdate"
      @delete="handleItemDelete"
    />

    <!-- Item Create Modal -->
    <ListsItemCreateModal
      v-model:open="createModalOpen"
      :initial-status="createInitialStatus"
      :parent-id="selectedProjectId"
      :users="users"
      :channels="channels"
      @created="handleItemCreated"
    />

    <!-- Create Project Modal -->
    <Modal v-model:open="createProjectModalOpen" title="New Project">
      <form @submit.prevent="handleCreateProject" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
            Project Name
          </label>
          <input
            v-model="newProjectName"
            type="text"
            placeholder="Enter project name..."
            class="w-full px-3 py-2 bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-neutral-900 dark:text-white placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-neutral-900 dark:focus:ring-white"
            autofocus
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
            @click="createProjectModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!newProjectName.trim()"
            class="px-4 py-2 text-sm font-medium bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded-lg hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Create Project
          </button>
        </div>
      </form>
    </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { ListItem, ListItemStatus, Priority } from '@/types'
import ListsItemFilters from '@/Components/lists/TaskFilters.vue'
import ListsItemBoard from '@/Components/lists/TaskBoard.vue'
import ListsItemDetail from '@/Components/lists/TaskDetail.vue'
import ListsItemCreateModal from '@/Components/lists/TaskCreateModal.vue'
import ListsProjectList from '@/Components/lists/ProjectList.vue'
import Icon from '@/Components/shared/Icon.vue'
import AgentAvatar from '@/Components/shared/AgentAvatar.vue'
import CostBadge from '@/Components/shared/CostBadge.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

type ViewType = 'board' | 'list' | 'timeline'

const { fetchListItems, fetchUsers, fetchChannels, updateListItem, reorderListItems, createListItem, deleteListItem } = useApi()

// Fetch data from API
const { data: listItemsData, refresh: refreshListItems } = fetchListItems()
const { data: usersData } = fetchUsers()
const { data: channelsData } = fetchChannels()

const currentFilter = ref('all')
const currentView = ref<ViewType>('board')
const selectedItem = ref<ListItem | null>(null)
const itemDetailOpen = ref(false)
const createModalOpen = ref(false)
const createInitialStatus = ref<ListItemStatus>('backlog')

// Project state
const selectedProjectId = ref<string | null>(null)
const createProjectModalOpen = ref(false)
const newProjectName = ref('')

const listItems = computed<ListItem[]>(() => listItemsData.value ?? [])
const users = computed(() => usersData.value ?? [])
const channels = computed(() => channelsData.value ?? [])

const filteredItems = computed(() => {
  // Start with non-folder items only
  let result = listItems.value.filter(t => !t.isFolder)

  // Filter by selected project
  if (selectedProjectId.value) {
    result = result.filter(t => t.parentId === selectedProjectId.value)
  }

  // Filter by assignee type
  if (currentFilter.value === 'agents') {
    result = result.filter(t => t.assignee?.type === 'agent')
  } else if (currentFilter.value === 'humans') {
    result = result.filter(t => t.assignee?.type === 'human')
  }

  return result
})

const itemCounts = computed(() => ({
  total: listItems.value.length,
  done: listItems.value.filter(t => t.status === 'done').length,
}))

// Status styling
const statusClasses: Record<ListItemStatus, string> = {
  backlog: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  in_progress: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  done: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
}

const statusDots: Record<ListItemStatus, string> = {
  backlog: 'bg-neutral-400',
  in_progress: 'bg-blue-500',
  done: 'bg-green-500',
}

const statusLabels: Record<ListItemStatus, string> = {
  backlog: 'Backlog',
  in_progress: 'In Progress',
  done: 'Done',
}

// Priority styling
const priorityClasses: Record<Priority, string> = {
  low: 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300',
  medium: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  high: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
  urgent: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
}

const priorityDots: Record<Priority, string> = {
  low: 'bg-neutral-400',
  medium: 'bg-blue-500',
  high: 'bg-amber-500',
  urgent: 'bg-red-500',
}

const handleItemUpdate = async (itemId: string, newStatus: ListItemStatus, newIndex: number) => {
  // Get items in the target status column
  const targetColumnItems = listItems.value
    .filter(t => t.status === newStatus && t.id !== itemId)
    .sort((a, b) => ((a as any).position || 0) - ((b as any).position || 0))

  // Insert the moved item at the new position
  const itemOrders: { id: string; position: number; status?: string }[] = []

  // Add the moved item with its new position and status
  itemOrders.push({ id: itemId, position: newIndex, status: newStatus })

  // Reorder remaining items in the column
  let position = 0
  for (const item of targetColumnItems) {
    if (position === newIndex) {
      position++ // Skip the position where we inserted the moved item
    }
    if ((item as any).position !== position) {
      itemOrders.push({ id: item.id, position })
    }
    position++
  }

  // Update via API
  if (itemOrders.length > 0) {
    await reorderListItems(itemOrders)
  }

  await refreshListItems()
}

const openItemDetail = (item: ListItem) => {
  selectedItem.value = item
  itemDetailOpen.value = true
}

const handleItemDetailUpdate = async () => {
  await refreshListItems()
  // Update selected item with refreshed data
  if (selectedItem.value) {
    const updated = listItems.value.find(t => t.id === selectedItem.value!.id)
    if (updated) {
      selectedItem.value = updated
    }
  }
}

const handleItemDelete = async (itemId: string) => {
  await refreshListItems()
  selectedItem.value = null
  itemDetailOpen.value = false
}

const openCreateModal = (status?: ListItemStatus) => {
  createInitialStatus.value = status || 'backlog'
  createModalOpen.value = true
}

const handleItemCreated = async (itemData: {
  title: string
  description: string
  status: ListItemStatus
  priority: Priority
  assigneeId: string
  estimatedCost: number | null
  channelId: string | null
  parentId: string | null
}) => {
  await createListItem(itemData)
  await refreshListItems()
}

// Project handlers
const handleProjectSelect = (projectId: string | null) => {
  selectedProjectId.value = projectId
}

const openCreateProjectModal = () => {
  newProjectName.value = ''
  createProjectModalOpen.value = true
}

const handleCreateProject = async () => {
  if (!newProjectName.value.trim()) return

  await createListItem({
    title: newProjectName.value.trim(),
    isFolder: true,
    parentId: selectedProjectId.value,
  })

  createProjectModalOpen.value = false
  newProjectName.value = ''
  await refreshListItems()
}

const handleRenameProject = async (project: ListItem) => {
  const newName = prompt('Enter new project name:', project.title)
  if (newName && newName.trim() !== project.title) {
    await updateListItem(project.id, { title: newName.trim() })
    await refreshListItems()
  }
}

const handleDeleteProject = async (project: ListItem) => {
  if (confirm(`Delete project "${project.title}"? All items in this project will also be deleted.`)) {
    await deleteListItem(project.id)
    if (selectedProjectId.value === project.id) {
      selectedProjectId.value = null
    }
    await refreshListItems()
  }
}
</script>
