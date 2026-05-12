import { ref } from 'vue'

export interface Toast {
  id: string
  title: string
  description?: string
  type: 'success' | 'error' | 'info'
  duration: number
}

const toasts = ref<Toast[]>([])

// Counter is module-scoped so IDs remain unique across all components using the
// shared toast composable.
let counter = 0

function addToast(toast: Omit<Toast, 'id'>) {
  const id = `toast-${++counter}`
  toasts.value.push({ ...toast, id })
}

function dismiss(id: string) {
  // Reassign the array to trigger Vue updates for all mounted ToastProviders.
  toasts.value = toasts.value.filter(t => t.id !== id)
}

export function useToast() {
  const success = (title: string, description?: string) =>
    addToast({ title, description, type: 'success', duration: 4000 })

  const error = (title: string, description?: string) =>
    addToast({ title, description, type: 'error', duration: 6000 })

  const info = (title: string, description?: string) =>
    addToast({ title, description, type: 'info', duration: 4000 })

  return { toasts, success, error, info, dismiss }
}
