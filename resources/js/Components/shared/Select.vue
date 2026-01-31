<template>
  <SelectRoot v-model="selectedValue">
    <SelectTrigger
      :class="[
        'inline-flex items-center justify-between gap-2',
        'bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg',
        'text-neutral-700 dark:text-neutral-200 placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
        'transition-colors duration-150',
        'hover:border-neutral-300 dark:hover:border-neutral-600',
        'focus:outline-none focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-200 dark:focus:ring-neutral-700',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        sizeClasses[size],
      ]"
    >
      <span class="flex items-center gap-2 truncate">
        <Icon v-if="icon" :name="icon" :class="['text-neutral-400 shrink-0', iconSizeClasses[size]]" />
        <SelectValue :placeholder="placeholder" />
      </span>
      <Icon name="ph:caret-down" :class="['text-neutral-400 shrink-0', iconSizeClasses[size]]" />
    </SelectTrigger>

    <SelectPortal>
      <SelectContent
        :class="[
          'z-50 min-w-[8rem] overflow-hidden',
          'bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg shadow-lg',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          'data-[side=bottom]:slide-in-from-top-2',
          'data-[side=top]:slide-in-from-bottom-2',
        ]"
        :side-offset="4"
        position="popper"
      >
        <SelectViewport class="p-1">
          <SelectItem
            v-for="item in items"
            :key="getItemValue(item)"
            :value="getItemValue(item)"
            :class="[
              'relative flex items-center gap-2 select-none rounded-md cursor-pointer',
              'text-neutral-700 dark:text-neutral-200 outline-none',
              'data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-neutral-700 data-[highlighted]:text-neutral-900 dark:data-[highlighted]:text-white',
              'data-[disabled]:opacity-50 data-[disabled]:pointer-events-none',
              itemSizeClasses[size],
            ]"
          >
            <SelectItemIndicator class="absolute left-2 flex items-center justify-center">
              <Icon name="ph:check" :class="['text-neutral-900 dark:text-white', checkIconSizeClasses[size]]" />
            </SelectItemIndicator>
            <span :class="{ 'pl-6': true }">
              <span class="flex items-center gap-2">
                <Icon v-if="getItemIcon(item)" :name="getItemIcon(item)" :class="['text-neutral-500', iconSizeClasses[size]]" />
                <span
                  v-if="getItemColor(item)"
                  :class="['w-2 h-2 rounded-full shrink-0', getItemColor(item)]"
                />
                <SelectItemText>{{ getItemLabel(item) }}</SelectItemText>
              </span>
            </span>
          </SelectItem>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectPortal,
  SelectContent,
  SelectViewport,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
} from 'reka-ui'
import Icon from './Icon.vue'

type SelectSize = 'sm' | 'md' | 'lg'

interface SelectItem {
  value: string
  label: string
  icon?: string
  color?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<{
  items: SelectItem[] | Record<string, unknown>[]
  valueKey?: string
  labelKey?: string
  placeholder?: string
  icon?: string
  size?: SelectSize
  disabled?: boolean
}>(), {
  valueKey: 'value',
  labelKey: 'label',
  placeholder: 'Select...',
  size: 'md',
  disabled: false,
})

const selectedValue = defineModel<string>()

// Helper functions to handle both simple and complex item structures
const getItemValue = (item: SelectItem | Record<string, unknown>): string => {
  return String((item as Record<string, unknown>)[props.valueKey] ?? item)
}

const getItemLabel = (item: SelectItem | Record<string, unknown>): string => {
  return String((item as Record<string, unknown>)[props.labelKey] ?? (item as Record<string, unknown>)[props.valueKey] ?? item)
}

const getItemIcon = (item: SelectItem | Record<string, unknown>): string | undefined => {
  return (item as Record<string, unknown>).icon as string | undefined
}

const getItemColor = (item: SelectItem | Record<string, unknown>): string | undefined => {
  const chip = (item as Record<string, unknown>).chip as { color?: string } | undefined
  return chip?.color
}

const sizeClasses: Record<SelectSize, string> = {
  sm: 'px-2 py-1.5 text-xs min-w-[120px]',
  md: 'px-3 py-2 text-sm min-w-[140px]',
  lg: 'px-4 py-2.5 text-base min-w-[160px]',
}

const itemSizeClasses: Record<SelectSize, string> = {
  sm: 'px-2 py-1.5 text-xs',
  md: 'px-3 py-2 text-sm',
  lg: 'px-4 py-2.5 text-base',
}

const iconSizeClasses: Record<SelectSize, string> = {
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
  lg: 'w-5 h-5',
}

const checkIconSizeClasses: Record<SelectSize, string> = {
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
  lg: 'w-4 h-4',
}
</script>
