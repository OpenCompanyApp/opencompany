<script setup lang="ts">
import {
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubTrigger,
  DropdownMenuSubContent,
  DropdownMenuLabel,
} from 'reka-ui'
import Icon from './Icon.vue'

interface MenuItem {
  label?: string
  icon?: string
  shortcut?: string
  disabled?: boolean
  color?: 'default' | 'error'
  slot?: string
  click?: () => void
  children?: MenuItem[]
}

type MenuItems = (MenuItem | MenuItem[])[]

withDefaults(defineProps<{
  items?: MenuItems
  side?: 'top' | 'right' | 'bottom' | 'left'
  align?: 'start' | 'center' | 'end'
  sideOffset?: number
}>(), {
  items: () => [],
  side: 'bottom',
  align: 'start',
  sideOffset: 4,
})
</script>

<template>
  <DropdownMenuRoot>
    <DropdownMenuTrigger as-child>
      <slot />
    </DropdownMenuTrigger>

    <DropdownMenuPortal>
      <DropdownMenuContent
        class="z-50 min-w-[180px] bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 shadow-lg p-1 animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
        :side="side"
        :align="align"
        :side-offset="sideOffset"
      >
        <template v-for="(group, groupIndex) in items" :key="groupIndex">
          <!-- Separator between groups -->
          <DropdownMenuSeparator
            v-if="groupIndex > 0"
            class="-mx-1 my-1 h-px bg-neutral-200 dark:bg-neutral-700"
          />

          <!-- Group items -->
          <template v-for="(item, itemIndex) in (Array.isArray(group) ? group : [group])" :key="itemIndex">
            <!-- Slot item -->
            <DropdownMenuLabel v-if="item.slot === 'header'" class="px-2 py-1.5">
              <slot name="header" />
            </DropdownMenuLabel>

            <!-- Submenu -->
            <DropdownMenuSub v-else-if="item.children?.length">
              <DropdownMenuSubTrigger
                class="flex items-center gap-2 px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-neutral-700 text-neutral-700 dark:text-neutral-200"
              >
                <Icon v-if="item.icon" :name="item.icon" class="w-4 h-4" />
                <span class="flex-1">{{ item.label }}</span>
                <Icon name="ph:caret-right" class="w-4 h-4 ml-auto" />
              </DropdownMenuSubTrigger>

              <DropdownMenuPortal>
                <DropdownMenuSubContent
                  class="z-50 min-w-[180px] bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 shadow-lg p-1 animate-in fade-in-0 zoom-in-95"
                  :side-offset="2"
                >
                  <DropdownMenuItem
                    v-for="(child, childIndex) in item.children"
                    :key="childIndex"
                    :disabled="child.disabled"
                    class="flex items-center gap-2 px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-neutral-700 data-[disabled]:opacity-50 data-[disabled]:pointer-events-none"
                    :class="child.color === 'error' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'"
                    @select="child.click?.()"
                  >
                    <Icon v-if="child.icon" :name="child.icon" class="w-4 h-4" />
                    <span class="flex-1">{{ child.label }}</span>
                    <span v-if="child.shortcut" class="ml-auto text-xs text-neutral-400">{{ child.shortcut }}</span>
                  </DropdownMenuItem>
                </DropdownMenuSubContent>
              </DropdownMenuPortal>
            </DropdownMenuSub>

            <!-- Regular item -->
            <DropdownMenuItem
              v-else-if="item.label"
              :disabled="item.disabled"
              class="flex items-center gap-2 px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-neutral-700 data-[disabled]:opacity-50 data-[disabled]:pointer-events-none"
              :class="item.color === 'error' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'"
              @select="item.click?.()"
            >
              <Icon v-if="item.icon" :name="item.icon" class="w-4 h-4" />
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.shortcut" class="ml-auto text-xs text-neutral-400">{{ item.shortcut }}</span>
            </DropdownMenuItem>
          </template>
        </template>

        <!-- Content slot for custom content -->
        <slot name="content" />
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>
