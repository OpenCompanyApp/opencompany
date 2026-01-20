<template>
  <div :class="containerClasses">
    <!-- Left Section: Icon + Title + Subtitle -->
    <div :class="leftSectionClasses">
      <!-- Icon Container -->
      <div v-if="icon || $slots.icon" :class="iconContainerClasses">
        <slot name="icon">
          <Icon :name="icon!" :class="iconClasses" />
        </slot>
        <!-- Icon Badge -->
        <span
          v-if="iconBadge"
          :class="iconBadgeClasses"
        >
          {{ typeof iconBadge === 'number' && iconBadge > 99 ? '99+' : iconBadge }}
        </span>
      </div>

      <!-- Avatar (alternative to icon) -->
      <SharedAgentAvatar
        v-if="avatar && !icon"
        :user="avatar"
        :size="avatarSize"
        :show-status="showAvatarStatus"
      />

      <!-- Title Content -->
      <div :class="titleContainerClasses">
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Title -->
          <component
            :is="titleTag"
            :class="titleClasses"
          >
            <span v-if="gradient" class="text-gradient">{{ title }}</span>
            <template v-else>{{ title }}</template>
          </component>

          <!-- Title Badge -->
          <SharedBadge
            v-if="badge"
            :variant="badgeVariant"
            size="xs"
          >
            {{ badge }}
          </SharedBadge>

          <!-- Status Indicator -->
          <SharedStatusBadge
            v-if="status"
            :status="status"
            size="xs"
          />

          <!-- Verified Badge -->
          <Icon
            v-if="verified"
            name="ph:seal-check-fill"
            class="w-4 h-4 text-olympus-primary shrink-0"
          />
        </div>

        <!-- Subtitle -->
        <p v-if="subtitle || $slots.subtitle" :class="subtitleClasses">
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>

        <!-- Description -->
        <p v-if="description" :class="descriptionClasses">
          {{ description }}
        </p>

        <!-- Meta Row (timestamp, author, etc.) -->
        <div v-if="meta || timestamp || author" :class="metaClasses">
          <span v-if="author" class="flex items-center gap-1">
            <Icon name="ph:user" class="w-3 h-3" />
            {{ author }}
          </span>
          <span v-if="author && timestamp" class="text-olympus-text-subtle">•</span>
          <span v-if="timestamp" class="flex items-center gap-1">
            <Icon name="ph:clock" class="w-3 h-3" />
            {{ timestamp }}
          </span>
          <template v-if="meta">
            <span class="text-olympus-text-subtle">•</span>
            {{ meta }}
          </template>
        </div>

        <!-- Tags -->
        <div v-if="tags && tags.length > 0" class="flex flex-wrap gap-1 mt-1.5">
          <SharedBadge
            v-for="tag in displayTags"
            :key="tag"
            variant="default"
            size="xs"
          >
            {{ tag }}
          </SharedBadge>
          <SharedBadge
            v-if="remainingTags > 0"
            variant="default"
            size="xs"
          >
            +{{ remainingTags }}
          </SharedBadge>
        </div>
      </div>
    </div>

    <!-- Right Section: Actions -->
    <div :class="rightSectionClasses">
      <!-- Action Button -->
      <button
        v-if="action"
        type="button"
        :class="actionButtonClasses"
        @click="handleAction"
      >
        <Icon v-if="action.icon" :name="action.icon" :class="actionIconClasses" />
        <span v-if="action.label" :class="action.icon && 'ml-1'">{{ action.label }}</span>
      </button>

      <!-- Secondary Action -->
      <button
        v-if="secondaryAction"
        type="button"
        :class="secondaryActionButtonClasses"
        @click="handleSecondaryAction"
      >
        <Icon v-if="secondaryAction.icon" :name="secondaryAction.icon" :class="actionIconClasses" />
        <span v-if="secondaryAction.label && !secondaryAction.iconOnly">{{ secondaryAction.label }}</span>
      </button>

      <!-- Quick Actions (icon buttons) -->
      <div v-if="quickActions && quickActions.length > 0" class="flex items-center gap-1">
        <TooltipProvider
          v-for="qa in quickActions"
          :key="qa.label"
          :delay-duration="200"
        >
          <TooltipRoot>
            <TooltipTrigger as-child>
              <button
                type="button"
                :class="quickActionButtonClasses"
                :disabled="qa.disabled"
                @click="qa.onClick"
              >
                <Icon :name="qa.icon" class="w-4 h-4" />
              </button>
            </TooltipTrigger>
            <TooltipPortal>
              <TooltipContent
                class="z-50 bg-olympus-elevated border border-olympus-border rounded-lg px-2 py-1 text-xs shadow-xl"
                :side-offset="5"
              >
                {{ qa.label }}
                <TooltipArrow class="fill-olympus-elevated" />
              </TooltipContent>
            </TooltipPortal>
          </TooltipRoot>
        </TooltipProvider>
      </div>

      <!-- More Menu -->
      <DropdownMenuRoot v-if="menuItems && menuItems.length > 0">
        <DropdownMenuTrigger as-child>
          <button
            type="button"
            :class="menuButtonClasses"
            aria-label="More options"
          >
            <Icon :name="menuIcon" class="w-4 h-4" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
          <DropdownMenuContent
            class="min-w-44 bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl p-1 z-50 animate-in fade-in-0 zoom-in-95 duration-150"
            :side-offset="5"
            :align="menuAlign"
          >
            <template v-for="(item, index) in menuItems" :key="item.label">
              <DropdownMenuSeparator
                v-if="item.separator"
                class="h-px bg-olympus-border my-1 -mx-1"
              />
              <DropdownMenuItem
                :class="[
                  'flex items-center gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer outline-none transition-colors duration-150',
                  item.variant === 'danger'
                    ? 'text-red-400 hover:bg-red-500/10 focus:bg-red-500/10'
                    : 'text-olympus-text-muted hover:bg-olympus-surface focus:bg-olympus-surface hover:text-olympus-text focus:text-olympus-text',
                  item.disabled && 'opacity-50 cursor-not-allowed',
                ]"
                :disabled="item.disabled"
                @click="!item.disabled && item.onClick?.()"
              >
                <Icon v-if="item.icon" :name="item.icon" class="w-4 h-4" />
                <span class="flex-1">{{ item.label }}</span>
                <SharedBadge v-if="item.badge" size="xs" variant="primary">
                  {{ item.badge }}
                </SharedBadge>
                <Icon
                  v-if="item.external"
                  name="ph:arrow-up-right"
                  class="w-3 h-3 opacity-50"
                />
              </DropdownMenuItem>
            </template>
          </DropdownMenuContent>
        </DropdownMenuPortal>
      </DropdownMenuRoot>

      <!-- Slot for custom actions -->
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { User, AgentStatus } from '~/types'

type CardHeaderSize = 'sm' | 'md' | 'lg'
type CardHeaderVariant = 'default' | 'compact' | 'prominent' | 'minimal'
type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'error'
type MenuAlign = 'start' | 'center' | 'end'
type AvatarSize = 'xs' | 'sm' | 'md'

interface CardHeaderAction {
  label?: string
  icon?: string
  variant?: 'primary' | 'secondary' | 'ghost'
  iconOnly?: boolean
  onClick?: () => void
}

interface QuickAction {
  label: string
  icon: string
  disabled?: boolean
  onClick: () => void
}

interface MenuItem {
  label: string
  icon?: string
  variant?: 'default' | 'danger'
  badge?: string
  external?: boolean
  separator?: boolean
  disabled?: boolean
  onClick?: () => void
}

const props = withDefaults(defineProps<{
  // Core
  title: string
  subtitle?: string
  description?: string

  // Icon
  icon?: string
  iconColor?: string
  iconBg?: string
  iconBadge?: number | string

  // Avatar (alternative to icon)
  avatar?: User
  showAvatarStatus?: boolean

  // Title styling
  gradient?: boolean
  titleTag?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'span'

  // Badges & Status
  badge?: string
  badgeVariant?: BadgeVariant
  status?: AgentStatus
  verified?: boolean

  // Meta
  meta?: string
  timestamp?: string
  author?: string
  tags?: string[]
  maxTags?: number

  // Appearance
  size?: CardHeaderSize
  variant?: CardHeaderVariant
  bordered?: boolean
  sticky?: boolean

  // Actions
  action?: CardHeaderAction
  secondaryAction?: CardHeaderAction
  quickActions?: QuickAction[]
  menuItems?: MenuItem[]
  menuIcon?: string
  menuAlign?: MenuAlign
}>(), {
  titleTag: 'h2',
  gradient: false,
  badgeVariant: 'default',
  showAvatarStatus: true,
  maxTags: 3,
  size: 'md',
  variant: 'default',
  bordered: false,
  sticky: false,
  menuIcon: 'ph:dots-three',
  menuAlign: 'end',
})

const emit = defineEmits<{
  action: []
  secondaryAction: []
}>()

// Avatar size mapping
const avatarSizeMap: Record<CardHeaderSize, AvatarSize> = {
  sm: 'xs',
  md: 'sm',
  lg: 'md',
}

const avatarSize = computed(() => avatarSizeMap[props.size])

// Tags display
const displayTags = computed(() => props.tags?.slice(0, props.maxTags) || [])
const remainingTags = computed(() => Math.max(0, (props.tags?.length || 0) - props.maxTags))

// Size configurations
const sizeConfig: Record<CardHeaderSize, {
  gap: string
  iconContainer: string
  icon: string
  title: string
  subtitle: string
  description: string
}> = {
  sm: {
    gap: 'gap-2',
    iconContainer: 'w-7 h-7',
    icon: 'w-4 h-4',
    title: 'text-sm',
    subtitle: 'text-xs',
    description: 'text-xs',
  },
  md: {
    gap: 'gap-3',
    iconContainer: 'w-9 h-9',
    icon: 'w-5 h-5',
    title: 'text-base',
    subtitle: 'text-xs',
    description: 'text-sm',
  },
  lg: {
    gap: 'gap-4',
    iconContainer: 'w-11 h-11',
    icon: 'w-6 h-6',
    title: 'text-lg',
    subtitle: 'text-sm',
    description: 'text-sm',
  },
}

// Container classes
const containerClasses = computed(() => [
  'flex items-start justify-between',
  sizeConfig[props.size].gap,
  props.bordered && 'pb-4 border-b border-olympus-border',
  props.sticky && 'sticky top-0 z-10 bg-olympus-bg/95 backdrop-blur-sm py-2',
  props.variant === 'compact' && 'py-0',
  props.variant === 'prominent' && 'py-2',
])

// Left section classes
const leftSectionClasses = computed(() => [
  'flex items-start',
  sizeConfig[props.size].gap,
  'min-w-0 flex-1',
])

// Icon container classes
const iconContainerClasses = computed(() => [
  'relative shrink-0 rounded-lg flex items-center justify-center transition-all duration-200',
  sizeConfig[props.size].iconContainer,
  props.iconBg || 'bg-olympus-primary/20',
])

// Icon classes
const iconClasses = computed(() => [
  sizeConfig[props.size].icon,
  props.iconColor || 'text-olympus-primary',
])

// Icon badge classes
const iconBadgeClasses = computed(() => [
  'absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full',
  'bg-red-500 text-white text-[10px] font-bold',
  'flex items-center justify-center',
])

// Title container classes
const titleContainerClasses = computed(() => [
  'min-w-0 flex-1',
])

// Title classes
const titleClasses = computed(() => [
  'font-semibold text-olympus-text',
  sizeConfig[props.size].title,
  props.variant === 'minimal' && 'font-medium',
])

// Subtitle classes
const subtitleClasses = computed(() => [
  'text-olympus-text-muted mt-0.5',
  sizeConfig[props.size].subtitle,
])

// Description classes
const descriptionClasses = computed(() => [
  'text-olympus-text-muted mt-1 line-clamp-2',
  sizeConfig[props.size].description,
])

// Meta classes
const metaClasses = computed(() => [
  'flex items-center gap-2 text-olympus-text-muted mt-1',
  sizeConfig[props.size].subtitle,
])

// Right section classes
const rightSectionClasses = computed(() => [
  'flex items-center gap-2 shrink-0',
])

// Action button classes
const actionButtonClasses = computed(() => {
  const variant = props.action?.variant || 'primary'
  const baseClasses = [
    'inline-flex items-center rounded-lg text-sm font-medium transition-all duration-200',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
  ]

  if (variant === 'primary') {
    baseClasses.push(
      'px-3 py-1.5',
      'text-olympus-primary hover:text-olympus-primary-hover',
      'hover:bg-olympus-primary/10',
    )
  } else if (variant === 'secondary') {
    baseClasses.push(
      'px-3 py-1.5',
      'bg-olympus-surface text-olympus-text',
      'hover:bg-olympus-elevated',
    )
  } else {
    baseClasses.push(
      'px-2 py-1',
      'text-olympus-text-muted hover:text-olympus-text',
      'hover:bg-olympus-surface',
    )
  }

  return baseClasses
})

// Secondary action button classes
const secondaryActionButtonClasses = computed(() => [
  'inline-flex items-center rounded-lg text-sm transition-all duration-200',
  'px-2 py-1.5',
  'text-olympus-text-muted hover:text-olympus-text',
  'hover:bg-olympus-surface',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Action icon classes
const actionIconClasses = computed(() => [
  'w-4 h-4',
])

// Quick action button classes
const quickActionButtonClasses = computed(() => [
  'p-1.5 rounded-lg transition-all duration-200',
  'text-olympus-text-muted hover:text-olympus-text',
  'hover:bg-olympus-surface',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
  'disabled:opacity-50 disabled:cursor-not-allowed',
])

// Menu button classes
const menuButtonClasses = computed(() => [
  'p-1.5 rounded-lg transition-all duration-200',
  'text-olympus-text-muted hover:text-olympus-text',
  'hover:bg-olympus-surface',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Handlers
const handleAction = () => {
  if (props.action?.onClick) {
    props.action.onClick()
  }
  emit('action')
}

const handleSecondaryAction = () => {
  if (props.secondaryAction?.onClick) {
    props.secondaryAction.onClick()
  }
  emit('secondaryAction')
}
</script>

<style scoped>
/* Gradient text */
.text-gradient {
  background: linear-gradient(135deg, oklch(var(--color-olympus-primary)), oklch(var(--color-olympus-primary-light, var(--color-olympus-primary))));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
</style>
