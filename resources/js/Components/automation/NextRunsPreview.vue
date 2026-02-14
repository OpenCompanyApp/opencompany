<template>
  <div class="bg-neutral-50 dark:bg-neutral-800/50 rounded-xl p-4 space-y-3">
    <div class="flex items-center gap-2 text-sm font-medium text-neutral-700 dark:text-neutral-200">
      <Icon name="ph:clock-countdown" class="w-4 h-4" />
      <span>Next runs</span>
    </div>

    <div v-if="runs.length" class="space-y-2">
      <div
        v-for="(run, i) in runs"
        :key="i"
        class="flex items-center justify-between text-sm"
      >
        <div class="flex items-center gap-2">
          <div
            :class="[
              'w-1.5 h-1.5 rounded-full',
              i === 0 ? 'bg-green-500' : 'bg-neutral-300 dark:bg-neutral-600',
            ]"
          />
          <span class="text-neutral-700 dark:text-neutral-300">
            {{ formatDateTime(run) }}
          </span>
        </div>
        <span class="text-xs text-neutral-400 dark:text-neutral-500">
          {{ formatRelative(run) }}
        </span>
      </div>
    </div>

    <div v-else class="text-sm text-neutral-400 dark:text-neutral-500 py-2">
      Configure a schedule to see upcoming runs
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'

const props = defineProps<{
  frequency: string
  hour: number
  minute: number
  intervalValue?: number
  intervalUnit?: string
  selectedDays: number[]
  selectedDates: number[]
  timezone: string
}>()

const runs = computed(() => computeNextRuns(5))

function computeNextRuns(count: number): Date[] {
  const now = new Date()
  const results: Date[] = []

  if (props.frequency === 'interval') {
    const base = new Date(now)
    base.setSeconds(0, 0)

    if (props.intervalUnit === 'minutes') {
      const interval = Math.max(1, props.intervalValue || 5)
      // Align to next interval boundary
      const currentMin = base.getMinutes()
      if (interval === 1) {
        base.setMinutes(currentMin + 1)
      } else {
        const nextMin = Math.ceil((currentMin + 1) / interval) * interval
        base.setMinutes(nextMin)
      }
      for (let i = 0; i < count; i++) {
        results.push(new Date(base))
        base.setMinutes(base.getMinutes() + interval)
      }
    } else {
      // Hours interval
      const interval = Math.max(1, props.intervalValue || 1)
      base.setMinutes(props.minute)
      const currentHour = base.getHours()
      const nextHour = Math.ceil((currentHour + 1) / interval) * interval
      base.setHours(nextHour)
      if (base <= now) {
        base.setHours(base.getHours() + interval)
      }
      for (let i = 0; i < count; i++) {
        results.push(new Date(base))
        base.setHours(base.getHours() + interval)
      }
    }
  } else if (props.frequency === 'hourly') {
    const base = new Date(now)
    base.setSeconds(0, 0)
    base.setMinutes(props.minute)
    if (base <= now) {
      base.setHours(base.getHours() + 1)
    }
    for (let i = 0; i < count; i++) {
      results.push(new Date(base))
      base.setHours(base.getHours() + 1)
    }
  } else if (props.frequency === 'daily') {
    const base = new Date(now)
    base.setSeconds(0, 0)
    base.setHours(props.hour, props.minute)
    if (base <= now) {
      base.setDate(base.getDate() + 1)
    }
    for (let i = 0; i < count; i++) {
      results.push(new Date(base))
      base.setDate(base.getDate() + 1)
    }
  } else if (props.frequency === 'weekly') {
    if (props.selectedDays.length === 0) return []
    const sortedDays = [...props.selectedDays].sort((a, b) => a - b)
    const base = new Date(now)
    base.setSeconds(0, 0)
    base.setHours(props.hour, props.minute)

    let safety = 0
    while (results.length < count && safety < 200) {
      const dow = base.getDay()
      if (sortedDays.includes(dow) && base > now) {
        results.push(new Date(base))
      }
      base.setDate(base.getDate() + 1)
      base.setHours(props.hour, props.minute, 0, 0)
      safety++
    }
  } else if (props.frequency === 'monthly') {
    if (props.selectedDates.length === 0) return []
    const sortedDates = [...props.selectedDates].sort((a, b) => a - b)
    const base = new Date(now)
    base.setSeconds(0, 0)
    base.setHours(props.hour, props.minute)

    let month = base.getMonth()
    let year = base.getFullYear()
    let safety = 0

    while (results.length < count && safety < 100) {
      for (const date of sortedDates) {
        const candidate = new Date(year, month, date, props.hour, props.minute)
        if (candidate.getMonth() !== month) continue
        if (candidate > now) {
          results.push(candidate)
          if (results.length >= count) break
        }
      }
      month++
      if (month > 11) {
        month = 0
        year++
      }
      safety++
    }
  }
  // 'custom' mode: no client-side computation (would need full cron parser)

  return results
}

function formatDateTime(date: Date): string {
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function formatRelative(date: Date): string {
  const now = new Date()
  const diff = date.getTime() - now.getTime()
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)

  if (seconds < 60) return `in ${seconds}s`
  if (minutes < 60) return `in ${minutes}m`
  if (hours < 24) return `in ${hours}h`
  if (days === 1) return 'tomorrow'
  if (days < 7) return `in ${days} days`
  return `in ${Math.floor(days / 7)} weeks`
}
</script>
