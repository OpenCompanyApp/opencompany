<template>
  <div
    :class="[
      !compact && 'border-t border-neutral-200 dark:border-neutral-800',
      'transition-colors duration-150',
      compact ? '' : sizeConfig[size].padding,
      collapsed && !compact && 'px-2'
    ]"
  >
    <Popover v-if="collapsed" mode="hover" :open-delay="300">
      <DropdownMenu :items="dropdownItems">
        <button
          :class="[
            'w-full flex items-center rounded-xl transition-all duration-150 cursor-pointer outline-none group relative overflow-hidden',
            sizeConfig[size].trigger,
            'justify-center p-2',
            'hover:bg-white/80 dark:hover:bg-white/[0.08]',
            'focus-visible:ring-1 focus-visible:ring-neutral-400'
          ]"
        >
          <!-- User avatar -->
          <div
            :class="[
              'relative shrink-0',
              sizeConfig[size].avatar
            ]"
          >
            <div
              v-if="!currentUser.avatar"
              :class="[
                'w-full h-full rounded-full flex items-center justify-center text-white font-medium bg-gradient-to-br from-indigo-500 to-violet-600 shadow-sm',
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
                'w-full h-full rounded-full object-cover ring-2 ring-white/80 dark:ring-neutral-700'
              ]"
            />
            <span
              :class="[
                'absolute rounded-full ring-2 ring-white dark:ring-neutral-950',
                statusColors[userStatusValue],
                sizeConfig[size].statusIndicator
              ]"
            />
          </div>
        </button>
      </DropdownMenu>

      <template #content>
        <div class="px-3 py-2.5">
          <div class="font-medium text-sm text-neutral-900 dark:text-white">{{ currentUser.name }}</div>
          <div class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 flex items-center gap-2">
            <span
              :class="['w-2 h-2 rounded-full', statusColors[userStatusValue]]"
            />
            {{ statusLabels[userStatusValue] }}
          </div>
        </div>
      </template>
    </Popover>

    <DropdownMenu v-else :items="dropdownItems">
      <button
        :class="[
          'flex items-center rounded-xl transition-all duration-150 cursor-pointer outline-none group relative overflow-hidden',
          compact ? 'gap-1.5 p-1.5' : ['w-full', sizeConfig[size].trigger],
          'hover:bg-neutral-200 dark:hover:bg-neutral-800',
          'focus-visible:ring-1 focus-visible:ring-neutral-400'
        ]"
      >
        <!-- User avatar -->
        <div
          :class="[
            'relative shrink-0',
            compact ? 'w-6 h-6' : sizeConfig[size].avatar
          ]"
        >
          <div
            v-if="!currentUser.avatar"
            :class="[
              'w-full h-full rounded-full flex items-center justify-center text-white font-medium bg-gradient-to-br from-indigo-500 to-violet-600 shadow-sm',
              compact ? 'text-[10px]' : sizeConfig[size].avatarText
            ]"
          >
            {{ getInitials(currentUser.name) }}
          </div>
          <img
            v-else
            :src="currentUser.avatar"
            :alt="currentUser.name"
            class="w-full h-full rounded-full object-cover ring-2 ring-white/80 dark:ring-neutral-700"
          />
          <span
            :class="[
              'absolute rounded-full ring-2 ring-white dark:ring-neutral-950',
              statusColors[userStatusValue],
              compact ? 'w-1.5 h-1.5 -bottom-px -right-px' : sizeConfig[size].statusIndicator
            ]"
          />
        </div>

        <!-- Compact: first name only -->
        <template v-if="compact">
          <span class="text-xs font-medium text-neutral-700 dark:text-neutral-300 truncate max-w-[4rem]">
            {{ currentUser.name.split(' ')[0] }}
          </span>
        </template>

        <!-- Full: name + email + badge -->
        <template v-else>
          <div class="flex-1 text-left min-w-0 ml-2.5">
            <div class="flex items-center gap-1.5">
              <p :class="['font-semibold truncate text-neutral-900 dark:text-white leading-tight', sizeConfig[size].name]">
                {{ currentUser.name }}
              </p>
              <span
                v-if="userRoleValue === 'admin'"
                class="shrink-0 px-1 py-px text-[9px] font-semibold uppercase tracking-wider bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 rounded"
              >
                Admin
              </span>
            </div>
            <p :class="['text-neutral-500 dark:text-neutral-400 truncate leading-tight mt-0.5', sizeConfig[size].status]">
              {{ currentUser.email || statusLabels[userStatusValue] }}
            </p>
          </div>

          <!-- Dropdown chevron -->
          <Icon
            name="ph:caret-up-down"
            class="w-3.5 h-3.5 text-neutral-400 dark:text-neutral-500 shrink-0 transition-colors duration-150 group-hover:text-neutral-600 dark:group-hover:text-neutral-300"
          />
        </template>
      </button>
    </DropdownMenu>

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
    <Modal v-model:open="customStatusDialogOpen">
      <template #content>
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-2 text-neutral-900 dark:text-white">
            Set custom status
          </h3>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-4">
            Let others know what you're up to
          </p>

          <div class="space-y-4">
            <!-- Emoji picker trigger -->
            <div class="flex items-center gap-3">
              <button
                class="w-12 h-12 rounded-lg bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors duration-150 flex items-center justify-center text-2xl"
              >
                {{ customStatusEmoji || '&#128522;' }}
              </button>
              <input
                v-model="customStatusText"
                type="text"
                placeholder="What's your status?"
                class="flex-1 bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-lg px-3 py-2 text-sm text-neutral-900 dark:text-white placeholder:text-neutral-400 dark:placeholder:text-neutral-500 focus:outline-none focus:border-neutral-400 focus:ring-1 focus:ring-neutral-400 transition-colors duration-150"
              />
            </div>

            <!-- Quick status options -->
            <div class="flex flex-wrap gap-2">
              <button
                v-for="quick in quickStatuses"
                :key="quick.text"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-neutral-600 dark:text-neutral-300 bg-neutral-100 dark:bg-neutral-800 hover:bg-neutral-200 dark:hover:bg-neutral-700 rounded-lg transition-colors duration-150"
                @click="setQuickStatus(quick)"
              >
                <span>{{ quick.emoji }}</span>
                <span>{{ quick.text }}</span>
              </button>
            </div>

            <!-- Clear after selector -->
            <div class="flex items-center justify-between text-sm">
              <span class="text-neutral-500 dark:text-neutral-400">Clear after</span>
              <select
                v-model="clearAfter"
                class="bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-700 rounded-lg px-3 py-1.5 text-sm text-neutral-900 dark:text-white focus:outline-none focus:border-neutral-400 focus:ring-1 focus:ring-neutral-400 transition-colors duration-150 cursor-pointer"
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
            <Button
              variant="ghost"
              @click="customStatusDialogOpen = false"
            >
              Cancel
            </Button>
            <Button
              @click="saveCustomStatus"
            >
              Save
            </Button>
          </div>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ConfirmDialog from '@/Components/shared/ConfirmDialog.vue'
import Icon from '@/Components/shared/Icon.vue'
import Popover from '@/Components/shared/Popover.vue'
import DropdownMenu from '@/Components/shared/DropdownMenu.vue'
import Button from '@/Components/shared/Button.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useColorMode } from '@/composables/useColorMode'
import { useWorkspace } from '@/composables/useWorkspace'

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
  compact?: boolean
  userStatus?: UserStatus
  userRole?: UserRole
  user?: User
}>(), {
  size: 'md',
  collapsed: false,
  compact: false,
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
  offline: 'bg-neutral-400',
}

const statusLabels: Record<UserStatus, string> = {
  online: 'Online',
  away: 'Away',
  dnd: 'Do not disturb',
  offline: 'Offline',
}

// Menu items
const menuItemsData: MenuItem[] = [
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

// Color mode
const { isDark, toggleDark } = useColorMode()

// Workspace
const { workspacePath } = useWorkspace()

// State
const currentUser = computed<User>(() => props.user)
const userStatusValue = ref<UserStatus>(props.userStatus)
const userRoleValue = ref<UserRole>(props.userRole)
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

// Dropdown items for UDropdownMenu
const dropdownItems = computed(() => [
  // User header
  [{
    slot: 'header',
    disabled: true,
  }],
  // Status options
  [{
    label: statusLabels[userStatusValue.value],
    icon: userStatusValue.value === 'online' ? 'ph:circle-fill' : userStatusValue.value === 'away' ? 'ph:circle-half-fill' : userStatusValue.value === 'dnd' ? 'ph:minus-circle-fill' : 'ph:circle',
    children: [
      { label: 'Online', icon: userStatusValue.value === 'online' ? 'ph:check' : undefined, click: () => setStatus('online') },
      { label: 'Away', icon: userStatusValue.value === 'away' ? 'ph:check' : undefined, click: () => setStatus('away') },
      { label: 'Do not disturb', icon: userStatusValue.value === 'dnd' ? 'ph:check' : undefined, click: () => setStatus('dnd') },
      { label: 'Appear offline', icon: userStatusValue.value === 'offline' ? 'ph:check' : undefined, click: () => setStatus('offline') },
    ],
  }],
  // Menu items
  menuItemsData.map(item => ({
    label: item.label,
    icon: item.icon,
    click: () => handleMenuAction(item.id),
  })),
  // Theme toggle
  [{
    label: isDark.value ? 'Light mode' : 'Dark mode',
    icon: isDark.value ? 'ph:sun-fill' : 'ph:moon-fill',
    click: toggleTheme,
  }],
  // Sign out
  [{
    label: 'Sign out',
    icon: 'ph:sign-out',
    color: 'error' as const,
    click: handleSignOut,
  }],
])

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
  toggleDark()
}

const handleMenuAction = (actionId: string) => {
  if (actionId === 'custom-status') {
    customStatusDialogOpen.value = true
  } else if (actionId === 'profile') {
    router.visit(workspacePath('/profile'))
  } else if (actionId === 'settings') {
    router.visit(workspacePath('/settings'))
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
