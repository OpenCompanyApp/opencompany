<template>
  <Modal :open="true" size="md" title="Calendar Feeds" @close="$emit('close')">
    <div class="space-y-6">
      <!-- ICS Feed URLs -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-medium text-neutral-900 dark:text-white">
            Subscribe from external apps
          </h3>
          <Button size="sm" @click="handleCreateFeed">
            <Icon name="ph:plus" class="w-3.5 h-3.5 mr-1" />
            New Feed
          </Button>
        </div>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
          Use these URLs to subscribe to your calendar from Google Calendar, Apple Calendar, or other apps.
        </p>

        <div v-if="loading" class="text-sm text-neutral-400 py-4 text-center">
          Loading feeds...
        </div>

        <div v-else-if="feeds.length === 0" class="text-sm text-neutral-400 py-4 text-center">
          No feeds yet. Create one to get a subscription URL.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="feed in feeds"
            :key="feed.id"
            class="flex items-center gap-2 p-2.5 bg-neutral-50 dark:bg-neutral-800/50 rounded-lg"
          >
            <Icon name="ph:rss" class="w-4 h-4 text-neutral-400 shrink-0" />
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-neutral-900 dark:text-white truncate">
                {{ feed.name }}
              </div>
              <div class="text-xs text-neutral-400 dark:text-neutral-500 truncate font-mono">
                {{ feed.url }}
              </div>
            </div>
            <button
              type="button"
              class="p-1.5 rounded hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors"
              title="Copy URL"
              @click="copyUrl(feed.url)"
            >
              <Icon
                :name="copiedId === feed.id ? 'ph:check' : 'ph:copy'"
                class="w-4 h-4 text-neutral-500"
              />
            </button>
            <button
              type="button"
              class="p-1.5 rounded hover:bg-red-100 dark:hover:bg-red-900/20 transition-colors"
              title="Delete feed"
              @click="handleDeleteFeed(feed.id)"
            >
              <Icon name="ph:trash" class="w-4 h-4 text-red-400" />
            </button>
          </div>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-neutral-200 dark:border-neutral-700" />

      <!-- Import from URL -->
      <div>
        <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-2">
          Import from ICS URL
        </h3>
        <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
          Import events from an external calendar by pasting its ICS feed URL.
        </p>
        <div class="flex gap-2">
          <Input
            v-model="importUrl"
            placeholder="https://example.com/calendar.ics"
            class="flex-1"
          />
          <Button
            :disabled="!importUrl || importing"
            @click="handleImportFromUrl"
          >
            {{ importing ? 'Importing...' : 'Import' }}
          </Button>
        </div>
        <p v-if="importResult" class="text-xs mt-2" :class="importResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-500'">
          {{ importResult.message }}
        </p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end">
        <Button variant="secondary" @click="$emit('close')">
          Close
        </Button>
      </div>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Modal from '@/Components/shared/Modal.vue'
import Button from '@/Components/shared/Button.vue'
import Input from '@/Components/shared/Input.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'
import type { CalendarFeed } from '@/types'

const emit = defineEmits<{
  close: []
  imported: []
}>()

const { fetchCalendarFeeds, createCalendarFeed, deleteCalendarFeed, importCalendarEventsFromUrl } = useApi()

const feeds = ref<CalendarFeed[]>([])
const loading = ref(true)
const copiedId = ref<string | null>(null)
const importUrl = ref('')
const importing = ref(false)
const importResult = ref<{ success: boolean; message: string } | null>(null)

const loadFeeds = async () => {
  loading.value = true
  try {
    const { data, promise } = fetchCalendarFeeds()
    await promise
    feeds.value = data.value || []
  } catch {
    feeds.value = []
  } finally {
    loading.value = false
  }
}

const handleCreateFeed = async () => {
  try {
    const response = await createCalendarFeed({ name: 'My Calendar' })
    feeds.value.unshift(response.data)
  } catch (error) {
    console.error('Failed to create feed:', error)
  }
}

const handleDeleteFeed = async (id: string) => {
  try {
    await deleteCalendarFeed(id)
    feeds.value = feeds.value.filter((f) => f.id !== id)
  } catch (error) {
    console.error('Failed to delete feed:', error)
  }
}

const copyUrl = async (url: string) => {
  try {
    await navigator.clipboard.writeText(url)
    const feed = feeds.value.find((f) => f.url === url)
    if (feed) {
      copiedId.value = feed.id
      setTimeout(() => { copiedId.value = null }, 2000)
    }
  } catch {
    // Fallback: select the text
  }
}

const handleImportFromUrl = async () => {
  if (!importUrl.value) return
  importing.value = true
  importResult.value = null

  try {
    const response = await importCalendarEventsFromUrl(importUrl.value)
    importResult.value = {
      success: true,
      message: `Successfully imported ${response.data.imported} event(s).`,
    }
    importUrl.value = ''
    emit('imported')
  } catch (error: any) {
    importResult.value = {
      success: false,
      message: error?.response?.data?.message || 'Failed to import from URL.',
    }
  } finally {
    importing.value = false
  }
}

onMounted(loadFeeds)
</script>
