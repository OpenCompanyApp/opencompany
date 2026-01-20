<template>
  <div :class="containerClasses">
    <!-- Label (if floating is disabled) -->
    <label
      v-if="label && !floatingLabel"
      :for="inputId"
      class="block text-sm font-medium text-olympus-text mb-1.5"
    >
      {{ label }}
      <span v-if="required" class="text-red-400 ml-0.5">*</span>
    </label>

    <!-- Input Container -->
    <div
      :class="[
        'relative group',
        fullWidth && 'w-full',
      ]"
    >
      <!-- Prefix / Search Icon -->
      <div
        :class="[
          'absolute left-0 top-0 bottom-0 flex items-center pointer-events-none transition-colors duration-200',
          paddingLeftClasses[size],
        ]"
      >
        <slot name="prefix">
          <Transition name="icon-fade" mode="out-in">
            <Icon
              v-if="loading"
              key="loading"
              name="ph:spinner"
              :class="[iconSizeClasses[size], 'text-olympus-text-muted animate-spin']"
            />
            <Icon
              v-else-if="searching && searchAnimation"
              key="searching"
              name="ph:magnifying-glass"
              :class="[iconSizeClasses[size], 'text-olympus-primary animate-pulse']"
            />
            <Icon
              v-else
              key="default"
              :name="prefixIcon"
              :class="[
                iconSizeClasses[size],
                isFocused ? 'text-olympus-primary' : 'text-olympus-text-muted',
                'transition-colors duration-200',
              ]"
            />
          </Transition>
        </slot>
      </div>

      <!-- Floating Label -->
      <label
        v-if="floatingLabel && label"
        :for="inputId"
        :class="floatingLabelClasses"
      >
        {{ label }}
        <span v-if="required" class="text-red-400 ml-0.5">*</span>
      </label>

      <!-- Input Element -->
      <input
        :id="inputId"
        ref="inputRef"
        v-model="model"
        type="search"
        :placeholder="floatingLabel ? '' : placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :class="inputClasses"
        :style="inputStyles"
        autocomplete="off"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown.enter="handleSearch"
        @keydown.escape="handleEscape"
        @keydown.up="handleArrowUp"
        @keydown.down="handleArrowDown"
        @input="handleInput"
      />

      <!-- Suffix Actions -->
      <div
        :class="[
          'absolute right-0 top-0 bottom-0 flex items-center gap-1',
          paddingRightClasses[size],
        ]"
      >
        <!-- Results Count -->
        <Transition name="fade">
          <span
            v-if="showResultsCount && resultsCount !== undefined && model"
            class="text-xs text-olympus-text-muted tabular-nums mr-1"
          >
            {{ resultsCount }} {{ resultsCount === 1 ? 'result' : 'results' }}
          </span>
        </Transition>

        <!-- Clear Button -->
        <Transition name="scale">
          <button
            v-if="clearable && model && !disabled && !readonly"
            type="button"
            :class="clearButtonClasses"
            :aria-label="clearLabel"
            @click="handleClear"
          >
            <Icon name="ph:x" :class="clearIconSizeClasses[size]" />
          </button>
        </Transition>

        <!-- Voice Input Button -->
        <button
          v-if="voiceInput && !disabled && !readonly"
          type="button"
          :class="[
            'p-1 rounded-md transition-all duration-200',
            isListening
              ? 'text-red-400 bg-red-500/20 animate-pulse'
              : 'text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface',
          ]"
          :aria-label="isListening ? 'Stop listening' : 'Start voice search'"
          @click="toggleVoiceInput"
        >
          <Icon
            :name="isListening ? 'ph:microphone-fill' : 'ph:microphone'"
            :class="iconSizeClasses[size]"
          />
        </button>

        <!-- Search/Submit Button -->
        <button
          v-if="showSearchButton && model && !disabled"
          type="button"
          :class="searchButtonClasses"
          :aria-label="searchButtonLabel"
          @click="handleSearch"
        >
          <Icon name="ph:arrow-right" :class="iconSizeClasses[size]" />
        </button>

        <!-- Custom suffix slot -->
        <slot name="suffix" />
      </div>

      <!-- Focus Ring (when using pill style) -->
      <div
        v-if="variant === 'pill'"
        :class="[
          'absolute inset-0 rounded-full pointer-events-none transition-all duration-200',
          isFocused && 'ring-2 ring-olympus-primary/50',
        ]"
      />
    </div>

    <!-- Helper Text / Error -->
    <div
      v-if="helperText || error"
      :class="[
        'mt-1.5 text-xs transition-colors duration-200',
        error ? 'text-red-400' : 'text-olympus-text-muted',
      ]"
    >
      <Icon
        v-if="error"
        name="ph:warning-circle"
        class="w-3 h-3 inline mr-1"
      />
      {{ error || helperText }}
    </div>

    <!-- Keyboard Hints -->
    <div
      v-if="showKeyboardHints && isFocused && !disabled"
      class="absolute right-3 top-full mt-2 flex items-center gap-2 text-xs text-olympus-text-muted z-10"
    >
      <span class="flex items-center gap-1">
        <kbd class="px-1.5 py-0.5 bg-olympus-surface border border-olympus-border rounded text-[10px] font-mono">Enter</kbd>
        <span>to search</span>
      </span>
      <span class="flex items-center gap-1">
        <kbd class="px-1.5 py-0.5 bg-olympus-surface border border-olympus-border rounded text-[10px] font-mono">Esc</kbd>
        <span>to clear</span>
      </span>
    </div>

    <!-- Recent Searches Dropdown -->
    <Transition name="dropdown">
      <div
        v-if="showRecentSearches && recentSearches.length > 0 && isFocused && !model"
        class="absolute left-0 right-0 top-full mt-1 bg-olympus-elevated border border-olympus-border rounded-xl shadow-xl z-20 overflow-hidden"
      >
        <div class="px-3 py-2 border-b border-olympus-border flex items-center justify-between">
          <span class="text-xs font-medium text-olympus-text-muted uppercase tracking-wider">Recent Searches</span>
          <button
            type="button"
            class="text-xs text-olympus-text-muted hover:text-red-400 transition-colors"
            @click="$emit('clearRecentSearches')"
          >
            Clear all
          </button>
        </div>
        <ul class="py-1 max-h-48 overflow-y-auto">
          <li
            v-for="(search, index) in recentSearches"
            :key="index"
            :class="[
              'px-3 py-2 flex items-center gap-2 cursor-pointer transition-colors duration-150',
              highlightedIndex === index
                ? 'bg-olympus-primary/10 text-olympus-text'
                : 'text-olympus-text-muted hover:bg-olympus-surface hover:text-olympus-text',
            ]"
            @click="selectRecentSearch(search)"
            @mouseenter="highlightedIndex = index"
          >
            <Icon name="ph:clock-counter-clockwise" class="w-4 h-4" />
            <span class="text-sm truncate flex-1">{{ search }}</span>
            <Icon name="ph:arrow-up-left" class="w-3 h-3 opacity-50" />
          </li>
        </ul>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
type SearchInputSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type SearchInputVariant = 'default' | 'ghost' | 'filled' | 'outlined' | 'pill' | 'minimal'

const props = withDefaults(defineProps<{
  // Core
  modelValue?: string
  placeholder?: string
  label?: string
  floatingLabel?: boolean
  helperText?: string
  error?: string

  // Appearance
  size?: SearchInputSize
  variant?: SearchInputVariant
  fullWidth?: boolean
  prefixIcon?: string

  // State
  disabled?: boolean
  readonly?: boolean
  loading?: boolean
  required?: boolean

  // Features
  clearable?: boolean
  clearLabel?: string
  autofocus?: boolean
  voiceInput?: boolean
  showSearchButton?: boolean
  searchButtonLabel?: string
  showKeyboardHints?: boolean
  showResultsCount?: boolean
  resultsCount?: number
  searchAnimation?: boolean

  // Recent searches
  showRecentSearches?: boolean
  recentSearches?: string[]

  // Debounce
  debounce?: number

  // Custom ID
  id?: string
}>(), {
  modelValue: '',
  placeholder: 'Search...',
  floatingLabel: false,
  size: 'md',
  variant: 'default',
  fullWidth: true,
  prefixIcon: 'ph:magnifying-glass',
  disabled: false,
  readonly: false,
  loading: false,
  required: false,
  clearable: true,
  clearLabel: 'Clear search',
  autofocus: false,
  voiceInput: false,
  showSearchButton: false,
  searchButtonLabel: 'Search',
  showKeyboardHints: false,
  showResultsCount: false,
  searchAnimation: true,
  showRecentSearches: false,
  recentSearches: () => [],
  debounce: 0,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'search': [value: string]
  'clear': []
  'focus': []
  'blur': []
  'voiceStart': []
  'voiceEnd': [transcript: string]
  'clearRecentSearches': []
  'selectRecentSearch': [search: string]
}>()

const inputRef = ref<HTMLInputElement | null>(null)
const isFocused = ref(false)
const isListening = ref(false)
const searching = ref(false)
const highlightedIndex = ref(-1)

// Generate unique ID
const inputId = computed(() => props.id || `search-input-${Math.random().toString(36).substring(2, 9)}`)

// v-model binding
const model = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
    if (props.debounce > 0) {
      debouncedSearch(value)
    }
  },
})

// Size configurations
const sizeClasses: Record<SearchInputSize, string> = {
  xs: 'h-7 text-xs',
  sm: 'h-8 text-sm',
  md: 'h-10 text-sm',
  lg: 'h-12 text-base',
  xl: 'h-14 text-lg',
}

const iconSizeClasses: Record<SearchInputSize, string> = {
  xs: 'w-3 h-3',
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4',
  lg: 'w-5 h-5',
  xl: 'w-6 h-6',
}

const clearIconSizeClasses: Record<SearchInputSize, string> = {
  xs: 'w-2.5 h-2.5',
  sm: 'w-3 h-3',
  md: 'w-3.5 h-3.5',
  lg: 'w-4 h-4',
  xl: 'w-5 h-5',
}

const paddingLeftClasses: Record<SearchInputSize, string> = {
  xs: 'pl-2',
  sm: 'pl-2.5',
  md: 'pl-3',
  lg: 'pl-4',
  xl: 'pl-5',
}

const paddingRightClasses: Record<SearchInputSize, string> = {
  xs: 'pr-2',
  sm: 'pr-2.5',
  md: 'pr-3',
  lg: 'pr-4',
  xl: 'pr-5',
}

const inputPaddingClasses: Record<SearchInputSize, string> = {
  xs: 'pl-7 pr-7',
  sm: 'pl-8 pr-8',
  md: 'pl-10 pr-10',
  lg: 'pl-12 pr-12',
  xl: 'pl-14 pr-14',
}

const roundedClasses: Record<SearchInputVariant, string> = {
  default: 'rounded-xl',
  ghost: 'rounded-xl',
  filled: 'rounded-xl',
  outlined: 'rounded-xl',
  pill: 'rounded-full',
  minimal: 'rounded-lg',
}

// Container classes
const containerClasses = computed(() => [
  'relative',
  props.fullWidth && 'w-full',
])

// Input classes
const inputClasses = computed(() => {
  const classes = [
    'w-full transition-all duration-200',
    'placeholder:text-olympus-text-subtle',
    'focus:outline-none',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    '[appearance:textfield]',
    '[&::-webkit-search-cancel-button]:hidden',
    '[&::-webkit-search-decoration]:hidden',
    sizeClasses[props.size],
    inputPaddingClasses[props.size],
    roundedClasses[props.variant],
  ]

  // Variant-specific styling
  switch (props.variant) {
    case 'ghost':
      classes.push(
        'bg-transparent border border-transparent text-olympus-text',
        'hover:bg-olympus-surface/50',
        'focus:bg-olympus-surface focus:border-olympus-border',
      )
      break
    case 'filled':
      classes.push(
        'bg-olympus-surface/80 border border-transparent text-olympus-text',
        'hover:bg-olympus-surface',
        'focus:bg-olympus-surface focus:border-olympus-primary focus:shadow-glow-sm',
      )
      break
    case 'outlined':
      classes.push(
        'bg-transparent border-2 border-olympus-border text-olympus-text',
        'hover:border-olympus-text-muted',
        'focus:border-olympus-primary focus:shadow-glow-sm',
      )
      break
    case 'pill':
      classes.push(
        'bg-olympus-surface border border-olympus-border text-olympus-text',
        'focus:border-olympus-primary',
      )
      break
    case 'minimal':
      classes.push(
        'bg-transparent border-b border-olympus-border text-olympus-text',
        'focus:border-olympus-primary',
        'rounded-none',
      )
      break
    default:
      classes.push(
        'bg-olympus-surface border border-olympus-border text-olympus-text',
        'focus:border-olympus-primary focus:shadow-glow-sm',
      )
  }

  // Floating label adjustments
  if (props.floatingLabel && props.label) {
    classes.push('pt-4')
  }

  // Error state
  if (props.error) {
    classes.push('border-red-400 focus:border-red-400 focus:shadow-red-500/20')
  }

  return classes
})

// Input styles
const inputStyles = computed(() => {
  const styles: Record<string, string> = {}
  return styles
})

// Floating label classes
const floatingLabelClasses = computed(() => {
  const hasValue = model.value || isFocused.value

  return [
    'absolute left-3 transition-all duration-200 pointer-events-none',
    'text-olympus-text-muted',
    hasValue
      ? 'top-1 text-[10px] text-olympus-primary'
      : 'top-1/2 -translate-y-1/2 text-sm',
  ]
})

// Clear button classes
const clearButtonClasses = computed(() => [
  'p-1 rounded-md transition-all duration-200',
  'text-olympus-text-muted hover:text-olympus-text',
  'hover:bg-olympus-surface active:scale-95',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Search button classes
const searchButtonClasses = computed(() => [
  'p-1.5 rounded-lg transition-all duration-200',
  'bg-olympus-primary text-white',
  'hover:bg-olympus-primary-hover active:scale-95',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50',
])

// Debounced search
let debounceTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = (value: string) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  searching.value = true
  debounceTimer = setTimeout(() => {
    emit('search', value)
    searching.value = false
  }, props.debounce)
}

// Event handlers
const handleFocus = () => {
  isFocused.value = true
  emit('focus')
}

const handleBlur = () => {
  // Delay to allow click on recent searches
  setTimeout(() => {
    isFocused.value = false
    highlightedIndex.value = -1
    emit('blur')
  }, 150)
}

const handleInput = () => {
  highlightedIndex.value = -1
}

const handleSearch = () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  searching.value = false
  emit('search', model.value)
}

const handleEscape = () => {
  if (model.value) {
    handleClear()
  } else {
    inputRef.value?.blur()
  }
}

const handleClear = () => {
  model.value = ''
  emit('clear')
  nextTick(() => inputRef.value?.focus())
}

const handleArrowUp = () => {
  if (props.showRecentSearches && props.recentSearches.length > 0) {
    highlightedIndex.value = Math.max(0, highlightedIndex.value - 1)
  }
}

const handleArrowDown = () => {
  if (props.showRecentSearches && props.recentSearches.length > 0) {
    highlightedIndex.value = Math.min(props.recentSearches.length - 1, highlightedIndex.value + 1)
  }
}

const selectRecentSearch = (search: string) => {
  model.value = search
  emit('selectRecentSearch', search)
  emit('search', search)
}

// Voice input (Web Speech API)
const toggleVoiceInput = () => {
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    console.warn('Speech recognition not supported')
    return
  }

  if (isListening.value) {
    isListening.value = false
    return
  }

  const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition
  const recognition = new SpeechRecognition()

  recognition.continuous = false
  recognition.interimResults = false

  recognition.onstart = () => {
    isListening.value = true
    emit('voiceStart')
  }

  recognition.onresult = (event: any) => {
    const transcript = event.results[0][0].transcript
    model.value = transcript
    emit('voiceEnd', transcript)
    emit('search', transcript)
  }

  recognition.onend = () => {
    isListening.value = false
  }

  recognition.onerror = () => {
    isListening.value = false
  }

  recognition.start()
}

// Focus management
const focus = () => inputRef.value?.focus()
const blur = () => inputRef.value?.blur()
const select = () => inputRef.value?.select()

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
defineExpose({ focus, blur, select })
</script>

<style scoped>
/* Icon fade transition */
.icon-fade-enter-active,
.icon-fade-leave-active {
  transition: all 0.2s ease;
}

.icon-fade-enter-from,
.icon-fade-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

/* Scale transition */
.scale-enter-active,
.scale-leave-active {
  transition: all 0.2s ease;
}

.scale-enter-from,
.scale-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

/* Fade transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Custom focus glow */
.shadow-glow-sm {
  box-shadow: 0 0 0 3px oklch(var(--color-olympus-primary) / 0.15);
}
</style>
