<template>
  <div class="relative group my-4 rounded-xl overflow-hidden border border-olympus-border bg-olympus-surface">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-2 bg-olympus-elevated border-b border-olympus-border">
      <span class="text-xs text-olympus-text-muted font-mono">
        {{ language || 'plaintext' }}
      </span>
      <button
        class="flex items-center gap-1.5 px-2 py-1 rounded-md text-xs text-olympus-text-muted hover:text-olympus-text hover:bg-olympus-surface transition-colors opacity-0 group-hover:opacity-100"
        :class="{ 'opacity-100': copied }"
        @click="copyCode"
      >
        <Icon :name="copied ? 'ph:check' : 'ph:copy'" class="w-3.5 h-3.5" />
        <span>{{ copied ? 'Copied!' : 'Copy' }}</span>
      </button>
    </div>

    <!-- Code -->
    <pre class="p-4 overflow-x-auto"><code class="text-sm font-mono text-olympus-text leading-relaxed">{{ code }}</code></pre>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  code: string
  language?: string
}>()

const copied = ref(false)
let copyTimeout: ReturnType<typeof setTimeout> | null = null

const copyCode = async () => {
  try {
    await navigator.clipboard.writeText(props.code)
    copied.value = true

    if (copyTimeout) {
      clearTimeout(copyTimeout)
    }

    copyTimeout = setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // Clipboard API not available
  }
}

onUnmounted(() => {
  if (copyTimeout) {
    clearTimeout(copyTimeout)
  }
})
</script>
