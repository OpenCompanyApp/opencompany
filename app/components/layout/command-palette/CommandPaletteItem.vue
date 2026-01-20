<template>
  <button
    :class="[
      'w-full flex items-center text-left group relative overflow-hidden transition-all duration-150',
      sizeConfig[size].container,
      selected
        ? 'bg-olympus-primary text-white'
        : 'hover:bg-olympus-surface text-olympus-text',
      disabled && 'opacity-50 cursor-not-allowed',
      !disabled && 'cursor-pointer'
    ]"
    :disabled="disabled"
    @click="!disabled && $emit('select')"
    @mouseenter="$emit('hover')"
  >
    <!-- Selection indicator -->
    <Transition
      enter-active-class="transition-all duration-150"
      leave-active-class="transition-all duration-100"
      enter-from-class="opacity-0 scale-x-0"
      leave-to-class="opacity-0 scale-x-0"
    >
      <div
        v-if="selected"
        class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-4 bg-white rounded-r"
      />
    </Transition>

    <!-- Avatar or Icon container -->
    <div
      v-if="avatar || icon"
      :class="[
        'relative shrink-0 flex items-center justify-center transition-all duration-200',
        sizeConfig[size].iconContainer,
        selected ? 'bg-white/20' : 'bg-olympus-surface group-hover:bg-olympus-border'
      ]"
    >
      <!-- Avatar -->
      <template v-if="avatar">
        <div
          v-if="!avatar.src"
          :class="[
            'w-full h-full rounded-lg flex items-center justify-center font-medium',
            avatar.isAI
              ? 'bg-gradient-to-br from-olympus-accent to-purple-500 text-white'
              : 'bg-olympus-primary text-white',
            sizeConfig[size].avatarText
          ]"
        >
          {{ getInitials(avatar.name) }}
        </div>
        <img
          v-else
          :src="avatar.src"
          :alt="avatar.name"
          :class="['w-full h-full rounded-lg object-cover', sizeConfig[size].avatar]"
        />
        <!-- AI indicator -->
        <div
          v-if="avatar.isAI"
          class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-olympus-accent rounded-full ring-2 ring-olympus-elevated flex items-center justify-center"
        >
          <Icon name="ph:robot" class="w-2 h-2 text-white" />
        </div>
      </template>

      <!-- Icon -->
      <Icon
        v-else
        :name="icon"
        :class="[
          'transition-colors duration-150',
          sizeConfig[size].icon,
          selected ? 'text-white' : iconColor || 'text-olympus-text-muted'
        ]"
      />
    </div>

    <!-- Content -->
    <div :class="['flex-1 min-w-0', sizeConfig[size].content]">
      <!-- Label with highlight -->
      <div class="flex items-center gap-2">
        <p
          :class="[
            'font-medium truncate',
            sizeConfig[size].label
          ]"
          v-html="highlightedLabel"
        />

        <!-- Badge -->
        <span
          v-if="badge"
          :class="[
            'shrink-0 font-medium rounded-full',
            sizeConfig[size].badge,
            badgeVariantClasses[badgeVariant || 'default'],
            selected && 'bg-white/20 text-white'
          ]"
        >
          {{ badge }}
        </span>
      </div>

      <!-- Description with highlight -->
      <p
        v-if="description"
        :class="[
          'truncate',
          sizeConfig[size].description,
          selected ? 'text-white/70' : 'text-olympus-text-muted'
        ]"
        v-html="highlightedDescription"
      />
    </div>

    <!-- Meta info -->
    <div v-if="meta || shortcut" class="flex items-center gap-3 shrink-0">
      <!-- Meta text -->
      <span
        v-if="meta"
        :class="[
          'font-medium',
          sizeConfig[size].meta,
          selected ? 'text-white/60' : 'text-olympus-text-subtle'
        ]"
      >
        {{ meta }}
      </span>

      <!-- Keyboard shortcut -->
      <div v-if="shortcut" class="flex items-center gap-1">
        <kbd
          v-for="(key, index) in shortcutKeys"
          :key="index"
          :class="[
            'font-mono rounded',
            sizeConfig[size].kbd,
            selected
              ? 'bg-white/20 text-white/80'
              : 'bg-olympus-surface text-olympus-text-subtle'
          ]"
        >
          {{ key }}
        </kbd>
      </div>
    </div>

    <!-- Hover chevron -->
    <Transition
      enter-active-class="transition-all duration-150"
      leave-active-class="transition-all duration-100"
      enter-from-class="opacity-0 translate-x-2"
      leave-to-class="opacity-0 translate-x-2"
    >
      <Icon
        v-if="selected || showChevron"
        name="ph:caret-right"
        :class="[
          sizeConfig[size].chevron,
          selected ? 'text-white/70' : 'text-olympus-text-subtle opacity-0 group-hover:opacity-100'
        ]"
      />
    </Transition>

    <!-- Hover effect overlay -->
    <div
      class="absolute inset-0 bg-gradient-to-r from-olympus-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none"
      :class="selected && 'opacity-0 group-hover:opacity-0'"
    />
  </button>
</template>

<script setup lang="ts">
// Types
type ItemSize = 'sm' | 'md' | 'lg'
type BadgeVariant = 'default' | 'success' | 'warning' | 'error'

interface Avatar {
  src?: string
  name: string
  isAI?: boolean
}

interface SizeConfig {
  container: string
  iconContainer: string
  icon: string
  avatar: string
  avatarText: string
  content: string
  label: string
  description: string
  badge: string
  meta: string
  kbd: string
  chevron: string
}

// Props
const props = withDefaults(defineProps<{
  icon?: string
  iconColor?: string
  label: string
  description?: string
  shortcut?: string
  badge?: string
  badgeVariant?: BadgeVariant
  meta?: string
  avatar?: Avatar
  selected?: boolean
  disabled?: boolean
  highlight?: string
  showChevron?: boolean
  size?: ItemSize
}>(), {
  selected: false,
  disabled: false,
  showChevron: false,
  size: 'md',
})

// Emits
defineEmits<{
  select: []
  hover: []
}>()

// Size configuration
const sizeConfig: Record<ItemSize, SizeConfig> = {
  sm: {
    container: 'gap-2 px-2.5 py-2 rounded-lg',
    iconContainer: 'w-6 h-6 rounded-md',
    icon: 'w-3.5 h-3.5',
    avatar: 'w-6 h-6',
    avatarText: 'text-[10px]',
    content: 'ml-2',
    label: 'text-xs',
    description: 'text-[10px]',
    badge: 'text-[10px] px-1.5 py-0.5',
    meta: 'text-[10px]',
    kbd: 'text-[10px] px-1 py-0.5',
    chevron: 'w-3 h-3',
  },
  md: {
    container: 'gap-3 px-3 py-2.5 rounded-xl',
    iconContainer: 'w-8 h-8 rounded-lg',
    icon: 'w-4 h-4',
    avatar: 'w-8 h-8',
    avatarText: 'text-xs',
    content: 'ml-1',
    label: 'text-sm',
    description: 'text-xs',
    badge: 'text-xs px-2 py-0.5',
    meta: 'text-xs',
    kbd: 'text-xs px-1.5 py-0.5',
    chevron: 'w-3.5 h-3.5',
  },
  lg: {
    container: 'gap-4 px-4 py-3 rounded-xl',
    iconContainer: 'w-10 h-10 rounded-lg',
    icon: 'w-5 h-5',
    avatar: 'w-10 h-10',
    avatarText: 'text-sm',
    content: 'ml-1',
    label: 'text-base',
    description: 'text-sm',
    badge: 'text-sm px-2.5 py-1',
    meta: 'text-sm',
    kbd: 'text-sm px-2 py-1',
    chevron: 'w-4 h-4',
  },
}

// Badge variant classes
const badgeVariantClasses: Record<BadgeVariant, string> = {
  default: 'bg-olympus-surface text-olympus-text-muted',
  success: 'bg-olympus-success/20 text-olympus-success',
  warning: 'bg-olympus-warning/20 text-olympus-warning',
  error: 'bg-olympus-error/20 text-olympus-error',
}

// Computed
const shortcutKeys = computed(() => {
  if (!props.shortcut) return []
  return props.shortcut.split(' ')
})

const highlightedLabel = computed(() => {
  if (!props.highlight || !props.highlight.trim()) {
    return props.label
  }
  return highlightText(props.label, props.highlight)
})

const highlightedDescription = computed(() => {
  if (!props.description) return ''
  if (!props.highlight || !props.highlight.trim()) {
    return props.description
  }
  return highlightText(props.description, props.highlight)
})

// Methods
const getInitials = (name: string): string => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const highlightText = (text: string, query: string): string => {
  const cleanQuery = query.replace(/[#@]/g, '')
  if (!cleanQuery) return text

  const regex = new RegExp(`(${escapeRegex(cleanQuery)})`, 'gi')
  return text.replace(regex, '<mark class="bg-olympus-warning/30 text-inherit rounded px-0.5">$1</mark>')
}

const escapeRegex = (string: string): string => {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
</script>
