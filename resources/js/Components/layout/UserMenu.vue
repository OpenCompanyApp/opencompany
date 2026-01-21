<template>
  <div
    :class="[
      'border-t border-gray-200 transition-colors duration-150',
      sizeConfig[size].padding,
      collapsed && 'px-2'
    ]"
  >
    <DropdownMenuRoot>
      <TooltipProvider :delay-duration="300">
        <TooltipRoot :disabled="!collapsed">
          <TooltipTrigger as-child>
          <DropdownMenuTrigger
            :class="[
              'w-full flex items-center rounded-lg transition-colors duration-150 cursor-pointer outline-none group relative overflow-hidden',
              sizeConfig[size].trigger,
              collapsed && 'justify-center p-2',
              'hover:bg-gray-100',
              'focus-visible:ring-1 focus-visible:ring-gray-400'
            ]"
          >
            <!-- User avatar -->
            <div
              :class="[
                'relative shrink-0',
                sizeConfig[size].avatar
              ]"
            >
              <!-- Avatar with solid background -->
              <div
                v-if="!currentUser.avatar"
                :class="[
                  'w-full h-full rounded-full flex items-center justify-center text-white font-semibold bg-gray-600',
                  sizeConfig[size].avatarText
                ]"
              >
                {{ getInitials(currentUser.name) }}
              </div>
              <img
                v-else
                :src="currentUser.avatar"
                :alt="currentUser.name"
                :class="[
                  'w-full h-full rounded-full object-cover ring-2 ring-gray-200'
                ]"
              />

              <!-- Status indicator -->
              <span
                :class="[
                  'absolute rounded-full ring-2 ring-white',
                  statusColors[userStatusValue],
                  sizeConfig[size].statusIndicator
                ]"
              />
            </div>

            <!-- User info (hidden when collapsed) -->
            <Transition
              enter-active-class="transition-opacity duration-150 ease-out"
              leave-active-class="transition-opacity duration-100 ease-out"
              enter-from-class="opacity-0"
              leave-to-class="opacity-0"
            >
              <div v-if="!collapsed" class="flex-1 text-left min-w-0 ml-3">
                <div class="flex items-center gap-2">
                  <p :class="['font-medium truncate text-gray-900', sizeConfig[size].name]">
                    {{ currentUser.name }}
                  </p>
                  <span
                    v-if="userRoleValue === 'admin'"
                    class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-gray-100 text-gray-600 rounded"
                  >
                    Admin
                  </span>
                </div>
                <p :class="['text-gray-500 truncate', sizeConfig[size].status]">
                  {{ statusLabels[userStatusValue] }}
                </p>
              </div>
            </Transition>

            <!-- Dropdown chevron -->
            <Icon
              v-if="!collapsed"
              name="ph:caret-up-down"
              class="w-4 h-4 text-gray-400 shrink-0 transition-colors duration-150 group-hover:text-gray-600"
            />
          </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipPortal>
            <TooltipContent
              side="right"
              :side-offset="12"
              class="bg-white border border-gray-200 px-3 py-2.5 rounded-lg shadow-md animate-in fade-in-0 duration-150"
            >
              <div class="font-medium text-sm text-gray-900">{{ currentUser.name }}</div>
              <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                <span
                  :class="['w-2 h-2 rounded-full', statusColors[userStatusValue]]"
                />
                {{ statusLabels[userStatusValue] }}
              </div>
              <TooltipArrow class="fill-white" />
            </TooltipContent>
          </TooltipPortal>
        </TooltipRoot>
      </TooltipProvider>

      <DropdownMenuPortal>
        <DropdownMenuContent
          :class="[
            'bg-white border border-gray-200 rounded-lg shadow-md z-50 overflow-hidden',
            'animate-in fade-in-0 duration-150',
            sizeConfig[size].dropdown
          ]"
          :side-offset="8"
          side="top"
        >
          <!-- User header in dropdown -->
          <div class="px-3 py-3 border-b border-gray-200">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center text-white font-semibold text-sm">
                {{ getInitials(currentUser.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium truncate text-gray-900">{{ currentUser.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ currentUser.email || 'user@example.com' }}</p>
              </div>
            </div>
          </div>

          <!-- Status selector -->
          <DropdownMenuSub>
            <DropdownMenuSubTrigger
              class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-gray-50"
            >
              <span
                :class="['w-2.5 h-2.5 rounded-full', statusColors[userStatusValue]]"
              />
              <span class="flex-1 text-gray-900">{{ statusLabels[userStatusValue] }}</span>
              <Icon name="ph:caret-right" class="w-4 h-4 text-gray-400" />
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
              <DropdownMenuSubContent
                class="bg-white border border-gray-200 rounded-lg p-1.5 shadow-md z-50 animate-in fade-in-0 duration-150 min-w-40"
                :side-offset="8"
              >
                <DropdownMenuItem
                  v-for="status in statusOptions"
                  :key="status.value"
                  :class="[
                    'flex items-center gap-3 px-3 py-2 rounded-md cursor-pointer outline-none transition-colors duration-150 text-sm',
                    userStatusValue === status.value ? 'bg-gray-100' : 'hover:bg-gray-50 focus:bg-gray-50'
                  ]"
                  @click="setStatus(status.value)"
                >
                  <span :class="['w-2.5 h-2.5 rounded-full', statusColors[status.value as UserStatus]]" />
                  <span class="flex-1 text-gray-900">{{ status.label }}</span>
                  <Icon
                    v-if="userStatusValue === status.value"
                    name="ph:check"
                    class="w-4 h-4 text-gray-600"
                  />
                </DropdownMenuItem>
              </DropdownMenuSubContent>
            </DropdownMenuPortal>
          </DropdownMenuSub>

          <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />

          <!-- Menu items -->
          <DropdownMenuItem
            v-for="item in menuItems"
            :key="item.id"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-md cursor-pointer outline-none transition-colors duration-150 text-sm',
              'hover:bg-gray-50 focus:bg-gray-50'
            ]"
            @click="handleMenuAction(item.id)"
          >
            <div
              :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center',
                'bg-gray-100'
              ]"
            >
              <Icon :name="item.icon" class="w-4 h-4 text-gray-500" />
            </div>
            <div class="flex-1">
              <span class="text-gray-900">{{ item.label }}</span>
              <p v-if="item.description" class="text-xs text-gray-500 mt-0.5">
                {{ item.description }}
              </p>
            </div>
            <kbd
              v-if="item.shortcut"
              class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded font-mono"
            >
              {{ item.shortcut }}
            </kbd>
            <Icon
              v-if="item.external"
              name="ph:arrow-square-out"
              class="w-3.5 h-3.5 text-gray-400"
            />
          </DropdownMenuItem>

          <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />

          <!-- Theme toggle -->
          <div class="px-3 py-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <Icon
                  :name="isDark ? 'ph:moon-fill' : 'ph:sun-fill'"
                  class="w-4 h-4 text-gray-500"
                />
                <span class="text-sm text-gray-900">{{ isDark ? 'Dark' : 'Light' }} mode</span>
              </div>
              <button
                class="relative w-11 h-6 rounded-full transition-colors duration-150 focus:outline-none focus-visible:ring-1 focus-visible:ring-gray-400"
                :class="isDark ? 'bg-gray-900' : 'bg-gray-200'"
                @click="toggleTheme"
              >
                <span
                  :class="[
                    'absolute top-1 w-4 h-4 rounded-full bg-white shadow-sm transition-all duration-150',
                    isDark ? 'left-6' : 'left-1'
                  ]"
                />
              </button>
            </div>
          </div>

          <DropdownMenuSeparator class="h-px bg-gray-200 my-1" />

          <!-- Sign out -->
          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-red-50 cursor-pointer outline-none transition-colors duration-150 text-sm text-red-600 focus:bg-red-50 mx-1.5 mb-1.5"
            @click="handleSignOut"
          >
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-50">
              <Icon name="ph:sign-out" class="w-4 h-4" />
            </div>
            <span class="font-medium">Sign out</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenuPortal>
    </DropdownMenuRoot>

    <!-- Sign Out Confirmation Dialog -->
    <ConfirmDialog
      v-model:open="confirmDialogOpen"
      :title="confirmDialogOptions.title"
      :description="confirmDialogOptions.description"
      :confirm-label="confirmDialogOptions.confirmLabel"
      :cancel-label="confirmDialogOptions.cancelLabel"
      :variant="confirmDialogOptions.variant"
      :loading="confirmDialogLoading"
      @confirm="handleConfirmDialogConfirm"
      @cancel="handleConfirmDialogCancel"
    />

    <!-- Set custom status dialog -->
    <AlertDialogRoot v-model:open="customStatusDialogOpen">
      <AlertDialogPortal>
        <AlertDialogOverlay class="fixed inset-0 bg-black/50 z-50 animate-in fade-in-0 duration-150" />
        <AlertDialogContent class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white border border-gray-200 rounded-lg p-6 shadow-lg z-50 animate-in fade-in-0 duration-150">
          <AlertDialogTitle class="text-lg font-semibold mb-2 text-gray-900">
            Set custom status
          </AlertDialogTitle>
          <AlertDialogDescription class="text-sm text-gray-500 mb-4">
            Let others know what you're up to
          </AlertDialogDescription>

          <div class="space-y-4">
            <!-- Emoji picker trigger -->
            <div class="flex items-center gap-3">
              <button
                class="w-12 h-12 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors duration-150 flex items-center justify-center text-2xl"
              >
                {{ customStatusEmoji || '&#128522;' }}
              </button>
              <input
                v-model="customStatusText"
                type="text"
                placeholder="What's your status?"
                class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-gray-400 focus:ring-1 focus:ring-gray-400 transition-colors duration-150"
              />
            </div>

            <!-- Quick status options -->
            <div class="flex flex-wrap gap-2">
              <button
                v-for="quick in quickStatuses"
                :key="quick.text"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-150"
                @click="setQuickStatus(quick)"
              >
                <span>{{ quick.emoji }}</span>
                <span>{{ quick.text }}</span>
              </button>
            </div>

            <!-- Clear after selector -->
            <div class="flex items-center justify-between text-sm">
              <span class="text-gray-500">Clear after</span>
              <select
                v-model="clearAfter"
                class="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 focus:outline-none focus:border-gray-400 focus:ring-1 focus:ring-gray-400 transition-colors duration-150 cursor-pointer"
              >
                <option value="never">Don't clear</option>
                <option value="30m">30 minutes</option>
                <option value="1h">1 hour</option>
                <option value="4h">4 hours</option>
                <option value="today">Today</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <AlertDialogCancel
              class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors duration-150"
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              class="px-4 py-2 text-sm font-medium bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors duration-150"
              @click="saveCustomStatus"
            >
              Save
            </AlertDialogAction>
          </div>
        </AlertDialogContent>
      </AlertDialogPortal>
    </AlertDialogRoot>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from '@/Components/shared/Icon.vue'
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuPortal,
  DropdownMenuRoot,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'

// Types
type UserMenuSize = 'sm' | 'md' | 'lg'
type UserStatus = 'online' | 'away' | 'dnd' | 'offline'
type UserRole = 'admin' | 'member' | 'guest'

interface MenuItem {
  id: string
  label: string
  description?: string
  icon: string
  shortcut?: string
  external?: boolean
}

interface QuickStatus {
  emoji: string
  text: string
}

interface UserMenuSizeConfig {
  padding: string
  trigger: string
  avatar: string
  avatarText: string
  statusIndicator: string
  name: string
  status: string
  dropdown: string
}

interface User {
  name: string
  email?: string
  avatar?: string
}

// Props
const props = withDefaults(defineProps<{
  size?: UserMenuSize
  collapsed?: boolean
  userStatus?: UserStatus
  userRole?: UserRole
  user?: User
}>(), {
  size: 'md',
  collapsed: false,
  userStatus: 'online',
  userRole: 'admin',
  user: () => ({ name: 'User', email: 'user@example.com' }),
})

// Emits
defineEmits<{
  statusChange: [status: UserStatus]
  menuAction: [actionId: string]
}>()

// Size configuration
const sizeConfig: Record<UserMenuSize, UserMenuSizeConfig> = {
  sm: {
    padding: 'p-3',
    trigger: 'gap-2 p-2',
    avatar: 'w-7 h-7',
    avatarText: 'text-xs',
    statusIndicator: 'w-2 h-2 -bottom-0.5 -right-0.5',
    name: 'text-xs',
    status: 'text-[10px]',
    dropdown: 'min-w-52 p-1.5',
  },
  md: {
    padding: 'p-4',
    trigger: 'gap-3 p-2',
    avatar: 'w-9 h-9',
    avatarText: 'text-sm',
    statusIndicator: 'w-2.5 h-2.5 -bottom-0.5 -right-0.5',
    name: 'text-sm',
    status: 'text-xs',
    dropdown: 'min-w-56 p-1.5',
  },
  lg: {
    padding: 'p-5',
    trigger: 'gap-3 p-3',
    avatar: 'w-11 h-11',
    avatarText: 'text-base',
    statusIndicator: 'w-3 h-3 -bottom-0.5 -right-0.5',
    name: 'text-base',
    status: 'text-sm',
    dropdown: 'min-w-64 p-2',
  },
}

// Status configuration
const statusColors: Record<UserStatus, string> = {
  online: 'bg-green-500',
  away: 'bg-amber-500',
  dnd: 'bg-red-500',
  offline: 'bg-gray-400',
}

const statusLabels: Record<UserStatus, string> = {
  online: 'Online',
  away: 'Away',
  dnd: 'Do not disturb',
  offline: 'Offline',
}

const statusOptions = [
  { value: 'online', label: 'Online' },
  { value: 'away', label: 'Away' },
  { value: 'dnd', label: 'Do not disturb' },
  { value: 'offline', label: 'Appear offline' },
]

// Menu items
const menuItems: MenuItem[] = [
  { id: 'profile', label: 'Profile', description: 'View and edit your profile', icon: 'ph:user' },
  { id: 'settings', label: 'Settings', icon: 'ph:gear-six', shortcut: 'Cmd+,' },
  { id: 'shortcuts', label: 'Keyboard shortcuts', icon: 'ph:keyboard', shortcut: '?' },
  { id: 'notifications', label: 'Notification preferences', icon: 'ph:bell' },
  { id: 'help', label: 'Help & support', icon: 'ph:question', external: true },
]

// Quick statuses
const quickStatuses: QuickStatus[] = [
  { emoji: '&#127968;', text: 'Working from home' },
  { emoji: '&#128640;', text: 'In a meeting' },
  { emoji: '&#127919;', text: 'Focusing' },
  { emoji: '&#127958;&#65039;', text: 'On vacation' },
]

// State
const currentUser = computed<User>(() => props.user)
const userStatusValue = ref<UserStatus>(props.userStatus)
const userRoleValue = ref<UserRole>(props.userRole)
const isDark = ref(true)
const customStatusDialogOpen = ref(false)
const customStatusEmoji = ref('')
const customStatusText = ref('')
const clearAfter = ref('never')

// Confirm dialog state
const confirmDialogOpen = ref(false)
const confirmDialogLoading = ref(false)
const confirmDialogOptions = ref({
  title: '',
  description: '',
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  variant: 'danger' as 'danger' | 'default',
})
let confirmDialogResolve: ((value: boolean) => void) | null = null

// Methods
const getInitials = (name: string): string => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const setStatus = async (status: string) => {
  userStatusValue.value = status as UserStatus
}

const toggleTheme = () => {
  isDark.value = !isDark.value
}

const handleMenuAction = (actionId: string) => {
  if (actionId === 'custom-status') {
    customStatusDialogOpen.value = true
  } else if (actionId === 'profile') {
    router.visit('/profile')
  } else if (actionId === 'settings') {
    router.visit('/settings')
  }
}

const setQuickStatus = (quick: QuickStatus) => {
  customStatusEmoji.value = quick.emoji
  customStatusText.value = quick.text
}

const saveCustomStatus = () => {
  customStatusDialogOpen.value = false
}

const confirm = (options: {
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'default'
}): Promise<boolean> => {
  return new Promise((resolve) => {
    confirmDialogOptions.value = {
      title: options.title,
      description: options.description,
      confirmLabel: options.confirmLabel || 'Confirm',
      cancelLabel: options.cancelLabel || 'Cancel',
      variant: options.variant || 'default',
    }
    confirmDialogResolve = resolve
    confirmDialogOpen.value = true
  })
}

const handleConfirmDialogConfirm = () => {
  confirmDialogResolve?.(true)
  confirmDialogOpen.value = false
}

const handleConfirmDialogCancel = () => {
  confirmDialogResolve?.(false)
  confirmDialogOpen.value = false
}

const handleSignOut = async () => {
  const confirmed = await confirm({
    title: 'Sign out',
    description: 'Are you sure you want to sign out? You will need to sign in again to access your workspace.',
    confirmLabel: 'Sign out',
    cancelLabel: 'Cancel',
    variant: 'danger',
  })

  if (confirmed) {
    router.post('/logout')
  }
}
</script>

<style scoped>
/* Minimal styling */
</style>
