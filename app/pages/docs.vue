<template>
  <div class="h-full flex">
    <!-- Document List Sidebar -->
    <aside class="w-72 bg-olympus-sidebar border-r border-olympus-border flex flex-col shrink-0">
      <div class="p-4 border-b border-olympus-border">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-gradient">Documents</h2>
          <button class="p-1.5 rounded-lg hover:bg-olympus-surface text-olympus-primary transition-colors">
            <Icon name="ph:plus" class="w-5 h-5" />
          </button>
        </div>

        <!-- Search -->
        <div class="flex items-center gap-2 px-3 py-2 bg-olympus-surface rounded-xl border border-olympus-border">
          <Icon name="ph:magnifying-glass" class="w-4 h-4 text-olympus-text-muted" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search docs..."
            class="flex-1 bg-transparent text-sm outline-none placeholder:text-olympus-text-muted"
          />
        </div>
      </div>

      <div class="flex-1 overflow-y-auto p-3">
        <DocsDocList
          :documents="filteredDocuments"
          :selected="selectedDoc"
          @select="selectedDoc = $event"
        />
      </div>
    </aside>

    <!-- Document Viewer -->
    <DocsDocViewer :document="selectedDoc" class="flex-1" />
  </div>
</template>

<script setup lang="ts">
const { documents } = useMockData()

// Select the first actual document (not a folder)
const firstDocument = documents.find(doc => !doc.isFolder) || documents[0]
const selectedDoc = ref(firstDocument)
const searchQuery = ref('')

const filteredDocuments = computed(() =>
  documents.filter(doc =>
    doc.title.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
)
</script>
