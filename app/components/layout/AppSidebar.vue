<template>
  <aside class="w-64 bg-olympus-sidebar border-r border-olympus-border flex flex-col shrink-0">
    <!-- Logo / Org Header -->
    <div class="p-4 border-b border-olympus-border">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-olympus-primary flex items-center justify-center shadow-lg shadow-olympus-primary/40 glow">
          <Icon name="ph:lightning-fill" class="w-5 h-5 text-white" />
        </div>
        <div class="flex-1 min-w-0">
          <h1 class="font-semibold text-base leading-tight">Olympus</h1>
          <p class="text-xs text-olympus-text-muted truncate">Bloom Agency</p>
        </div>
        <button class="p-2 rounded-lg hover:bg-olympus-surface transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-olympus-primary/50">
          <Icon name="ph:gear-six" class="w-4 h-4 text-olympus-text-muted" />
        </button>
      </div>
    </div>

    <!-- Navigation -->
    <LayoutSidebarNav />

    <!-- Credits Display -->
    <div class="p-4 border-t border-olympus-border">
      <div class="flex items-center justify-between text-sm">
        <span class="text-olympus-text-muted">Credits</span>
        <span class="font-medium">${{ stats.creditsRemaining.toLocaleString() }}</span>
      </div>
      <div class="mt-2 h-1.5 bg-olympus-bg rounded-full overflow-hidden">
        <div
          class="h-full bg-gradient-to-r from-olympus-primary to-olympus-accent rounded-full transition-all duration-500 shadow-sm shadow-olympus-primary/50"
          :style="{ width: `${(stats.creditsRemaining / 3000) * 100}%` }"
        />
      </div>
    </div>

    <!-- User Menu -->
    <LayoutUserMenu />
  </aside>
</template>

<script setup lang="ts">
const { stats } = useMockData()
</script>
