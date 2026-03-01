<template>
  <SettingsSection title="Storage Disks" icon="ph:hard-drives" description="Manage where your workspace files are stored.">
    <template #actions>
      <button
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 text-xs font-medium hover:opacity-90 transition-opacity"
        @click="openCreate"
      >
        <Icon name="ph:plus" class="w-3.5 h-3.5" />
        Add Disk
      </button>
    </template>

    <div v-if="loading" class="flex justify-center py-8">
      <Icon name="ph:spinner" class="w-5 h-5 animate-spin text-neutral-400" />
    </div>

    <div v-else-if="disks.length === 0" class="text-center py-8 text-sm text-neutral-500">
      No storage disks configured.
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="disk in disks"
        :key="disk.id"
        class="flex items-center gap-3 px-4 py-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-800/50"
      >
        <Icon :name="driverIcon(disk.driver)" class="w-5 h-5 text-neutral-500 dark:text-neutral-400 shrink-0" />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ disk.name }}</span>
            <span class="text-[10px] uppercase tracking-wider font-semibold px-1.5 py-0.5 rounded bg-neutral-200 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400">
              {{ disk.driver }}
            </span>
            <span v-if="disk.isDefault" class="text-[10px] uppercase tracking-wider font-semibold px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
              Default
            </span>
          </div>
          <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ disk.fileCount ?? 0 }} files</span>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button
            class="p-1.5 rounded-md text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors"
            title="Edit"
            @click="openEdit(disk)"
          >
            <Icon name="ph:pencil-simple" class="w-4 h-4" />
          </button>
          <button
            v-if="!disk.isDefault"
            class="p-1.5 rounded-md text-neutral-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
            title="Set as default"
            @click="handleSetDefault(disk)"
          >
            <Icon name="ph:star" class="w-4 h-4" />
          </button>
          <button
            v-if="!disk.isDefault"
            class="p-1.5 rounded-md text-neutral-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
            title="Delete"
            @click="handleDelete(disk)"
          >
            <Icon name="ph:trash" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <DiskConfigModal
      v-model:open="showModal"
      :disk="editingDisk"
      @saved="handleSaved"
    />

    <ConfirmDialog
      :open="showDeleteDialog"
      variant="danger"
      title="Delete Disk"
      :description="deleteDescription"
      confirm-label="Delete"
      @update:open="showDeleteDialog = $event"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />
  </SettingsSection>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import SettingsSection from './SettingsSection.vue'
import DiskConfigModal from './DiskConfigModal.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import type { WorkspaceDisk } from '@/types'

const { fetchDisks, setDefaultDisk, deleteDisk } = useApi()
const toast = useToast()

const loading = ref(true)
const disks = ref<WorkspaceDisk[]>([])
const showModal = ref(false)
const editingDisk = ref<WorkspaceDisk | null>(null)
const showDeleteDialog = ref(false)
const diskToDelete = ref<WorkspaceDisk | null>(null)

const deleteDescription = ref('')

const driverIcon = (driver: string) => {
  switch (driver) {
    case 's3': return 'ph:cloud'
    case 'sftp': return 'ph:plugs-connected'
    default: return 'ph:hard-drive'
  }
}

const loadDisks = async () => {
  loading.value = true
  const { promise } = fetchDisks()
  const result = await promise
  disks.value = result?.data ?? []
  loading.value = false
}

const openCreate = () => {
  editingDisk.value = null
  showModal.value = true
}

const openEdit = (disk: WorkspaceDisk) => {
  editingDisk.value = disk
  showModal.value = true
}

const handleSaved = () => {
  showModal.value = false
  loadDisks()
}

const handleSetDefault = async (disk: WorkspaceDisk) => {
  try {
    await setDefaultDisk(disk.id)
    toast.success(`${disk.name} is now the default disk.`)
    loadDisks()
  } catch {
    toast.error('Failed to set default disk.')
  }
}

const handleDelete = (disk: WorkspaceDisk) => {
  diskToDelete.value = disk
  deleteDescription.value = `Are you sure you want to delete the disk "${disk.name}"? This cannot be undone.`
  showDeleteDialog.value = true
}

const executeDelete = async () => {
  if (!diskToDelete.value) return
  try {
    await deleteDisk(diskToDelete.value.id)
    toast.success('Disk deleted.')
    showDeleteDialog.value = false
    loadDisks()
  } catch (e: any) {
    toast.error(e.response?.data?.error || 'Failed to delete disk.')
    showDeleteDialog.value = false
  }
}

onMounted(loadDisks)
</script>
