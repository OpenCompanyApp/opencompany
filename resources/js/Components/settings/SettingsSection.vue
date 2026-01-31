<template>
  <section
    class="group/section bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden transition-colors duration-150 hover:border-neutral-300"
    :class="variant === 'danger' && 'hover:border-red-300'"
  >
    <header
      class="px-6 py-4 border-b flex items-center gap-3 transition-colors duration-150"
      :class="variant === 'danger' ? 'border-red-200' : 'border-neutral-200 dark:border-neutral-700'"
    >
      <div
        class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors duration-150"
        :class="[
          variant === 'danger'
            ? 'bg-red-50 text-red-600'
            : 'bg-neutral-100 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-200'
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
          :class="variant === 'danger' ? 'text-red-600' : 'text-neutral-900 dark:text-white'"
        >
          {{ title }}
        </h2>
        <p
          v-if="description"
          class="text-sm text-neutral-500 dark:text-neutral-300 mt-0.5"
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
import Icon from '@/Components/shared/Icon.vue'

withDefaults(defineProps<{
  title: string
  icon: string
  description?: string
  variant?: 'default' | 'danger'
}>(), {
  variant: 'default',
})
</script>
