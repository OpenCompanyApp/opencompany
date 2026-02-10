import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useMagicKeys, whenever } from '@vueuse/core'

const isInputFocused = () => {
  const activeElement = document.activeElement
  if (!activeElement) return false
  const tagName = activeElement.tagName.toLowerCase()
  return tagName === 'input' || tagName === 'textarea' || (activeElement as HTMLElement).isContentEditable
}

// Shared state for command palette
const commandPaletteOpen = ref(false)

export const useKeyboardShortcuts = () => {
  const keys = useMagicKeys()

  // Cmd+K / Ctrl+K - Open command palette
  whenever(keys['Meta+k'], () => {
    if (isInputFocused()) return
    commandPaletteOpen.value = true
  })
  whenever(keys['Ctrl+k'], () => {
    if (isInputFocused()) return
    commandPaletteOpen.value = true
  })

  // Escape - Close command palette (always allow escape)
  whenever(keys.escape, () => {
    commandPaletteOpen.value = false
  })

  // Navigation shortcuts (g + key)
  const gPressed = ref(false)
  let gTimeout: ReturnType<typeof setTimeout> | null = null

  whenever(keys.g, () => {
    if (isInputFocused()) return
    gPressed.value = true
    if (gTimeout) clearTimeout(gTimeout)
    gTimeout = setTimeout(() => {
      gPressed.value = false
    }, 500)
  })

  // g + h - Go to Dashboard (home)
  whenever(keys.h, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/')
      gPressed.value = false
    }
  })

  // g + c - Go to Chat
  whenever(keys.c, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/chat')
      gPressed.value = false
    }
  })

  // g + t - Go to Tasks
  whenever(keys.t, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/tasks')
      gPressed.value = false
    }
  })

  // g + d - Go to Docs
  whenever(keys.d, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/docs')
      gPressed.value = false
    }
  })

  // g + a - Go to Approvals
  whenever(keys.a, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/approvals')
      gPressed.value = false
    }
  })

  // g + o - Go to Organization
  whenever(keys.o, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/org')
      gPressed.value = false
    }
  })

  // g + s - Go to Settings
  whenever(keys.s, () => {
    if (isInputFocused()) return
    if (gPressed.value) {
      router.visit('/settings')
      gPressed.value = false
    }
  })

  // n + t - New task
  const nPressed = ref(false)
  let nTimeout: ReturnType<typeof setTimeout> | null = null

  whenever(keys.n, () => {
    if (isInputFocused()) return
    nPressed.value = true
    if (nTimeout) clearTimeout(nTimeout)
    nTimeout = setTimeout(() => {
      nPressed.value = false
    }, 500)
  })

  // n + t - New task
  whenever(keys.t, () => {
    if (isInputFocused()) return
    if (nPressed.value) {
      router.visit('/tasks?action=new')
      nPressed.value = false
    }
  })

  // n + a - New agent (spawn modal)
  whenever(keys.a, () => {
    if (isInputFocused()) return
    if (nPressed.value) {
      // TODO: Open agent spawn modal
      nPressed.value = false
    }
  })

  return {
    commandPaletteOpen,
    gPressed,
    nPressed,
  }
}

export const useCommandPalette = () => {
  const open = () => {
    commandPaletteOpen.value = true
  }

  const close = () => {
    commandPaletteOpen.value = false
  }

  const toggle = () => {
    commandPaletteOpen.value = !commandPaletteOpen.value
  }

  return {
    isOpen: commandPaletteOpen,
    open,
    close,
    toggle,
  }
}
