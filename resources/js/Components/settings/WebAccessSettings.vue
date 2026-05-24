<template>
  <SettingsSection title="Web Access" icon="ph:globe">
    <div class="space-y-4">
      <SettingsField label="Search Provider" description="Default adapter used by web_search. Provider API keys are configured under Integrations.">
        <select v-model="settings.web_search_default_provider" class="settings-input">
          <option v-for="provider in searchProviders" :key="provider" :value="provider">{{ provider }}</option>
        </select>
      </SettingsField>

      <SettingsField label="Search Fallbacks" description="Comma-separated fallback providers tried when the default search provider fails.">
        <input v-model="searchFallbacksText" class="settings-input font-mono text-sm" placeholder="exa, brave" />
      </SettingsField>

      <SettingsField label="Fetch Provider" description="Default adapter used by web_fetch. Direct fetch is the safest cheap default.">
        <select v-model="settings.web_fetch_default_provider" class="settings-input">
          <option v-for="provider in fetchProviders" :key="provider" :value="provider">{{ provider }}</option>
        </select>
      </SettingsField>

      <SettingsField label="Fetch Fallbacks" description="Comma-separated provider-backed fetch fallbacks.">
        <input v-model="fetchFallbacksText" class="settings-input font-mono text-sm" placeholder="jina, firecrawl, zai" />
      </SettingsField>

      <SettingsField label="External Fetch Providers">
        <label class="flex items-center gap-3 cursor-pointer">
          <div class="relative">
            <input v-model="settings.web_fetch_allow_external" type="checkbox" class="sr-only" />
            <div
              class="w-11 h-6 rounded-full transition-colors"
              :class="settings.web_fetch_allow_external ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'"
            >
              <div
                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform"
                :class="{ 'translate-x-5': settings.web_fetch_allow_external }"
              />
            </div>
          </div>
          <span class="text-sm text-neutral-500 dark:text-neutral-400">
            Allow web_fetch to send URLs to configured reader/extraction providers
          </span>
        </label>
      </SettingsField>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <SettingsField label="Max Search Results">
          <input v-model.number="settings.web_search_max_results" type="number" min="1" max="20" class="settings-input" />
        </SettingsField>

        <SettingsField label="Max Fetch Characters">
          <input v-model.number="settings.web_fetch_max_chars" type="number" min="50" max="50000" class="settings-input" />
        </SettingsField>

        <SettingsField label="Max Fetch Bytes">
          <input v-model.number="settings.web_fetch_max_bytes" type="number" min="1000" class="settings-input" />
        </SettingsField>

        <SettingsField label="Cache TTL Seconds">
          <input v-model.number="settings.web_cache_ttl_seconds" type="number" min="0" class="settings-input" />
        </SettingsField>
      </div>

      <SettingsField label="Allowed Domains" description="Comma-separated workspace allowlist. Leave blank to allow normal public web URLs.">
        <input v-model="allowedDomainsText" class="settings-input font-mono text-sm" placeholder="laravel.com, developer.mozilla.org" />
      </SettingsField>

      <SettingsField label="Blocked Domains" description="Comma-separated workspace blocklist applied after provider results and before fetches.">
        <input v-model="blockedDomainsText" class="settings-input font-mono text-sm" placeholder="example.internal, badsite.test" />
      </SettingsField>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <SettingsField label="Country">
          <input v-model="settings.web_country" class="settings-input" placeholder="US" />
        </SettingsField>
        <SettingsField label="Language">
          <input v-model="settings.web_language" class="settings-input" placeholder="en" />
        </SettingsField>
        <SettingsField label="Recency">
          <input v-model="settings.web_recency" class="settings-input" placeholder="news, d, w, m" />
        </SettingsField>
      </div>
    </div>

    <template #actions>
      <SaveButton :saving="savingCategory === 'web'" :saved="savedCategory === 'web'" @click="save" />
    </template>
  </SettingsSection>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import SaveButton from '@/Components/settings/SaveButton.vue'
import type { WebSettingsData } from '@/Components/settings/types'

const props = defineProps<{
  initialWeb: WebSettingsData
  savingCategory: string | null
  savedCategory: string | null
}>()

const emit = defineEmits<{
  save: [category: string, settings: Record<string, unknown>]
}>()

const settings = reactive<WebSettingsData>({ ...props.initialWeb })

const searchProviders = ['tavily', 'zai', 'exa', 'brave', 'firecrawl', 'parallel', 'jina', 'searxng', 'perplexity', 'openai_native', 'anthropic_native']
const fetchProviders = ['direct', 'jina', 'firecrawl', 'tavily', 'exa', 'parallel', 'zai']

const listText = (key: keyof WebSettingsData) => computed({
  get: () => (Array.isArray(settings[key]) ? (settings[key] as string[]).join(', ') : ''),
  set: (value: string) => {
    ;(settings as unknown as Record<string, unknown>)[key] = value.split(',').map(item => item.trim()).filter(Boolean)
  },
})

const searchFallbacksText = listText('web_search_fallback_providers')
const fetchFallbacksText = listText('web_fetch_fallback_providers')
const allowedDomainsText = listText('web_allowed_domains')
const blockedDomainsText = listText('web_blocked_domains')

function save() {
  emit('save', 'web', { ...settings })
}
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
