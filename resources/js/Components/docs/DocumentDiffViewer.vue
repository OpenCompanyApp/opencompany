<template>
  <Modal
    :open="open"
    title="Compare Versions"
    description="Compare document versions side by side"
    fullscreen
    @update:open="$emit('update:open', $event)"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="text-lg font-semibold text-neutral-900">Compare Versions</h2>
        <button
          class="p-2 text-neutral-500 hover:text-neutral-700 hover:bg-neutral-50 rounded-lg transition-colors"
          @click="$emit('update:open', false)"
        >
          <Icon name="ph:x" class="w-5 h-5" />
        </button>
      </div>

          <!-- Version Selectors -->
          <div class="mt-4 flex items-center gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium text-neutral-500 mb-1">From (older)</label>
              <select
                v-model="selectedOldVersion"
                class="w-full px-3 py-2 bg-neutral-50 border border-neutral-200 rounded-lg text-neutral-700 text-sm focus:border-neutral-300 focus:outline-none"
              >
                <option v-for="v in availableOldVersions" :key="v.id" :value="v">
                  Version {{ v.versionNumber }} - {{ formatDate(v.createdAt) }}
                </option>
              </select>
            </div>
            <Icon name="ph:arrow-right" class="w-5 h-5 text-neutral-400 mt-6" />
            <div class="flex-1">
              <label class="block text-sm font-medium text-neutral-500 mb-1">To (newer)</label>
              <select
                v-model="selectedNewVersion"
                class="w-full px-3 py-2 bg-neutral-50 border border-neutral-200 rounded-lg text-neutral-700 text-sm focus:border-neutral-300 focus:outline-none"
              >
                <option :value="currentDocument">
                  Current - {{ formatDate(currentDocument?.updatedAt) }}
                </option>
                <option v-for="v in availableNewVersions" :key="v.id" :value="v">
                  Version {{ v.versionNumber }} - {{ formatDate(v.createdAt) }}
                </option>
              </select>
            </div>
          </div>
      </template>

      <template #body>
        <!-- Diff Content -->
        <div class="flex-1 overflow-hidden flex">
          <!-- Stats -->
          <div v-if="diffStats" class="absolute top-24 right-8 flex items-center gap-3 text-sm">
            <span class="text-green-600">+{{ diffStats.additions }} added</span>
            <span class="text-red-600">-{{ diffStats.deletions }} removed</span>
          </div>

          <!-- Side by Side View -->
          <div class="flex-1 grid grid-cols-2 divide-x divide-neutral-200 overflow-hidden">
            <!-- Old Version -->
            <div class="flex flex-col overflow-hidden">
              <div class="shrink-0 px-4 py-2 bg-red-50 border-b border-neutral-200">
                <span class="text-sm font-medium text-red-700">
                  {{ selectedOldVersion ? `Version ${selectedOldVersion.versionNumber}` : 'Previous' }}
                </span>
                <span v-if="selectedOldVersion?.author" class="text-xs text-red-600 ml-2">
                  by {{ selectedOldVersion.author.name }}
                </span>
              </div>
              <div class="flex-1 overflow-auto font-mono text-sm">
                <div
                  v-for="(line, index) in diffLines"
                  :key="`old-${index}`"
                  :class="[
                    'flex border-b border-neutral-100',
                    line.type === 'removed' ? 'bg-red-50' : '',
                    line.type === 'added' ? 'bg-neutral-50 text-neutral-300' : ''
                  ]"
                >
                  <span class="w-12 shrink-0 px-2 py-1 text-right text-neutral-400 border-r border-neutral-200 bg-neutral-50 select-none">
                    {{ line.type !== 'added' ? line.oldLineNum : '' }}
                  </span>
                  <pre
                    class="flex-1 px-3 py-1 whitespace-pre-wrap break-words"
                    :class="line.type === 'removed' ? 'text-red-700' : 'text-neutral-700'"
                  >{{ line.type !== 'added' ? line.oldContent : '' }}</pre>
                </div>
              </div>
            </div>

            <!-- New Version -->
            <div class="flex flex-col overflow-hidden">
              <div class="shrink-0 px-4 py-2 bg-green-50 border-b border-neutral-200">
                <span class="text-sm font-medium text-green-700">
                  {{ isCurrentVersion ? 'Current Version' : `Version ${(selectedNewVersion as any)?.versionNumber}` }}
                </span>
                <span v-if="!isCurrentVersion && (selectedNewVersion as any)?.author" class="text-xs text-green-600 ml-2">
                  by {{ (selectedNewVersion as any).author.name }}
                </span>
              </div>
              <div class="flex-1 overflow-auto font-mono text-sm">
                <div
                  v-for="(line, index) in diffLines"
                  :key="`new-${index}`"
                  :class="[
                    'flex border-b border-neutral-100',
                    line.type === 'added' ? 'bg-green-50' : '',
                    line.type === 'removed' ? 'bg-neutral-50 text-neutral-300' : ''
                  ]"
                >
                  <span class="w-12 shrink-0 px-2 py-1 text-right text-neutral-400 border-r border-neutral-200 bg-neutral-50 select-none">
                    {{ line.type !== 'removed' ? line.newLineNum : '' }}
                  </span>
                  <pre
                    class="flex-1 px-3 py-1 whitespace-pre-wrap break-words"
                    :class="line.type === 'added' ? 'text-green-700' : 'text-neutral-700'"
                  >{{ line.type !== 'removed' ? line.newContent : '' }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <div class="text-sm text-neutral-500">
            {{ diffLines.length }} lines compared
          </div>
          <Button
            v-if="selectedOldVersion"
            @click="handleRestore"
          >
            Restore to Version {{ selectedOldVersion.versionNumber }}
          </Button>
        </div>
      </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { Document } from '@/types'
import Modal from '@/Components/shared/Modal.vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'

interface DocumentVersion {
  id: string
  documentId: string
  title: string
  content: string
  authorId: string
  versionNumber: number
  changeDescription: string | null
  createdAt: Date
  author?: { id: string; name: string; type: string }
}

interface DiffLine {
  type: 'unchanged' | 'added' | 'removed'
  oldContent: string
  newContent: string
  oldLineNum: number | null
  newLineNum: number | null
}

const props = defineProps<{
  open: boolean
  versions: DocumentVersion[]
  currentDocument: Document | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'restore': [versionId: string]
}>()

const selectedOldVersion = ref<DocumentVersion | null>(null)
const selectedNewVersion = ref<DocumentVersion | Document | null>(null)

// Initialize with sensible defaults
watch(() => props.open, (isOpen) => {
  if (isOpen && props.versions.length > 0) {
    selectedOldVersion.value = props.versions[0] // oldest/first version
    selectedNewVersion.value = props.currentDocument // current version
  }
}, { immediate: true })

const isCurrentVersion = computed(() => {
  return selectedNewVersion.value === props.currentDocument
})

const availableOldVersions = computed(() => {
  return [...props.versions].sort((a, b) => a.versionNumber - b.versionNumber)
})

const availableNewVersions = computed(() => {
  if (!selectedOldVersion.value) return props.versions
  return props.versions.filter(v => v.versionNumber > selectedOldVersion.value!.versionNumber)
})

const oldContent = computed(() => {
  return selectedOldVersion.value?.content || ''
})

const newContent = computed(() => {
  if (selectedNewVersion.value === props.currentDocument) {
    return props.currentDocument?.content || ''
  }
  return (selectedNewVersion.value as DocumentVersion)?.content || ''
})

// Simple line-based diff algorithm
const diffLines = computed<DiffLine[]>(() => {
  const oldLines = oldContent.value.split('\n')
  const newLines = newContent.value.split('\n')

  // Use longest common subsequence for better diff
  const lcs = computeLCS(oldLines, newLines)
  const result: DiffLine[] = []

  let oldIdx = 0
  let newIdx = 0
  let oldLineNum = 1
  let newLineNum = 1

  for (const [oldLcsIdx, newLcsIdx] of lcs) {
    // Add removed lines (in old but not in common)
    while (oldIdx < oldLcsIdx) {
      result.push({
        type: 'removed',
        oldContent: oldLines[oldIdx],
        newContent: '',
        oldLineNum: oldLineNum++,
        newLineNum: null,
      })
      oldIdx++
    }

    // Add added lines (in new but not in common)
    while (newIdx < newLcsIdx) {
      result.push({
        type: 'added',
        oldContent: '',
        newContent: newLines[newIdx],
        oldLineNum: null,
        newLineNum: newLineNum++,
      })
      newIdx++
    }

    // Add unchanged line
    result.push({
      type: 'unchanged',
      oldContent: oldLines[oldIdx],
      newContent: newLines[newIdx],
      oldLineNum: oldLineNum++,
      newLineNum: newLineNum++,
    })
    oldIdx++
    newIdx++
  }

  // Add remaining removed lines
  while (oldIdx < oldLines.length) {
    result.push({
      type: 'removed',
      oldContent: oldLines[oldIdx],
      newContent: '',
      oldLineNum: oldLineNum++,
      newLineNum: null,
    })
    oldIdx++
  }

  // Add remaining added lines
  while (newIdx < newLines.length) {
    result.push({
      type: 'added',
      oldContent: '',
      newContent: newLines[newIdx],
      oldLineNum: null,
      newLineNum: newLineNum++,
    })
    newIdx++
  }

  return result
})

const diffStats = computed(() => {
  const additions = diffLines.value.filter(l => l.type === 'added').length
  const deletions = diffLines.value.filter(l => l.type === 'removed').length
  return { additions, deletions }
})

// Compute longest common subsequence indices
function computeLCS(oldLines: string[], newLines: string[]): [number, number][] {
  const m = oldLines.length
  const n = newLines.length

  // DP table
  const dp: number[][] = Array(m + 1).fill(null).map(() => Array(n + 1).fill(0))

  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      if (oldLines[i - 1] === newLines[j - 1]) {
        dp[i][j] = dp[i - 1][j - 1] + 1
      } else {
        dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1])
      }
    }
  }

  // Backtrack to find the LCS indices
  const result: [number, number][] = []
  let i = m
  let j = n

  while (i > 0 && j > 0) {
    if (oldLines[i - 1] === newLines[j - 1]) {
      result.unshift([i - 1, j - 1])
      i--
      j--
    } else if (dp[i - 1][j] > dp[i][j - 1]) {
      i--
    } else {
      j--
    }
  }

  return result
}

const formatDate = (date: Date | string | undefined) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const handleRestore = () => {
  if (selectedOldVersion.value) {
    emit('restore', selectedOldVersion.value.id)
  }
}
</script>
