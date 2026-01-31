<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-neutral-50 dark:bg-neutral-800 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 fill-current text-neutral-900 dark:text-white" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white dark:bg-neutral-900 px-8 py-6 border border-neutral-200 dark:border-neutral-700 shadow-sm sm:max-w-md sm:rounded-xl"
        >
            <slot />
        </div>
    </div>
</template>
