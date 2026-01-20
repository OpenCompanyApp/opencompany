<template>
  <div class="space-y-1">
    <!-- Loading State -->
    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="flex items-center gap-2 px-2 py-2">
        <SharedSkeleton custom-class="w-5 h-5" rounded="md" />
        <SharedSkeleton custom-class="w-7 h-7" rounded="md" />
        <div class="flex-1 space-y-1">
          <SharedSkeleton custom-class="h-3 w-32" />
          <SharedSkeleton custom-class="h-2 w-20" />
        </div>
      </div>
    </template>

    <!-- Content -->
    <template v-else-if="documents.length > 0">
      <DocsDocTreeItem
        v-for="doc in rootDocuments"
        :key="doc.id"
        :item="doc"
        :all-items="documents"
        :level="0"
        :selected-id="selected?.id ?? null"
        @select="$emit('select', $event)"
      />
    </template>

    <!-- Empty State -->
    <SharedEmptyState
      v-else
      icon="ph:file-dashed"
      title="No documents yet"
      description="Create your first document to get started"
      size="sm"
      :action="showCreateAction ? {
        label: 'Create document',
        icon: 'ph:plus',
        onClick: () => $emit('create')
      } : undefined"
    />
  </div>
</template>

<script setup lang="ts">
import type { Document } from '~/types'

const props = withDefaults(defineProps<{
  documents: Document[]
  selected: Document | null
  loading?: boolean
  showCreateAction?: boolean
}>(), {
  loading: false,
  showCreateAction: true,
})

defineEmits<{
  select: [doc: Document]
  create: []
}>()

const rootDocuments = computed(() =>
  props.documents.filter(doc => !doc.parentId)
)
</script>
