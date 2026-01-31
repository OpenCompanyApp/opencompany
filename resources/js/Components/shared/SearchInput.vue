<template>
  <div :class="containerClasses">
    <!-- Label (if floating is disabled) -->
    <label
      v-if="label && !floatingLabel"
      :for="inputId"
      class="block text-sm font-medium text-neutral-700 dark:text-neutral-200 mb-1.5"
    >
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
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
          'absolute left-0 top-0 bottom-0 flex items-center pointer-events-none',
          paddingLeftClasses[size],
        ]"
      >
        <slot name="prefix">
          <Icon
            v-if="loading"
            name="ph:spinner"
            :class="[iconSizeClasses[size], 'text-neutral-400 dark:text-neutral-400 animate-spin']"
          />
          <Icon
            v-else
            :name="prefixIcon"
            :class="[
              iconSizeClasses[size],
              isFocused ? 'text-neutral-600 dark:text-neutral-200' : 'text-neutral-400 dark:text-neutral-400',
              'transition-colors duration-150',
            ]"
          />
        </slot>
      </div>

      <!-- Floating Label -->
      <label
        v-if="floatingLabel && label"
        :for="inputId"
        :class="floatingLabelClasses"
      >
        {{ label }}
        <span v-if="required" class="text-red-500 ml-0.5">*</span>
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
        <span
          v-if="showResultsCount && resultsCount !== undefined && model"
          class="text-xs text-neutral-500 dark:text-neutral-300 tabular-nums mr-1"
        >
          {{ resultsCount }} {{ resultsCount === 1 ? 'result' : 'results' }}
        </span>

        <!-- Clear Button -->
        <Button
          v-if="clearable && model && !disabled && !readonly"
          icon="ph:x"
          color="neutral"
          variant="ghost"
          size="xs"
          :padded="false"
          :aria-label="clearLabel"
          @click="handleClear"
        />

        <!-- Voice Input Button -->
        <Button
          v-if="voiceInput && !disabled && !readonly"
          :icon="isListening ? 'ph:microphone-fill' : 'ph:microphone'"
          :color="isListening ? 'error' : 'neutral'"
          variant="ghost"
          size="xs"
          :aria-label="isListening ? 'Stop listening' : 'Start voice search'"
          @click="toggleVoiceInput"
        />

        <!-- Search/Submit Button -->
        <Button
          v-if="showSearchButton && model && !disabled"
          icon="ph:arrow-right"
          color="neutral"
          variant="solid"
          size="xs"
          :aria-label="searchButtonLabel"
          @click="handleSearch"
        />

        <!-- Custom suffix slot -->
        <slot name="suffix" />
      </div>
    </div>

    <!-- Helper Text / Error -->
    <div
      v-if="helperText || error"
      :class="[
        'mt-1.5 text-xs',
        error ? 'text-red-500' : 'text-neutral-500 dark:text-neutral-300',
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
      class="absolute right-3 top-full mt-2 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-300 z-10"
    >
      <kbd class="px-1.5 py-0.5 text-xs font-mono bg-neutral-100 dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded">Enter</kbd>
      <span>to search</span>
      <kbd class="px-1.5 py-0.5 text-xs font-mono bg-neutral-100 dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-600 rounded">Esc</kbd>
      <span>to clear</span>
    </div>

    <!-- Recent Searches Dropdown -->
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-100 ease-out"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showRecentSearches && recentSearches.length > 0 && isFocused && !model"
        class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg shadow-md z-20 overflow-hidden"
      >
        <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-700 flex items-center justify-between">
          <span class="text-xs font-semibold text-neutral-500 dark:text-neutral-300 uppercase tracking-wider">Recent Searches</span>
          <Button
            color="neutral"
            variant="link"
            size="xs"
            @click="$emit('clearRecentSearches')"
          >
            Clear all
          </Button>
        </div>
        <ul class="py-2 max-h-48 overflow-y-auto">
          <li
            v-for="(search, index) in recentSearches"
            :key="index"
            :class="[
              'px-4 py-2.5 flex items-center gap-3 cursor-pointer transition-colors duration-150',
              highlightedIndex === index
                ? 'bg-neutral-100 dark:bg-neutral-700 text-neutral-900 dark:text-white'
                : 'text-neutral-600 dark:text-neutral-200 hover:bg-neutral-50 dark:hover:bg-neutral-800 hover:text-neutral-900 dark:hover:text-white',
            ]"
            @click="selectRecentSearch(search)"
            @mouseenter="highlightedIndex = index"
          >
            <Icon name="ph:clock-counter-clockwise" class="w-4 h-4 flex-shrink-0" />
            <span class="text-sm truncate flex-1">{{ search }}</span>
            <Icon name="ph:arrow-up-left" class="w-3 h-3 opacity-50" />
          </li>
        </ul>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import Icon from '@/Components/shared/Icon.vue'
import Button from '@/Components/shared/Button.vue'

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
  searchAnimation: false,
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
  default: 'rounded-lg',
  ghost: 'rounded-lg',
  filled: 'rounded-lg',
  outlined: 'rounded-lg',
  pill: 'rounded-full',
  minimal: 'rounded-md',
}

// Container classes
const containerClasses = computed(() => [
  'relative',
  props.fullWidth && 'w-full',
])

// Input classes
const inputClasses = computed(() => {
  const classes = [
    'w-full transition-colors duration-150',
    'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
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
        'bg-transparent border border-transparent text-neutral-900 dark:text-white',
        'hover:bg-neutral-50 dark:hover:bg-neutral-800',
        'focus:bg-neutral-50 dark:focus:bg-neutral-800 focus:border-neutral-200 dark:focus:border-neutral-700',
      )
      break
    case 'filled':
      classes.push(
        'bg-neutral-100 dark:bg-neutral-700 border border-transparent text-neutral-900 dark:text-white',
        'hover:bg-neutral-50 dark:hover:bg-neutral-800',
        'focus:bg-white dark:focus:bg-neutral-900 focus:border-neutral-300 dark:focus:border-neutral-600',
      )
      break
    case 'outlined':
      classes.push(
        'bg-transparent border border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white',
        'hover:border-neutral-400 dark:hover:border-neutral-500',
        'focus:border-neutral-900 dark:focus:border-neutral-400',
      )
      break
    case 'pill':
      classes.push(
        'bg-neutral-100 dark:bg-neutral-700 border border-neutral-200 dark:border-neutral-700 text-neutral-900 dark:text-white',
        'focus:border-neutral-300 dark:focus:border-neutral-600',
      )
      break
    case 'minimal':
      classes.push(
        'bg-transparent border-b border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white',
        'focus:border-neutral-600 dark:focus:border-neutral-400',
        'rounded-none',
      )
      break
    default:
      classes.push(
        'bg-white dark:bg-neutral-900 border border-neutral-300 dark:border-neutral-600 text-neutral-900 dark:text-white',
        'focus:border-neutral-900 dark:focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/20',
      )
  }

  // Floating label adjustments
  if (props.floatingLabel && props.label) {
    classes.push('pt-4')
  }

  // Error state
  if (props.error) {
    classes.push('border-red-300 focus:border-red-500 focus:ring-red-500/20')
  }

  return classes
})

// Floating label classes
const floatingLabelClasses = computed(() => {
  const hasValue = model.value || isFocused.value

  return [
    'absolute left-3 transition-all duration-150 pointer-events-none',
    'text-neutral-500 dark:text-neutral-300',
    hasValue
      ? 'top-1 text-[10px] text-neutral-600 dark:text-neutral-200'
      : 'top-1/2 -translate-y-1/2 text-sm',
  ]
})

// Clear button classes
const clearButtonClasses = computed(() => [
  'p-1 rounded-md transition-colors duration-150',
  'text-neutral-400 dark:text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300',
  'hover:bg-neutral-100 dark:hover:bg-neutral-700',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/50',
])

// Search button classes
const searchButtonClasses = computed(() => [
  'p-1.5 rounded-md transition-colors duration-150',
  'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900',
  'hover:bg-neutral-700 dark:hover:bg-neutral-200',
  'focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/50',
])

// Debounced search
let debounceTimer: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = (value: string) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    emit('search', value)
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
