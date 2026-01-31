<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center justify-center bg-neutral-100 dark:bg-neutral-950 px-4">
    <!-- Noise texture overlay -->
    <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]" aria-hidden="true">
      <svg class="w-full h-full">
        <filter id="noise">
          <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch"/>
        </filter>
        <rect width="100%" height="100%" filter="url(#noise)"/>
      </svg>
    </div>

    <!-- Dot grid overlay -->
    <div
      class="absolute inset-0 opacity-[0.4] dark:opacity-[0.15]"
      style="background-image: radial-gradient(circle, rgb(163 163 163) 1px, transparent 1px); background-size: 24px 24px;"
      aria-hidden="true"
    />

    <!-- Logo -->
    <Link href="/" class="relative z-10 mb-8">
      <ApplicationLogo />
    </Link>

    <!-- Card -->
    <div class="relative z-10 w-full max-w-sm bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 shadow-sm p-6">
      <slot />
    </div>

    <!-- Footer -->
    <p class="relative z-10 mt-6 text-sm text-neutral-500 dark:text-neutral-400 text-center">
      <slot name="footer" />
    </p>
  </div>
</template>
