<script setup lang="ts">
import {
  ContextMenuRoot,
  ContextMenuTrigger,
  ContextMenuPortal,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuSub,
  ContextMenuSubTrigger,
  ContextMenuSubContent,
} from 'reka-ui'
import Icon from './Icon.vue'

interface MenuItem {
  label?: string
  icon?: string
  shortcut?: string
  disabled?: boolean
  color?: 'default' | 'error'
  click?: () => void
  children?: MenuItem[]
}

type MenuItems = (MenuItem | MenuItem[])[]

withDefaults(defineProps<{
  items?: MenuItems
}>(), {
  items: () => [],
})

const open = defineModel<boolean>('open', { default: false })
</script>

<template>
  <ContextMenuRoot v-model:open="open">
    <ContextMenuTrigger as-child>
      <slot />
    </ContextMenuTrigger>

    <ContextMenuPortal>
      <ContextMenuContent
        class="z-50 min-w-[180px] bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 shadow-lg p-1 animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
      >
        <template v-for="(group, groupIndex) in items" :key="groupIndex">
          <!-- Separator between groups -->
          <ContextMenuSeparator
            v-if="groupIndex > 0"
            class="-mx-1 my-1 h-px bg-neutral-200 dark:bg-neutral-700"
          />

          <!-- Group items -->
          <template v-for="(item, itemIndex) in (Array.isArray(group) ? group : [group])" :key="itemIndex">
            <!-- Submenu -->
            <ContextMenuSub v-if="item.children?.length">
              <ContextMenuSubTrigger
                class="flex items-center gap-2 px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-neutral-700 text-neutral-700 dark:text-neutral-200"
              >
                <Icon v-if="item.icon" :name="item.icon" class="w-4 h-4" />
                <span class="flex-1">{{ item.label }}</span>
                <Icon name="ph:caret-right" class="w-4 h-4 ml-auto" />
              </ContextMenuSubTrigger>

              <ContextMenuPortal>
                <ContextMenuSubContent
                  class="z-50 min-w-[180px] bg-white dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 shadow-lg p-1 animate-in fade-in-0 zoom-in-95"
                  :side-offset="2"
                >
                  <ContextMenuItem
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
                  </ContextMenuItem>
                </ContextMenuSubContent>
              </ContextMenuPortal>
            </ContextMenuSub>

            <!-- Regular item -->
            <ContextMenuItem
              v-else-if="item.label"
              :disabled="item.disabled"
              class="flex items-center gap-2 px-2 py-1.5 text-sm rounded cursor-pointer outline-none select-none data-[highlighted]:bg-neutral-100 dark:data-[highlighted]:bg-neutral-700 data-[disabled]:opacity-50 data-[disabled]:pointer-events-none"
              :class="item.color === 'error' ? 'text-red-600 dark:text-red-400' : 'text-neutral-700 dark:text-neutral-200'"
              @select="item.click?.()"
            >
              <Icon v-if="item.icon" :name="item.icon" class="w-4 h-4" />
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.shortcut" class="ml-auto text-xs text-neutral-400">{{ item.shortcut }}</span>
            </ContextMenuItem>
          </template>
        </template>

        <!-- Content slot for custom content -->
        <slot name="content" />
      </ContextMenuContent>
    </ContextMenuPortal>
  </ContextMenuRoot>
</template>
