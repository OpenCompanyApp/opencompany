export interface ConfirmOptions {
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'danger'
}

export function useConfirmDialog() {
  const isOpen = ref(false)
  const isLoading = ref(false)
  const options = ref<ConfirmOptions>({
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'default',
  })

  let resolvePromise: ((value: boolean) => void) | null = null

  const confirm = (opts: ConfirmOptions): Promise<boolean> => {
    options.value = {
      confirmLabel: 'Confirm',
      cancelLabel: 'Cancel',
      variant: 'default',
      ...opts,
    }
    isOpen.value = true
    isLoading.value = false

    return new Promise((resolve) => {
      resolvePromise = resolve
    })
  }

  const handleConfirm = () => {
    isOpen.value = false
    resolvePromise?.(true)
    resolvePromise = null
  }

  const handleCancel = () => {
    isOpen.value = false
    resolvePromise?.(false)
    resolvePromise = null
  }

  const setLoading = (loading: boolean) => {
    isLoading.value = loading
  }

  return {
    isOpen,
    isLoading,
    options,
    confirm,
    handleConfirm,
    handleCancel,
    setLoading,
  }
}
