<template>
  <header class="sticky top-0 bg-olympus-bg/95 backdrop-blur-sm border-b border-olympus-border px-8 py-4 shrink-0 z-10">
    <div class="flex items-center justify-between">
      <div class="flex-1 min-w-0">
        <h1 class="text-2xl font-bold truncate text-olympus-text">{{ title }}</h1>
        <div class="flex items-center gap-3 mt-2 text-sm text-olympus-text-muted">
          <div v-if="author" class="flex items-center gap-2">
            <SharedAgentAvatar :user="author" size="xs" :show-status="false" />
            <span>{{ author.name }}</span>
          </div>
          <span v-if="author && updatedAt" class="text-olympus-border">/</span>
          <span v-if="updatedAt">{{ formatDate(updatedAt) }}</span>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <!-- Viewers Presence -->
        <div v-if="viewers && viewers.length > 0" class="flex items-center gap-2">
          <SharedPresenceRow :users="viewers" />
        </div>

        <!-- Editors Indicator -->
        <div
          v-if="editors && editors.length > 0"
          class="flex items-center gap-2 px-3 py-1.5 bg-olympus-primary/20 rounded-full"
        >
          <Icon name="ph:pencil-simple" class="w-4 h-4 text-olympus-primary animate-pulse" />
          <span class="text-xs text-olympus-primary font-medium">
            {{ editors?.[0]?.name }} editing
          </span>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-1">
          <button
            class="p-2 rounded-lg hover:bg-olympus-surface transition-colors outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
            aria-label="Edit document"
            @click="$emit('edit')"
          >
            <Icon name="ph:pencil-simple" class="w-5 h-5 text-olympus-text-muted" />
          </button>
          <button
            class="p-2 rounded-lg hover:bg-olympus-surface transition-colors outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
            aria-label="Share document"
            @click="$emit('share')"
          >
            <Icon name="ph:share" class="w-5 h-5 text-olympus-text-muted" />
          </button>
          <button
            class="p-2 rounded-lg hover:bg-olympus-surface transition-colors outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
            aria-label="More options"
            @click="$emit('menu')"
          >
            <Icon name="ph:dots-three" class="w-5 h-5 text-olympus-text-muted" />
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import type { User } from '~/types'

defineProps<{
  title: string
  author?: User
  updatedAt?: Date
  viewers?: User[]
  editors?: User[]
}>()

defineEmits<{
  edit: []
  share: []
  menu: []
}>()

const formatDate = (date: Date) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })
}
</script>
