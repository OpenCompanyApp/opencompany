<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-7xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-5 shrink-0">
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
              API reference for all tools and Lua functions
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
      </header>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <Icon name="ph:spinner" class="w-5 h-5 text-neutral-400 animate-spin" />
        <span class="ml-2 text-sm text-neutral-500">Loading tool catalog...</span>
      </div>

      <!-- Mobile Nav (horizontal pills) -->
      <div v-if="!loading" class="flex gap-1.5 overflow-x-auto pb-3 -mx-4 px-4 md:hidden shrink-0" style="-ms-overflow-style: none; scrollbar-width: none;">
        <button
          v-for="item in visibleSidebarItems"
          :key="'mobile-' + item.id"
          type="button"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
            activeItem === item.id
              ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
              : item.enabled === false
                ? 'bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500'
                : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
          ]"
          @click="activeItem = item.id"
        >
          <Icon :name="item.icon" class="w-3.5 h-3.5" />
          {{ item.label }}
        </button>
      </div>

      <!-- Sidebar + Content -->
      <div v-if="!loading" class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
        <!-- Desktop Sidebar -->
        <nav class="hidden md:flex w-48 shrink-0 flex-col gap-0.5 overflow-y-auto pb-6">
          <!-- Guides section -->
          <template v-if="guideItems.length > 0">
            <div class="px-2 py-1.5 text-[10px] uppercase tracking-wider text-neutral-400 dark:text-neutral-500 font-medium">Guides</div>
            <button
              v-for="item in guideItems"
              :key="item.id"
              type="button"
              :class="sidebarButtonClass(item.id)"
              @click="activeItem = item.id"
            >
              <Icon :name="item.icon" class="w-4 h-4 shrink-0" />
              <span class="flex-1 truncate">{{ item.label }}</span>
            </button>
          </template>

          <!-- Built-in section -->
          <template v-if="builtInItems.length > 0">
            <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />
            <div class="px-2 py-1.5 text-[10px] uppercase tracking-wider text-neutral-400 dark:text-neutral-500 font-medium">Built-in</div>
            <button
              v-for="item in builtInItems"
              :key="item.id"
              type="button"
              :class="sidebarButtonClass(item.id)"
              @click="activeItem = item.id"
            >
              <Icon :name="item.icon" class="w-4 h-4 shrink-0" />
              <span class="flex-1 truncate">{{ item.label }}</span>
              <span class="text-[10px] tabular-nums text-neutral-400 dark:text-neutral-500 shrink-0">{{ item.toolCount }}</span>
            </button>
          </template>

          <!-- Integrations section -->
          <template v-if="allIntegrationItems.length > 0">
            <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />
            <div class="flex items-center justify-between px-2 py-1.5">
              <span class="text-[10px] uppercase tracking-wider text-neutral-400 dark:text-neutral-500 font-medium">Integrations</span>
              <button
                type="button"
                class="text-[10px] text-neutral-400 dark:text-neutral-500 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                :title="showAllIntegrations ? 'Show enabled only' : 'Show all integrations'"
                @click="showAllIntegrations = !showAllIntegrations"
              >
                {{ showAllIntegrations ? 'enabled only' : 'show all' }}
              </button>
            </div>
            <button
              v-for="item in integrationItems"
              :key="item.id"
              type="button"
              :class="sidebarButtonClass(item.id, item.enabled === false)"
              @click="activeItem = item.id"
            >
              <Icon :name="item.logo || item.icon" :class="['w-4 h-4 shrink-0', item.enabled === false ? 'opacity-40' : '']" />
              <span class="flex-1 truncate">{{ item.label }}</span>
              <span v-if="item.enabled === false" class="px-1 py-0.5 text-[9px] font-medium rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500 shrink-0">off</span>
              <span v-else class="text-[10px] tabular-nums text-neutral-400 dark:text-neutral-500 shrink-0">{{ item.toolCount }}</span>
            </button>
          </template>

          <!-- MCP section -->
          <template v-if="mcpItems.length > 0">
            <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />
            <div class="px-2 py-1.5 text-[10px] uppercase tracking-wider text-neutral-400 dark:text-neutral-500 font-medium">MCP Servers</div>
            <button
              v-for="item in mcpItems"
              :key="item.id"
              type="button"
              :class="sidebarButtonClass(item.id)"
              @click="activeItem = item.id"
            >
              <Icon :name="item.icon" class="w-4 h-4 shrink-0" />
              <span class="flex-1 truncate">{{ item.label }}</span>
              <span class="text-[10px] tabular-nums text-neutral-400 dark:text-neutral-500 shrink-0">{{ item.toolCount }}</span>
            </button>
          </template>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 overflow-y-auto pb-6">
          <!-- Empty state -->
          <div v-if="!activeItem" class="text-center py-16">
            <Icon name="ph:book-open-text" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Select a capability from the sidebar</p>
          </div>

          <!-- Static doc content -->
          <div v-else-if="activeStaticDoc" class="max-w-3xl">
            <div
              v-html="renderMarkdown(activeStaticDoc.content)"
              class="prose-doc"
            />
          </div>

          <!-- App group content -->
          <div v-else-if="activeGroup" class="max-w-3xl">
            <!-- Group header -->
            <div class="mb-6">
              <div class="flex items-center gap-3 mb-1">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-neutral-100 dark:bg-neutral-800">
                  <Icon
                    :name="activeGroup.logo || activeGroup.icon"
                    :class="[activeGroup.logo ? 'w-5 h-5' : 'w-4 h-4 text-neutral-600 dark:text-neutral-300']"
                  />
                </div>
                <div>
                  <h2 class="text-lg font-semibold text-neutral-900 dark:text-white capitalize">{{ activeGroup.name.replace(/_/g, ' ') }}</h2>
                  <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ activeGroup.description }}</p>
                </div>
              </div>
              <div class="flex items-center gap-3 mt-2 ml-11 text-xs text-neutral-400 dark:text-neutral-500">
                <span v-if="activeGroup.luaNamespace" class="font-mono">{{ activeGroup.luaNamespace }}.*</span>
                <span>{{ filteredTools.length }} {{ filteredTools.length === 1 ? 'tool' : 'tools' }}</span>
                <span
                  v-if="activeGroup.isIntegration && activeGroup.enabled === false"
                  class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500"
                >
                  not enabled
                </span>
              </div>
            </div>

            <!-- Search empty state within group -->
            <div v-if="searchQuery && filteredTools.length === 0" class="text-center py-12">
              <p class="text-sm text-neutral-500 dark:text-neutral-400">No tools match "{{ searchQuery }}" in this namespace</p>
            </div>

            <!-- Tool cards -->
            <div class="space-y-3">
              <div
                v-for="tool in filteredTools"
                :key="tool.slug"
                class="bg-white dark:bg-neutral-800/50 rounded-lg border border-neutral-200 dark:border-neutral-700 overflow-hidden"
              >
                <div class="px-4 py-3">
                  <!-- Slug + type badge -->
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

                  <!-- Name + description -->
                  <p class="text-sm font-medium text-neutral-900 dark:text-white mt-1.5">{{ tool.name }}</p>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{{ tool.description }}</p>

                  <!-- Lua function signature -->
                  <div v-if="tool.luaFunction && activeGroup.luaNamespace" class="mt-2">
                    <code
                      v-html="highlightSignature(buildLuaSignature(tool, activeGroup.luaNamespace))"
                      class="text-xs font-mono bg-neutral-50 dark:bg-neutral-900/50 border border-neutral-200 dark:border-neutral-700/50 px-2 py-1 rounded inline-block lua-sig"
                    />
                  </div>

                  <!-- Full description (when different from short) -->
                  <div
                    v-if="hasFullDescription(tool)"
                    v-html="renderMarkdown(tool.fullDescription!)"
                    class="tool-description mt-2 text-xs text-neutral-600 dark:text-neutral-300"
                  />

                  <!-- Parameters -->
                  <div v-if="tool.parameters?.length" class="mt-3">
                    <p class="text-xs font-medium text-neutral-500 dark:text-neutral-400 mb-1.5">Parameters</p>
                    <div class="bg-neutral-50 dark:bg-neutral-900/50 rounded-md border border-neutral-100 dark:border-neutral-700/50 divide-y divide-neutral-100 dark:divide-neutral-800">
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
                  <p v-else class="mt-2 text-[10px] text-neutral-400 dark:text-neutral-500 italic">No parameters</p>
                </div>
              </div>
            </div>

            <!-- Supplementary Lua docs -->
            <div v-if="activeGroup.luaDocs" class="mt-8 pt-6 border-t border-neutral-200 dark:border-neutral-700">
              <h3 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300 mb-3">Supplementary Documentation</h3>
              <div
                v-html="renderMarkdown(activeGroup.luaDocs)"
                class="prose-doc"
              />
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
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
  luaFunction?: string
}

interface AppGroup {
  name: string
  description: string
  icon: string
  logo?: string
  isIntegration: boolean
  enabled?: boolean
  tools: ToolEntry[]
  luaNamespace?: string
  luaDocs?: string
}

interface StaticDoc {
  slug: string
  title: string
  content: string
}

interface SidebarItem {
  id: string
  label: string
  icon: string
  logo?: string
  toolCount?: number
  enabled?: boolean
  section: 'guide' | 'builtin' | 'integration' | 'mcp'
}

const loading = ref(true)
const groups = ref<AppGroup[]>([])
const staticDocs = ref<StaticDoc[]>([])
const searchQuery = ref('')
const activeItem = ref<string | null>(null)
const showAllIntegrations = ref(false)

// Sidebar items derived from data
const sidebarItems = computed<SidebarItem[]>(() => {
  const items: SidebarItem[] = []

  // Static docs
  for (const doc of staticDocs.value) {
    items.push({
      id: `doc:${doc.slug}`,
      label: doc.title,
      icon: 'ph:book-open-text',
      section: 'guide',
    })
  }

  // App groups
  for (const group of groups.value) {
    const isMcp = group.name.startsWith('mcp_') || group.luaNamespace?.startsWith('app.mcp.')
    const section = isMcp ? 'mcp' : group.isIntegration ? 'integration' : 'builtin'

    items.push({
      id: `group:${group.name}`,
      label: group.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
      icon: group.icon,
      logo: group.logo,
      toolCount: group.tools.length,
      enabled: group.enabled,
      section,
    })
  }

  return items
})

const guideItems = computed(() => sidebarItems.value.filter(i => i.section === 'guide'))
const builtInItems = computed(() => sidebarItems.value.filter(i => i.section === 'builtin'))
const allIntegrationItems = computed(() => sidebarItems.value.filter(i => i.section === 'integration'))
const integrationItems = computed(() => {
  if (showAllIntegrations.value) return allIntegrationItems.value
  return allIntegrationItems.value.filter(i => i.enabled !== false)
})
const mcpItems = computed(() => sidebarItems.value.filter(i => i.section === 'mcp'))

// Mobile: all visible items respecting the integration toggle
const visibleSidebarItems = computed(() => {
  return sidebarItems.value.filter(i => {
    if (i.section !== 'integration') return true
    if (showAllIntegrations.value) return true
    return i.enabled !== false
  })
})

// Active content
const activeStaticDoc = computed(() => {
  if (!activeItem.value?.startsWith('doc:')) return null
  const slug = activeItem.value.slice(4)
  return staticDocs.value.find(d => d.slug === slug) || null
})

const activeGroup = computed(() => {
  if (!activeItem.value?.startsWith('group:')) return null
  const name = activeItem.value.slice(6)
  return groups.value.find(g => g.name === name) || null
})

// Filtered tools within the active group (for search)
const filteredTools = computed(() => {
  if (!activeGroup.value) return []
  if (!searchQuery.value) return activeGroup.value.tools

  const q = searchQuery.value.toLowerCase()
  return activeGroup.value.tools.filter(
    t => t.slug.includes(q)
      || t.name.toLowerCase().includes(q)
      || t.description.toLowerCase().includes(q)
      || (t.fullDescription && t.fullDescription.toLowerCase().includes(q))
      || (t.luaFunction && t.luaFunction.toLowerCase().includes(q))
  )
})

// When searching, auto-select first group that has matches
watch(searchQuery, (q) => {
  if (!q) return

  const qLower = q.toLowerCase()

  // Check if current active group has matches
  if (activeGroup.value) {
    const hasMatches = activeGroup.value.tools.some(
      t => t.slug.includes(qLower)
        || t.name.toLowerCase().includes(qLower)
        || t.description.toLowerCase().includes(qLower)
        || (t.fullDescription && t.fullDescription.toLowerCase().includes(qLower))
    )
    if (hasMatches) return
  }

  // Find first group with matches
  for (const group of groups.value) {
    const hasMatches = group.tools.some(
      t => t.slug.includes(qLower)
        || t.name.toLowerCase().includes(qLower)
        || t.description.toLowerCase().includes(qLower)
        || (t.fullDescription && t.fullDescription.toLowerCase().includes(qLower))
    )
    if (hasMatches) {
      activeItem.value = `group:${group.name}`
      return
    }
  }
})

const hasFullDescription = (tool: ToolEntry) => {
  return tool.fullDescription && tool.fullDescription !== tool.description
}

const formatType = (type: string | string[]) => {
  if (Array.isArray(type)) return type.join(' | ')
  return type
}

const buildLuaSignature = (tool: ToolEntry, namespace: string) => {
  if (!tool.luaFunction) return ''
  const params = (tool.parameters || []).map(p => p.required ? p.name : p.name + '?')
  return `${namespace}.${tool.luaFunction}(${params.join(', ')})`
}

const highlightSignature = (sig: string): string => {
  const match = sig.match(/^(.+)\.(\w+)\(([^)]*)\)$/)
  if (!match) return sig

  const [, ns, fn, paramsStr] = match

  const nsHtml = ns.split('.').map(s =>
    `<span class="sig-ns">${s}</span>`
  ).join('<span class="sig-dot">.</span>')

  const paramsHtml = paramsStr
    ? paramsStr.split(/,\s*/).map(p => {
        const optional = p.endsWith('?')
        const name = optional ? p.slice(0, -1) : p
        return optional
          ? `<span class="sig-param sig-optional">${name}</span><span class="sig-opt-mark">?</span>`
          : `<span class="sig-param">${name}</span>`
      }).join('<span class="sig-comma">, </span>')
    : ''

  return `${nsHtml}<span class="sig-dot">.</span><span class="sig-fn">${fn}</span><span class="sig-paren">(</span>${paramsHtml}<span class="sig-paren">)</span>`
}

const sidebarButtonClass = (id: string, dimmed = false) => [
  'flex items-center gap-2 px-2 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
  activeItem.value === id
    ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
    : dimmed
      ? 'text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800'
      : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
]

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/tools/catalog')

    // Handle both old (array) and new (object) response shapes
    if (Array.isArray(data)) {
      groups.value = data
      staticDocs.value = []
    } else {
      groups.value = data.groups || []
      staticDocs.value = data.staticDocs || []
    }

    // Auto-select first guide or first group
    if (staticDocs.value.length > 0) {
      activeItem.value = `doc:${staticDocs.value[0].slug}`
    } else if (groups.value.length > 0) {
      activeItem.value = `group:${groups.value[0].name}`
    }
  } catch (err) {
    console.error('Failed to load tool catalog:', err)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
/* Prose styling for full markdown docs */
.prose-doc :deep(h1) {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-neutral-900);
  margin: 0 0 0.75rem;
}
:is(.dark) .prose-doc :deep(h1) {
  color: white;
}
.prose-doc :deep(h2) {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-neutral-800);
  margin: 1.5rem 0 0.5rem;
}
:is(.dark) .prose-doc :deep(h2) {
  color: var(--color-neutral-100);
}
.prose-doc :deep(h3) {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-neutral-700);
  margin: 1.25rem 0 0.375rem;
}
:is(.dark) .prose-doc :deep(h3) {
  color: var(--color-neutral-200);
}
.prose-doc :deep(p) {
  font-size: 0.8125rem;
  line-height: 1.6;
  color: var(--color-neutral-600);
  margin: 0.5rem 0;
}
:is(.dark) .prose-doc :deep(p) {
  color: var(--color-neutral-300);
}
.prose-doc :deep(ul),
.prose-doc :deep(ol) {
  margin: 0.5rem 0;
  padding-left: 1.5rem;
}
.prose-doc :deep(li) {
  font-size: 0.8125rem;
  line-height: 1.6;
  color: var(--color-neutral-600);
  margin: 0.25rem 0;
}
:is(.dark) .prose-doc :deep(li) {
  color: var(--color-neutral-300);
}
.prose-doc :deep(table) {
  width: 100%;
  font-size: 0.8125rem;
  border-collapse: collapse;
  margin: 0.75rem 0;
}
.prose-doc :deep(th),
.prose-doc :deep(td) {
  text-align: left;
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--color-neutral-200);
}
:is(.dark) .prose-doc :deep(th),
:is(.dark) .prose-doc :deep(td) {
  border-color: var(--color-neutral-700);
}
.prose-doc :deep(th) {
  font-weight: 600;
  background: var(--color-neutral-50);
  color: var(--color-neutral-700);
}
:is(.dark) .prose-doc :deep(th) {
  background: var(--color-neutral-800);
  color: var(--color-neutral-200);
}
.prose-doc :deep(hr) {
  border: none;
  border-top: 1px solid var(--color-neutral-200);
  margin: 1.5rem 0;
}
:is(.dark) .prose-doc :deep(hr) {
  border-color: var(--color-neutral-700);
}
.prose-doc :deep(strong) {
  font-weight: 600;
  color: var(--color-neutral-700);
}
:is(.dark) .prose-doc :deep(strong) {
  color: var(--color-neutral-200);
}

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

/* Lua signature syntax highlighting (v-html needs :deep) */
.lua-sig :deep(.sig-ns)       { color: var(--color-neutral-500); }
.lua-sig :deep(.sig-dot)      { color: var(--color-neutral-400); }
.lua-sig :deep(.sig-fn)       { color: #16a34a; font-weight: 600; }
.lua-sig :deep(.sig-paren)    { color: var(--color-neutral-500); }
.lua-sig :deep(.sig-param)    { color: #2563eb; }
.lua-sig :deep(.sig-optional) { color: #a855f7; }
.lua-sig :deep(.sig-opt-mark) { color: #a855f7; }
.lua-sig :deep(.sig-comma)    { color: var(--color-neutral-400); }

:is(.dark) .lua-sig :deep(.sig-ns)       { color: var(--color-neutral-400); }
:is(.dark) .lua-sig :deep(.sig-dot)      { color: var(--color-neutral-500); }
:is(.dark) .lua-sig :deep(.sig-fn)       { color: #4ade80; font-weight: 600; }
:is(.dark) .lua-sig :deep(.sig-paren)    { color: var(--color-neutral-400); }
:is(.dark) .lua-sig :deep(.sig-param)    { color: #60a5fa; }
:is(.dark) .lua-sig :deep(.sig-optional) { color: #c084fc; }
:is(.dark) .lua-sig :deep(.sig-opt-mark) { color: #c084fc; }
:is(.dark) .lua-sig :deep(.sig-comma)    { color: var(--color-neutral-500); }
</style>
