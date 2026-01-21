<template>
  <DialogRoot :open="open" @update:open="$emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/50 z-40" />
      <DialogContent
        class="fixed inset-4 bg-white border border-gray-200 rounded-xl z-50 overflow-hidden flex flex-col"
      >
        <DialogTitle class="sr-only">Compare Versions</DialogTitle>
        <DialogDescription class="sr-only">Compare document versions side by side</DialogDescription>

        <!-- Header -->
        <div class="shrink-0 p-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Compare Versions</h2>
            <DialogClose
              class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
            >
              <Icon name="ph:x" class="w-5 h-5" />
            </DialogClose>
          </div>

          <!-- Version Selectors -->
          <div class="mt-4 flex items-center gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-500 mb-1">From (older)</label>
              <select
                v-model="selectedOldVersion"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 text-sm focus:border-gray-300 focus:outline-none"
              >
                <option v-for="v in availableOldVersions" :key="v.id" :value="v">
                  Version {{ v.versionNumber }} - {{ formatDate(v.createdAt) }}
                </option>
              </select>
            </div>
            <Icon name="ph:arrow-right" class="w-5 h-5 text-gray-400 mt-6" />
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-500 mb-1">To (newer)</label>
              <select
                v-model="selectedNewVersion"
                class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700 text-sm focus:border-gray-300 focus:outline-none"
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
        </div>

        <!-- Diff Content -->
        <div class="flex-1 overflow-hidden flex">
          <!-- Stats -->
          <div v-if="diffStats" class="absolute top-24 right-8 flex items-center gap-3 text-sm">
            <span class="text-green-600">+{{ diffStats.additions }} added</span>
            <span class="text-red-600">-{{ diffStats.deletions }} removed</span>
          </div>

          <!-- Side by Side View -->
          <div class="flex-1 grid grid-cols-2 divide-x divide-gray-200 overflow-hidden">
            <!-- Old Version -->
            <div class="flex flex-col overflow-hidden">
              <div class="shrink-0 px-4 py-2 bg-red-50 border-b border-gray-200">
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
                    'flex border-b border-gray-100',
                    line.type === 'removed' ? 'bg-red-50' : '',
                    line.type === 'added' ? 'bg-gray-50 text-gray-300' : ''
                  ]"
                >
                  <span class="w-12 shrink-0 px-2 py-1 text-right text-gray-400 border-r border-gray-200 bg-gray-50 select-none">
                    {{ line.type !== 'added' ? line.oldLineNum : '' }}
                  </span>
                  <pre
                    class="flex-1 px-3 py-1 whitespace-pre-wrap break-words"
                    :class="line.type === 'removed' ? 'text-red-700' : 'text-gray-700'"
                  >{{ line.type !== 'added' ? line.oldContent : '' }}</pre>
                </div>
              </div>
            </div>

            <!-- New Version -->
            <div class="flex flex-col overflow-hidden">
              <div class="shrink-0 px-4 py-2 bg-green-50 border-b border-gray-200">
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
                    'flex border-b border-gray-100',
                    line.type === 'added' ? 'bg-green-50' : '',
                    line.type === 'removed' ? 'bg-gray-50 text-gray-300' : ''
                  ]"
                >
                  <span class="w-12 shrink-0 px-2 py-1 text-right text-gray-400 border-r border-gray-200 bg-gray-50 select-none">
                    {{ line.type !== 'removed' ? line.newLineNum : '' }}
                  </span>
                  <pre
                    class="flex-1 px-3 py-1 whitespace-pre-wrap break-words"
                    :class="line.type === 'added' ? 'text-green-700' : 'text-gray-700'"
                  >{{ line.type !== 'removed' ? line.newContent : '' }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="shrink-0 p-4 border-t border-gray-200 flex items-center justify-between">
          <div class="text-sm text-gray-500">
            {{ diffLines.length }} lines compared
          </div>
          <button
            v-if="selectedOldVersion"
            class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors"
            @click="handleRestore"
          >
            Restore to Version {{ selectedOldVersion.versionNumber }}
          </button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { Document } from '@/types'
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'reka-ui'

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
