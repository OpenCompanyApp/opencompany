<template>
  <div class="space-y-5">
    <!-- Frequency Tabs -->
    <div class="flex p-1 bg-neutral-100 dark:bg-neutral-800 rounded-lg gap-1">
      <button
        v-for="freq in frequencies"
        :key="freq.id"
        type="button"
        :class="[
          'flex-1 flex items-center justify-center gap-1.5 px-2.5 py-2 text-xs font-medium rounded-md transition-all',
          selectedFrequency === freq.id
            ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm'
            : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300',
        ]"
        @click="selectedFrequency = freq.id"
      >
        <Icon :name="freq.icon" class="w-3.5 h-3.5" />
        {{ freq.label }}
      </button>
    </div>

    <!-- Frequency-specific controls -->
    <div class="min-h-[100px]">
      <!-- Interval -->
      <div v-if="selectedFrequency === 'interval'" class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200 flex-wrap">
          <span>Every</span>
          <input
            v-model.number="intervalValue"
            type="number"
            :min="1"
            :max="intervalUnit === 'minutes' ? 59 : 23"
            class="w-16 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500 text-center"
          />
          <select
            v-model="intervalUnit"
            class="bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
          >
            <option value="minutes">minutes</option>
            <option value="hours">hours</option>
          </select>
          <template v-if="intervalUnit === 'hours'">
            <span>at minute</span>
            <select
              v-model.number="minute"
              class="bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            >
              <option v-for="m in minuteOptions" :key="m" :value="m">
                :{{ String(m).padStart(2, '0') }}
              </option>
            </select>
          </template>
        </div>
        <!-- Quick presets -->
        <div class="flex flex-wrap gap-2">
          <button
            v-for="preset in intervalPresets"
            :key="preset.label"
            type="button"
            :class="[
              'px-3 py-1.5 text-xs font-medium rounded-lg border transition-all',
              isPresetActive(preset)
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 border-transparent'
                : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600',
            ]"
            @click="applyPreset(preset)"
          >
            {{ preset.label }}
          </button>
        </div>
      </div>

      <!-- Hourly -->
      <div v-if="selectedFrequency === 'hourly'" class="space-y-4">
        <div class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
          <span>At minute</span>
          <select
            v-model.number="minute"
            class="bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500 appearance-none cursor-pointer"
          >
            <option v-for="m in minuteOptions" :key="m" :value="m">
              :{{ String(m).padStart(2, '0') }}
            </option>
          </select>
          <span>of every hour</span>
        </div>
      </div>

      <!-- Daily -->
      <div v-if="selectedFrequency === 'daily'" class="space-y-4">
        <div class="flex items-center gap-3 text-sm text-neutral-700 dark:text-neutral-200">
          <span>Every day</span>
          <TimeSelector v-model:hour="hour" v-model:minute="minute" />
        </div>
      </div>

      <!-- Weekly -->
      <div v-if="selectedFrequency === 'weekly'" class="space-y-4">
        <DayOfWeekPicker v-model="selectedDays" />
        <TimeSelector v-model:hour="hour" v-model:minute="minute" />
      </div>

      <!-- Monthly -->
      <div v-if="selectedFrequency === 'monthly'" class="space-y-4">
        <DayOfMonthPicker v-model="selectedDates" />
        <TimeSelector v-model:hour="hour" v-model:minute="minute" />
      </div>

      <!-- Custom -->
      <div v-if="selectedFrequency === 'custom'" class="space-y-4">
        <div class="space-y-2">
          <div class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
            <span>Cron expression</span>
          </div>
          <input
            v-model="customCron"
            type="text"
            class="w-full bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg px-4 py-2.5 text-sm text-neutral-900 dark:text-white font-mono focus:outline-none focus:ring-2 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            placeholder="* * * * *"
            spellcheck="false"
          />
          <div class="flex items-center gap-4 text-[11px] text-neutral-400 dark:text-neutral-500 font-mono">
            <span>minute</span>
            <span>hour</span>
            <span>day</span>
            <span>month</span>
            <span>weekday</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Human-readable summary -->
    <div class="flex items-center gap-2 px-4 py-3 bg-neutral-900/5 dark:bg-white/5 rounded-lg">
      <Icon name="ph:repeat" class="w-4 h-4 text-neutral-500 dark:text-neutral-400 shrink-0" />
      <span class="text-sm font-medium text-neutral-700 dark:text-neutral-200">
        {{ humanReadable }}
      </span>
    </div>

    <!-- Next runs preview -->
    <NextRunsPreview
      :frequency="selectedFrequency"
      :hour="hour"
      :minute="minute"
      :interval-value="intervalValue"
      :interval-unit="intervalUnit"
      :selected-days="selectedDays"
      :selected-dates="selectedDates"
      :timezone="timezone"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import TimeSelector from './TimeSelector.vue'
import DayOfWeekPicker from './DayOfWeekPicker.vue'
import DayOfMonthPicker from './DayOfMonthPicker.vue'
import NextRunsPreview from './NextRunsPreview.vue'

const props = defineProps<{
  modelValue: string
  timezone: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const frequencies = [
  { id: 'interval', label: 'Interval', icon: 'ph:gauge' },
  { id: 'hourly', label: 'Hourly', icon: 'ph:timer' },
  { id: 'daily', label: 'Daily', icon: 'ph:sun' },
  { id: 'weekly', label: 'Weekly', icon: 'ph:calendar-blank' },
  { id: 'monthly', label: 'Monthly', icon: 'ph:calendar-dots' },
  { id: 'custom', label: 'Custom', icon: 'ph:terminal' },
]

const selectedFrequency = ref('daily')
const hour = ref(9)
const minute = ref(0)
const selectedDays = ref<number[]>([1, 2, 3, 4, 5])
const selectedDates = ref<number[]>([1])
const intervalValue = ref(5)
const intervalUnit = ref<'minutes' | 'hours'>('minutes')
const customCron = ref('* * * * *')

const minuteOptions = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]

const intervalPresets = [
  { label: 'Every 1 min', value: 1, unit: 'minutes' as const },
  { label: 'Every 5 min', value: 5, unit: 'minutes' as const },
  { label: 'Every 15 min', value: 15, unit: 'minutes' as const },
  { label: 'Every 30 min', value: 30, unit: 'minutes' as const },
  { label: 'Every hour', value: 1, unit: 'hours' as const },
]

function isPresetActive(preset: typeof intervalPresets[number]): boolean {
  return intervalValue.value === preset.value && intervalUnit.value === preset.unit
}

function applyPreset(preset: typeof intervalPresets[number]) {
  intervalValue.value = preset.value
  intervalUnit.value = preset.unit
}

// Generate cron expression from UI state
const cronExpression = computed<string>(() => {
  const m = minute.value
  const h = hour.value

  switch (selectedFrequency.value) {
    case 'interval': {
      if (intervalUnit.value === 'minutes') {
        const v = Math.max(1, Math.min(59, intervalValue.value || 1))
        return v === 1 ? '* * * * *' : `*/${v} * * * *`
      } else {
        const v = Math.max(1, Math.min(23, intervalValue.value || 1))
        return `${m} */${v} * * *`
      }
    }
    case 'hourly':
      return `${m} * * * *`
    case 'daily':
      return `${m} ${h} * * *`
    case 'weekly': {
      const days = selectedDays.value.length > 0
        ? [...selectedDays.value].sort((a, b) => a - b).join(',')
        : '*'
      return `${m} ${h} * * ${days}`
    }
    case 'monthly': {
      const dates = selectedDates.value.length > 0
        ? [...selectedDates.value].sort((a, b) => a - b).join(',')
        : '1'
      return `${m} ${h} ${dates} * *`
    }
    case 'custom':
      return customCron.value.trim() || '* * * * *'
    default:
      return '0 9 * * *'
  }
})

// Human-readable description
const humanReadable = computed<string>(() => {
  const timeStr = formatTime(hour.value, minute.value)

  switch (selectedFrequency.value) {
    case 'interval': {
      if (intervalUnit.value === 'minutes') {
        const v = Math.max(1, intervalValue.value || 1)
        return v === 1 ? 'Every minute' : `Every ${v} minutes`
      } else {
        const v = Math.max(1, intervalValue.value || 1)
        const atMin = `:${String(minute.value).padStart(2, '0')}`
        return v === 1 ? `Every hour at ${atMin}` : `Every ${v} hours at ${atMin}`
      }
    }
    case 'hourly':
      return `Every hour at :${String(minute.value).padStart(2, '0')}`
    case 'daily':
      return `Every day at ${timeStr}`
    case 'weekly': {
      if (selectedDays.value.length === 0) return 'Select at least one day'
      const dayNames = formatDayNames(selectedDays.value)
      return `Every ${dayNames} at ${timeStr}`
    }
    case 'monthly': {
      if (selectedDates.value.length === 0) return 'Select at least one date'
      const dateStr = formatDateNumbers(selectedDates.value)
      return `Monthly on the ${dateStr} at ${timeStr}`
    }
    case 'custom': {
      return describeCron(customCron.value.trim())
    }
    default:
      return ''
  }
})

// Parse existing cron expression into UI state
function parseCronToState(expr: string) {
  if (!expr) return
  const parts = expr.trim().split(/\s+/)
  if (parts.length !== 5) return

  const [m, h, dom, , dow] = parts

  // Detect interval patterns first
  if (m.startsWith('*/') && h === '*' && dom === '*' && dow === '*') {
    selectedFrequency.value = 'interval'
    intervalUnit.value = 'minutes'
    intervalValue.value = parseInt(m.slice(2)) || 5
    return
  }
  if (m === '*' && h === '*' && dom === '*' && dow === '*') {
    selectedFrequency.value = 'interval'
    intervalUnit.value = 'minutes'
    intervalValue.value = 1
    return
  }
  if (h.startsWith('*/') && dom === '*' && dow === '*') {
    selectedFrequency.value = 'interval'
    intervalUnit.value = 'hours'
    intervalValue.value = parseInt(h.slice(2)) || 1
    minute.value = parseInt(m) || 0
    return
  }

  // Standard modes
  minute.value = parseInt(m) || 0
  hour.value = h === '*' ? 0 : (parseInt(h) || 0)

  if (h === '*') {
    selectedFrequency.value = 'hourly'
  } else if (dow !== '*' && dom === '*') {
    selectedFrequency.value = 'weekly'
    selectedDays.value = dow.split(',').map(Number).filter(n => !isNaN(n))
  } else if (dom !== '*') {
    selectedFrequency.value = 'monthly'
    selectedDates.value = dom.split(',').map(Number).filter(n => !isNaN(n))
  } else {
    selectedFrequency.value = 'daily'
  }
}

// Emit cron expression when state changes
watch(cronExpression, (val) => {
  emit('update:modelValue', val)
})

// Parse initial value
onMounted(() => {
  if (props.modelValue) {
    parseCronToState(props.modelValue)
  }
})

// Re-parse if modelValue changes externally
watch(() => props.modelValue, (val) => {
  if (val && val !== cronExpression.value) {
    parseCronToState(val)
  }
})

// --- Formatting helpers ---

function formatTime(h: number, m: number): string {
  const period = h >= 12 ? 'PM' : 'AM'
  const hour12 = h === 0 ? 12 : h > 12 ? h - 12 : h
  return `${hour12}:${String(m).padStart(2, '0')} ${period}`
}

function formatDayNames(days: number[]): string {
  const names: Record<number, string> = {
    0: 'Sunday',
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
  }
  const sorted = [...days].sort((a, b) => a - b)

  if (sorted.length === 5 && [1, 2, 3, 4, 5].every((d) => sorted.includes(d))) {
    return 'weekday'
  }
  if (sorted.length === 7) return 'day'

  return sorted.map((d) => names[d]?.slice(0, 3) ?? String(d)).join(', ')
}

function formatDateNumbers(dates: number[]): string {
  const sorted = [...dates].sort((a, b) => a - b)
  return sorted
    .map((d) => {
      if (d === 1 || d === 21 || d === 31) return `${d}st`
      if (d === 2 || d === 22) return `${d}nd`
      if (d === 3 || d === 23) return `${d}rd`
      return `${d}th`
    })
    .join(', ')
}

function describeCron(expr: string): string {
  if (!expr) return 'Enter a cron expression'
  const parts = expr.split(/\s+/)
  if (parts.length !== 5) return 'Invalid cron expression'

  const [m, h, dom, mon, dow] = parts

  if (m === '*' && h === '*') return 'Every minute'
  if (m.startsWith('*/') && h === '*') return `Every ${m.slice(2)} minutes`
  if (h.startsWith('*/')) return `Every ${h.slice(2)} hours at :${String(parseInt(m) || 0).padStart(2, '0')}`
  if (h === '*') return `Every hour at :${String(parseInt(m) || 0).padStart(2, '0')}`

  const time = formatTime(parseInt(h) || 0, parseInt(m) || 0)

  if (dow !== '*' && dom === '*') return `${dow} at ${time}`
  if (dom !== '*') return `Day ${dom} at ${time}`
  if (mon !== '*') return `Month ${mon}, daily at ${time}`
  return `Daily at ${time}`
}
</script>
