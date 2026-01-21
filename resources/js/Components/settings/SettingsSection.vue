<template>
  <section
    class="group/section bg-white rounded-xl border border-gray-200 overflow-hidden transition-colors duration-150 hover:border-gray-300"
    :class="variant === 'danger' && 'hover:border-red-300'"
  >
    <header
      class="px-6 py-4 border-b flex items-center gap-3 transition-colors duration-150"
      :class="variant === 'danger' ? 'border-red-200' : 'border-gray-200'"
    >
      <div
        class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-150"
        :class="[
          variant === 'danger'
            ? 'bg-red-50 text-red-600'
            : 'bg-gray-100 text-gray-600'
        ]"
      >
        <Icon
          :name="icon"
          class="w-5 h-5"
        />
      </div>
      <div class="flex-1">
        <h2
          class="text-lg font-semibold transition-colors duration-150"
          :class="variant === 'danger' ? 'text-red-600' : 'text-gray-900'"
        >
          {{ title }}
        </h2>
        <p
          v-if="description"
          class="text-sm text-gray-500 mt-0.5"
        >
          {{ description }}
        </p>
      </div>
      <slot name="actions" />
    </header>
    <div class="p-6">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'

withDefaults(defineProps<{
  title: string
  icon: string
  description?: string
  variant?: 'default' | 'danger'
}>(), {
  variant: 'default',
})
</script>
