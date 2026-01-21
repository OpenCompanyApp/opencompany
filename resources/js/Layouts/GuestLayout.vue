<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-50 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 fill-current text-gray-900" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-8 py-6 border border-gray-200 shadow-sm sm:max-w-md sm:rounded-xl"
        >
            <slot />
        </div>
    </div>
</template>
