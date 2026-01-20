<template>
  <div
    :class="[
      'border-t border-olympus-border transition-all duration-300',
      sizeConfig[size].padding,
      collapsed && 'px-2'
    ]"
  >
    <DropdownMenuRoot>
      <TooltipRoot :delay-duration="300" :disabled="!collapsed">
        <TooltipTrigger as-child>
          <DropdownMenuTrigger
            :class="[
              'w-full flex items-center rounded-lg transition-all duration-200 cursor-pointer outline-none group relative overflow-hidden',
              sizeConfig[size].trigger,
              collapsed && 'justify-center p-2',
              'hover:bg-olympus-surface',
              'focus-visible:ring-2 focus-visible:ring-olympus-primary/50'
            ]"
          >
            <!-- User avatar -->
            <div
              :class="[
                'relative shrink-0',
                sizeConfig[size].avatar
              ]"
            >
              <!-- Avatar with gradient -->
              <div
                v-if="!currentUser.avatar"
                :class="[
                  'w-full h-full rounded-full flex items-center justify-center text-white font-semibold shadow-lg transition-transform duration-200 group-hover:scale-105',
                  statusGradients[userStatus],
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
                  'w-full h-full rounded-full object-cover ring-2 transition-all duration-200 group-hover:scale-105',
                  statusRings[userStatus]
                ]"
              />

              <!-- Status indicator -->
              <span
                :class="[
                  'absolute rounded-full ring-2 ring-olympus-sidebar transition-all duration-200',
                  statusColors[userStatus],
                  sizeConfig[size].statusIndicator
                ]"
              />

              <!-- DND indicator -->
              <div
                v-if="userStatus === 'dnd'"
                class="absolute inset-0 rounded-full border-2 border-dashed border-olympus-error/50 animate-spin-slow"
              />
            </div>

            <!-- User info (hidden when collapsed) -->
            <Transition
              enter-active-class="transition-all duration-200"
              leave-active-class="transition-all duration-150"
              enter-from-class="opacity-0 translate-x-[-8px]"
              leave-to-class="opacity-0 translate-x-[-8px]"
            >
              <div v-if="!collapsed" class="flex-1 text-left min-w-0 ml-3">
                <div class="flex items-center gap-2">
                  <p :class="['font-medium truncate', sizeConfig[size].name]">
                    {{ currentUser.name }}
                  </p>
                  <span
                    v-if="userRole === 'admin'"
                    class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-olympus-accent/20 text-olympus-accent rounded"
                  >
                    Admin
                  </span>
                </div>
                <p :class="['text-olympus-text-muted truncate', sizeConfig[size].status]">
                  {{ statusLabels[userStatus] }}
                </p>
              </div>
            </Transition>

            <!-- Dropdown chevron -->
            <Icon
              v-if="!collapsed"
              name="ph:caret-up-down"
              class="w-4 h-4 text-olympus-text-muted shrink-0 transition-transform duration-200 group-hover:text-olympus-text"
            />

            <!-- Hover glow -->
            <div class="absolute inset-0 bg-gradient-to-r from-olympus-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none" />
          </DropdownMenuTrigger>
        </TooltipTrigger>
        <TooltipPortal>
          <TooltipContent
            side="right"
            :side-offset="12"
            class="glass px-3 py-2 rounded-lg shadow-xl animate-in fade-in-0 slide-in-from-left-2 duration-150"
          >
            <div class="font-medium text-sm">{{ currentUser.name }}</div>
            <div class="text-xs text-olympus-text-muted mt-0.5 flex items-center gap-2">
              <span
                :class="['w-2 h-2 rounded-full', statusColors[userStatus]]"
              />
              {{ statusLabels[userStatus] }}
            </div>
            <TooltipArrow class="fill-olympus-elevated" />
          </TooltipContent>
        </TooltipPortal>
      </TooltipRoot>

      <DropdownMenuPortal>
        <DropdownMenuContent
          :class="[
            'glass border border-olympus-border rounded-xl shadow-2xl z-50 overflow-hidden',
            'animate-in fade-in-0 zoom-in-95 duration-200',
            sizeConfig[size].dropdown
          ]"
          :side-offset="8"
          side="top"
        >
          <!-- User header in dropdown -->
          <div class="px-3 py-3 border-b border-olympus-border">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-olympus-primary flex items-center justify-center text-white font-semibold text-sm shadow-lg shadow-olympus-primary/30">
                {{ getInitials(currentUser.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium truncate">{{ currentUser.name }}</p>
                <p class="text-xs text-olympus-text-muted truncate">{{ currentUser.email || 'user@example.com' }}</p>
              </div>
            </div>
          </div>

          <!-- Status selector -->
          <DropdownMenuSub>
            <DropdownMenuSubTrigger
              class="flex items-center gap-3 px-3 py-2.5 hover:bg-olympus-surface cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-olympus-surface"
            >
              <span
                :class="['w-2.5 h-2.5 rounded-full', statusColors[userStatus]]"
              />
              <span class="flex-1">{{ statusLabels[userStatus] }}</span>
              <Icon name="ph:caret-right" class="w-4 h-4 text-olympus-text-muted" />
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
              <DropdownMenuSubContent
                class="glass border border-olympus-border rounded-lg p-1.5 shadow-xl z-50 animate-in fade-in-0 slide-in-from-left-2 duration-150 min-w-40"
                :side-offset="8"
              >
                <DropdownMenuItem
                  v-for="status in statusOptions"
                  :key="status.value"
                  :class="[
                    'flex items-center gap-3 px-3 py-2 rounded-md cursor-pointer outline-none transition-colors duration-150 text-sm',
                    userStatus === status.value ? 'bg-olympus-surface' : 'hover:bg-olympus-surface focus:bg-olympus-surface'
                  ]"
                  @click="setStatus(status.value)"
                >
                  <span :class="['w-2.5 h-2.5 rounded-full', statusColors[status.value as UserStatus]]" />
                  <span class="flex-1">{{ status.label }}</span>
                  <Icon
                    v-if="userStatus === status.value"
                    name="ph:check"
                    class="w-4 h-4 text-olympus-primary"
                  />
                </DropdownMenuItem>
              </DropdownMenuSubContent>
            </DropdownMenuPortal>
          </DropdownMenuSub>

          <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />

          <!-- Menu items -->
          <DropdownMenuItem
            v-for="item in menuItems"
            :key="item.id"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-md cursor-pointer outline-none transition-colors duration-150 text-sm',
              'hover:bg-olympus-surface focus:bg-olympus-surface'
            ]"
            @click="handleMenuAction(item.id)"
          >
            <div
              :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center',
                'bg-olympus-surface group-hover:bg-olympus-border transition-colors'
              ]"
            >
              <Icon :name="item.icon" class="w-4 h-4 text-olympus-text-muted" />
            </div>
            <div class="flex-1">
              <span>{{ item.label }}</span>
              <p v-if="item.description" class="text-xs text-olympus-text-muted mt-0.5">
                {{ item.description }}
              </p>
            </div>
            <kbd
              v-if="item.shortcut"
              class="text-xs text-olympus-text-subtle bg-olympus-surface px-1.5 py-0.5 rounded font-mono"
            >
              {{ item.shortcut }}
            </kbd>
            <Icon
              v-if="item.external"
              name="ph:arrow-square-out"
              class="w-3.5 h-3.5 text-olympus-text-subtle"
            />
          </DropdownMenuItem>

          <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />

          <!-- Theme toggle -->
          <div class="px-3 py-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <Icon
                  :name="isDark ? 'ph:moon-fill' : 'ph:sun-fill'"
                  class="w-4 h-4 text-olympus-text-muted"
                />
                <span class="text-sm">{{ isDark ? 'Dark' : 'Light' }} mode</span>
              </div>
              <button
                class="relative w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
                :class="isDark ? 'bg-olympus-primary' : 'bg-olympus-surface'"
                @click="toggleTheme"
              >
                <span
                  :class="[
                    'absolute top-1 w-4 h-4 rounded-full bg-white shadow-sm transition-all duration-200',
                    isDark ? 'left-6' : 'left-1'
                  ]"
                />
              </button>
            </div>
          </div>

          <DropdownMenuSeparator class="h-px bg-olympus-border my-1" />

          <!-- Sign out -->
          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-red-500/10 cursor-pointer outline-none transition-colors duration-150 text-sm text-red-400 focus:bg-red-500/10 mx-1.5 mb-1.5"
            @click="handleSignOut"
          >
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-500/10">
              <Icon name="ph:sign-out" class="w-4 h-4" />
            </div>
            <span class="font-medium">Sign out</span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenuPortal>
    </DropdownMenuRoot>

    <!-- Sign Out Confirmation Dialog -->
    <SharedConfirmDialog
      v-model:open="confirmDialog.isOpen.value"
      :title="confirmDialog.options.value.title"
      :description="confirmDialog.options.value.description"
      :confirm-label="confirmDialog.options.value.confirmLabel"
      :cancel-label="confirmDialog.options.value.cancelLabel"
      :variant="confirmDialog.options.value.variant"
      :loading="confirmDialog.isLoading.value"
      @confirm="confirmDialog.handleConfirm"
      @cancel="confirmDialog.handleCancel"
    />

    <!-- Set custom status dialog -->
    <AlertDialogRoot v-model:open="customStatusDialogOpen">
      <AlertDialogPortal>
        <AlertDialogOverlay class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 animate-in fade-in-0 duration-200" />
        <AlertDialogContent class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-olympus-elevated border border-olympus-border rounded-2xl p-6 shadow-2xl z-50 animate-in fade-in-0 zoom-in-95 duration-200">
          <AlertDialogTitle class="text-lg font-semibold mb-2">
            Set custom status
          </AlertDialogTitle>
          <AlertDialogDescription class="text-sm text-olympus-text-muted mb-4">
            Let others know what you're up to
          </AlertDialogDescription>

          <div class="space-y-4">
            <!-- Emoji picker trigger -->
            <div class="flex items-center gap-3">
              <button
                class="w-12 h-12 rounded-xl bg-olympus-surface hover:bg-olympus-border transition-colors flex items-center justify-center text-2xl"
              >
                {{ customStatusEmoji || '😊' }}
              </button>
              <input
                v-model="customStatusText"
                type="text"
                placeholder="What's your status?"
                class="flex-1 bg-olympus-surface border border-olympus-border rounded-lg px-3 py-2 text-sm placeholder:text-olympus-text-muted focus:outline-none focus:ring-2 focus:ring-olympus-primary/50"
              />
            </div>

            <!-- Quick status options -->
            <div class="flex flex-wrap gap-2">
              <button
                v-for="quick in quickStatuses"
                :key="quick.text"
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-olympus-surface hover:bg-olympus-border rounded-lg transition-colors"
                @click="setQuickStatus(quick)"
              >
                <span>{{ quick.emoji }}</span>
                <span>{{ quick.text }}</span>
              </button>
            </div>

            <!-- Clear after selector -->
            <div class="flex items-center justify-between text-sm">
              <span class="text-olympus-text-muted">Clear after</span>
              <select
                v-model="clearAfter"
                class="bg-olympus-surface border border-olympus-border rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-olympus-primary/50"
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
              class="px-4 py-2 text-sm font-medium text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface rounded-lg transition-colors"
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              class="px-4 py-2 text-sm font-medium bg-olympus-primary text-white rounded-lg hover:bg-olympus-primary/90 transition-colors"
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
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'

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

// Props
const props = withDefaults(defineProps<{
  size?: UserMenuSize
  collapsed?: boolean
  userStatus?: UserStatus
  userRole?: UserRole
}>(), {
  size: 'md',
  collapsed: false,
  userStatus: 'online',
  userRole: 'admin',
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
  online: 'bg-olympus-success',
  away: 'bg-olympus-warning',
  dnd: 'bg-olympus-error',
  offline: 'bg-olympus-text-subtle',
}

const statusGradients: Record<UserStatus, string> = {
  online: 'bg-gradient-to-br from-olympus-primary to-olympus-accent shadow-olympus-primary/30',
  away: 'bg-gradient-to-br from-olympus-warning to-orange-500 shadow-olympus-warning/30',
  dnd: 'bg-gradient-to-br from-olympus-error to-red-600 shadow-olympus-error/30',
  offline: 'bg-gradient-to-br from-gray-400 to-gray-500 shadow-gray-500/30',
}

const statusRings: Record<UserStatus, string> = {
  online: 'ring-olympus-success/30',
  away: 'ring-olympus-warning/30',
  dnd: 'ring-olympus-error/30',
  offline: 'ring-olympus-text-subtle/30',
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
  { id: 'settings', label: 'Settings', icon: 'ph:gear-six', shortcut: '⌘,' },
  { id: 'shortcuts', label: 'Keyboard shortcuts', icon: 'ph:keyboard', shortcut: '?' },
  { id: 'notifications', label: 'Notification preferences', icon: 'ph:bell' },
  { id: 'help', label: 'Help & support', icon: 'ph:question', external: true },
]

// Quick statuses
const quickStatuses: QuickStatus[] = [
  { emoji: '🏠', text: 'Working from home' },
  { emoji: '🚀', text: 'In a meeting' },
  { emoji: '🎯', text: 'Focusing' },
  { emoji: '🏖️', text: 'On vacation' },
]

// Composables
const { humans } = useMockData()
const confirmDialog = useConfirmDialog()

// State
const currentUser = humans[0]!
const userStatus = ref<UserStatus>(props.userStatus)
const userRole = ref<UserRole>(props.userRole)
const isDark = ref(true)
const customStatusDialogOpen = ref(false)
const customStatusEmoji = ref('')
const customStatusText = ref('')
const clearAfter = ref('never')

// Methods
const getInitials = (name: string): string => {
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const setStatus = (status: string) => {
  userStatus.value = status as UserStatus
}

const toggleTheme = () => {
  isDark.value = !isDark.value
}

const handleMenuAction = (actionId: string) => {
  if (actionId === 'custom-status') {
    customStatusDialogOpen.value = true
  }
}

const setQuickStatus = (quick: QuickStatus) => {
  customStatusEmoji.value = quick.emoji
  customStatusText.value = quick.text
}

const saveCustomStatus = () => {
  customStatusDialogOpen.value = false
}

const handleSignOut = async () => {
  const confirmed = await confirmDialog.confirm({
    title: 'Sign out',
    description: 'Are you sure you want to sign out? You will need to sign in again to access your workspace.',
    confirmLabel: 'Sign out',
    cancelLabel: 'Cancel',
    variant: 'danger',
  })

  if (confirmed) {
    console.log('Signing out...')
  }
}
</script>

<style scoped>
@keyframes spin-slow {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin-slow {
  animation: spin-slow 8s linear infinite;
}
</style>
