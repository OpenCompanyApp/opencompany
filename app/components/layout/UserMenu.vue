<template>
  <div class="p-4 border-t border-olympus-border">
    <DropdownMenuRoot>
      <DropdownMenuTrigger
        class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50"
      >
        <div class="w-9 h-9 rounded-full bg-olympus-primary flex items-center justify-center text-white font-semibold text-sm shadow-lg shadow-olympus-primary/30">
          {{ currentUser.name.charAt(0) }}
        </div>
        <div class="flex-1 text-left min-w-0">
          <p class="text-sm font-medium truncate">{{ currentUser.name }}</p>
          <p class="text-xs text-olympus-text-muted">Admin</p>
        </div>
        <Icon name="ph:caret-up-down" class="w-4 h-4 text-olympus-text-muted shrink-0" />
      </DropdownMenuTrigger>

      <DropdownMenuPortal>
        <DropdownMenuContent
          class="min-w-56 glass border border-olympus-border rounded-lg p-1.5 shadow-xl z-50 animate-in fade-in-0 zoom-in-95 duration-150"
          :side-offset="8"
          side="top"
        >
          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-olympus-surface cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-olympus-surface"
          >
            <Icon name="ph:user" class="w-4 h-4 text-olympus-text-muted" />
            <span>Profile</span>
          </DropdownMenuItem>

          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-olympus-surface cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-olympus-surface"
          >
            <Icon name="ph:gear-six" class="w-4 h-4 text-olympus-text-muted" />
            <span>Settings</span>
          </DropdownMenuItem>

          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-olympus-surface cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-olympus-surface"
          >
            <Icon name="ph:keyboard" class="w-4 h-4 text-olympus-text-muted" />
            <span>Keyboard shortcuts</span>
            <span class="ml-auto text-xs text-olympus-text-subtle bg-olympus-surface px-1.5 py-0.5 rounded font-mono">?</span>
          </DropdownMenuItem>

          <DropdownMenuSeparator class="h-px bg-olympus-border my-1.5" />

          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-olympus-surface cursor-pointer outline-none transition-colors duration-150 text-sm focus:bg-olympus-surface"
          >
            <Icon name="ph:moon" class="w-4 h-4 text-olympus-text-muted" />
            <span>Toggle theme</span>
          </DropdownMenuItem>

          <DropdownMenuSeparator class="h-px bg-olympus-border my-1.5" />

          <DropdownMenuItem
            class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-red-500/10 cursor-pointer outline-none transition-colors duration-150 text-sm text-red-400 focus:bg-red-500/10"
            @click="handleSignOut"
          >
            <Icon name="ph:sign-out" class="w-4 h-4" />
            <span>Sign out</span>
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
} from 'reka-ui'

const { humans } = useMockData()
const currentUser = humans[0]!  // Non-null assertion - humans array always has at least one element

const confirmDialog = useConfirmDialog()

const handleSignOut = async () => {
  const confirmed = await confirmDialog.confirm({
    title: 'Sign out',
    description: 'Are you sure you want to sign out? You will need to sign in again to access your workspace.',
    confirmLabel: 'Sign out',
    cancelLabel: 'Cancel',
    variant: 'danger',
  })

  if (confirmed) {
    // TODO: Implement sign out
    console.log('Signing out...')
  }
}
</script>
