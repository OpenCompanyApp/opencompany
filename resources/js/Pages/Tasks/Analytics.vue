<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <header class="shrink-0 px-6 py-4 border-b border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-1">
          <Link
            :href="workspacePath('/tasks')"
            class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
          >
            Tasks
          </Link>
          <Link
            :href="workspacePath('/workload')"
            class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
          >
            Workload
          </Link>
          <Link
            :href="workspacePath('/activity')"
            class="px-2 py-1 text-sm font-medium text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
          >
            Activity
          </Link>
          <span class="text-xl font-semibold text-neutral-900 dark:text-white">Analytics</span>
        </div>
        <div class="flex items-center gap-3">
          <!-- Period selector -->
          <div class="flex bg-neutral-100 dark:bg-neutral-800 rounded-lg p-0.5">
            <button
              v-for="p in periods"
              :key="p.value"
              class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
              :class="period === p.value
                ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
                : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300'"
              @click="setPeriod(p.value)"
            >
              {{ p.label }}
            </button>
          </div>
          <button
            class="p-2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg transition-colors"
            title="Refresh"
            @click="refresh"
          >
            <Icon name="ph:arrows-clockwise" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </header>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <Icon name="ph:circle-notch" class="w-6 h-6 text-neutral-400 animate-spin" />
      </div>

      <template v-else-if="data">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-neutral-100 dark:bg-neutral-700">
                <Icon name="ph:coins" class="w-5 h-5 text-neutral-600 dark:text-neutral-300" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ fmtTokens(totalTokens) }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Total Tokens</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-green-50 dark:bg-green-900/30">
                <Icon name="ph:currency-dollar" class="w-5 h-5 text-green-600 dark:text-green-400" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ totalCostFormatted }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Estimated Cost</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/30">
                <Icon name="ph:lightning" class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ data.summary.avg_tokens_per_second || '---' }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Avg Tokens/sec</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30">
                <Icon name="ph:briefcase" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ data.summary.total_tasks }}</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Total Tasks</p>
              </div>
            </div>
          </div>

          <div class="p-4 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-purple-50 dark:bg-purple-900/30">
                <Icon name="ph:database" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
              </div>
              <div>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white tabular-nums">{{ cacheHitRate }}%</p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Cache Hit Rate</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Token Usage Over Time -->
        <div v-if="data.timeSeries.length > 0" class="mb-8 p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
          <h3 class="text-sm font-semibold text-neutral-900 dark:text-white mb-4">Token Usage Over Time</h3>
          <div ref="chartContainer" class="relative h-48">
            <svg :width="chartWidth" height="192" class="w-full">
              <g v-for="(bar, i) in chartBars" :key="i">
                <!-- Prompt tokens (bottom) -->
                <rect
                  :x="bar.x"
                  :y="bar.promptY"
                  :width="bar.width"
                  :height="bar.promptH"
                  class="fill-blue-400 dark:fill-blue-500"
                  rx="1"
                  @mouseenter="hoveredBar = i"
                  @mouseleave="hoveredBar = null"
                />
                <!-- Completion tokens (top) -->
                <rect
                  :x="bar.x"
                  :y="bar.completionY"
                  :width="bar.width"
                  :height="bar.completionH"
                  class="fill-emerald-400 dark:fill-emerald-500"
                  rx="1"
                  @mouseenter="hoveredBar = i"
                  @mouseleave="hoveredBar = null"
                />
              </g>
              <!-- X axis labels (show every Nth) -->
              <text
                v-for="(label, i) in chartXLabels"
                :key="'label-' + i"
                :x="label.x"
                y="190"
                text-anchor="middle"
                class="fill-neutral-400 dark:fill-neutral-500 text-[9px]"
              >
                {{ label.text }}
              </text>
            </svg>
            <!-- Tooltip -->
            <div
              v-if="hoveredBar !== null && chartBars[hoveredBar]"
              class="absolute top-0 left-1/2 -translate-x-1/2 px-3 py-2 bg-neutral-900 dark:bg-neutral-700 text-white text-xs rounded-lg shadow-lg pointer-events-none z-10"
            >
              <div class="font-medium">{{ data.timeSeries[hoveredBar].date }}</div>
              <div class="flex items-center gap-2 mt-1">
                <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                Input: {{ Number(data.timeSeries[hoveredBar].prompt_tokens).toLocaleString() }}
              </div>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                Output: {{ Number(data.timeSeries[hoveredBar].completion_tokens).toLocaleString() }}
              </div>
              <div class="text-neutral-300 mt-1">{{ data.timeSeries[hoveredBar].task_count }} tasks</div>
            </div>
          </div>
          <!-- Legend -->
          <div class="flex items-center gap-4 mt-3 text-xs text-neutral-500 dark:text-neutral-400">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-blue-400 dark:bg-blue-500"></span> Input</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-400 dark:bg-emerald-500"></span> Output</span>
          </div>
        </div>

        <!-- Two-column grid: By Agent + By Model -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <!-- By Agent -->
          <div class="p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white mb-4">By Agent</h3>
            <div v-if="data.byAgent.length === 0" class="text-sm text-neutral-400 py-4 text-center">No data</div>
            <div v-else class="space-y-3">
              <div v-for="agent in data.byAgent" :key="agent.agent_id" class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-xs font-medium text-neutral-600 dark:text-neutral-300 shrink-0 overflow-hidden">
                  <img v-if="agent.agent_avatar" :src="agent.agent_avatar" class="w-full h-full object-cover" />
                  <template v-else>{{ agent.agent_name?.[0] || '?' }}</template>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ agent.agent_name }}</span>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums ml-2 shrink-0">{{ fmtTokens(Number(agent.prompt_tokens) + Number(agent.completion_tokens)) }}</span>
                  </div>
                  <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-1.5">
                    <div
                      class="h-1.5 rounded-full bg-blue-500 dark:bg-blue-400"
                      :style="{ width: agentBarWidth(agent) }"
                    ></div>
                  </div>
                </div>
                <div class="text-right shrink-0">
                  <div class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">{{ agent.task_count }} tasks</div>
                  <div v-if="agent.avg_tokens_per_second" class="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums">{{ agent.avg_tokens_per_second }} tok/s</div>
                </div>
              </div>
            </div>
          </div>

          <!-- By Model -->
          <div class="p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white mb-4">By Model</h3>
            <div v-if="data.byModel.length === 0" class="text-sm text-neutral-400 py-4 text-center">No data</div>
            <div v-else class="space-y-3">
              <div v-for="model in data.byModel" :key="model.model" class="flex items-center gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-sm font-medium text-neutral-900 dark:text-white truncate">{{ shortModelName(model.model) }}</span>
                      <span v-if="model.provider" class="shrink-0 px-1.5 py-0.5 text-[10px] font-medium rounded bg-neutral-100 dark:bg-neutral-700 text-neutral-500 dark:text-neutral-400">{{ model.provider }}</span>
                    </div>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums ml-2 shrink-0">{{ fmtTokens(Number(model.prompt_tokens) + Number(model.completion_tokens)) }}</span>
                  </div>
                  <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-1.5">
                    <div
                      class="h-1.5 rounded-full bg-purple-500 dark:bg-purple-400"
                      :style="{ width: modelBarWidth(model) }"
                    ></div>
                  </div>
                </div>
                <div class="text-right shrink-0">
                  <div class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">{{ modelCost(model) }}</div>
                  <div class="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums">{{ model.task_count }} tasks</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom row: By Source + Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- By Source -->
          <div class="p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white mb-4">By Source</h3>
            <div v-if="data.bySource.length === 0" class="text-sm text-neutral-400 py-4 text-center">No data</div>
            <div v-else class="space-y-3">
              <div v-for="source in data.bySource" :key="source.source" class="flex items-center gap-3">
                <div class="p-1.5 rounded-md bg-neutral-100 dark:bg-neutral-700 shrink-0">
                  <Icon :name="sourceIcon(source.source)" class="w-4 h-4 text-neutral-500 dark:text-neutral-400" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-neutral-900 dark:text-white">{{ sourceLabel(source.source) }}</span>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">{{ fmtTokens(Number(source.prompt_tokens) + Number(source.completion_tokens)) }}</span>
                  </div>
                  <div class="w-full bg-neutral-100 dark:bg-neutral-700 rounded-full h-1.5">
                    <div
                      class="h-1.5 rounded-full bg-amber-500 dark:bg-amber-400"
                      :style="{ width: sourceBarWidth(source) }"
                    ></div>
                  </div>
                </div>
                <span class="text-xs text-neutral-400 dark:text-neutral-500 tabular-nums shrink-0">{{ source.task_count }} tasks</span>
              </div>
            </div>
          </div>

          <!-- Performance by Model -->
          <div class="p-5 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <h3 class="text-sm font-semibold text-neutral-900 dark:text-white mb-4">Performance</h3>
            <div v-if="data.byModel.length === 0" class="text-sm text-neutral-400 py-4 text-center">No data</div>
            <table v-else class="w-full text-sm">
              <thead>
                <tr class="text-xs text-neutral-500 dark:text-neutral-400">
                  <th class="text-left font-medium pb-2">Model</th>
                  <th class="text-right font-medium pb-2">Tok/s</th>
                  <th class="text-right font-medium pb-2">Avg Time</th>
                  <th class="text-right font-medium pb-2">Tasks</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="model in data.byModel" :key="'perf-' + model.model" class="border-t border-neutral-100 dark:border-neutral-700/50">
                  <td class="py-2 text-neutral-900 dark:text-white truncate max-w-[200px]">{{ shortModelName(model.model) }}</td>
                  <td class="py-2 text-right tabular-nums">
                    <span :class="speedColor(model.avg_tokens_per_second)">{{ model.avg_tokens_per_second || '---' }}</span>
                  </td>
                  <td class="py-2 text-right text-neutral-500 dark:text-neutral-400 tabular-nums">{{ formatDuration(model.avg_generation_time_ms) }}</td>
                  <td class="py-2 text-right text-neutral-500 dark:text-neutral-400 tabular-nums">{{ model.task_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <!-- Empty state -->
      <div v-else class="flex flex-col items-center justify-center py-20 text-neutral-400">
        <Icon name="ph:chart-bar" class="w-12 h-12 mb-3" />
        <p class="text-sm">No analytics data available</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Icon } from '@iconify/vue'
import { useWorkspace } from '@/composables/useWorkspace'
import { useApi } from '@/composables/useApi'
import type { TokenAnalyticsResponse, TokenByAgent, TokenByModel, TokenBySource } from '@/types'
import { estimateCost, formatCost, formatTokens } from '@/utils/tokenPricing'

const { workspacePath } = useWorkspace()
const { fetchTokenAnalytics } = useApi()

const periods = [
  { label: '7d', value: '7' },
  { label: '30d', value: '30' },
  { label: '90d', value: '90' },
  { label: 'All', value: 'all' },
]

const period = ref('30')
const data = ref<TokenAnalyticsResponse | null>(null)
const loading = ref(true)
const hoveredBar = ref<number | null>(null)
const chartContainer = ref<HTMLElement | null>(null)
const chartWidth = ref(800)

const fmtTokens = formatTokens

async function loadData() {
  loading.value = true
  try {
    const result = fetchTokenAnalytics(period.value)
    await result.promise
    data.value = result.data.value
  } finally {
    loading.value = false
  }
}

function setPeriod(p: string) {
  period.value = p
  loadData()
}

function refresh() {
  loadData()
}

// Summary computeds
const totalTokens = computed(() => {
  if (!data.value) return 0
  return Number(data.value.summary.total_prompt_tokens) + Number(data.value.summary.total_completion_tokens)
})

const totalCostFormatted = computed(() => {
  if (!data.value) return '$0.00'
  // Estimate cost per model and sum
  let total = 0
  for (const m of data.value.byModel) {
    total += estimateCost(
      Number(m.prompt_tokens),
      Number(m.completion_tokens),
      m.model,
      Number(m.cache_read_tokens),
    )
  }
  // Add cost from tasks without model info using default pricing
  const modelTokens = data.value.byModel.reduce((sum, m) => sum + Number(m.prompt_tokens) + Number(m.completion_tokens), 0)
  const unmodeledTokens = totalTokens.value - modelTokens
  if (unmodeledTokens > 0) {
    total += estimateCost(unmodeledTokens * 0.6, unmodeledTokens * 0.4) // rough 60/40 split
  }
  return formatCost(total)
})

const cacheHitRate = computed(() => {
  if (!data.value) return 0
  const promptTotal = Number(data.value.summary.total_prompt_tokens)
  const cacheRead = Number(data.value.summary.total_cache_read_tokens)
  if (promptTotal === 0) return 0
  return Math.round((cacheRead / promptTotal) * 100)
})

// Chart computeds
const chartBars = computed(() => {
  if (!data.value || data.value.timeSeries.length === 0) return []
  const series = data.value.timeSeries
  const maxTotal = Math.max(...series.map(d => Number(d.prompt_tokens) + Number(d.completion_tokens)), 1)
  const barCount = series.length
  const availableWidth = chartWidth.value
  const slotWidth = availableWidth / barCount
  const barWidth = Math.max(4, slotWidth * 0.7)
  const chartHeight = 170

  return series.map((d, i) => {
    const prompt = Number(d.prompt_tokens)
    const completion = Number(d.completion_tokens)
    const total = prompt + completion
    const totalH = (total / maxTotal) * chartHeight
    const promptH = (prompt / maxTotal) * chartHeight
    const completionH = totalH - promptH
    const x = i * slotWidth + (slotWidth - barWidth) / 2
    return {
      x,
      width: barWidth,
      promptY: chartHeight - promptH,
      promptH: Math.max(0, promptH),
      completionY: chartHeight - totalH,
      completionH: Math.max(0, completionH),
    }
  })
})

const chartXLabels = computed(() => {
  if (!data.value || data.value.timeSeries.length === 0) return []
  const series = data.value.timeSeries
  const step = Math.max(1, Math.floor(series.length / 8))
  const slotWidth = chartWidth.value / series.length

  return series
    .filter((_, i) => i % step === 0 || i === series.length - 1)
    .map((d) => {
      const idx = series.indexOf(d)
      return {
        x: idx * slotWidth + slotWidth / 2,
        text: new Date(d.date + 'T00:00:00').toLocaleDateString('en', { month: 'short', day: 'numeric' }),
      }
    })
})

// Bar width helpers
function agentBarWidth(agent: TokenByAgent) {
  if (!data.value || data.value.byAgent.length === 0) return '0%'
  const max = Math.max(...data.value.byAgent.map(a => Number(a.prompt_tokens) + Number(a.completion_tokens)))
  const pct = max > 0 ? ((Number(agent.prompt_tokens) + Number(agent.completion_tokens)) / max) * 100 : 0
  return `${pct}%`
}

function modelBarWidth(model: TokenByModel) {
  if (!data.value || data.value.byModel.length === 0) return '0%'
  const max = Math.max(...data.value.byModel.map(m => Number(m.prompt_tokens) + Number(m.completion_tokens)))
  const pct = max > 0 ? ((Number(model.prompt_tokens) + Number(model.completion_tokens)) / max) * 100 : 0
  return `${pct}%`
}

function sourceBarWidth(source: TokenBySource) {
  if (!data.value || data.value.bySource.length === 0) return '0%'
  const max = Math.max(...data.value.bySource.map(s => Number(s.prompt_tokens) + Number(s.completion_tokens)))
  const pct = max > 0 ? ((Number(source.prompt_tokens) + Number(source.completion_tokens)) / max) * 100 : 0
  return `${pct}%`
}

// Model helpers
function shortModelName(model: string) {
  if (!model) return 'Unknown'
  // Remove date suffixes like -20250929
  return model.replace(/-\d{8}$/, '')
}

function modelCost(model: TokenByModel) {
  return formatCost(estimateCost(
    Number(model.prompt_tokens),
    Number(model.completion_tokens),
    model.model,
    Number(model.cache_read_tokens),
  ))
}

// Source helpers
function sourceIcon(source: string): string {
  const icons: Record<string, string> = {
    chat: 'ph:chat-circle',
    automation: 'ph:lightning',
    manual: 'ph:hand',
    agent_delegation: 'ph:arrows-split',
    agent_ask: 'ph:question',
    agent_notify: 'ph:bell',
  }
  return icons[source] || 'ph:circle'
}

function sourceLabel(source: string): string {
  const labels: Record<string, string> = {
    chat: 'Chat',
    automation: 'Automation',
    manual: 'Manual',
    agent_delegation: 'Delegation',
    agent_ask: 'Agent Ask',
    agent_notify: 'Agent Notify',
  }
  return labels[source] || source
}

// Performance helpers
function speedColor(tps: number): string {
  if (!tps) return 'text-neutral-400'
  if (tps >= 30) return 'text-green-600 dark:text-green-400'
  if (tps >= 15) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-red-500 dark:text-red-400'
}

function formatDuration(ms: number): string {
  if (!ms) return '---'
  if (ms < 1000) return `${Math.round(ms)}ms`
  const seconds = ms / 1000
  if (seconds < 60) return `${seconds.toFixed(1)}s`
  const minutes = Math.floor(seconds / 60)
  const remaining = Math.round(seconds % 60)
  return `${minutes}m ${remaining}s`
}

// Resize observer for chart
let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  loadData()
  if (chartContainer.value) {
    resizeObserver = new ResizeObserver(entries => {
      for (const entry of entries) {
        chartWidth.value = entry.contentRect.width
      }
    })
    resizeObserver.observe(chartContainer.value)
  }
})

watch(chartContainer, (el) => {
  if (el && !resizeObserver) {
    resizeObserver = new ResizeObserver(entries => {
      for (const entry of entries) {
        chartWidth.value = entry.contentRect.width
      }
    })
    resizeObserver.observe(el)
  }
})

onUnmounted(() => {
  resizeObserver?.disconnect()
})
</script>
