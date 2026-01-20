import type { AgentType, AgentStatus } from '~/types'

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg'

export const containerSizes: Record<AvatarSize, string> = {
  xs: 'w-6 h-6',
  sm: 'w-8 h-8',
  md: 'w-10 h-10',
  lg: 'w-12 h-12',
}

export const avatarSizes: Record<AvatarSize, string> = {
  xs: 'w-6 h-6',
  sm: 'w-8 h-8',
  md: 'w-10 h-10',
  lg: 'w-12 h-12',
}

export const iconSizes: Record<AvatarSize, string> = {
  xs: 'w-3 h-3',
  sm: 'w-4 h-4',
  md: 'w-5 h-5',
  lg: 'w-6 h-6',
}

export const textSizes: Record<AvatarSize, string> = {
  xs: 'text-xs',
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
}

export const dotSizes: Record<AvatarSize, string> = {
  xs: 'w-2 h-2',
  sm: 'w-2.5 h-2.5',
  md: 'w-3 h-3',
  lg: 'w-3.5 h-3.5',
}

export const dotPositions: Record<AvatarSize, string> = {
  xs: '-bottom-0.5 -right-0.5',
  sm: 'bottom-0 right-0',
  md: 'bottom-0 right-0',
  lg: 'bottom-0.5 right-0.5',
}

export const agentIcons: Record<AgentType, string> = {
  manager: 'ph:crown-fill',
  writer: 'ph:pencil-fill',
  analyst: 'ph:chart-line-fill',
  creative: 'ph:palette-fill',
  researcher: 'ph:magnifying-glass-fill',
  coder: 'ph:terminal-fill',
  coordinator: 'ph:calendar-fill',
}

export const agentBgColors: Record<AgentType, string> = {
  manager: 'bg-amber-600/80',
  writer: 'bg-blue-600/80',
  analyst: 'bg-indigo-600/80',
  creative: 'bg-pink-600/80',
  researcher: 'bg-emerald-600/80',
  coder: 'bg-cyan-600/80',
  coordinator: 'bg-orange-600/80',
}

export const statusColors: Record<AgentStatus, string> = {
  idle: 'bg-gray-400',
  working: 'bg-green-500 shadow-sm shadow-green-500/50',
  offline: 'bg-gray-600',
}
