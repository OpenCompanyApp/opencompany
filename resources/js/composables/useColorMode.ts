import { ref, watch, computed } from 'vue'

type ColorMode = 'light' | 'dark' | 'system'

// Global state (shared across all component instances)
const colorMode = ref<ColorMode>((typeof localStorage !== 'undefined' && localStorage.getItem('color-mode') as ColorMode) || 'system')

// Reactive system preference
const systemPrefersDark = ref(typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches)

// Listen for system preference changes
if (typeof window !== 'undefined') {
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    systemPrefersDark.value = e.matches
  })
}

// Computed: is dark mode active?
const isDark = computed(() => {
  if (colorMode.value === 'system') {
    return systemPrefersDark.value
  }
  return colorMode.value === 'dark'
})

// Apply theme to DOM
const applyTheme = () => {
  if (typeof document === 'undefined') return

  const dark = isDark.value
  document.documentElement.classList.toggle('dark', dark)
  document.documentElement.classList.toggle('light', !dark && colorMode.value === 'light')
}

// Watch for changes and apply
watch([colorMode, systemPrefersDark], () => {
  applyTheme()
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('color-mode', colorMode.value)
  }
}, { immediate: true })

export function useColorMode() {
  const setColorMode = (mode: ColorMode) => {
    colorMode.value = mode
  }

  const toggleDark = () => {
    colorMode.value = isDark.value ? 'light' : 'dark'
  }

  const cycleColorMode = () => {
    const modes: ColorMode[] = ['system', 'light', 'dark']
    const currentIndex = modes.indexOf(colorMode.value)
    colorMode.value = modes[(currentIndex + 1) % modes.length]
  }

  return {
    colorMode,
    isDark,
    systemPrefersDark,
    setColorMode,
    toggleDark,
    cycleColorMode,
  }
}
