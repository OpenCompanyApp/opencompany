<template>
  <button
    :class="[
      'w-full flex items-start gap-3 p-3 rounded-xl transition-all duration-200 text-left group',
      selected
        ? 'bg-olympus-primary text-white shadow-md shadow-olympus-primary/20'
        : 'hover:bg-olympus-surface text-olympus-text'
    ]"
  >
    <div
      :class="[
        'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform duration-200 group-hover:scale-105',
        selected ? 'bg-white/20' : 'bg-olympus-surface'
      ]"
    >
      <Icon
        name="ph:file-text-fill"
        :class="[
          'w-5 h-5',
          selected ? 'text-white' : 'text-olympus-text-muted'
        ]"
      />
    </div>

    <div class="flex-1 min-w-0">
      <p class="font-medium text-sm truncate mb-1">{{ document.title }}</p>
      <div class="flex items-center gap-2">
        <SharedAgentAvatar :user="document.author" size="xs" :show-status="false" />
        <span
          :class="[
            'text-xs truncate',
            selected ? 'text-white/70' : 'text-olympus-text-muted'
          ]"
        >
          {{ document.author.name }}
        </span>
      </div>
      <p
        :class="[
          'text-xs mt-1',
          selected ? 'text-white/60' : 'text-olympus-text-subtle'
        ]"
      >
        Updated {{ formatDate(document.updatedAt) }}
      </p>
    </div>

    <!-- Viewers indicator -->
    <div
      v-if="document.viewers && document.viewers.length > 0"
      class="flex -space-x-1 shrink-0"
    >
      <div
        v-for="viewer in document.viewers.slice(0, 2)"
        :key="viewer.id"
        class="w-5 h-5 rounded-full border-2 border-olympus-sidebar overflow-hidden"
      >
        <SharedAgentAvatar :user="viewer" size="xs" :show-status="false" />
      </div>
    </div>
  </button>
</template>

<script setup lang="ts">
import type { Document } from '~/types'

defineProps<{
  document: Document
  selected: boolean
}>()

const formatDate = (date: Date) => {
  const d = new Date(date)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))

  if (days === 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days} days ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>
