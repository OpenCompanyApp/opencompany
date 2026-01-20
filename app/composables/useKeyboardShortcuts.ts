import { useMagicKeys, whenever } from '@vueuse/core'

export const useKeyboardShortcuts = () => {
  const router = useRouter()
  const keys = useMagicKeys()
  const commandPaletteOpen = useState('commandPaletteOpen', () => false)

  // Cmd+K / Ctrl+K - Open command palette
  whenever(keys['Meta+k'], () => {
    commandPaletteOpen.value = true
  })
  whenever(keys['Ctrl+k'], () => {
    commandPaletteOpen.value = true
  })

  // Escape - Close command palette
  whenever(keys.escape, () => {
    commandPaletteOpen.value = false
  })

  // Navigation shortcuts (g + key)
  const gPressed = ref(false)
  let gTimeout: ReturnType<typeof setTimeout> | null = null

  whenever(keys.g, () => {
    gPressed.value = true
    if (gTimeout) clearTimeout(gTimeout)
    gTimeout = setTimeout(() => {
      gPressed.value = false
    }, 500)
  })

  // g + h - Go to Dashboard (home)
  whenever(keys.h, () => {
    if (gPressed.value) {
      router.push('/')
      gPressed.value = false
    }
  })

  // g + c - Go to Chat
  whenever(keys.c, () => {
    if (gPressed.value) {
      router.push('/chat')
      gPressed.value = false
    }
  })

  // g + t - Go to Tasks
  whenever(keys.t, () => {
    if (gPressed.value) {
      router.push('/tasks')
      gPressed.value = false
    }
  })

  // g + d - Go to Docs
  whenever(keys.d, () => {
    if (gPressed.value) {
      router.push('/docs')
      gPressed.value = false
    }
  })

  // n + t - New task
  const nPressed = ref(false)
  let nTimeout: ReturnType<typeof setTimeout> | null = null

  whenever(keys.n, () => {
    nPressed.value = true
    if (nTimeout) clearTimeout(nTimeout)
    nTimeout = setTimeout(() => {
      nPressed.value = false
    }, 500)
  })

  // n + a - New agent (spawn modal)
  whenever(keys.a, () => {
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
  const commandPaletteOpen = useState('commandPaletteOpen', () => false)

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
