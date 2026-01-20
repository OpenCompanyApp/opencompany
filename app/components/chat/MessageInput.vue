<template>
  <div class="p-4 border-t border-olympus-border shrink-0 bg-olympus-bg">
    <div
      :class="[
        'flex items-end gap-3 bg-olympus-surface rounded-lg px-4 py-3 border transition-all duration-200',
        isFocused ? 'border-olympus-primary shadow-glow-sm' : 'border-olympus-border',
        sending && 'opacity-75'
      ]"
    >
      <button
        class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-elevated transition-colors duration-150 shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="sending"
      >
        <Icon name="ph:plus-circle" class="w-5 h-5" />
      </button>

      <div class="flex-1 min-w-0">
        <textarea
          ref="textarea"
          v-model="message"
          :placeholder="`Message #${channel.name}`"
          rows="1"
          :disabled="sending"
          class="w-full bg-transparent outline-none text-sm text-olympus-text placeholder:text-olympus-text-subtle resize-none max-h-32 disabled:opacity-50 disabled:cursor-not-allowed"
          @input="autoResize"
          @keydown.enter.exact.prevent="handleSend"
          @focus="isFocused = true"
          @blur="isFocused = false"
        />
      </div>

      <div class="flex items-center gap-0.5 shrink-0">
        <button
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-elevated transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="sending"
        >
          <Icon name="ph:at" class="w-5 h-5" />
        </button>
        <button
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-elevated transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="sending"
        >
          <Icon name="ph:smiley" class="w-5 h-5" />
        </button>
        <button
          class="p-1.5 rounded-md text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-elevated transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="sending"
        >
          <Icon name="ph:paperclip" class="w-5 h-5" />
        </button>
        <button
          :class="[
            'ml-2 h-9 px-4 rounded-lg text-sm font-medium transition-all duration-150 outline-none',
            'focus-visible:ring-2 focus-visible:ring-olympus-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-olympus-surface',
            canSend
              ? 'bg-olympus-primary hover:bg-olympus-primary-hover text-white active:scale-[0.98] shadow-lg shadow-olympus-primary/25 hover:shadow-olympus-primary/40'
              : 'bg-olympus-elevated text-olympus-text-subtle cursor-not-allowed'
          ]"
          :disabled="!canSend"
          @click="handleSend"
        >
          <Icon v-if="sending" name="ph:spinner" class="w-4 h-4 animate-spin" />
          <span v-else>Send</span>
        </button>
      </div>
    </div>

    <p class="text-xs text-olympus-text-subtle mt-2 px-1">
      <kbd class="px-1.5 py-0.5 bg-olympus-surface border border-olympus-border rounded text-[10px] font-mono">Enter</kbd> to send,
      <kbd class="px-1.5 py-0.5 bg-olympus-surface border border-olympus-border rounded text-[10px] font-mono">Shift+Enter</kbd> for new line
    </p>
  </div>
</template>

<script setup lang="ts">
import type { Channel } from '~/types'

const props = withDefaults(defineProps<{
  channel: Channel
  sending?: boolean
}>(), {
  sending: false,
})

const emit = defineEmits<{
  send: [message: string]
}>()

const message = ref('')
const textarea = ref<HTMLTextAreaElement | null>(null)
const isFocused = ref(false)

const canSend = computed(() => message.value.trim() && !props.sending)

const autoResize = () => {
  if (textarea.value) {
    textarea.value.style.height = 'auto'
    textarea.value.style.height = `${textarea.value.scrollHeight}px`
  }
}

const handleSend = () => {
  if (canSend.value) {
    emit('send', message.value.trim())
    message.value = ''
    if (textarea.value) {
      textarea.value.style.height = 'auto'
    }
  }
}
</script>
