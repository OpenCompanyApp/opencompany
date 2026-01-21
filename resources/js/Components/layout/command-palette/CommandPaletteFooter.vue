<template>
  <div
    :class="[
      'flex items-center justify-between border-t border-gray-200 bg-gray-50',
      sizeConfig[size].container
    ]"
  >
    <!-- Keyboard hints -->
    <div :class="['flex items-center', sizeConfig[size].hints]">
      <!-- Navigation hints -->
      <div class="flex items-center gap-1 cursor-default">
        <kbd
          :class="[
            'bg-white border border-gray-200 rounded font-mono text-gray-500',
            sizeConfig[size].kbd
          ]"
        >
          up
        </kbd>
        <kbd
          :class="[
            'bg-white border border-gray-200 rounded font-mono text-gray-500',
            sizeConfig[size].kbd
          ]"
        >
          down
        </kbd>
        <span :class="['text-gray-400', sizeConfig[size].hintText]">
          navigate
        </span>
      </div>

      <div :class="['w-px h-4 bg-gray-200', sizeConfig[size].divider]" />

      <!-- Select hint -->
      <div class="flex items-center gap-1 cursor-default">
        <kbd
          :class="[
            'bg-white border border-gray-200 rounded font-mono text-gray-500',
            sizeConfig[size].kbd
          ]"
        >
          enter
        </kbd>
        <span :class="['text-gray-400', sizeConfig[size].hintText]">
          select
        </span>
      </div>

      <div :class="['w-px h-4 bg-gray-200', sizeConfig[size].divider]" />

      <!-- Close hint -->
      <div class="flex items-center gap-1 cursor-default">
        <kbd
          :class="[
            'bg-white border border-gray-200 rounded font-mono text-gray-500',
            sizeConfig[size].kbd
          ]"
        >
          esc
        </kbd>
        <span :class="['text-gray-400', sizeConfig[size].hintText]">
          close
        </span>
      </div>

      <!-- Tab hint -->
      <template v-if="showTabHint">
        <div :class="['w-px h-4 bg-gray-200', sizeConfig[size].divider]" />
        <div class="flex items-center gap-1 cursor-default">
          <kbd
            :class="[
              'bg-white border border-gray-200 rounded font-mono text-gray-500',
              sizeConfig[size].kbd
            ]"
          >
            tab
          </kbd>
          <span :class="['text-gray-400', sizeConfig[size].hintText]">
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
        <span :class="['text-gray-900 font-medium', sizeConfig[size].selectedText]">
          {{ selectedCount }} selected
        </span>
        <button
          :class="[
            'text-gray-500 hover:text-gray-900 transition-colors duration-150',
            sizeConfig[size].actionButton
          ]"
          @click="$emit('clearSelection')"
        >
          Clear
        </button>
        <div class="w-px h-4 bg-gray-200" />
        <DropdownMenuRoot>
          <DropdownMenuTrigger
            :class="[
              'group/actions flex items-center gap-1 text-gray-600 hover:text-gray-900 transition-colors duration-150',
              sizeConfig[size].actionButton
            ]"
          >
            <span>Actions</span>
            <Icon name="ph:caret-down" class="w-3 h-3 transition-transform duration-150 group-data-[state=open]/actions:rotate-180" />
          </DropdownMenuTrigger>
          <DropdownMenuPortal>
            <DropdownMenuContent
              class="bg-white border border-gray-200 rounded-lg p-1.5 shadow-md z-50 animate-in fade-in-0 duration-150 min-w-36"
              :side-offset="8"
              side="top"
            >
              <DropdownMenuItem
                v-for="action in bulkActions"
                :key="action.id"
                class="flex items-center gap-2 px-3 py-2 rounded-md hover:bg-gray-50 cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-gray-50"
                @click="$emit('bulkAction', action.id)"
              >
                <Icon :name="action.icon" class="w-4 h-4 text-gray-500" />
                <span class="text-gray-900">{{ action.label }}</span>
                <kbd
                  v-if="action.shortcut"
                  class="ml-auto text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded font-mono"
                >
                  {{ action.shortcut }}
                </kbd>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenuPortal>
        </DropdownMenuRoot>
      </div>
    </Transition>

    <!-- Title / branding -->
    <div class="flex items-center gap-2 cursor-default">
      <!-- Version badge -->
      <span
        v-if="showVersion"
        :class="[
          'text-gray-400 bg-gray-100 rounded-full',
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
            'rounded bg-gray-900 flex items-center justify-center',
            sizeConfig[size].logo
          ]"
        >
          <Icon name="ph:lightning-fill" :class="['text-white', sizeConfig[size].logoIcon]" />
        </div>
        <span :class="['text-gray-400 font-medium', sizeConfig[size].title]">
          {{ title }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuTrigger,
} from 'reka-ui'

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
defineEmits<{
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
</script>
