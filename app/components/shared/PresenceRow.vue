<template>
  <TooltipProvider :delay-duration="tooltipDelay">
    <TooltipRoot>
      <TooltipTrigger as-child>
        <component
          :is="interactive ? 'button' : 'div'"
          :type="interactive ? 'button' : undefined"
          :class="containerClasses"
          @click="handleClick"
          @mouseenter="isHovered = true"
          @mouseleave="isHovered = false"
        >
          <!-- Avatar Stack -->
          <div :class="avatarStackClasses">
            <TransitionGroup name="avatar-stack" tag="div" class="flex -space-x-2">
              <div
                v-for="(user, index) in displayUsers"
                :key="user.id"
                :class="avatarWrapperClasses"
                :style="{ zIndex: displayUsers.length - index }"
              >
                <SharedAgentAvatar
                  :user="user"
                  :size="avatarSize"
                  :show-status="showStatus"
                  :stacked="true"
                  :stack-index="index"
                />
                <!-- Presence indicator ring -->
                <div
                  v-if="showPresenceRing && user.presence"
                  :class="presenceRingClasses(user.presence)"
                />
              </div>
            </TransitionGroup>

            <!-- Overflow Badge -->
            <Transition name="scale">
              <div
                v-if="remaining > 0"
                :class="overflowBadgeClasses"
              >
                +{{ remaining > 99 ? '99' : remaining }}
              </div>
            </Transition>
          </div>

          <!-- Label Section -->
          <div v-if="showLabel || showNames" :class="labelSectionClasses">
            <!-- Names -->
            <span v-if="showNames && displayNames" :class="namesClasses">
              {{ displayNames }}
            </span>

            <!-- Label -->
            <span v-if="showLabel" :class="labelClasses">
              {{ computedLabel }}
            </span>
          </div>

          <!-- Activity Indicator -->
          <div v-if="showActivity && activityType" :class="activityIndicatorClasses">
            <Icon :name="activityIcon" :class="activityIconClasses" />
            <span v-if="activityText" class="text-xs text-olympus-text-muted">
              {{ activityText }}
            </span>
          </div>

          <!-- Live Indicator -->
          <div
            v-if="live"
            class="flex items-center gap-1.5 ml-2"
          >
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75" />
              <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500" />
            </span>
            <span class="text-xs text-green-400 font-medium">LIVE</span>
          </div>
        </component>
      </TooltipTrigger>

      <!-- Tooltip -->
      <TooltipPortal>
        <TooltipContent
          :class="tooltipContentClasses"
          :side="tooltipSide"
          :side-offset="5"
        >
          <!-- Tooltip Header -->
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-olympus-text-muted font-medium uppercase tracking-wider">
              {{ tooltipTitle }}
            </p>
            <SharedBadge v-if="users.length > 0" size="xs" variant="default">
              {{ users.length }}
            </SharedBadge>
          </div>

          <!-- User List -->
          <ul :class="tooltipListClasses">
            <li
              v-for="user in tooltipUsers"
              :key="user.id"
              :class="tooltipUserClasses"
            >
              <SharedAgentAvatar
                :user="user"
                size="xs"
                :show-status="true"
                :show-tooltip="false"
              />
              <div class="flex-1 min-w-0">
                <span class="text-sm text-olympus-text truncate block">{{ user.name }}</span>
                <span v-if="getUserPresence(user)" class="text-xs text-olympus-text-muted">
                  {{ getPresenceLabel(getUserPresence(user)!) }}
                </span>
              </div>
              <!-- User-specific action in tooltip -->
              <button
                v-if="userAction"
                type="button"
                class="p-1 rounded text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors"
                @click.stop="handleUserAction(user)"
              >
                <Icon :name="userAction.icon" class="w-3.5 h-3.5" />
              </button>
            </li>
          </ul>

          <!-- View All Link -->
          <button
            v-if="users.length > maxTooltipUsers && viewAllAction"
            type="button"
            class="w-full mt-2 pt-2 border-t border-olympus-border text-xs text-olympus-primary hover:text-olympus-primary-hover transition-colors text-center"
            @click="viewAllAction"
          >
            View all {{ users.length }} users
          </button>

          <TooltipArrow class="fill-olympus-elevated" />
        </TooltipContent>
      </TooltipPortal>
    </TooltipRoot>
  </TooltipProvider>
</template>

<script setup lang="ts">
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { User } from '~/types'

type PresenceRowSize = 'xs' | 'sm' | 'md' | 'lg'
type PresenceRowVariant = 'default' | 'compact' | 'inline' | 'card'
type ActivityType = 'typing' | 'editing' | 'viewing' | 'idle'
type TooltipSide = 'top' | 'right' | 'bottom' | 'left'
type AvatarSize = 'xs' | 'sm' | 'md'

interface UserAction {
  icon: string
  label: string
}

interface UserPresenceMap {
  [userId: string]: ActivityType
}

const props = withDefaults(defineProps<{
  // Core
  users: User[]
  max?: number

  // Labels
  showLabel?: boolean
  label?: string
  showNames?: boolean
  maxNames?: number

  // Appearance
  size?: PresenceRowSize
  variant?: PresenceRowVariant

  // Avatar options
  showStatus?: boolean
  showPresenceRing?: boolean

  // Tooltip
  tooltipTitle?: string
  tooltipSide?: TooltipSide
  tooltipDelay?: number
  maxTooltipUsers?: number

  // Activity
  showActivity?: boolean
  activityType?: ActivityType
  activityText?: string

  // User presence map (for per-user presence)
  userPresence?: UserPresenceMap

  // Live indicator
  live?: boolean

  // Interactive
  interactive?: boolean

  // Actions
  userAction?: UserAction
  viewAllAction?: () => void
}>(), {
  max: 4,
  showLabel: true,
  label: 'viewing',
  showNames: false,
  maxNames: 2,
  size: 'md',
  variant: 'default',
  showStatus: false,
  showPresenceRing: false,
  tooltipTitle: 'Currently viewing',
  tooltipSide: 'top',
  tooltipDelay: 300,
  maxTooltipUsers: 10,
  showActivity: false,
  live: false,
  interactive: false,
})

const emit = defineEmits<{
  click: [users: User[]]
  userAction: [user: User]
}>()

const isHovered = ref(false)

// Avatar size mapping
const avatarSizeMap: Record<PresenceRowSize, AvatarSize> = {
  xs: 'xs',
  sm: 'xs',
  md: 'sm',
  lg: 'md',
}

const avatarSize = computed(() => avatarSizeMap[props.size])

// Computed users
const displayUsers = computed(() => props.users.slice(0, props.max))
const remaining = computed(() => Math.max(0, props.users.length - props.max))
const tooltipUsers = computed(() => props.users.slice(0, props.maxTooltipUsers))

// Display names
const displayNames = computed(() => {
  if (!props.showNames) return ''
  const names = displayUsers.value.slice(0, props.maxNames).map(u => u.name.split(' ')[0])
  if (props.users.length > props.maxNames) {
    return `${names.join(', ')} and ${props.users.length - props.maxNames} more`
  }
  if (names.length > 1) {
    return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`
  }
  return names[0] || ''
})

// Computed label
const computedLabel = computed(() => {
  if (props.users.length === 0) return ''
  if (props.users.length === 1) return props.label.replace(/s$/, '') // Remove plural 's'
  return props.label
})

// Activity icon
const activityIcon = computed(() => {
  const icons: Record<ActivityType, string> = {
    typing: 'ph:chat-dots',
    editing: 'ph:pencil-simple',
    viewing: 'ph:eye',
    idle: 'ph:moon',
  }
  return icons[props.activityType || 'viewing']
})

// Size classes
const sizeClasses: Record<PresenceRowSize, { gap: string; text: string }> = {
  xs: { gap: 'gap-1', text: 'text-[10px]' },
  sm: { gap: 'gap-1.5', text: 'text-xs' },
  md: { gap: 'gap-2', text: 'text-xs' },
  lg: { gap: 'gap-2.5', text: 'text-sm' },
}

// Get user presence
const getUserPresence = (user: User): ActivityType | undefined => {
  return props.userPresence?.[user.id]
}

// Get presence label
const getPresenceLabel = (presence: ActivityType): string => {
  const labels: Record<ActivityType, string> = {
    typing: 'Typing...',
    editing: 'Editing',
    viewing: 'Viewing',
    idle: 'Idle',
  }
  return labels[presence]
}

// Presence ring classes
const presenceRingClasses = (presence: ActivityType) => {
  const baseClasses = 'absolute inset-0 rounded-full animate-ping pointer-events-none'
  const colorClasses: Record<ActivityType, string> = {
    typing: 'bg-olympus-primary/30',
    editing: 'bg-amber-500/30',
    viewing: 'bg-blue-500/30',
    idle: 'bg-gray-500/30',
  }
  return [baseClasses, colorClasses[presence]]
}

// Container classes
const containerClasses = computed(() => {
  const classes = [
    'flex items-center cursor-default transition-all duration-200',
    sizeClasses[props.size].gap,
  ]

  // Variant styling
  switch (props.variant) {
    case 'compact':
      // Minimal padding
      break
    case 'inline':
      classes.push('inline-flex')
      break
    case 'card':
      classes.push(
        'p-2 rounded-lg bg-olympus-surface/50',
        'border border-transparent',
        'hover:bg-olympus-surface hover:border-olympus-border',
      )
      break
    default:
      break
  }

  // Interactive
  if (props.interactive) {
    classes.push(
      'cursor-pointer',
      'hover:opacity-80',
      'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 rounded-full',
    )
  }

  return classes
})

// Avatar stack classes
const avatarStackClasses = computed(() => [
  'flex items-center',
])

// Avatar wrapper classes
const avatarWrapperClasses = computed(() => [
  'relative transition-transform duration-200',
  isHovered.value && 'first:-translate-x-0 last:translate-x-0',
])

// Overflow badge classes
const overflowBadgeClasses = computed(() => {
  const sizeMap: Record<PresenceRowSize, string> = {
    xs: 'w-5 h-5 text-[9px] -ml-1',
    sm: 'w-6 h-6 text-[10px] -ml-1.5',
    md: 'w-7 h-7 text-[11px] -ml-2',
    lg: 'w-8 h-8 text-xs -ml-2',
  }

  return [
    'rounded-full flex items-center justify-center',
    'bg-olympus-surface border-2 border-olympus-bg',
    'font-medium text-olympus-text-muted',
    sizeMap[props.size],
  ]
})

// Label section classes
const labelSectionClasses = computed(() => [
  'flex flex-col min-w-0',
  props.variant === 'compact' && 'ml-1',
])

// Names classes
const namesClasses = computed(() => [
  'text-olympus-text font-medium truncate',
  sizeClasses[props.size].text,
])

// Label classes
const labelClasses = computed(() => [
  'text-olympus-text-subtle',
  sizeClasses[props.size].text,
])

// Activity indicator classes
const activityIndicatorClasses = computed(() => [
  'flex items-center gap-1 ml-2',
])

// Activity icon classes
const activityIconClasses = computed(() => {
  const baseClasses = 'w-3.5 h-3.5'
  const colorClasses: Record<ActivityType, string> = {
    typing: 'text-olympus-primary animate-pulse',
    editing: 'text-amber-400',
    viewing: 'text-blue-400',
    idle: 'text-gray-400',
  }
  return [baseClasses, colorClasses[props.activityType || 'viewing']]
})

// Tooltip content classes
const tooltipContentClasses = computed(() => [
  'bg-olympus-elevated border border-olympus-border rounded-xl',
  'px-3 py-2.5 text-sm shadow-xl max-w-xs z-50',
  'animate-in fade-in-0 zoom-in-95 duration-150',
])

// Tooltip list classes
const tooltipListClasses = computed(() => [
  'space-y-2 max-h-64 overflow-y-auto',
])

// Tooltip user classes
const tooltipUserClasses = computed(() => [
  'flex items-center gap-2',
])

// Handlers
const handleClick = () => {
  if (props.interactive) {
    emit('click', props.users)
  }
}

const handleUserAction = (user: User) => {
  emit('userAction', user)
}
</script>

<style scoped>
/* Avatar stack animation */
.avatar-stack-enter-active,
.avatar-stack-leave-active {
  transition: all 0.3s ease;
}

.avatar-stack-enter-from {
  opacity: 0;
  transform: scale(0.8) translateX(-10px);
}

.avatar-stack-leave-to {
  opacity: 0;
  transform: scale(0.8) translateX(10px);
}

.avatar-stack-move {
  transition: transform 0.3s ease;
}

/* Scale transition */
.scale-enter-active,
.scale-leave-active {
  transition: all 0.2s ease;
}

.scale-enter-from,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.8);
}
</style>
