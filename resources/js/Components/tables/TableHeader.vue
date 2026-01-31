<template>
  <header class="shrink-0 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
    <div class="px-6 py-3 flex items-center justify-between gap-4">
      <!-- Left: Back + Table Info -->
      <div class="flex items-center gap-3 min-w-0">
        <Link
          href="/tables"
          class="shrink-0 p-1.5 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
        >
          <Icon name="ph:arrow-left" class="w-5 h-5 text-neutral-500 dark:text-neutral-400" />
        </Link>

        <!-- Icon Picker -->
        <DropdownMenu :items="iconOptions">
          <button
            type="button"
            class="shrink-0 w-9 h-9 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
          >
            <Icon :name="table.icon || 'ph:table'" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
          </button>
        </DropdownMenu>

        <!-- Name & Description -->
        <div class="flex flex-col min-w-0">
          <input
            v-model="editableName"
            type="text"
            class="text-base font-semibold text-neutral-900 dark:text-white bg-transparent border-0 outline-none focus:ring-0 p-0 min-w-0 truncate"
            @blur="handleNameBlur"
            @keydown.enter="($event.target as HTMLInputElement).blur()"
          />
          <input
            v-model="editableDescription"
            type="text"
            :placeholder="'Add description...'"
            class="text-xs text-neutral-500 dark:text-neutral-400 bg-transparent border-0 outline-none focus:ring-0 p-0 placeholder:text-neutral-400 dark:placeholder:text-neutral-500 min-w-0 truncate"
            @blur="handleDescriptionBlur"
            @keydown.enter="($event.target as HTMLInputElement).blur()"
          />
        </div>
      </div>

      <!-- Right: Actions -->
      <div class="flex items-center gap-1 shrink-0">
        <DropdownMenu :items="importOptions">
          <Button variant="ghost" size="sm" icon-left="ph:download-simple">Import</Button>
        </DropdownMenu>

        <DropdownMenu :items="exportOptions">
          <Button variant="ghost" size="sm" icon-left="ph:upload-simple">Export</Button>
        </DropdownMenu>

        <div class="w-px h-5 bg-neutral-200 dark:bg-neutral-700 mx-1" />

        <Button variant="ghost" size="sm" icon-left="ph:gear" icon-only @click="emit('settings')" />

        <DropdownMenu :items="moreOptions">
          <Button variant="ghost" size="sm" icon-left="ph:dots-three" icon-only />
        </DropdownMenu>
      </div>
    </div>

    <!-- Hidden file input for import -->
    <input
      ref="fileInputRef"
      type="file"
      class="hidden"
      :accept="importAccept"
      @change="handleFileImport"
    />
  </header>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import type { DataTable } from '@/types'

const props = defineProps<{
  table: DataTable
}>()

const emit = defineEmits<{
  'update:name': [name: string]
  'update:description': [description: string]
  'update:icon': [icon: string]
  'export': [format: 'csv' | 'json']
  'import': [file: File, format: 'csv' | 'json']
  'settings': []
  'duplicate': []
  'delete': []
}>()

const editableName = ref(props.table.name)
const editableDescription = ref(props.table.description || '')
const fileInputRef = ref<HTMLInputElement | null>(null)
const importAccept = ref('.csv,.json')
const pendingImportFormat = ref<'csv' | 'json'>('csv')

// Sync with props
watch(() => props.table.name, (newName) => {
  editableName.value = newName
})
watch(() => props.table.description, (newDesc) => {
  editableDescription.value = newDesc || ''
})

const handleNameBlur = () => {
  if (editableName.value !== props.table.name && editableName.value.trim()) {
    emit('update:name', editableName.value.trim())
  } else {
    editableName.value = props.table.name
  }
}

const handleDescriptionBlur = () => {
  if (editableDescription.value !== (props.table.description || '')) {
    emit('update:description', editableDescription.value.trim())
  }
}

// Icon options
const tableIcons = [
  'ph:table',
  'ph:database',
  'ph:list-bullets',
  'ph:squares-four',
  'ph:chart-bar',
  'ph:users',
  'ph:package',
  'ph:calendar',
  'ph:folder',
  'ph:file-text',
  'ph:kanban',
  'ph:clipboard-text',
]

const iconOptions = computed(() => [
  tableIcons.map(icon => ({
    label: icon.replace('ph:', '').replace(/-/g, ' '),
    icon,
    click: () => emit('update:icon', icon),
  })),
])

// Import options
const importOptions = computed(() => [
  [
    {
      label: 'Import CSV',
      icon: 'ph:file-csv',
      click: () => triggerImport('csv'),
    },
    {
      label: 'Import JSON',
      icon: 'ph:file-js',
      click: () => triggerImport('json'),
    },
  ],
])

const triggerImport = (format: 'csv' | 'json') => {
  pendingImportFormat.value = format
  importAccept.value = format === 'csv' ? '.csv' : '.json'
  fileInputRef.value?.click()
}

const handleFileImport = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) {
    emit('import', file, pendingImportFormat.value)
  }
  input.value = ''
}

// Export options
const exportOptions = computed(() => [
  [
    {
      label: 'Export as CSV',
      icon: 'ph:file-csv',
      click: () => emit('export', 'csv'),
    },
    {
      label: 'Export as JSON',
      icon: 'ph:file-js',
      click: () => emit('export', 'json'),
    },
  ],
])

// More options
const moreOptions = computed(() => [
  [
    {
      label: 'Duplicate Table',
      icon: 'ph:copy',
      click: () => emit('duplicate'),
    },
  ],
  [
    {
      label: 'Delete Table',
      icon: 'ph:trash',
      color: 'error' as const,
      click: () => emit('delete'),
    },
  ],
])
</script>
