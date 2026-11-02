<template>
  <section class="space-y-2" aria-label="Script source history">
    <h3 class="text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Source history</h3>
    <p class="text-xs text-neutral-500 dark:text-neutral-400">Previous bodies are preserved. Loading one only replaces your unsaved editor text; saving requires fresh Ruby validation.</p>
    <p v-if="error" role="alert" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    <p v-if="loading" role="status" class="text-xs text-neutral-500">Loading revisions…</p>
    <Button v-for="revision in revisions" :key="revision.id" size="sm" variant="ghost" class="w-full justify-start" @click="inspect(revision.id)">
      {{ revision.source_digest.slice(0, 8) }} · {{ revision.runtime ?? 'legacy' }} · {{ revision.status }}
    </Button>
    <div v-if="pages > 1" class="flex items-center gap-2">
      <Button size="sm" variant="ghost" :disabled="loading || page <= 1" @click="load(page - 1)">Previous</Button>
      <span class="text-xs text-neutral-500">{{ page }} / {{ pages }}</span>
      <Button size="sm" variant="ghost" :disabled="loading || page >= pages" @click="load(page + 1)">Next</Button>
    </div>
    <Modal v-model:open="open" title="Preserved script source" size="lg">
      <template v-if="selected" #default>
        <p class="mb-2 text-xs text-neutral-500 dark:text-neutral-400">{{ selected.runtime ?? 'Unknown legacy language' }} · {{ selected.status }} · {{ selected.created_at }}</p>
        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded bg-neutral-50 p-3 text-xs text-neutral-800 dark:bg-neutral-900 dark:text-neutral-200">{{ selected.source }}</pre>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Review before loading. Legacy source is not translated into Ruby. This does not enable or run the automation.</p>
      </template>
      <template #footer>
        <Button v-if="selected" size="sm" variant="primary" @click="loadIntoEditor">Replace editor text with this source</Button>
      </template>
    </Modal>
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import Button from '@/Components/shared/Button.vue'
import Modal from '@/Components/shared/Modal.vue'
import { scriptRevisions, scriptRevision } from '@/actions/App/Http/Controllers/Api/AutomationController'
import { wayfinderRequest } from '@/utils/wayfinder'
import { useWorkspace } from '@/composables/useWorkspace'

interface Revision {
  id: string
  runtime: string | null
  source_digest: string
  status: string
  created_at: string
  source?: string
}

const props = defineProps<{ automationId: string }>()
const emit = defineEmits<{ select: [source: string] }>()
const { workspace } = useWorkspace()
const revisions = ref<Revision[]>([])
const selected = ref<Revision | null>(null)
const open = ref(false)
const loading = ref(false)
const error = ref('')
const page = ref(1)
const pages = ref(1)
let generation = 0

async function load(nextPage = 1) {
  const request = ++generation
  loading.value = true
  error.value = ''
  try {
    const response = await wayfinderRequest<{ data: Revision[]; last_page: number }>(scriptRevisions(props.automationId, { query: { page: nextPage } }))
    if (request !== generation) return
    revisions.value = response.data.data
    pages.value = response.data.last_page
    page.value = nextPage
  } catch {
    if (request === generation) error.value = 'Source history could not be loaded.'
  } finally {
    if (request === generation) loading.value = false
  }
}

async function inspect(id: string) {
  const request = ++generation
  error.value = ''
  try {
    const response = await wayfinderRequest<Revision>(scriptRevision({ id: props.automationId, revision: id }))
    if (request !== generation) return
    selected.value = response.data
    open.value = true
  } catch {
    if (request === generation) error.value = 'This preserved source could not be loaded.'
  }
}

function loadIntoEditor() {
  if (selected.value?.source !== undefined) emit('select', selected.value.source)
  open.value = false
}

// Do not retain source from a previous workspace while the next request loads.
watch(() => [props.automationId, workspace.value?.id], () => {
  revisions.value = []
  selected.value = null
  open.value = false
  void load()
}, { immediate: true })
</script>
