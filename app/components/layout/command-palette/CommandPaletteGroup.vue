<template>
  <div
    :class="[
      'mb-2 last:mb-0',
      sizeConfig[size].container
    ]"
  >
    <!-- Group header -->
    <component
      :is="collapsible ? CollapsibleRoot : 'div'"
      v-model:open="isOpen"
    >
      <component
        :is="collapsible ? CollapsibleTrigger : 'div'"
        :class="[
          'w-full flex items-center justify-between group',
          sizeConfig[size].header,
          collapsible && 'cursor-pointer hover:bg-olympus-surface/50 rounded-lg transition-colors duration-150'
        ]"
        @click="collapsible && $emit('toggle')"
      >
        <div class="flex items-center gap-2">
          <!-- Group icon -->
          <div
            v-if="icon"
            :class="[
              'flex items-center justify-center rounded-md bg-olympus-surface transition-colors',
              sizeConfig[size].iconContainer
            ]"
          >
            <Icon
              :name="icon"
              :class="['text-olympus-text-muted', sizeConfig[size].icon]"
            />
          </div>

          <!-- Group label -->
          <span
            :class="[
              'font-semibold text-olympus-text-muted uppercase tracking-wider',
              sizeConfig[size].label
            ]"
          >
            {{ label }}
          </span>

          <!-- Item count badge -->
          <Transition
            enter-active-class="transition-all duration-200"
            leave-active-class="transition-all duration-150"
            enter-from-class="opacity-0 scale-75"
            leave-to-class="opacity-0 scale-75"
          >
            <span
              v-if="showCount && count !== undefined"
              :class="[
                'font-medium bg-olympus-surface text-olympus-text-subtle rounded-full',
                sizeConfig[size].count
              ]"
            >
              {{ count }}
            </span>
          </Transition>
        </div>

        <div class="flex items-center gap-2">
          <!-- Custom actions slot -->
          <slot name="actions" />

          <!-- Collapse chevron -->
          <Transition
            enter-active-class="transition-all duration-200"
            leave-active-class="transition-all duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
          >
            <Icon
              v-if="collapsible"
              name="ph:caret-down"
              :class="[
                'text-olympus-text-subtle transition-transform duration-200',
                sizeConfig[size].chevron,
                !isOpen && '-rotate-90'
              ]"
            />
          </Transition>
        </div>
      </component>

      <!-- Group content -->
      <component
        :is="collapsible ? CollapsibleContent : 'div'"
        :class="sizeConfig[size].content"
      >
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          leave-active-class="transition-all duration-150 ease-in"
          enter-from-class="opacity-0 translate-y-[-4px]"
          leave-to-class="opacity-0 translate-y-[-4px]"
        >
          <div v-if="collapsible ? isOpen : true" class="space-y-0.5">
            <slot />
          </div>
        </Transition>
      </component>
    </component>

    <!-- Loading state -->
    <div v-if="loading" class="space-y-2 mt-2">
      <div
        v-for="i in skeletonCount"
        :key="i"
        :class="[
          'flex items-center gap-3 animate-pulse',
          sizeConfig[size].skeleton
        ]"
      >
        <div :class="['rounded-lg bg-olympus-surface', sizeConfig[size].skeletonIcon]" />
        <div class="flex-1 space-y-1.5">
          <div class="h-4 w-28 bg-olympus-surface rounded" />
          <div class="h-3 w-40 bg-olympus-surface/60 rounded" />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="!loading && isEmpty"
      :class="[
        'flex flex-col items-center justify-center text-center py-4',
        sizeConfig[size].empty
      ]"
    >
      <Icon
        :name="emptyIcon"
        :class="['text-olympus-text-subtle mb-2', sizeConfig[size].emptyIcon]"
      />
      <p class="text-sm text-olympus-text-muted">{{ emptyText }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  CollapsibleContent,
  CollapsibleRoot,
  CollapsibleTrigger,
} from 'reka-ui'

// Types
type GroupSize = 'sm' | 'md' | 'lg'

interface SizeConfig {
  container: string
  header: string
  iconContainer: string
  icon: string
  label: string
  count: string
  chevron: string
  content: string
  skeleton: string
  skeletonIcon: string
  empty: string
  emptyIcon: string
}

// Props
const props = withDefaults(defineProps<{
  label: string
  icon?: string
  count?: number
  showCount?: boolean
  collapsible?: boolean
  collapsed?: boolean
  loading?: boolean
  isEmpty?: boolean
  emptyText?: string
  emptyIcon?: string
  skeletonCount?: number
  size?: GroupSize
}>(), {
  showCount: true,
  collapsible: false,
  collapsed: false,
  loading: false,
  isEmpty: false,
  emptyText: 'No items found',
  emptyIcon: 'ph:folder-open',
  skeletonCount: 3,
  size: 'md',
})

// Emits
defineEmits<{
  toggle: []
}>()

// Size configuration
const sizeConfig: Record<GroupSize, SizeConfig> = {
  sm: {
    container: 'p-1',
    header: 'px-2 py-1.5',
    iconContainer: 'w-5 h-5',
    icon: 'w-3 h-3',
    label: 'text-[10px]',
    count: 'text-[10px] px-1.5 py-0.5',
    chevron: 'w-3 h-3',
    content: 'mt-1',
    skeleton: 'px-2 py-2',
    skeletonIcon: 'w-6 h-6',
    empty: 'py-3',
    emptyIcon: 'w-6 h-6',
  },
  md: {
    container: 'p-1.5',
    header: 'px-3 py-2',
    iconContainer: 'w-6 h-6',
    icon: 'w-3.5 h-3.5',
    label: 'text-xs',
    count: 'text-xs px-2 py-0.5',
    chevron: 'w-3.5 h-3.5',
    content: 'mt-1.5',
    skeleton: 'px-3 py-2.5',
    skeletonIcon: 'w-8 h-8',
    empty: 'py-4',
    emptyIcon: 'w-8 h-8',
  },
  lg: {
    container: 'p-2',
    header: 'px-3 py-2.5',
    iconContainer: 'w-7 h-7',
    icon: 'w-4 h-4',
    label: 'text-xs',
    count: 'text-xs px-2 py-1',
    chevron: 'w-4 h-4',
    content: 'mt-2',
    skeleton: 'px-3 py-3',
    skeletonIcon: 'w-10 h-10',
    empty: 'py-6',
    emptyIcon: 'w-10 h-10',
  },
}

// State
const isOpen = ref(!props.collapsed)

// Watch for external collapse changes
watch(() => props.collapsed, (newValue) => {
  isOpen.value = !newValue
})
</script>
