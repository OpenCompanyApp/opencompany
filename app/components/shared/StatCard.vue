<template>
  <div
    class="card-gradient card-interactive bg-olympus-surface rounded-xl p-4 transition-all duration-200 group"
  >
    <div class="flex items-center justify-between mb-3">
      <span class="text-olympus-text-muted text-sm font-medium">{{ label }}</span>
      <div
        :class="[
          'w-9 h-9 rounded-lg flex items-center justify-center transition-transform duration-150 group-hover:scale-105',
          iconBgClass
        ]"
      >
        <Icon :name="icon" :class="['w-5 h-5', iconColorClass]" />
      </div>
    </div>
    <div class="flex items-baseline gap-2">
      <span class="text-2xl font-bold tracking-tight">{{ formattedValue }}</span>
      <span v-if="subValue" class="text-sm text-olympus-text-muted">{{ subValue }}</span>
    </div>
    <div v-if="trend" class="mt-2 flex items-center gap-1.5">
      <Icon
        :name="trend > 0 ? 'ph:trend-up' : 'ph:trend-down'"
        :class="[
          'w-4 h-4',
          trend > 0 ? 'text-green-400' : 'text-red-400'
        ]"
      />
      <span
        :class="[
          'text-xs font-medium',
          trend > 0 ? 'text-green-400' : 'text-red-400'
        ]"
      >
        {{ Math.abs(trend) }}% from last week
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  label: string
  value: string | number
  subValue?: string
  icon: string
  iconColor?: 'primary' | 'success' | 'warning' | 'error'
  trend?: number
  prefix?: string
}>()

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return props.prefix ? `${props.prefix}${props.value.toLocaleString()}` : props.value.toLocaleString()
  }
  return props.value
})

const iconBgClass = computed(() => {
  const colors = {
    primary: 'bg-olympus-primary/20',
    success: 'bg-green-500/20',
    warning: 'bg-amber-500/20',
    error: 'bg-red-500/20',
  }
  return colors[props.iconColor || 'primary']
})

const iconColorClass = computed(() => {
  const colors = {
    primary: 'text-olympus-primary',
    success: 'text-green-400',
    warning: 'text-amber-400',
    error: 'text-red-400',
  }
  return colors[props.iconColor || 'primary']
})
</script>
