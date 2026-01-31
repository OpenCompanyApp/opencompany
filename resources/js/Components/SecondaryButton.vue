<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center h-10 px-4 rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 text-sm font-medium text-neutral-700 dark:text-neutral-200 shadow-sm transition-colors duration-150 hover:bg-neutral-50 dark:hover:bg-neutral-800 hover:border-neutral-400 dark:hover:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 dark:focus:ring-white/20 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
    >
        <slot />
    </button>
</template>
