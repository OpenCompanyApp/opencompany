<template>
  <SettingsSection title="System Health" icon="ph:bug">
    <template #actions>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
          :disabled="debugLoading"
          @click="loadDebugData"
        >
          <Icon name="ph:arrow-clockwise" :class="['w-3.5 h-3.5', debugLoading && 'animate-spin']" />
          Refresh
        </button>
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
          :disabled="!debugData"
          @click="copyDebugMarkdown"
        >
          <Icon :name="debugCopied ? 'ph:check' : 'ph:copy'" class="w-3.5 h-3.5" />
          {{ debugCopied ? 'Copied' : 'Copy as Markdown' }}
        </button>
      </div>
    </template>

    <!-- Loading -->
    <div v-if="debugLoading && !debugData" class="flex items-center justify-center py-12">
      <Icon name="ph:spinner" class="w-5 h-5 text-neutral-400 animate-spin" />
    </div>

    <template v-else-if="debugData">
      <!-- Health Overview -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mb-6">
        <div
          v-for="card in healthCards"
          :key="card.label"
          class="flex items-center gap-2.5 p-3 rounded-lg border"
          :class="healthCardClass(card.status)"
        >
          <div class="w-2 h-2 rounded-full shrink-0" :class="healthDotClass(card.status)" />
          <div class="min-w-0">
            <p class="text-xs font-medium text-neutral-900 dark:text-white truncate">{{ card.label }}</p>
            <p class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">{{ card.detail }}</p>
          </div>
        </div>
      </div>

      <!-- Compaction -->
      <details class="group mb-3" open>
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Compaction
        </summary>
        <div class="pl-5.5 pb-3">
          <div class="grid grid-cols-3 gap-3 text-xs">
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Summaries</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.compaction.summary_count }}</p>
            </div>
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Latest</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ formatRelative(debugData.compaction.latest_at) }}</p>
            </div>
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Avg Compression</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.compaction.avg_compression_pct != null ? debugData.compaction.avg_compression_pct + '%' : 'N/A' }}</p>
            </div>
          </div>
        </div>
      </details>

      <!-- Embedding -->
      <details class="group mb-3">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Embedding
        </summary>
        <div class="pl-5.5 pb-3">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Total Chunks</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.embedding.total_chunks }}</p>
            </div>
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Indexed</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.embedding.indexed_chunks }}</p>
            </div>
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Unindexed</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.embedding.unindexed_chunks }}</p>
            </div>
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Cache Entries</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.embedding.cache_entries }}</p>
            </div>
          </div>
        </div>
      </details>

      <!-- Queue -->
      <details class="group mb-3">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Queue
        </summary>
        <div class="pl-5.5 pb-3">
          <div class="grid grid-cols-2 gap-3 text-xs mb-3">
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Pending Jobs</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.queue.pending_jobs }}</p>
            </div>
            <div>
              <span class="text-neutral-400 dark:text-neutral-500">Failed Jobs</span>
              <p class="font-mono text-neutral-900 dark:text-white">{{ debugData.queue.failed_jobs }}</p>
            </div>
          </div>
          <div v-if="debugData.queue.recent_failures?.length" class="space-y-1.5">
            <p class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider">Recent Failures</p>
            <div
              v-for="failure in debugData.queue.recent_failures"
              :key="failure.uuid"
              class="p-2 rounded-md bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/30 text-xs"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="font-medium text-red-700 dark:text-red-400">{{ failure.job }}</span>
                <span class="text-red-500/70 dark:text-red-400/60 text-[11px]">{{ failure.failed_at }}</span>
              </div>
              <p class="text-red-600/80 dark:text-red-300/60 font-mono text-[11px] break-all line-clamp-3">{{ failure.exception }}</p>
            </div>
          </div>
        </div>
      </details>

      <!-- Automations -->
      <details class="group mb-3">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Automations
          <span class="text-[11px] text-neutral-400 dark:text-neutral-500 font-normal">{{ debugData.automations.active }}/{{ debugData.automations.total }} active</span>
        </summary>
        <div class="pl-5.5 pb-3">
          <div v-if="debugData.automations.items?.length" class="space-y-1.5">
            <div
              v-for="auto in debugData.automations.items"
              :key="auto.name"
              class="flex items-center gap-2 p-2 rounded-md text-xs"
              :class="auto.consecutive_failures > 0
                ? 'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/30'
                : 'bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700'"
            >
              <div class="w-1.5 h-1.5 rounded-full shrink-0" :class="auto.is_active ? (auto.consecutive_failures > 0 ? 'bg-red-500' : 'bg-green-500') : 'bg-neutral-300 dark:bg-neutral-600'" />
              <span class="font-medium text-neutral-900 dark:text-white flex-1 truncate">{{ auto.name }}</span>
              <span v-if="auto.consecutive_failures > 0" class="text-red-600 dark:text-red-400 text-[11px]">{{ auto.consecutive_failures }} failures</span>
              <span class="text-neutral-400 dark:text-neutral-500 text-[11px] shrink-0">{{ formatRelative(auto.last_run_at) }}</span>
            </div>
          </div>
          <p v-else class="text-xs text-neutral-400 dark:text-neutral-500">No automations configured</p>
        </div>
      </details>

      <!-- MCP Servers -->
      <details class="group mb-3">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          MCP Servers
          <span class="text-[11px] text-neutral-400 dark:text-neutral-500 font-normal">{{ debugData.mcp_servers.enabled }}/{{ debugData.mcp_servers.total }} enabled</span>
        </summary>
        <div class="pl-5.5 pb-3">
          <div v-if="debugData.mcp_servers.items?.length" class="space-y-1.5">
            <div
              v-for="server in debugData.mcp_servers.items"
              :key="server.name"
              class="flex items-center gap-2 p-2 rounded-md bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs"
            >
              <div class="w-1.5 h-1.5 rounded-full shrink-0" :class="server.enabled ? (server.is_stale ? 'bg-amber-500' : 'bg-green-500') : 'bg-neutral-300 dark:bg-neutral-600'" />
              <span class="font-medium text-neutral-900 dark:text-white flex-1 truncate">{{ server.name }}</span>
              <span class="text-neutral-400 dark:text-neutral-500 text-[11px]">{{ server.tool_count }} tools</span>
              <span v-if="server.is_stale" class="text-amber-600 dark:text-amber-400 text-[11px]">stale</span>
            </div>
          </div>
          <p v-else class="text-xs text-neutral-400 dark:text-neutral-500">No MCP servers configured</p>
        </div>
      </details>

      <!-- Agents -->
      <details class="group mb-3">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Agents
        </summary>
        <div class="pl-5.5 pb-3">
          <div v-if="debugData.agents.items?.length" class="space-y-1.5">
            <div
              v-for="agent in debugData.agents.items"
              :key="agent.name"
              class="flex items-center gap-2 p-2 rounded-md bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-xs"
            >
              <div class="w-1.5 h-1.5 rounded-full shrink-0" :class="agent.status === 'working' ? 'bg-green-500' : agent.status === 'idle' ? 'bg-neutral-400' : 'bg-amber-500'" />
              <span class="font-medium text-neutral-900 dark:text-white flex-1 truncate">{{ agent.name }}</span>
              <span class="text-neutral-400 dark:text-neutral-500 text-[11px]">{{ agent.status }}</span>
            </div>
          </div>
          <p v-else class="text-xs text-neutral-400 dark:text-neutral-500">No agents</p>
        </div>
      </details>

      <!-- Recent Errors -->
      <details class="group" :open="debugData.logs?.length > 0">
        <summary class="flex items-center gap-2 cursor-pointer text-sm font-medium text-neutral-900 dark:text-white py-2 select-none">
          <Icon name="ph:caret-right" class="w-3.5 h-3.5 transition-transform group-open:rotate-90" />
          Recent Errors
          <span v-if="debugData.logs?.length" class="text-[11px] text-red-500 dark:text-red-400 font-normal">{{ debugData.logs.length }}</span>
        </summary>
        <div class="pl-5.5 pb-3">
          <div v-if="debugData.logs?.length" class="space-y-1.5 max-h-80 overflow-y-auto">
            <div
              v-for="(log, i) in debugData.logs"
              :key="i"
              class="p-2 rounded-md text-xs border"
              :class="log.level === 'ERROR' || log.level === 'CRITICAL' || log.level === 'EMERGENCY'
                ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800/30'
                : 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/30'"
            >
              <div class="flex items-center gap-2 mb-1">
                <span
                  class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider"
                  :class="log.level === 'ERROR' || log.level === 'CRITICAL' || log.level === 'EMERGENCY'
                    ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                    : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'"
                >{{ log.level }}</span>
                <span class="text-neutral-400 dark:text-neutral-500 text-[11px]">{{ log.timestamp }}</span>
              </div>
              <p class="text-neutral-700 dark:text-neutral-300 font-mono text-[11px] break-all line-clamp-4">{{ log.message }}</p>
            </div>
          </div>
          <p v-else class="text-xs text-neutral-400 dark:text-neutral-500">No recent errors</p>
        </div>
      </details>
    </template>

    <!-- Empty state -->
    <div v-else class="py-8 text-center">
      <Icon name="ph:bug" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
      <p class="text-sm text-neutral-500 dark:text-neutral-400">Failed to load debug data</p>
      <button
        type="button"
        class="mt-2 text-xs text-neutral-600 dark:text-neutral-400 underline hover:no-underline"
        @click="loadDebugData"
      >
        Try again
      </button>
    </div>
  </SettingsSection>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useApi } from '@/composables/useApi'

const { fetchDebugInfo } = useApi()
const debugData = ref<any>(null)
const debugLoading = ref(false)
const debugCopied = ref(false)

async function loadDebugData() {
  debugLoading.value = true
  try {
    const result = fetchDebugInfo()
    await result.promise
    debugData.value = result.data.value
  } catch (e) {
    console.error('Failed to load debug data:', e)
  } finally {
    debugLoading.value = false
  }
}

onMounted(() => {
  loadDebugData()
})

function formatRelative(dateStr: string | null): string {
  if (!dateStr) return 'Never'
  const date = new Date(dateStr)
  const diff = Date.now() - date.getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

const healthCards = computed(() => {
  if (!debugData.value) return []
  const d = debugData.value
  const cards = []

  // Compaction
  const compLatest = d.compaction.latest_at ? new Date(d.compaction.latest_at) : null
  const compAge = compLatest ? (Date.now() - compLatest.getTime()) / 3600000 : Infinity
  cards.push({
    label: 'Compaction',
    detail: d.compaction.summary_count > 0 ? `${d.compaction.summary_count} summaries` : 'No summaries',
    status: d.compaction.summary_count === 0 ? 'error' : compAge > 24 ? 'warning' : 'healthy',
  })

  // Embedding
  const embTotal = d.embedding.total_chunks
  const embIndexed = d.embedding.indexed_chunks
  cards.push({
    label: 'Embedding',
    detail: embTotal > 0 ? `${embIndexed}/${embTotal} indexed` : 'No chunks',
    status: embTotal === 0 ? 'neutral' : embIndexed === embTotal ? 'healthy' : embIndexed === 0 ? 'error' : 'warning',
  })

  // Queue
  cards.push({
    label: 'Queue',
    detail: d.queue.failed_jobs > 0 ? `${d.queue.failed_jobs} failed` : `${d.queue.pending_jobs} pending`,
    status: d.queue.failed_jobs >= 5 ? 'error' : d.queue.failed_jobs > 0 ? 'warning' : 'healthy',
  })

  // Automations
  cards.push({
    label: 'Automations',
    detail: `${d.automations.active}/${d.automations.total} active`,
    status: d.automations.failing >= 3 ? 'error' : d.automations.failing > 0 ? 'warning' : 'healthy',
  })

  // MCP Servers
  cards.push({
    label: 'MCP Servers',
    detail: `${d.mcp_servers.enabled}/${d.mcp_servers.total} enabled`,
    status: d.mcp_servers.stale > 0 ? 'warning' : d.mcp_servers.total === 0 ? 'neutral' : 'healthy',
  })

  return cards
})

function healthCardClass(status: string): string {
  if (status === 'error') return 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800/30'
  if (status === 'warning') return 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/30'
  if (status === 'healthy') return 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800/30'
  return 'bg-neutral-50 dark:bg-neutral-800 border-neutral-200 dark:border-neutral-700'
}

function healthDotClass(status: string): string {
  if (status === 'error') return 'bg-red-500'
  if (status === 'warning') return 'bg-amber-500'
  if (status === 'healthy') return 'bg-green-500'
  return 'bg-neutral-300 dark:bg-neutral-600'
}

function copyDebugMarkdown() {
  if (!debugData.value) return
  const d = debugData.value
  const now = new Date().toISOString().replace('T', ' ').slice(0, 16)

  let md = `# System Debug Report\nGenerated: ${now} UTC\n\n`

  md += `## Compaction\n`
  md += `- Summaries: ${d.compaction.summary_count}\n`
  md += `- Latest: ${formatRelative(d.compaction.latest_at)}\n`
  md += `- Avg compression: ${d.compaction.avg_compression_pct != null ? d.compaction.avg_compression_pct + '%' : 'N/A'}\n\n`

  md += `## Embedding\n`
  md += `- Total chunks: ${d.embedding.total_chunks}\n`
  md += `- Indexed: ${d.embedding.indexed_chunks}\n`
  md += `- Unindexed: ${d.embedding.unindexed_chunks}\n`
  md += `- Cache entries: ${d.embedding.cache_entries}\n\n`

  md += `## Queue\n`
  md += `- Pending: ${d.queue.pending_jobs}\n`
  md += `- Failed: ${d.queue.failed_jobs}\n`
  if (d.queue.recent_failures?.length) {
    md += `\n### Recent Failures\n`
    for (const f of d.queue.recent_failures) {
      md += `- **${f.job}** (${f.failed_at}): ${f.exception?.slice(0, 200)}\n`
    }
  }
  md += '\n'

  md += `## Automations\n`
  md += `- Total: ${d.automations.total}, Active: ${d.automations.active}, Failing: ${d.automations.failing}\n`
  if (d.automations.items?.length) {
    md += `\n| Name | Active | Failures | Last Run |\n|------|--------|----------|----------|\n`
    for (const a of d.automations.items) {
      md += `| ${a.name} | ${a.is_active ? 'Yes' : 'No'} | ${a.consecutive_failures} | ${formatRelative(a.last_run_at)} |\n`
    }
  }
  md += '\n'

  md += `## MCP Servers\n`
  md += `- Total: ${d.mcp_servers.total}, Enabled: ${d.mcp_servers.enabled}, Stale: ${d.mcp_servers.stale}\n`
  if (d.mcp_servers.items?.length) {
    md += `\n| Name | Enabled | Tools | Stale |\n|------|---------|-------|-------|\n`
    for (const s of d.mcp_servers.items) {
      md += `| ${s.name} | ${s.enabled ? 'Yes' : 'No'} | ${s.tool_count} | ${s.is_stale ? 'Yes' : 'No'} |\n`
    }
  }
  md += '\n'

  md += `## Agents\n`
  if (d.agents.items?.length) {
    md += `| Name | Status |\n|------|--------|\n`
    for (const a of d.agents.items) {
      md += `| ${a.name} | ${a.status} |\n`
    }
  }
  md += '\n'

  if (d.logs?.length) {
    md += `## Recent Errors\n`
    md += `| Time | Level | Message |\n|------|-------|--------|\n`
    for (const log of d.logs) {
      const msg = log.message?.slice(0, 100)?.replace(/\|/g, '\\|')?.replace(/\n/g, ' ') ?? ''
      md += `| ${log.timestamp} | ${log.level} | ${msg} |\n`
    }
  }

  navigator.clipboard.writeText(md).then(() => {
    debugCopied.value = true
    setTimeout(() => { debugCopied.value = false }, 2000)
  })
}
</script>
