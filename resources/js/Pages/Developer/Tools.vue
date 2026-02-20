<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-6xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3">
              <Link
                :href="workspacePath('/integrations')"
                class="text-sm text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
              >
                <Icon name="ph:arrow-left" class="w-4 h-4" />
              </Link>
              <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Tool Catalog</h1>
            </div>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1 ml-7">
              Developer reference for all tools available to agents
            </p>
          </div>
          <!-- Search -->
          <div class="relative hidden md:block">
            <Icon name="ph:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search tools..."
              class="w-64 pl-8 pr-8 py-2 text-sm rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="searchQuery = ''"
            >
              <Icon name="ph:x" class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Mobile Search -->
        <div class="relative mt-3 md:hidden">
          <Icon name="ph:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search tools..."
            class="w-full pl-8 pr-8 py-2 text-sm rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
            @click="searchQuery = ''"
          >
            <Icon name="ph:x" class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Stats -->
        <div v-if="!loading && catalog.length > 0" class="flex items-center gap-4 mt-3 ml-7 text-xs text-neutral-400 dark:text-neutral-500">
          <span>{{ totalTools }} tools</span>
          <span>{{ builtInCount }} built-in</span>
          <span v-if="integrationCount > 0">{{ integrationCount }} from integrations</span>
          <span>{{ catalog.length }} apps</span>
        </div>
      </header>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto min-h-0 -mx-4 md:-mx-6 px-4 md:px-6 pb-6">
        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-12">
          <Icon name="ph:spinner" class="w-5 h-5 text-neutral-400 animate-spin" />
          <span class="ml-2 text-sm text-neutral-500">Loading tool catalog...</span>
        </div>

        <!-- Empty -->
        <div v-else-if="filteredCatalog.length === 0 && searchQuery" class="text-center py-12">
          <Icon name="ph:magnifying-glass" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No tools match "{{ searchQuery }}"</p>
        </div>

        <div v-else-if="filteredCatalog.length === 0" class="text-center py-12">
          <Icon name="ph:wrench" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
          <p class="text-sm text-neutral-500 dark:text-neutral-400">No tools available</p>
        </div>

        <!-- App groups -->
        <div v-else class="space-y-2">
          <template v-for="(group, idx) in filteredCatalog" :key="group.name">
            <!-- Separator between integration and built-in tools -->
            <div
              v-if="!group.isIntegration && idx > 0 && filteredCatalog[idx - 1].isIntegration"
              class="flex items-center gap-3 pt-2 pb-1"
            >
              <div class="flex-1 border-t border-neutral-200 dark:border-neutral-700" />
              <span class="text-[10px] uppercase tracking-wider text-neutral-400 dark:text-neutral-500 font-medium">Built-in</span>
              <div class="flex-1 border-t border-neutral-200 dark:border-neutral-700" />
            </div>

            <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
              <!-- Group header -->
              <button
                type="button"
                class="w-full px-4 py-3 flex items-center gap-3 hover:bg-neutral-100 dark:hover:bg-neutral-700/50 transition-colors"
                @click="toggleGroup(group.name)"
              >
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-neutral-200 dark:bg-neutral-700/50">
                  <Icon
                    :name="group.logo || group.icon"
                    :class="[group.logo ? 'w-5 h-5' : 'w-4 h-4 text-neutral-600 dark:text-neutral-300']"
                  />
                </div>
                <div class="flex-1 min-w-0 text-left">
                  <p class="text-sm font-medium text-neutral-900 dark:text-white capitalize">{{ group.name.replace(/_/g, ' ') }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ group.description }}</p>
                </div>
                <span class="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums shrink-0">
                  {{ group.tools.length }} {{ group.tools.length === 1 ? 'tool' : 'tools' }}
                </span>
                <Icon
                  name="ph:caret-right"
                  :class="[
                    'w-4 h-4 text-neutral-400 dark:text-neutral-500 transition-transform shrink-0',
                    isGroupExpanded(group.name) ? 'rotate-90' : ''
                  ]"
                />
              </button>

              <!-- Tools list -->
              <div v-if="isGroupExpanded(group.name)" class="divide-y divide-neutral-200 dark:divide-neutral-700 border-t border-neutral-200 dark:border-neutral-700">
                <div v-for="tool in group.tools" :key="tool.slug" class="px-4 py-3">
                  <!-- Tool header row: slug + type badge -->
                  <div class="flex items-center justify-between gap-2">
                    <code class="text-xs font-mono text-neutral-700 dark:text-neutral-300 bg-neutral-100 dark:bg-neutral-900 px-1.5 py-0.5 rounded">
                      {{ tool.slug }}
                    </code>
                    <span
                      :class="[
                        'px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider rounded shrink-0',
                        tool.type === 'write'
                          ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400'
                          : 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                      ]"
                    >
                      {{ tool.type }}
                    </span>
                  </div>

                  <!-- Tool name + short description -->
                  <p class="text-sm font-medium text-neutral-900 dark:text-white mt-1">{{ tool.name }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{{ tool.description }}</p>

                  <!-- Full description toggle (when different from short) -->
                  <div v-if="hasFullDescription(tool)" class="mt-2">
                    <button
                      type="button"
                      class="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
                      @click="toggleDescription(tool.slug)"
                    >
                      <Icon
                        name="ph:caret-right"
                        :class="[
                          'w-3 h-3 transition-transform',
                          isDescriptionExpanded(tool.slug) ? 'rotate-90' : ''
                        ]"
                      />
                      <span class="font-medium">Details</span>
                    </button>
                    <div
                      v-if="isDescriptionExpanded(tool.slug)"
                      v-html="renderMarkdown(tool.fullDescription!)"
                      class="tool-description mt-2 ml-1 text-xs text-neutral-600 dark:text-neutral-300"
                    />
                  </div>

                  <!-- Parameters toggle -->
                  <div v-if="tool.parameters?.length" class="mt-2">
                    <button
                      type="button"
                      class="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors"
                      @click="toggleParams(tool.slug)"
                    >
                      <Icon
                        name="ph:caret-right"
                        :class="[
                          'w-3 h-3 transition-transform',
                          isParamsExpanded(tool.slug) ? 'rotate-90' : ''
                        ]"
                      />
                      <span class="font-medium">Parameters</span>
                      <span class="text-neutral-400 dark:text-neutral-500">({{ tool.parameters.length }})</span>
                    </button>

                    <!-- Parameter list — compact divider layout -->
                    <div v-if="isParamsExpanded(tool.slug)" class="mt-2 ml-1 bg-white dark:bg-neutral-900 rounded-lg border border-neutral-200 dark:border-neutral-700 divide-y divide-neutral-100 dark:divide-neutral-800">
                      <div
                        v-for="param in tool.parameters"
                        :key="param.name"
                        class="px-3 py-2"
                      >
                        <div class="flex items-center gap-2 flex-wrap">
                          <code class="text-xs font-mono font-medium text-neutral-800 dark:text-neutral-200">{{ param.name }}</code>
                          <span class="px-1.5 py-0.5 text-[10px] font-mono rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400">
                            {{ formatType(param.type) }}
                          </span>
                          <span
                            v-if="param.required"
                            class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400"
                          >
                            required
                          </span>
                        </div>
                        <div
                          v-if="param.description"
                          v-html="renderMarkdown(param.description)"
                          class="param-description text-xs text-neutral-500 dark:text-neutral-400 mt-1"
                        />
                        <!-- Enum values -->
                        <div v-if="param.enum?.length" class="flex flex-wrap gap-1 mt-1.5">
                          <span class="text-[10px] text-neutral-400 dark:text-neutral-500 mr-1">values:</span>
                          <code
                            v-for="val in param.enum"
                            :key="val"
                            class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400"
                          >
                            {{ val }}
                          </code>
                        </div>
                        <!-- Array items -->
                        <div v-if="param.items" class="mt-1.5 text-[10px] text-neutral-400 dark:text-neutral-500">
                          items: <code class="font-mono">{{ param.items.type || 'any' }}</code>
                        </div>
                        <!-- Nested object properties -->
                        <div v-if="param.properties" class="mt-1.5 ml-2 space-y-1 border-l-2 border-neutral-200 dark:border-neutral-700 pl-2">
                          <div v-for="(propSchema, propName) in param.properties" :key="propName" class="text-[10px]">
                            <code class="font-mono text-neutral-700 dark:text-neutral-300">{{ propName }}</code>
                            <span class="text-neutral-400 dark:text-neutral-500 ml-1">{{ propSchema.type || 'any' }}</span>
                            <span v-if="propSchema.description" class="text-neutral-400 dark:text-neutral-500 ml-1">— {{ propSchema.description }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- No parameters -->
                  <p v-else class="mt-1.5 text-[10px] text-neutral-400 dark:text-neutral-500 italic">No parameters</p>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import { useMarkdown } from '@/composables/useMarkdown'

const { workspacePath } = useWorkspace()
const { renderMarkdown } = useMarkdown()

interface ToolParameter {
  name: string
  type: string | string[]
  required: boolean
  description?: string
  enum?: string[]
  items?: { type: string }
  properties?: Record<string, { type: string; description?: string }>
}

interface ToolEntry {
  slug: string
  name: string
  description: string
  fullDescription?: string
  type: 'read' | 'write'
  icon: string
  parameters: ToolParameter[]
}

interface AppGroup {
  name: string
  description: string
  icon: string
  logo?: string
  isIntegration: boolean
  tools: ToolEntry[]
}

const loading = ref(true)
const catalog = ref<AppGroup[]>([])
const searchQuery = ref('')
const expandedGroups = reactive<Record<string, boolean>>({})
const expandedParams = reactive(new Set<string>())
const expandedDescriptions = reactive(new Set<string>())

const totalTools = computed(() => catalog.value.reduce((sum, g) => sum + g.tools.length, 0))
const builtInCount = computed(() => catalog.value.filter(g => !g.isIntegration).reduce((sum, g) => sum + g.tools.length, 0))
const integrationCount = computed(() => catalog.value.filter(g => g.isIntegration).reduce((sum, g) => sum + g.tools.length, 0))

const filteredCatalog = computed<AppGroup[]>(() => {
  if (!searchQuery.value) return catalog.value

  const q = searchQuery.value.toLowerCase()
  return catalog.value
    .map(group => ({
      ...group,
      tools: group.tools.filter(
        t => t.slug.includes(q)
          || t.name.toLowerCase().includes(q)
          || t.description.toLowerCase().includes(q)
          || (t.fullDescription && t.fullDescription.toLowerCase().includes(q))
      ),
    }))
    .filter(group => group.tools.length > 0)
})

const hasFullDescription = (tool: ToolEntry) => {
  return tool.fullDescription && tool.fullDescription !== tool.description
}

// Set of tool slugs that belong to integration groups (auto-expand details+params)
const integrationSlugs = computed(() => {
  const slugs = new Set<string>()
  for (const group of catalog.value) {
    if (group.isIntegration) {
      for (const tool of group.tools) slugs.add(tool.slug)
    }
  }
  return slugs
})

// Auto-expand groups when searching
const isGroupExpanded = (name: string) => {
  if (searchQuery.value) return true
  return expandedGroups[name] ?? false
}

// Auto-expand descriptions and params when searching or for integration tools
const isDescriptionExpanded = (slug: string) => {
  if (searchQuery.value) return true
  if (integrationSlugs.value.has(slug)) return !expandedDescriptions.has(slug) // default open, toggle closes
  return expandedDescriptions.has(slug)
}

const isParamsExpanded = (slug: string) => {
  if (searchQuery.value) return true
  if (integrationSlugs.value.has(slug)) return !expandedParams.has(slug) // default open, toggle closes
  return expandedParams.has(slug)
}

const toggleGroup = (name: string) => {
  expandedGroups[name] = !expandedGroups[name]
}

const toggleDescription = (slug: string) => {
  if (expandedDescriptions.has(slug)) expandedDescriptions.delete(slug)
  else expandedDescriptions.add(slug)
}

const toggleParams = (slug: string) => {
  if (expandedParams.has(slug)) expandedParams.delete(slug)
  else expandedParams.add(slug)
}

const formatType = (type: string | string[]) => {
  if (Array.isArray(type)) return type.join(' | ')
  return type
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/tools/catalog')
    catalog.value = data
  } catch (err) {
    console.error('Failed to load tool catalog:', err)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
/* Compact prose for tool full descriptions */
.tool-description :deep(p) {
  margin: 0.25rem 0;
  font-size: 0.75rem;
  line-height: 1.5;
}
.tool-description :deep(ul),
.tool-description :deep(ol) {
  margin: 0.375rem 0;
  padding-left: 1.25rem;
}
.tool-description :deep(li) {
  margin: 0.125rem 0;
  font-size: 0.75rem;
  line-height: 1.5;
}
.tool-description :deep(strong) {
  font-weight: 600;
  color: var(--color-neutral-700);
}
:is(.dark) .tool-description :deep(strong) {
  color: var(--color-neutral-200);
}
.tool-description :deep(code) {
  font-size: 0.6875rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  background: var(--color-neutral-100);
  color: var(--color-neutral-600);
}
:is(.dark) .tool-description :deep(code) {
  background: var(--color-neutral-800);
  color: var(--color-neutral-400);
}
.tool-description :deep(pre) {
  margin: 0.5rem 0;
  font-size: 0.75rem;
}
.tool-description :deep(h1),
.tool-description :deep(h2),
.tool-description :deep(h3),
.tool-description :deep(h4) {
  font-size: 0.75rem;
  font-weight: 600;
  margin: 0.5rem 0 0.25rem;
}

/* Compact prose for parameter descriptions (inline, no margins) */
.param-description :deep(p) {
  margin: 0;
  font-size: inherit;
  line-height: 1.5;
}
.param-description :deep(code) {
  font-size: 0.6875rem;
  padding: 0.0625rem 0.25rem;
  border-radius: 0.1875rem;
  background: var(--color-neutral-100);
  color: var(--color-neutral-600);
}
:is(.dark) .param-description :deep(code) {
  background: var(--color-neutral-800);
  color: var(--color-neutral-400);
}
</style>
