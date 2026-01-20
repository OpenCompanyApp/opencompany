<template>
  <div class="relative">
    <!-- Search Icon / Loading Spinner -->
    <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
      <Icon
        v-if="loading"
        name="ph:spinner"
        class="w-4 h-4 text-olympus-text-muted animate-spin"
      />
      <Icon
        v-else
        name="ph:magnifying-glass"
        class="w-4 h-4 text-olympus-text-muted"
      />
    </div>

    <input
      ref="inputRef"
      v-model="model"
      type="search"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="[
        'w-full text-sm rounded-xl transition-all duration-200',
        'bg-olympus-surface border text-olympus-text placeholder:text-olympus-text-subtle',
        'focus:outline-none focus:border-olympus-primary focus:shadow-glow-sm',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        'pl-10',
        clearable && model ? 'pr-10' : 'pr-4',
        sizeClasses[size],
        variant === 'ghost' ? 'border-transparent bg-transparent hover:bg-olympus-surface' : 'border-olympus-border',
      ]"
      @keydown.enter="handleSearch"
      @keydown.escape="handleClear"
    />

    <!-- Clear Button -->
    <button
      v-if="clearable && model"
      type="button"
      class="absolute right-3 top-1/2 -translate-y-1/2 p-0.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-elevated transition-colors duration-150"
      @click="handleClear"
    >
      <Icon name="ph:x" class="w-3.5 h-3.5" />
    </button>
  </div>
</template>

<script setup lang="ts">
type SearchInputSize = 'sm' | 'md'
type SearchInputVariant = 'default' | 'ghost'

const props = withDefaults(defineProps<{
  modelValue?: string
  placeholder?: string
  size?: SearchInputSize
  variant?: SearchInputVariant
  disabled?: boolean
  loading?: boolean
  clearable?: boolean
  autofocus?: boolean
  debounce?: number
}>(), {
  modelValue: '',
  placeholder: 'Search...',
  size: 'md',
  variant: 'default',
  disabled: false,
  loading: false,
  clearable: true,
  autofocus: false,
  debounce: 0,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'search': [value: string]
  'clear': []
}>()

const inputRef = ref<HTMLInputElement | null>(null)

const model = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
    if (props.debounce > 0) {
      debouncedSearch(value)
    }
  },
})

const sizeClasses: Record<SearchInputSize, string> = {
  sm: 'h-9 text-sm',
  md: 'h-10 text-sm',
}

// Debounced search
let debounceTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = (value: string) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    emit('search', value)
  }, props.debounce)
}

const handleSearch = () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  emit('search', model.value)
}

const handleClear = () => {
  model.value = ''
  emit('clear')
  inputRef.value?.focus()
}

// Focus management
const focus = () => inputRef.value?.focus()
const blur = () => inputRef.value?.blur()

// Autofocus
onMounted(() => {
  if (props.autofocus) {
    nextTick(() => inputRef.value?.focus())
  }
})

// Cleanup
onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})

// Expose methods
defineExpose({ focus, blur })
</script>
