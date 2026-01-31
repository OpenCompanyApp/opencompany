<template>
  <div class="h-full overflow-y-auto">
    <div class="max-w-4xl mx-auto p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <header>
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Credits & Usage</h1>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
            Track credit consumption and manage your budget
          </p>
        </header>
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150"
        >
          <Icon name="ph:plus" class="w-4 h-4" />
          Add Credits
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <SharedSkeleton v-for="i in 3" :key="i" custom-class="h-32 rounded-xl" />
        </div>
        <SharedSkeleton custom-class="h-64 rounded-xl" />
      </div>

      <template v-else>
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-5 border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm text-neutral-500 dark:text-neutral-300">Available Credits</span>
              <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center">
                <Icon name="ph:coins" class="w-5 h-5 text-green-400" />
              </div>
            </div>
            <p class="text-3xl font-bold text-neutral-900 dark:text-white">
              {{ formatNumber(creditsData?.stats?.creditsRemaining || 0) }}
            </p>
            <div class="mt-3 h-2 bg-white dark:bg-neutral-700 rounded-full overflow-hidden">
              <div
                class="h-full bg-gradient-to-r from-neutral-700 to-neutral-500 rounded-full transition-all"
                :style="{ width: `${creditPercentage}%` }"
              />
            </div>
            <p class="text-xs text-neutral-500 dark:text-neutral-300 mt-2">
              {{ creditPercentage }}% of {{ formatNumber(3000) }} total
            </p>
          </div>

          <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-5 border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm text-neutral-500 dark:text-neutral-300">Credits Used</span>
              <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center">
                <Icon name="ph:chart-line-up" class="w-5 h-5 text-amber-400" />
              </div>
            </div>
            <p class="text-3xl font-bold text-neutral-900 dark:text-white">
              {{ formatNumber(creditsData?.stats?.creditsUsed || 0) }}
            </p>
            <p class="text-xs text-neutral-500 dark:text-neutral-300 mt-2">
              This billing period
            </p>
          </div>

          <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-5 border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm text-neutral-500 dark:text-neutral-300">Today's Usage</span>
              <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                <Icon name="ph:clock" class="w-5 h-5 text-blue-400" />
              </div>
            </div>
            <p class="text-3xl font-bold text-neutral-900 dark:text-white">
              {{ formatNumber(todayUsage) }}
            </p>
            <p class="text-xs text-neutral-500 dark:text-neutral-300 mt-2">
              {{ creditsData?.transactions?.filter((t: Transaction) => isToday(t.createdAt)).length || 0 }} transactions
            </p>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Daily Usage Chart -->
          <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-5 border border-neutral-200 dark:border-neutral-700">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">Daily Usage (Last 7 Days)</h2>
            <div class="h-48 flex items-end gap-2">
              <div
                v-for="day in creditsData?.dailyUsage"
                :key="day.date"
                class="flex-1 flex flex-col items-center gap-2"
              >
                <div
                  class="w-full bg-neutral-700 rounded-t transition-all hover:bg-neutral-900"
                  :style="{ height: `${(day.amount / maxDailyUsage) * 100}%`, minHeight: day.amount > 0 ? '4px' : '0' }"
                />
                <span class="text-[10px] text-neutral-500 dark:text-neutral-300">
                  {{ formatDayLabel(day.date) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Usage by Agent -->
          <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl p-5 border border-neutral-200 dark:border-neutral-700">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-white mb-4">Usage by Agent</h2>
            <div class="space-y-3">
              <div
                v-for="agent in creditsData?.agentUsage?.slice(0, 5)"
                :key="agent.userId"
                class="flex items-center gap-3"
              >
                <div
                  :class="[
                    'w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-semibold',
                    agentColorMap[agent.agentType || 'default'] || 'bg-neutral-500',
                  ]"
                >
                  {{ agent.name.charAt(0) }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-1">
                    <Link :href="`/profile/${agent.userId}`" class="text-sm font-medium text-neutral-900 dark:text-white truncate hover:text-neutral-900 dark:hover:text-white transition-colors">{{ agent.name }}</Link>
                    <span class="text-sm text-neutral-500 dark:text-neutral-300">{{ formatNumber(agent.amount) }}</span>
                  </div>
                  <div class="h-1.5 bg-white dark:bg-neutral-700 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-neutral-900 rounded-full transition-all"
                      :style="{ width: `${(agent.amount / totalAgentUsage) * 100}%` }"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700">
          <div class="flex items-center justify-between p-5 border-b border-neutral-200 dark:border-neutral-700">
            <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Recent Transactions</h2>
            <select
              v-model="transactionFilter"
              class="px-3 py-1.5 rounded-lg bg-white dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-300"
            >
              <option value="all">All Types</option>
              <option value="usage">Usage</option>
              <option value="purchase">Purchases</option>
            </select>
          </div>

          <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
            <div
              v-for="transaction in filteredTransactions"
              :key="transaction.id"
              class="flex items-center gap-4 p-4 hover:bg-white/50 dark:hover:bg-neutral-700/50 transition-colors"
            >
              <div
                :class="[
                  'w-10 h-10 rounded-xl flex items-center justify-center shrink-0',
                  transaction.type === 'usage' ? 'bg-red-500/20' : 'bg-green-500/20',
                ]"
              >
                <Icon
                  :name="transaction.type === 'usage' ? 'ph:arrow-down' : 'ph:arrow-up'"
                  :class="['w-5 h-5', transaction.type === 'usage' ? 'text-red-400' : 'text-green-400']"
                />
              </div>

              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-neutral-900 dark:text-white truncate">
                  {{ transaction.description }}
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-300">
                  <Link v-if="transaction.userId" :href="`/profile/${transaction.userId}`" class="hover:text-neutral-900 dark:hover:text-white transition-colors">{{ transaction.user?.name || 'Unknown' }}</Link>
                  <span v-else>System</span>
                  &bull; {{ formatDateTime(transaction.createdAt) }}
                </p>
              </div>

              <span
                :class="[
                  'text-sm font-semibold',
                  transaction.amount < 0 ? 'text-red-400' : 'text-green-400',
                ]"
              >
                {{ transaction.amount > 0 ? '+' : '' }}{{ formatNumber(transaction.amount) }}
              </span>
            </div>

            <div v-if="filteredTransactions.length === 0" class="p-8 text-center">
              <Icon name="ph:receipt" class="w-12 h-12 mx-auto text-neutral-500 dark:text-neutral-300 mb-3" />
              <p class="text-sm text-neutral-500 dark:text-neutral-300">No transactions found</p>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import SharedSkeleton from '@/Components/shared/Skeleton.vue'

interface User {
  id: string
  name: string
  type: 'human' | 'agent'
  agentType?: string
}

interface Transaction {
  id: string
  type: 'usage' | 'purchase' | 'refund' | 'bonus'
  amount: number
  description: string
  userId?: string
  createdAt: string
  user?: User
}

interface AgentUsage {
  userId: string
  name: string
  type?: string
  agentType?: string
  amount: number
}

interface CreditsData {
  transactions: Transaction[]
  stats: {
    creditsUsed: number
    creditsRemaining: number
  }
  agentUsage: AgentUsage[]
  dailyUsage: { date: string; amount: number }[]
}

const loading = ref(true)
const creditsData = ref<CreditsData | null>(null)
const transactionFilter = ref<'all' | 'usage' | 'purchase'>('all')

const agentColorMap: Record<string, string> = {
  manager: 'bg-purple-500',
  writer: 'bg-green-500',
  analyst: 'bg-cyan-500',
  creative: 'bg-pink-500',
  researcher: 'bg-amber-500',
  coder: 'bg-indigo-500',
  coordinator: 'bg-teal-500',
  default: 'bg-neutral-500',
}

const creditPercentage = computed(() => {
  if (!creditsData.value?.stats) return 0
  return Math.round((creditsData.value.stats.creditsRemaining / 3000) * 100)
})

const todayUsage = computed(() => {
  if (!creditsData.value?.transactions) return 0
  return creditsData.value.transactions
    .filter((t) => t.type === 'usage' && isToday(t.createdAt))
    .reduce((sum, t) => sum + Math.abs(t.amount), 0)
})

const maxDailyUsage = computed(() => {
  if (!creditsData.value?.dailyUsage) return 1
  return Math.max(...creditsData.value.dailyUsage.map((d) => d.amount), 1)
})

const totalAgentUsage = computed(() => {
  if (!creditsData.value?.agentUsage) return 1
  return creditsData.value.agentUsage.reduce((sum, a) => sum + a.amount, 0) || 1
})

const filteredTransactions = computed(() => {
  if (!creditsData.value?.transactions) return []
  if (transactionFilter.value === 'all') return creditsData.value.transactions
  return creditsData.value.transactions.filter((t) => t.type === transactionFilter.value)
})

const fetchCredits = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/credits')
    const data = await response.json()
    creditsData.value = data
  } catch (error) {
    console.error('Failed to fetch credits:', error)
  } finally {
    loading.value = false
  }
}

const formatNumber = (value: number) => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value)
}

const formatDateTime = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const formatDayLabel = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { weekday: 'short' })
}

const isToday = (dateString: string) => {
  const date = new Date(dateString)
  const today = new Date()
  return date.toDateString() === today.toDateString()
}

onMounted(() => {
  fetchCredits()
})
</script>
