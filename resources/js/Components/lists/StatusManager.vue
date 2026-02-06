<template>
  <Modal :open="open" title="Manage Statuses" @update:open="$emit('update:open', $event)">
      <div class="space-y-3">
        <!-- Status List -->
        <div class="space-y-1">
          <div
            v-for="(status, index) in localStatuses"
            :key="status.id"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 group"
          >
            <!-- Reorder Buttons -->
            <div class="flex flex-col gap-0.5 shrink-0">
              <button
                class="p-0.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :disabled="index === 0"
                @click="moveStatus(index, -1)"
              >
                <Icon name="ph:caret-up-bold" class="w-3 h-3" />
              </button>
              <button
                class="p-0.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                :disabled="index === localStatuses.length - 1"
                @click="moveStatus(index, 1)"
              >
                <Icon name="ph:caret-down-bold" class="w-3 h-3" />
              </button>
            </div>

            <!-- Color Dot -->
            <button
              :class="['w-4 h-4 rounded-full shrink-0 ring-2 ring-offset-2 ring-offset-white dark:ring-offset-neutral-800 transition-colors', colorBgMap[status.color] ?? 'bg-neutral-400', editingId === status.id && editField === 'color' ? 'ring-neutral-900 dark:ring-white' : 'ring-transparent']"
              @click="toggleColorPicker(status.id)"
            />

            <!-- Icon -->
            <button
              class="shrink-0 text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors"
              @click="toggleIconPicker(status.id)"
            >
              <Icon :name="status.icon" class="w-4 h-4" />
            </button>

            <!-- Name (editable) -->
            <input
              v-if="editingId === status.id && editField === 'name'"
              v-model="editName"
              type="text"
              class="flex-1 text-sm bg-transparent border-none outline-none text-neutral-900 dark:text-white py-0"
              @keydown.enter="saveNameEdit(status)"
              @keydown.escape="cancelEdit"
              @blur="saveNameEdit(status)"
              ref="nameInput"
            />
            <span
              v-else
              class="flex-1 text-sm text-neutral-900 dark:text-white cursor-pointer"
              @click="startNameEdit(status)"
            >
              {{ status.name }}
            </span>

            <!-- Done Badge -->
            <button
              :class="[
                'shrink-0 text-xs px-1.5 py-0.5 rounded transition-colors',
                status.isDone
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                  : 'bg-neutral-100 text-neutral-400 dark:bg-neutral-700 dark:text-neutral-500 hover:bg-neutral-200 dark:hover:bg-neutral-600'
              ]"
              @click="toggleDone(status)"
            >
              {{ status.isDone ? 'Done' : 'Active' }}
            </button>

            <!-- Default Badge -->
            <span
              v-if="status.isDefault"
              class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
            >
              Default
            </span>

            <!-- Delete -->
            <button
              v-if="!status.isDefault && localStatuses.length > 1"
              class="shrink-0 p-1 text-neutral-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
              @click="confirmDelete(status)"
            >
              <Icon name="ph:trash" class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Color Picker -->
        <div v-if="editingId && editField === 'color'" class="flex items-center gap-2 px-3 py-2 bg-neutral-50 dark:bg-neutral-800/50 rounded-lg">
          <span class="text-xs text-neutral-500 dark:text-neutral-400 shrink-0">Color:</span>
          <div class="flex gap-1.5">
            <button
              v-for="c in colorOptions"
              :key="c.name"
              :class="[
                'w-5 h-5 rounded-full transition-transform',
                c.bg,
                editingStatus?.color === c.name ? 'ring-2 ring-offset-1 ring-neutral-900 dark:ring-white scale-110' : 'hover:scale-110'
              ]"
              @click="setColor(c.name)"
            />
          </div>
        </div>

        <!-- Icon Picker -->
        <div v-if="editingId && editField === 'icon'" class="flex items-center gap-2 px-3 py-2 bg-neutral-50 dark:bg-neutral-800/50 rounded-lg">
          <span class="text-xs text-neutral-500 dark:text-neutral-400 shrink-0">Icon:</span>
          <div class="flex gap-1.5">
            <button
              v-for="ic in iconOptions"
              :key="ic"
              :class="[
                'p-1.5 rounded transition-colors',
                editingStatus?.icon === ic ? 'bg-neutral-200 dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'text-neutral-500 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700'
              ]"
              @click="setIcon(ic)"
            >
              <Icon :name="ic" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Add New Status -->
        <div v-if="addingNew" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-neutral-300 dark:border-neutral-600">
          <span :class="['w-4 h-4 rounded-full shrink-0', colorBgMap[newStatus.color]]" />
          <Icon :name="newStatus.icon" class="w-4 h-4 text-neutral-500 shrink-0" />
          <input
            ref="newNameInput"
            v-model="newStatus.name"
            type="text"
            placeholder="Status name..."
            class="flex-1 text-sm bg-transparent border-none outline-none text-neutral-900 dark:text-white placeholder:text-neutral-400 py-0"
            @keydown.enter="submitNewStatus"
            @keydown.escape="cancelNewStatus"
          />
          <button
            class="text-xs px-2 py-1 bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 rounded hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors disabled:opacity-50"
            :disabled="!newStatus.name.trim()"
            @click="submitNewStatus"
          >
            Add
          </button>
          <button
            class="text-xs px-2 py-1 text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 transition-colors"
            @click="cancelNewStatus"
          >
            Cancel
          </button>
        </div>
        <button
          v-else
          class="flex items-center gap-2 w-full px-3 py-2 text-neutral-400 dark:text-neutral-500 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors rounded-lg border border-dashed border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600"
          @click="startAddNew"
        >
          <Icon name="ph:plus" class="w-4 h-4" />
          <span class="text-sm">Add status</span>
        </button>

        <!-- Delete Confirmation -->
        <div v-if="deletingStatus" class="p-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg">
          <p class="text-sm text-red-700 dark:text-red-400 mb-2">
            Delete "{{ deletingStatus.name }}"? Items with this status will be moved to:
          </p>
          <select
            v-model="deleteReplacementSlug"
            class="w-full px-2 py-1.5 text-sm bg-white dark:bg-neutral-800 border border-red-200 dark:border-red-800 rounded text-neutral-700 dark:text-neutral-200 mb-2"
          >
            <option
              v-for="s in localStatuses.filter(s => s.id !== deletingStatus?.id)"
              :key="s.slug"
              :value="s.slug"
            >
              {{ s.name }}
            </option>
          </select>
          <div class="flex gap-2">
            <button
              class="text-xs px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
              @click="executeDelete"
            >
              Delete
            </button>
            <button
              class="text-xs px-3 py-1.5 text-neutral-600 dark:text-neutral-400 hover:text-neutral-800 dark:hover:text-neutral-200 transition-colors"
              @click="deletingStatus = null"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import type { ListStatus } from '@/types'
import Modal from '@/Components/shared/Modal.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'

const props = defineProps<{
  open: boolean
  statuses: ListStatus[]
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'updated': []
}>()

const { createListStatus, updateListStatus, deleteListStatus, reorderListStatuses } = useApi()

// Local copy for editing
const localStatuses = ref<ListStatus[]>([])

watch(() => props.statuses, (statuses) => {
  localStatuses.value = [...statuses].sort((a, b) => a.position - b.position)
}, { immediate: true, deep: true })

// Color and icon options
const colorOptions = [
  { name: 'neutral', bg: 'bg-neutral-400' },
  { name: 'blue', bg: 'bg-blue-500' },
  { name: 'green', bg: 'bg-green-500' },
  { name: 'yellow', bg: 'bg-yellow-500' },
  { name: 'orange', bg: 'bg-orange-500' },
  { name: 'red', bg: 'bg-red-500' },
  { name: 'purple', bg: 'bg-purple-500' },
  { name: 'pink', bg: 'bg-pink-500' },
]

const colorBgMap: Record<string, string> = {
  neutral: 'bg-neutral-400',
  blue: 'bg-blue-500',
  green: 'bg-green-500',
  yellow: 'bg-yellow-500',
  orange: 'bg-orange-500',
  red: 'bg-red-500',
  purple: 'bg-purple-500',
  pink: 'bg-pink-500',
}

const iconOptions = [
  'ph:circle-dashed',
  'ph:circle-half',
  'ph:check-circle',
  'ph:circle',
  'ph:star',
  'ph:flag',
  'ph:clock',
  'ph:eye',
  'ph:lightning',
  'ph:hourglass',
]

// Edit state
const editingId = ref<string | null>(null)
const editField = ref<'name' | 'color' | 'icon' | null>(null)
const editName = ref('')
const nameInput = ref<HTMLInputElement[] | null>(null)

const editingStatus = computed(() =>
  localStatuses.value.find(s => s.id === editingId.value)
)

const startNameEdit = async (status: ListStatus) => {
  editingId.value = status.id
  editField.value = 'name'
  editName.value = status.name
  await nextTick()
  nameInput.value?.[0]?.focus()
}

const saveNameEdit = async (status: ListStatus) => {
  const name = editName.value.trim()
  if (name && name !== status.name) {
    await updateListStatus(status.id, { name })
    status.name = name
    emit('updated')
  }
  cancelEdit()
}

const cancelEdit = () => {
  editingId.value = null
  editField.value = null
}

const toggleColorPicker = (id: string) => {
  if (editingId.value === id && editField.value === 'color') {
    cancelEdit()
  } else {
    editingId.value = id
    editField.value = 'color'
  }
}

const toggleIconPicker = (id: string) => {
  if (editingId.value === id && editField.value === 'icon') {
    cancelEdit()
  } else {
    editingId.value = id
    editField.value = 'icon'
  }
}

const setColor = async (color: string) => {
  if (!editingStatus.value) return
  await updateListStatus(editingStatus.value.id, { color })
  editingStatus.value.color = color
  emit('updated')
  cancelEdit()
}

const setIcon = async (icon: string) => {
  if (!editingStatus.value) return
  await updateListStatus(editingStatus.value.id, { icon })
  editingStatus.value.icon = icon
  emit('updated')
  cancelEdit()
}

const toggleDone = async (status: ListStatus) => {
  await updateListStatus(status.id, { isDone: !status.isDone })
  status.isDone = !status.isDone
  emit('updated')
}

// Reorder
const moveStatus = async (index: number, direction: -1 | 1) => {
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= localStatuses.value.length) return

  const items = [...localStatuses.value]
  const [moved] = items.splice(index, 1)
  items.splice(targetIndex, 0, moved)

  // Update positions
  const orders = items.map((s, i) => ({ id: s.id, position: i }))
  localStatuses.value = items

  await reorderListStatuses(orders)
  emit('updated')
}

// Add new status
const addingNew = ref(false)
const newStatus = ref({ name: '', color: 'neutral', icon: 'ph:circle' })
const newNameInput = ref<HTMLInputElement | null>(null)

const startAddNew = async () => {
  addingNew.value = true
  newStatus.value = { name: '', color: 'neutral', icon: 'ph:circle' }
  cancelEdit()
  await nextTick()
  newNameInput.value?.focus()
}

const submitNewStatus = async () => {
  const name = newStatus.value.name.trim()
  if (!name) return

  await createListStatus({
    name,
    color: newStatus.value.color,
    icon: newStatus.value.icon,
  })
  addingNew.value = false
  emit('updated')
}

const cancelNewStatus = () => {
  addingNew.value = false
}

// Delete
const deletingStatus = ref<ListStatus | null>(null)
const deleteReplacementSlug = ref('')

const confirmDelete = (status: ListStatus) => {
  deletingStatus.value = status
  const defaultStatus = localStatuses.value.find(s => s.isDefault && s.id !== status.id)
  deleteReplacementSlug.value = defaultStatus?.slug ?? localStatuses.value.find(s => s.id !== status.id)?.slug ?? ''
  cancelEdit()
}

const executeDelete = async () => {
  if (!deletingStatus.value) return
  await deleteListStatus(deletingStatus.value.id, deleteReplacementSlug.value)
  deletingStatus.value = null
  emit('updated')
}
</script>
