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
          class="p-1.5 text-neutral-400 dark:text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors shrink-0"
          title="Manage statuses"
          @click="statusManagerOpen = true"
        >
          <Icon name="ph:gear" class="w-4 h-4" />
        </button>
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
      :statuses="statuses"
      class="flex-1"
      @update="handleItemUpdate"
      @task-click="openItemDetail"
      @add-task="openCreateModal"
    />

    <!-- List View -->
    <ListsListView
      v-else-if="currentView === 'list'"
      :items="filteredItems"
      :statuses="statuses"
      @select="openItemDetail"
      @update="handleQuickUpdate"
      @create="handleQuickCreate"
    />

    <!-- Item Detail Drawer -->
    <ListsItemDetail
      v-if="selectedItem"
      v-model:open="itemDetailOpen"
      :task="selectedItem"
      :statuses="statuses"
      :comments="itemComments"
      @update="handleItemDetailUpdate"
      @delete="handleItemDelete"
      @add-comment="handleAddComment"
      @delete-comment="handleDeleteComment"
    />

    <!-- Item Create Modal -->
    <ListsItemCreateModal
      v-model:open="createModalOpen"
      :initial-status="createInitialStatus"
      :statuses="statuses"
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

    <!-- Status Manager Modal -->
    <StatusManager
      v-model:open="statusManagerOpen"
      :statuses="statuses"
      @updated="handleStatusesUpdated"
    />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { ListItem, ListItemStatus, ListStatus, Priority } from '@/types'
import ListsItemFilters from '@/Components/lists/TaskFilters.vue'
import ListsItemBoard from '@/Components/lists/TaskBoard.vue'
import ListsItemDetail from '@/Components/lists/TaskDetail.vue'
import ListsItemCreateModal from '@/Components/lists/TaskCreateModal.vue'
import ListsProjectList from '@/Components/lists/ProjectList.vue'
import ListsListView from '@/Components/lists/ListsListView.vue'
import StatusManager from '@/Components/lists/StatusManager.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'

type ViewType = 'board' | 'list'

const { fetchListItems, fetchUsers, fetchChannels, fetchListStatuses, updateListItem, reorderListItems, createListItem, deleteListItem, fetchListItemComments, addListItemComment, deleteListItemComment } = useApi()

// Fetch data from API
const { data: listItemsData, refresh: refreshListItems } = fetchListItems()
const { data: usersData } = fetchUsers()
const { data: channelsData } = fetchChannels()
const { data: listStatusesData, refresh: refreshListStatuses } = fetchListStatuses()

const currentFilter = ref('all')
const currentView = ref<ViewType>('list')
const selectedItem = ref<ListItem | null>(null)
const itemDetailOpen = ref(false)
const createModalOpen = ref(false)
const createInitialStatus = ref<ListItemStatus>('backlog')

// Comments
const itemComments = ref<any[]>([])

// Status manager
const statusManagerOpen = ref(false)

// Project state
const selectedProjectId = ref<string | null>(null)
const createProjectModalOpen = ref(false)
const newProjectName = ref('')

const listItems = computed<ListItem[]>(() => listItemsData.value ?? [])
const users = computed(() => usersData.value ?? [])
const channels = computed(() => channelsData.value ?? [])
const statuses = computed<ListStatus[]>(() => listStatusesData.value ?? [])

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

const doneSlugs = computed(() => statuses.value.filter(s => s.isDone).map(s => s.slug))

const itemCounts = computed(() => ({
  total: filteredItems.value.length,
  done: filteredItems.value.filter(t => doneSlugs.value.includes(t.status)).length,
}))

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

// Quick update from list view (status change, etc.)
const handleQuickUpdate = async (itemId: string, data: Record<string, unknown>) => {
  await updateListItem(itemId, data as Partial<ListItem>)
  await refreshListItems()
}

// Quick create from list view inline add
const handleQuickCreate = async (data: { title: string; status: ListItemStatus }) => {
  await createListItem({
    title: data.title,
    status: data.status,
    parentId: selectedProjectId.value,
  })
  await refreshListItems()
}

const openItemDetail = async (item: ListItem) => {
  selectedItem.value = item
  itemDetailOpen.value = true
  // Load comments
  try {
    const { data, promise } = fetchListItemComments(item.id)
    await promise
    itemComments.value = (data.value as any[]) ?? []
  } catch {
    itemComments.value = []
  }
}

// Reload comments when selected item changes
watch(itemDetailOpen, (open) => {
  if (!open) {
    itemComments.value = []
  }
})

const handleItemDetailUpdate = async (taskData?: Partial<ListItem>) => {
  if (taskData?.id) {
    await updateListItem(taskData.id, taskData)
  }
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
  await deleteListItem(itemId)
  await refreshListItems()
  selectedItem.value = null
  itemDetailOpen.value = false
}

const handleAddComment = async (content: string, parentId?: string) => {
  if (!selectedItem.value) return
  await addListItemComment(selectedItem.value.id, { content, parentId })
  // Refresh comments
  const { data: commentsData, promise } = fetchListItemComments(selectedItem.value.id)
  await promise
  itemComments.value = (commentsData.value as any[]) ?? []
}

const handleDeleteComment = async (commentId: string) => {
  if (!selectedItem.value) return
  await deleteListItemComment(selectedItem.value.id, commentId)
  // Refresh comments
  const { data: commentsData, promise } = fetchListItemComments(selectedItem.value.id)
  await promise
  itemComments.value = (commentsData.value as any[]) ?? []
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
  channelId: string | null
  parentId: string | null
  dueDate?: string | null
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

// Status manager
const handleStatusesUpdated = async () => {
  await refreshListStatuses()
  await refreshListItems()
}
</script>
