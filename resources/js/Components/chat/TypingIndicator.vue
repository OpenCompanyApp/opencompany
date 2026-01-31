<template>
  <Transition :name="transitionName">
    <div v-if="users.length > 0" :class="containerClasses">
      <!-- Avatar Stack -->
      <div :class="avatarStackClasses">
        <TransitionGroup name="avatar-pop">
          <div
            v-for="(user, index) in displayUsers"
            :key="user.id"
            :class="avatarWrapperClasses"
            :style="{ zIndex: displayUsers.length - index }"
          >
            <SharedAgentAvatar
              :user="user"
              :size="avatarSize"
              :show-status="false"
            />
            <!-- Typing ring animation -->
            <div
              v-if="showTypingRing"
              :class="typingRingClasses"
            />
          </div>
        </TransitionGroup>

        <!-- Overflow indicator -->
        <Transition name="scale">
          <div
            v-if="overflowCount > 0"
            :class="overflowBadgeClasses"
          >
            +{{ overflowCount > 99 ? '99' : overflowCount }}
          </div>
        </Transition>
      </div>

      <!-- Text Content -->
      <div :class="textContainerClasses">
        <!-- Names -->
        <span :class="namesClasses">
          {{ displayNames }}
        </span>

        <!-- Action Text -->
        <span :class="actionClasses">
          {{ actionText }}
        </span>
      </div>

      <!-- Animated Dots -->
      <div :class="dotsContainerClasses">
        <template v-if="variant === 'dots'">
          <span
            v-for="i in 3"
            :key="i"
            :class="dotClasses"
            :style="{ animationDelay: `${(i - 1) * dotDelay}ms` }"
          />
        </template>

        <!-- Wave animation variant -->
        <template v-else-if="variant === 'wave'">
          <span
            v-for="i in 5"
            :key="i"
            :class="waveBarClasses"
            :style="{ animationDelay: `${(i - 1) * 100}ms` }"
          />
        </template>

        <!-- Pulse variant -->
        <template v-else-if="variant === 'pulse'">
          <span :class="pulseClasses">
            <span :class="pulseInnerClasses" />
          </span>
        </template>

        <!-- Ellipsis variant -->
        <template v-else-if="variant === 'ellipsis'">
          <span>...</span>
        </template>
      </div>

      <!-- Activity Type Icon -->
      <div
        v-if="showActivityIcon && activityType !== 'typing'"
        :class="activityIconContainerClasses"
      >
        <Icon :name="activityIcon" :class="activityIconClasses" />
      </div>

      <!-- Duration (for long typing) -->
      <Transition name="fade">
        <span
          v-if="showDuration && typingDuration > 0"
          :class="durationClasses"
        >
          {{ formattedDuration }}
        </span>
      </Transition>

      <!-- Cancel/Dismiss Button -->
      <button
        v-if="dismissible"
        type="button"
        :class="dismissButtonClasses"
        @click="handleDismiss"
      >
        <Icon name="ph:x" class="w-3 h-3" />
      </button>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import type { User } from '@/types'

type TypingIndicatorSize = 'xs' | 'sm' | 'md' | 'lg'
type TypingIndicatorVariant = 'dots' | 'wave' | 'pulse' | 'ellipsis' | 'minimal'
type ActivityType = 'typing' | 'recording' | 'uploading' | 'thinking'

const props = withDefaults(defineProps<{
  // Core
  users: User[]

  // Appearance
  size?: TypingIndicatorSize
  variant?: TypingIndicatorVariant

  // Display options
  maxAvatars?: number
  maxNames?: number
  showAvatars?: boolean
  showTypingRing?: boolean
  showActivityIcon?: boolean
  showDuration?: boolean

  // Activity
  activityType?: ActivityType
  typingDuration?: number

  // Behavior
  dismissible?: boolean

  // Animation
  dotDelay?: number
  animated?: boolean
}>(), {
  size: 'sm',
  variant: 'dots',
  maxAvatars: 3,
  maxNames: 2,
  showAvatars: true,
  showTypingRing: true,
  showActivityIcon: true,
  showDuration: false,
  activityType: 'typing',
  typingDuration: 0,
  dismissible: false,
  dotDelay: 150,
  animated: true,
})

const emit = defineEmits<{
  dismiss: []
}>()

// Size configurations
const sizeConfig: Record<TypingIndicatorSize, {
  container: string
  avatar: 'xs' | 'sm'
  avatarSpacing: string
  text: string
  dot: string
  gap: string
}> = {
  xs: {
    container: 'py-1 px-2',
    avatar: 'xs',
    avatarSpacing: '-space-x-1.5',
    text: 'text-[10px]',
    dot: 'w-1 h-1',
    gap: 'gap-1.5',
  },
  sm: {
    container: 'py-1.5 px-2.5',
    avatar: 'xs',
    avatarSpacing: '-space-x-2',
    text: 'text-xs',
    dot: 'w-1.5 h-1.5',
    gap: 'gap-2',
  },
  md: {
    container: 'py-2 px-3',
    avatar: 'sm',
    avatarSpacing: '-space-x-2.5',
    text: 'text-sm',
    dot: 'w-2 h-2',
    gap: 'gap-2.5',
  },
  lg: {
    container: 'py-2.5 px-4',
    avatar: 'sm',
    avatarSpacing: '-space-x-3',
    text: 'text-base',
    dot: 'w-2.5 h-2.5',
    gap: 'gap-3',
  },
}

// Computed values
const displayUsers = computed(() => props.users.slice(0, props.maxAvatars))
const overflowCount = computed(() => Math.max(0, props.users.length - props.maxAvatars))
const avatarSize = computed(() => sizeConfig[props.size].avatar)

// Display names
const displayNames = computed(() => {
  const names = props.users.slice(0, props.maxNames).map(u => u.name.split(' ')[0])

  if (props.users.length === 1) {
    return names[0]
  }

  if (props.users.length === 2) {
    return `${names[0]} and ${names[1]}`
  }

  if (props.users.length <= props.maxNames) {
    return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`
  }

  return `${names.join(', ')} and ${props.users.length - props.maxNames} others`
})

// Action text based on activity type and user count
const actionText = computed(() => {
  const isPlural = props.users.length > 1

  const actions: Record<ActivityType, { singular: string; plural: string }> = {
    typing: { singular: 'is typing', plural: 'are typing' },
    recording: { singular: 'is recording', plural: 'are recording' },
    uploading: { singular: 'is uploading', plural: 'are uploading' },
    thinking: { singular: 'is thinking', plural: 'are thinking' },
  }

  return isPlural ? actions[props.activityType].plural : actions[props.activityType].singular
})

// Activity icon
const activityIcon = computed(() => {
  const icons: Record<ActivityType, string> = {
    typing: 'ph:keyboard',
    recording: 'ph:microphone',
    uploading: 'ph:cloud-arrow-up',
    thinking: 'ph:brain',
  }
  return icons[props.activityType]
})

// Formatted duration
const formattedDuration = computed(() => {
  if (props.typingDuration < 60) return `${props.typingDuration}s`
  const mins = Math.floor(props.typingDuration / 60)
  const secs = props.typingDuration % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
})

// Transition name
const transitionName = computed(() => props.animated ? 'typing-indicator' : '')

// Container classes
const containerClasses = computed(() => [
  'flex items-center text-neutral-500 dark:text-neutral-300',
  sizeConfig[props.size].container,
  sizeConfig[props.size].gap,
  props.variant === 'minimal' ? 'bg-transparent' : 'bg-neutral-50 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700',
  props.animated && 'transition-colors duration-150',
  'hover:bg-neutral-100 dark:hover:bg-neutral-700',
])

// Avatar stack classes
const avatarStackClasses = computed(() => [
  'flex items-center',
  sizeConfig[props.size].avatarSpacing,
  !props.showAvatars && 'hidden',
])

// Avatar wrapper classes
const avatarWrapperClasses = computed(() => [
  'relative',
])

// Typing ring classes
const typingRingClasses = computed(() => [
  'absolute inset-0 rounded-full pointer-events-none',
  'ring-2 ring-neutral-300 dark:ring-neutral-600',
])

// Overflow badge classes
const overflowBadgeClasses = computed(() => {
  const sizeMap: Record<TypingIndicatorSize, string> = {
    xs: 'w-4 h-4 text-[8px] -ml-1',
    sm: 'w-5 h-5 text-[9px] -ml-1.5',
    md: 'w-6 h-6 text-[10px] -ml-2',
    lg: 'w-7 h-7 text-[11px] -ml-2',
  }

  return [
    'rounded-full flex items-center justify-center',
    'bg-neutral-100 dark:bg-neutral-700 border-2 border-white dark:border-neutral-900',
    'font-medium text-neutral-500 dark:text-neutral-300',
    sizeMap[props.size],
  ]
})

// Text container classes
const textContainerClasses = computed(() => [
  'flex items-center gap-1 min-w-0',
  props.variant === 'minimal' && 'flex-1',
])

// Names classes
const namesClasses = computed(() => [
  'font-medium text-neutral-900 dark:text-white truncate',
  sizeConfig[props.size].text,
])

// Action classes
const actionClasses = computed(() => [
  'text-neutral-500 dark:text-neutral-300 whitespace-nowrap',
  sizeConfig[props.size].text,
])

// Dots container classes
const dotsContainerClasses = computed(() => [
  'flex items-center',
  props.variant === 'dots' && 'gap-0.5',
  props.variant === 'wave' && 'gap-[2px] h-4 items-end',
])

// Dot classes
const dotClasses = computed(() => [
  'rounded-full bg-neutral-400 dark:bg-neutral-500 animate-typing-dot',
  sizeConfig[props.size].dot,
])

// Wave bar classes
const waveBarClasses = computed(() => [
  'w-[3px] bg-neutral-400 dark:bg-neutral-500 rounded-full animate-typing-wave',
])

// Pulse classes
const pulseClasses = computed(() => [
  'relative flex h-3 w-3',
])

// Pulse inner classes
const pulseInnerClasses = computed(() => [
  'absolute inline-flex h-full w-full rounded-full bg-neutral-400 dark:bg-neutral-500 opacity-75',
])

// Activity icon container classes
const activityIconContainerClasses = computed(() => [
  'flex items-center justify-center',
])

// Activity icon classes
const activityIconClasses = computed(() => {
  const sizeMap: Record<TypingIndicatorSize, string> = {
    xs: 'w-3 h-3',
    sm: 'w-3.5 h-3.5',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  }

  return [sizeMap[props.size], 'text-neutral-500 dark:text-neutral-300']
})

// Duration classes
const durationClasses = computed(() => [
  'text-neutral-400 dark:text-neutral-400',
  sizeConfig[props.size].text,
])

// Dismiss button classes
const dismissButtonClasses = computed(() => [
  'p-1 rounded-lg transition-colors duration-150',
  'text-neutral-400 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white',
  'hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'focus:outline-none focus-visible:ring-1 focus-visible:ring-neutral-400 dark:focus-visible:ring-neutral-500',
])

// Handler
const handleDismiss = () => {
  emit('dismiss')
}
</script>

<style scoped>
/* Typing dot animation */
@keyframes typing-dot {
  0%, 60%, 100% {
    opacity: 0.3;
    transform: scale(0.8);
  }
  30% {
    opacity: 1;
    transform: scale(1);
  }
}

.animate-typing-dot {
  animation: typing-dot 1.4s ease-in-out infinite;
}

/* Wave animation */
@keyframes typing-wave {
  0%, 100% {
    height: 4px;
    opacity: 0.5;
  }
  50% {
    height: 16px;
    opacity: 1;
  }
}

.animate-typing-wave {
  animation: typing-wave 1s ease-in-out infinite;
}

/* Typing indicator transition */
.typing-indicator-enter-active,
.typing-indicator-leave-active {
  transition: opacity 0.15s ease-out;
}

.typing-indicator-enter-from,
.typing-indicator-leave-to {
  opacity: 0;
}

/* Avatar pop animation */
.avatar-pop-enter-active,
.avatar-pop-leave-active {
  transition: opacity 0.15s ease-out;
}

.avatar-pop-enter-from,
.avatar-pop-leave-to {
  opacity: 0;
}

.avatar-pop-move {
  transition: transform 0.15s ease-out;
}

/* Scale transition */
.scale-enter-active,
.scale-leave-active {
  transition: opacity 0.15s ease-out;
}

.scale-enter-from,
.scale-leave-to {
  opacity: 0;
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
