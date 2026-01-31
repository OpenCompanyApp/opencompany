<template>
  <div
    :class="[
      'flex items-center justify-between border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800',
      sizeConfig[size].container
    ]"
  >
    <!-- Keyboard hints -->
    <div :class="['flex items-center', sizeConfig[size].hints]">
      <!-- Navigation hints -->
      <div class="flex items-center gap-1 cursor-default">
        <kbd
          :class="[
            'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded font-mono text-neutral-500 dark:text-neutral-300',
            sizeConfig[size].kbd
          ]"
        >
          up
        </kbd>
        <kbd
          :class="[
            'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded font-mono text-neutral-500 dark:text-neutral-300',
            sizeConfig[size].kbd
          ]"
        >
          down
        </kbd>
        <span :class="['text-neutral-400 dark:text-neutral-400', sizeConfig[size].hintText]">
          navigate
        </span>
      </div>

      <div :class="['w-px h-4 bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].divider]" />

      <!-- Select hint -->
      <div class="flex items-center gap-1 cursor-default">
        <kbd
          :class="[
            'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded font-mono text-neutral-500 dark:text-neutral-300',
            sizeConfig[size].kbd
          ]"
        >
          enter
        </kbd>
        <span :class="['text-neutral-400 dark:text-neutral-400', sizeConfig[size].hintText]">
          select
        </span>
      </div>

      <div :class="['w-px h-4 bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].divider]" />

      <!-- Close hint -->
      <div class="flex items-center gap-1 cursor-default">
        <kbd
          :class="[
            'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded font-mono text-neutral-500 dark:text-neutral-300',
            sizeConfig[size].kbd
          ]"
        >
          esc
        </kbd>
        <span :class="['text-neutral-400 dark:text-neutral-400', sizeConfig[size].hintText]">
          close
        </span>
      </div>

      <!-- Tab hint -->
      <template v-if="showTabHint">
        <div :class="['w-px h-4 bg-neutral-200 dark:bg-neutral-700', sizeConfig[size].divider]" />
        <div class="flex items-center gap-1 cursor-default">
          <kbd
            :class="[
              'bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded font-mono text-neutral-500 dark:text-neutral-300',
              sizeConfig[size].kbd
            ]"
          >
            tab
          </kbd>
          <span :class="['text-neutral-400 dark:text-neutral-400', sizeConfig[size].hintText]">
            autocomplete
          </span>
        </div>
      </template>
    </div>

    <!-- Selection actions -->
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      leave-active-class="transition-opacity duration-100 ease-out"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showActions && selectedCount > 0"
        class="flex items-center gap-2"
      >
        <span :class="['text-neutral-900 dark:text-white font-medium', sizeConfig[size].selectedText]">
          {{ selectedCount }} selected
        </span>
        <button
          :class="[
            'text-neutral-500 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white transition-colors duration-150',
            sizeConfig[size].actionButton
          ]"
          @click="$emit('clearSelection')"
        >
          Clear
        </button>
        <div class="w-px h-4 bg-neutral-200 dark:bg-neutral-700" />
        <DropdownMenu :items="dropdownItems">
          <button
            :class="[
              'group/actions flex items-center gap-1 text-neutral-600 dark:text-neutral-200 hover:text-neutral-900 dark:hover:text-white transition-colors duration-150',
              sizeConfig[size].actionButton
            ]"
          >
            <span>Actions</span>
            <Icon name="ph:caret-down" class="w-3 h-3 transition-transform duration-150 group-data-[state=open]/actions:rotate-180" />
          </button>
        </DropdownMenu>
      </div>
    </Transition>

    <!-- Title / branding -->
    <div class="flex items-center gap-2 cursor-default">
      <!-- Version badge -->
      <span
        v-if="showVersion"
        :class="[
          'text-neutral-400 dark:text-neutral-400 bg-neutral-100 dark:bg-neutral-700 rounded-full',
          sizeConfig[size].version
        ]"
      >
        v{{ version }}
      </span>

      <!-- Title with logo -->
      <div class="flex items-center gap-2">
        <div
          v-if="showLogo"
          :class="[
            'rounded bg-neutral-900 dark:bg-white flex items-center justify-center',
            sizeConfig[size].logo
          ]"
        >
          <Icon name="ph:lightning-fill" :class="['text-white dark:text-neutral-900', sizeConfig[size].logoIcon]" />
        </div>
        <span :class="['text-neutral-400 dark:text-neutral-400 font-medium', sizeConfig[size].title]">
          {{ title }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'

// Types
type FooterSize = 'sm' | 'md' | 'lg'

interface BulkAction {
  id: string
  label: string
  icon: string
  shortcut?: string
}

interface SizeConfig {
  container: string
  hints: string
  kbd: string
  hintText: string
  divider: string
  selectedText: string
  actionButton: string
  version: string
  logo: string
  logoIcon: string
  title: string
}

// Props
withDefaults(defineProps<{
  title?: string
  showActions?: boolean
  selectedCount?: number
  showTabHint?: boolean
  showVersion?: boolean
  version?: string
  showLogo?: boolean
  size?: FooterSize
}>(), {
  title: 'Command Palette',
  showActions: false,
  selectedCount: 0,
  showTabHint: true,
  showVersion: false,
  version: '1.0.0',
  showLogo: true,
  size: 'md',
})

// Emits
const emit = defineEmits<{
  clearSelection: []
  bulkAction: [actionId: string]
}>()

// Size configuration
const sizeConfig: Record<FooterSize, SizeConfig> = {
  sm: {
    container: 'px-3 py-2 text-[10px]',
    hints: 'gap-3',
    kbd: 'px-1 py-0.5 text-[9px]',
    hintText: 'text-[10px]',
    divider: 'mx-2',
    selectedText: 'text-[10px]',
    actionButton: 'text-[10px]',
    version: 'text-[9px] px-1.5 py-0.5',
    logo: 'w-4 h-4',
    logoIcon: 'w-2.5 h-2.5',
    title: 'text-[10px]',
  },
  md: {
    container: 'px-4 py-2.5 text-xs',
    hints: 'gap-4',
    kbd: 'px-1.5 py-0.5 text-[10px]',
    hintText: 'text-xs',
    divider: 'mx-3',
    selectedText: 'text-xs',
    actionButton: 'text-xs',
    version: 'text-[10px] px-2 py-0.5',
    logo: 'w-5 h-5',
    logoIcon: 'w-3 h-3',
    title: 'text-xs',
  },
  lg: {
    container: 'px-5 py-3 text-sm',
    hints: 'gap-5',
    kbd: 'px-2 py-1 text-xs',
    hintText: 'text-sm',
    divider: 'mx-4',
    selectedText: 'text-sm',
    actionButton: 'text-sm',
    version: 'text-xs px-2.5 py-1',
    logo: 'w-6 h-6',
    logoIcon: 'w-3.5 h-3.5',
    title: 'text-sm',
  },
}

// Bulk actions
const bulkActions: BulkAction[] = [
  { id: 'open-all', label: 'Open all', icon: 'ph:folder-open', shortcut: 'Cmd+O' },
  { id: 'copy', label: 'Copy links', icon: 'ph:copy', shortcut: 'Cmd+C' },
  { id: 'share', label: 'Share', icon: 'ph:share', shortcut: 'Cmd+S' },
  { id: 'archive', label: 'Archive', icon: 'ph:archive' },
]

// Dropdown items for UDropdownMenu
const dropdownItems = computed(() => [
  bulkActions.map(action => ({
    label: action.label,
    icon: action.icon,
    kbds: action.shortcut ? action.shortcut.split('+') : undefined,
    click: () => emit('bulkAction', action.id),
  })),
])
</script>
