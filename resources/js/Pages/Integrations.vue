<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-6xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <div class="flex items-center justify-between">
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Integrations</h1>
          <div class="flex items-center gap-1">
            <Link
              :href="developerToolsUrl()"
              class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              <Icon name="ph:code" class="w-3.5 h-3.5" />
              Tool Catalog
            </Link>
            <Link
              :href="developerLuaConsoleUrl()"
              class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              <Icon name="ph:terminal" class="w-3.5 h-3.5" />
              Lua Console
            </Link>
          </div>
        </div>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Connect external services and manage API access
        </p>
      </header>

      <!-- Sidebar + Content -->
      <div class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
        <!-- Mobile Nav -->
        <div class="flex flex-col gap-3 md:hidden shrink-0">
          <!-- Mobile Search -->
          <div class="relative">
            <Icon name="ph:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
            <input
              v-model="searchQuery"
              data-test="integration-search"
              type="text"
              placeholder="Search integrations..."
              class="w-full pl-8 pr-3 py-2 text-sm rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="searchQuery = ''"
            >
              <Icon name="ph:x" class="w-3.5 h-3.5" />
            </button>
          </div>
          <!-- Mobile Category Pills -->
          <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-4 px-4" style="-ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            <button
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === 'all' && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="setCategory('all')"
            >
              All
            </button>
            <button
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === 'installed' && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="setCategory('installed')"
            >
              <Icon name="ph:check-circle" class="w-3.5 h-3.5" />
              Installed
              <span
                v-if="installedCount > 0"
                class="text-[10px] px-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"
              >
                {{ installedCount }}
              </span>
            </button>
            <button
              v-for="category in visibleNativeCategories"
              :key="'mobile-' + category.id"
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === category.id && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="setCategory(category.id)"
            >
              <Icon :name="category.icon" class="w-3.5 h-3.5" />
              {{ category.name }}
            </button>
            <button
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === 'mcp-servers' && !searchQuery
                  ? 'bg-purple-600 text-white'
                  : 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400',
              ]"
              @click="setCategory('mcp-servers')"
            >
              <Icon name="ph:plugs-connected" class="w-3.5 h-3.5" />
              MCP
            </button>
            <button
              type="button"
              class="ai-gateway-btn flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0"
              @click="showAiGatewayConfigModal = true"
            >
              <Icon name="ph:diamond" class="w-3.5 h-3.5" />
              API
            </button>
          </div>
        </div>

        <!-- Desktop Sidebar -->
        <nav class="hidden md:flex w-52 shrink-0 flex-col gap-1 overflow-y-auto">
          <!-- Search -->
          <div class="relative mb-3">
            <Icon name="ph:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400" />
            <input
              v-model="searchQuery"
              data-test="integration-search"
              type="text"
              placeholder="Search..."
              class="w-full pl-8 pr-3 py-1.5 text-xs rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white placeholder-neutral-400 focus:outline-none focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500"
            />
            <button
              v-if="searchQuery"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="searchQuery = ''"
            >
              <Icon name="ph:x" class="w-3 h-3" />
            </button>
          </div>

          <!-- All -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === 'all' && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="setCategory('all')"
          >
            <Icon name="ph:squares-four" class="w-4 h-4" />
            All
            <span class="ml-auto text-[10px] opacity-60">{{ totalIntegrationCount }}</span>
          </button>

          <!-- Installed -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === 'installed' && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="setCategory('installed')"
          >
            <Icon name="ph:check-circle" class="w-4 h-4" />
            Installed
            <span
              v-if="installedCount > 0"
              class="ml-auto text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"
            >
              {{ installedCount }}
            </span>
          </button>

          <!-- Divider -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

          <!-- Categories -->
          <button
            v-for="category in visibleNativeCategories"
            :key="category.id"
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === category.id && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="setCategory(category.id)"
          >
            <Icon :name="category.icon" class="w-4 h-4" />
            {{ category.name }}
            <span class="ml-auto text-[10px] opacity-60">{{ category.integrations.length }}</span>
          </button>

          <!-- MCP Servers Section -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === 'mcp-servers' && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="setCategory('mcp-servers')"
          >
            <Icon name="ph:plugs-connected" class="w-4 h-4" />
            MCP Servers
            <span v-if="mcpCategory" class="ml-auto text-[10px] opacity-60">{{ mcpCategory.integrations.length }}</span>
          </button>

          <!-- AI Gateway -->
          <button
            type="button"
            class="ai-gateway-btn flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full"
            @click="showAiGatewayConfigModal = true"
          >
            <Icon name="ph:diamond" class="w-4 h-4" />
            AI Gateway
          </button>

          <!-- Add MCP Server -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />
          <button
            type="button"
            class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors text-left w-full"
            @click="openAddMcpServer"
          >
            <Icon name="ph:plus-circle" class="w-4 h-4" />
            Add MCP Server
          </button>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 overflow-y-auto">
          <!-- Search Results -->
          <template v-if="searchQuery">
            <div class="mb-4">
              <h2 class="text-sm font-medium text-neutral-900 dark:text-white">
                Search results for "{{ searchQuery }}"
              </h2>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                {{ searchResults.length }} integration{{ searchResults.length === 1 ? '' : 's' }} found
              </p>
            </div>

            <div v-if="searchResults.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <IntegrationCard
                v-for="integration in searchResults"
                :key="integration.id"
                :integration="integration"
                @install="handleInstall"
                @uninstall="handleUninstall"
                @configure="handleConfigure"
              />
            </div>

            <div v-else class="text-center py-16">
              <Icon name="ph:magnifying-glass" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
              <p class="text-sm text-neutral-500 dark:text-neutral-400">No integrations match your search</p>
            </div>
          </template>

          <!-- Installed View -->
          <template v-else-if="activeCategory === 'installed'">
            <!-- Connected Services -->
            <section class="mb-8">
              <h2 class="text-sm font-medium text-neutral-900 dark:text-white mb-3">Connected Services</h2>

              <div
                v-if="integrationStatusError"
                class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-300"
              >
                {{ integrationStatusError }}
              </div>

              <div v-if="!integrationStatusError && connectedServices.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                <IntegrationCard
                  v-for="service in installedIntegrations"
                  :key="service.id"
                  :integration="service"
                  @install="handleInstall"
                  @uninstall="handleUninstall"
                  @configure="handleConfigure"
                />
              </div>

              <div v-else-if="!integrationStatusError" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center mb-6">
                <Icon name="ph:plugs-connected" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                <p class="text-sm text-neutral-500 dark:text-neutral-400">No connected services</p>
                <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                  Browse the
                  <button type="button" class="text-neutral-900 dark:text-white underline" @click="setCategory('all')">
                    library
                  </button>
                  to connect integrations
                </p>
              </div>
            </section>

            <!-- Telegram Agent Shell -->
            <section v-if="telegramIntegrationInstalled" class="mb-8">
              <div class="flex flex-col gap-3 rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <h2 class="text-sm font-medium text-neutral-900 dark:text-white flex items-center gap-2">
                      <Icon name="ph:telegram-logo" class="w-4 h-4 text-sky-500" />
                      Telegram agent shell
                    </h2>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                      Health, command sync, webhook repair, and delivery operations for the agent-first Telegram bot.
                    </p>
                  </div>
                  <div class="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                      :disabled="telegramAction !== null"
                      @click="runTelegramHealthCheck"
                    >
                      <Icon :name="telegramAction === 'health' ? 'ph:circle-notch' : 'ph:pulse'" class="w-3.5 h-3.5" :class="{ 'animate-spin': telegramAction === 'health' }" />
                      Health
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                      :disabled="telegramAction !== null"
                      @click="syncTelegramBotProfile"
                    >
                      <Icon :name="telegramAction === 'sync' ? 'ph:circle-notch' : 'ph:arrows-clockwise'" class="w-3.5 h-3.5" :class="{ 'animate-spin': telegramAction === 'sync' }" />
                      Sync bot UX
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                      :disabled="telegramAction !== null"
                      @click="sendTelegramTest"
                    >
                      <Icon :name="telegramAction === 'test' ? 'ph:circle-notch' : 'ph:paper-plane-tilt'" class="w-3.5 h-3.5" :class="{ 'animate-spin': telegramAction === 'test' }" />
                      Test send
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                      :disabled="telegramAction !== null"
                      @click="setupTelegramWebhook"
                    >
                      <Icon :name="telegramAction === 'webhook' ? 'ph:circle-notch' : 'ph:webhooks-logo'" class="w-3.5 h-3.5" :class="{ 'animate-spin': telegramAction === 'webhook' }" />
                      Reset webhook
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                      :disabled="telegramAction !== null"
                      @click="rotateTelegramSecret"
                    >
                      <Icon :name="telegramAction === 'rotate' ? 'ph:circle-notch' : 'ph:key'" class="w-3.5 h-3.5" :class="{ 'animate-spin': telegramAction === 'rotate' }" />
                      Rotate secret
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                      :disabled="telegramAction !== null"
                      @click="repairTelegramLocalState"
                    >
                      <Icon :name="telegramAction === 'repair' ? 'ph:circle-notch' : 'ph:wrench'" class="w-3.5 h-3.5" :class="{ 'animate-spin': telegramAction === 'repair' }" />
                      Repair local state
                    </button>
                  </div>
                </div>

                <div v-if="telegramError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-300">
                  {{ telegramError }}
                </div>
                <div v-if="telegramNotice" class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700 dark:border-green-800/70 dark:bg-green-950/30 dark:text-green-300">
                  {{ telegramNotice }}
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                  <div
                    v-for="stat in telegramStats"
                    :key="stat.label"
                    class="rounded-md border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-950/40 px-3 py-2"
                  >
                    <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ stat.label }}</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900 dark:text-white">{{ stat.value }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                  <div class="rounded-md border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
                    <div class="px-3 py-2 flex items-center justify-between">
                      <p class="text-xs font-medium text-neutral-900 dark:text-white">Delivery queue</p>
                      <button
                        type="button"
                        class="text-[11px] text-neutral-500 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white"
                        @click="loadTelegramOperations"
                      >
                        Refresh
                      </button>
                    </div>
                    <div v-if="telegramDeliveryLogs.length" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                      <div v-for="delivery in telegramDeliveryLogs" :key="delivery.id" class="px-3 py-2 flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-medium text-neutral-900 dark:text-white truncate">
                            {{ delivery.method || 'sendMessage' }} · {{ delivery.status }}
                          </p>
                          <p class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">
                            Chat {{ delivery.chat_id }}{{ delivery.telegram_message_id ? ` · message ${delivery.telegram_message_id}` : '' }}
                          </p>
                        </div>
                        <button
                          v-if="delivery.status === 'failed'"
                          type="button"
                          class="shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[11px] rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                          :disabled="telegramAction !== null"
                          @click="retryTelegramDelivery(delivery.id)"
                        >
                          <Icon name="ph:arrow-clockwise" class="w-3 h-3" />
                          Retry
                        </button>
                      </div>
                    </div>
                    <p v-else class="px-3 py-5 text-xs text-neutral-500 dark:text-neutral-400 text-center">
                      No recent Telegram deliveries.
                    </p>
                  </div>

                  <div class="rounded-md border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
                    <div class="px-3 py-2 flex items-center justify-between">
                      <p class="text-xs font-medium text-neutral-900 dark:text-white">Webhook receipts</p>
                      <span class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ telegramReceiptLogs.length }}</span>
                    </div>
                    <div v-if="telegramReceiptLogs.length" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                      <div v-for="receipt in telegramReceiptLogs" :key="receipt.id" class="px-3 py-2 flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-medium text-neutral-900 dark:text-white truncate">
                            {{ receipt.update_type || 'update' }} · {{ receipt.status }}
                          </p>
                          <p class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">
                            Update {{ receipt.update_id }}{{ receipt.retry_count ? ` · ${receipt.retry_count} retries` : '' }}
                          </p>
                        </div>
                        <button
                          v-if="receipt.status === 'failed'"
                          type="button"
                          class="shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[11px] rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 disabled:opacity-60"
                          :disabled="telegramAction !== null"
                          @click="replayTelegramReceipt(receipt.id)"
                        >
                          <Icon name="ph:play" class="w-3 h-3" />
                          Replay
                        </button>
                      </div>
                    </div>
                    <p v-else class="px-3 py-5 text-xs text-neutral-500 dark:text-neutral-400 text-center">
                      No recent Telegram webhook receipts.
                    </p>
                  </div>
                </div>
              </div>
            </section>

            <!-- Webhooks Section -->
            <section class="mb-8">
              <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-medium text-neutral-900 dark:text-white">Webhooks</h2>
                <button
                  type="button"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150"
                  @click="openNewWebhook"
                >
                  <Icon name="ph:plus" class="w-3.5 h-3.5" />
                  Add webhook
                </button>
              </div>

              <div
                v-if="webhooksError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-300"
              >
                {{ webhooksError }}
              </div>

              <div v-else-if="webhooks.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
                <div
                  v-for="webhook in webhooks"
                  :key="webhook.id"
                  class="px-4 py-3"
                >
                  <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ webhook.name }}</p>
                        <span
                          :class="[
                            'px-1.5 py-0.5 text-xs rounded',
                            webhook.enabled
                              ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                              : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-500 dark:text-neutral-400'
                          ]"
                        >
                          {{ webhook.enabled ? 'Active' : 'Disabled' }}
                        </span>
                      </div>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 font-mono">
                        POST {{ webhook.endpoint || `/api/webhooks/${webhook.id}` }}
                      </p>
                      <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                        Last triggered: {{ webhook.lastTriggered || 'Never' }}
                        <span v-if="webhook.callCount"> · {{ webhook.callCount }} calls this week</span>
                      </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                        @click="editWebhook(webhook)"
                      >
                        <Icon name="ph:pencil-simple" class="w-4 h-4" />
                      </button>
                      <button
                        type="button"
                        class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors"
                        @click="deleteWebhook(webhook.id)"
                      >
                        <Icon name="ph:trash" class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center">
                <Icon name="ph:webhooks-logo" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                <p class="text-sm text-neutral-500 dark:text-neutral-400">No webhooks configured</p>
                <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Add a webhook to receive events from external services</p>
              </div>
            </section>

            <!-- API Keys Section -->
            <section>
              <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-medium text-neutral-900 dark:text-white">API Keys</h2>
                <button
                  type="button"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
                  @click="generateApiKey"
                >
                  <Icon name="ph:key" class="w-3.5 h-3.5" />
                  Generate key
                </button>
              </div>

              <div
                v-if="apiKeysError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-300"
              >
                {{ apiKeysError }}
              </div>

              <div v-else-if="apiKeys.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
                <div
                  v-for="key in apiKeys"
                  :key="key.id"
                  class="px-4 py-3"
                >
                  <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ key.name }}</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 font-mono">
                        {{ key.maskedKey }}
                      </p>
                      <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                        Created {{ key.createdAt }} · Last used {{ key.lastUsed || 'Never' }}
                      </p>
                    </div>
                    <button
                      type="button"
                      class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors shrink-0"
                      @click="revokeApiKey(key.id)"
                    >
                      <Icon name="ph:trash" class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>

              <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center">
                <Icon name="ph:key" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                <p class="text-sm text-neutral-500 dark:text-neutral-400">No API keys</p>
                <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Generate a key to access the API programmatically</p>
              </div>
            </section>
          </template>

          <!-- All Integrations View -->
          <template v-else-if="activeCategory === 'all'">
            <section v-for="category in visibleNativeCategories" :key="category.id" class="mb-8 last:mb-0">
              <h3 class="text-sm font-medium text-neutral-900 dark:text-white mb-3 flex items-center gap-2">
                <Icon :name="category.icon" class="w-4 h-4 text-neutral-500" />
                {{ category.name }}
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <IntegrationCard
                  v-for="integration in category.integrations"
                  :key="integration.id"
                  :integration="integration"
                  @install="handleInstall"
                  @uninstall="handleUninstall"
                  @configure="handleConfigure"
                />
              </div>
            </section>

            <!-- MCP Servers in All view -->
            <section v-if="mcpCategory" class="mb-8">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-neutral-900 dark:text-white flex items-center gap-2">
                  <Icon name="ph:plugs-connected" class="w-4 h-4 text-purple-500" />
                  MCP Servers
                </h3>
                <button
                  type="button"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors"
                  @click="openAddMcpServer"
                >
                  <Icon name="ph:plus" class="w-3.5 h-3.5" />
                  Add Server
                </button>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <IntegrationCard
                  v-for="integration in mcpCategory.integrations"
                  :key="integration.id"
                  :integration="integration"
                  @install="handleInstall"
                  @uninstall="handleUninstall"
                  @configure="handleConfigure"
                />
              </div>
            </section>
          </template>

          <!-- Single Category View -->
          <template v-else>
            <div v-if="selectedCategory" class="mb-4">
              <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium text-neutral-900 dark:text-white flex items-center gap-2">
                  <Icon :name="selectedCategory.icon" class="w-4 h-4 text-neutral-500" />
                  {{ selectedCategory.name }}
                </h2>
                <button
                  v-if="activeCategory === 'mcp-servers'"
                  type="button"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-purple-600 text-white hover:bg-purple-700 transition-colors"
                  @click="openAddMcpServer"
                >
                  <Icon name="ph:plus" class="w-3.5 h-3.5" />
                  Add MCP Server
                </button>
              </div>
            </div>
            <div v-if="selectedCategory && selectedCategory.integrations.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <IntegrationCard
                v-for="integration in selectedCategory.integrations"
                :key="integration.id"
                :integration="integration"
                @install="handleInstall"
                @uninstall="handleUninstall"
                @configure="handleConfigure"
              />
            </div>
            <div v-else-if="activeCategory === 'mcp-servers'" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-12 text-center">
              <Icon name="ph:plugs-connected" class="w-10 h-10 text-neutral-300 dark:text-neutral-600 mx-auto mb-3" />
              <p class="text-sm text-neutral-500 dark:text-neutral-400">No MCP servers connected</p>
              <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1 mb-4">
                Connect a remote MCP server to expose its tools to your agents
              </p>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium rounded-md bg-purple-600 text-white hover:bg-purple-700 transition-colors"
                @click="openAddMcpServer"
              >
                <Icon name="ph:plus" class="w-3.5 h-3.5" />
                Add MCP Server
              </button>
            </div>
          </template>

          <div
            v-if="showCatalogControls"
            class="mt-6 mb-2 flex flex-col items-center gap-2 text-center"
          >
            <p v-if="catalogAvailable === false" class="text-xs text-neutral-500 dark:text-neutral-400">
              Catalog file is not available in this environment.
            </p>
            <p v-else-if="catalogError" class="text-xs text-red-600 dark:text-red-400">
              {{ catalogError }}
            </p>
            <button
              v-if="catalogMeta.hasMore"
              type="button"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors"
              :disabled="catalogLoading"
              @click="loadMoreCatalog"
            >
              <Icon :name="catalogLoading ? 'ph:circle-notch' : 'ph:plus'" class="w-3.5 h-3.5" />
              {{ catalogLoading ? 'Loading catalog' : 'Load more catalog integrations' }}
            </button>
            <p v-else-if="catalogLoading" class="text-xs text-neutral-500 dark:text-neutral-400">
              Loading catalog...
            </p>
          </div>
        </main>
      </div>
    </div>

    <!-- AI Provider Config Modal -->
    <ProviderConfigModal
      v-model:open="showProviderConfigModal"
      :integration-id="activeProviderId"
      @saved="handleProviderSaved"
    />

    <!-- Codex Config Modal -->
    <CodexConfigModal
      v-model:open="showCodexConfigModal"
      @saved="handleCodexSaved"
    />

    <!-- Dynamic Config Modal (for package-provided integrations and chat platforms) -->
    <DynamicConfigModal
      v-model:open="showDynamicConfigModal"
      :integration-id="dynamicIntegrationId"
      :schema="dynamicConfigSchema"
      :meta="dynamicIntegrationMeta"
      @saved="handleDynamicSaved"
    />

    <!-- AI Gateway Config Modal -->
    <AiGatewayConfigModal
      v-model:open="showAiGatewayConfigModal"
      @saved="handleAiGatewaySaved"
    />

    <!-- MCP Server Config Modal -->
    <McpConfigModal
      v-model:open="showMcpConfigModal"
      :server-id="activeMcpServerId"
      @saved="handleMcpSaved"
      @deleted="handleMcpDeleted"
    />

    <!-- Webhook Modal -->
    <Modal v-model:open="showWebhookModal" :title="webhookEditingId ? 'Edit Webhook' : 'Add Webhook'">
      <template #body>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Name</label>
            <input
              v-model="webhookForm.name"
              type="text"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
              placeholder="e.g., GitHub PR Notifications"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Target</label>
            <select
              v-model="webhookForm.targetType"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            >
              <option value="agent">Send to Agent</option>
              <option value="channel">Send to Channel</option>
              <option value="task">Create Task</option>
            </select>
          </div>
          <div v-if="webhookForm.targetType === 'agent'">
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Agent</label>
            <select
              v-model="webhookForm.targetId"
              class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
            >
              <option value="">Select an agent...</option>
              <option value="agent-1">Logic (Coder)</option>
              <option value="agent-2">Scout (Researcher)</option>
            </select>
          </div>
        </div>
      </template>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
            @click="closeWebhookModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
            @click="saveWebhook"
          >
            {{ webhookEditingId ? 'Save Webhook' : 'Create Webhook' }}
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { apiKeys as aiGatewayApiKeys, deleteApiKey } from '@/actions/App/Http/Controllers/Api/AiGatewayController'
import { index as integrationCatalogIndex } from '@/actions/App/Http/Controllers/Api/IntegrationCatalogController'
import {
  index as integrationsIndex,
  rotateTelegramWebhookSecret,
  sendTelegramTestMessage,
  showConfig as showIntegrationConfig,
  setupWebhook as setupIntegrationWebhook,
  syncTelegramBotProfile as syncTelegramBotProfileAction,
  telegramHealthCheck,
  toggle as toggleIntegration,
} from '@/actions/App/Http/Controllers/Api/IntegrationController'
import {
  index as telegramOperationsIndex,
  repair as repairTelegramOperations,
  replayReceipt as replayTelegramReceiptAction,
  retryDelivery as retryTelegramDeliveryAction,
} from '@/actions/App/Http/Controllers/Api/TelegramOperationsController'
import {
  destroy as destroyIntegrationWebhook,
  index as integrationWebhooksIndex,
  store as storeIntegrationWebhook,
  update as updateIntegrationWebhook,
} from '@/actions/App/Http/Controllers/Api/IntegrationWebhookController'
import { destroy as destroyMcpServer, store as storeMcpServer } from '@/actions/App/Http/Controllers/Api/McpServerController'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import Modal from '@/Components/shared/Modal.vue'
import IntegrationCard from '@/Components/integrations/IntegrationCard.vue'
import ProviderConfigModal from '@/Components/integrations/ProviderConfigModal.vue'
import CodexConfigModal from '@/Components/integrations/CodexConfigModal.vue'
import DynamicConfigModal from '@/Components/integrations/DynamicConfigModal.vue'
import McpConfigModal from '@/Components/integrations/McpConfigModal.vue'
import AiGatewayConfigModal from '@/Components/integrations/AiGatewayConfigModal.vue'
import { wayfinderRequest } from '@/utils/wayfinder'
import type { Integration } from '@/Components/integrations/IntegrationCard.vue'

const { developerLuaConsoleUrl, developerToolsUrl } = useWorkspace()

const initialQuery = new URLSearchParams(window.location.search)

// Sidebar state. Start on installed services because catalog browsing is huge;
// direct query params still allow deep links to a search or category.
const activeCategory = ref<string>(initialQuery.get('category') || (initialQuery.get('q') ? 'all' : 'installed'))
const searchQuery = ref(initialQuery.get('q') || '')
const catalogLoading = ref(false)
const catalogError = ref<string | null>(null)
const catalogAvailable = ref<boolean | null>(null)
const integrationStatusError = ref<string | null>(null)
const apiKeysError = ref<string | null>(null)
const webhooksError = ref<string | null>(null)
const telegramError = ref<string | null>(null)
const telegramNotice = ref<string | null>(null)
const telegramAction = ref<'health' | 'sync' | 'test' | 'webhook' | 'rotate' | 'repair' | 'retry' | 'replay' | null>(null)
const telegramOperations = ref<TelegramOperations | null>(null)
const telegramProfile = ref<Record<string, any> | null>(null)
const catalogMeta = reactive({
  page: 0,
  perPage: 50,
  total: 0,
  hasMore: false,
  search: '',
  category: '',
})

// Interfaces
interface Webhook {
  id: string
  name: string
  enabled: boolean
  targetType: 'agent' | 'channel' | 'task'
  targetId: string | null
  endpoint?: string
  url?: string
  secret?: string | null
  lastTriggered?: string
  callCount?: number
}

interface ApiKey {
  id: string
  name: string
  maskedKey: string
  createdAt: string
  lastUsed?: string
}

interface Service {
  id: string
  name: string
  icon: string
  description: string
  connected: boolean
}

interface IntegrationCategory {
  id: string
  name: string
  icon: string
  integrations: Integration[]
}

interface TelegramOperations {
  metrics?: Record<string, any>
  logs?: {
    receipts?: Record<string, any>[]
    deliveries?: Record<string, any>[]
  }
  repairCandidates?: Record<string, any>
}

// Webhook state
const showWebhookModal = ref(false)
const webhookEditingId = ref<string | null>(null)
const webhookForm = reactive({
  name: '',
  targetType: 'agent' as 'agent' | 'channel' | 'task',
  targetId: '',
})

// AI Provider Config modal
const showProviderConfigModal = ref(false)
const activeProviderId = ref('glm-coding')

// Codex Config modal
const showCodexConfigModal = ref(false)

// Dynamic Config modal (for package-provided integrations and chat platforms)
const showDynamicConfigModal = ref(false)
const dynamicIntegrationId = ref('')
const dynamicIntegrationCardId = ref('')
const dynamicConfigSchema = ref<any[]>([])
const dynamicIntegrationMeta = ref<any>({ name: '', description: '', icon: 'ph:gear' })

// AI Gateway Config modal
const showAiGatewayConfigModal = ref(false)

// MCP Config modal
const showMcpConfigModal = ref(false)
const activeMcpServerId = ref<string | undefined>(undefined)

// Type filter
// Track which integrations are configurable (loaded from API)
const configurableIntegrations = ref<Record<string, any>>({})

// Load integration status from backend
onMounted(async () => {
  await Promise.all([loadCatalogThenStatus(), loadApiKeys(), loadWebhooks()])
})

const loadCatalogThenStatus = async () => {
  await loadIntegrationStatus()
  if (telegramIntegrationInstalled.value) {
    await loadTelegramOperations()
  }
  await loadIntegrationCatalog({
    reset: true,
    search: searchQuery.value.trim(),
    category: catalogCategoryFor(activeCategory.value, searchQuery.value),
  })
}

const categoryLabels: Record<string, { name: string; icon: string }> = {
  analytics: { name: 'Analytics', icon: 'ph:chart-line-up' },
  data: { name: 'Data & APIs', icon: 'ph:database' },
  productivity: { name: 'Productivity', icon: 'ph:briefcase' },
  rendering: { name: 'Rendering', icon: 'ph:paint-brush' },
  'web-providers': { name: 'Web Providers', icon: 'ph:globe' },
}

const categoryFor = (id: string, icon = 'ph:puzzle-piece'): IntegrationCategory => {
  let category = integrationCategories.value.find(c => c.id === id)
  if (!category) {
    const label = categoryLabels[id] || {
      name: id.replace(/-/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase()),
      icon,
    }
    category = { id, name: label.name, icon: label.icon, integrations: [] }
    integrationCategories.value.push(category)
  }
  return category
}

const mcpIdentityFor = (integration: Integration): string | null => {
  if (integration.type !== 'mcp') return null

  const url = integration.url || integration.suggestedMcpConfig?.url
  if (integration.mcpServerId) return `mcp:id:${integration.mcpServerId}`
  if (url) return `mcp:url:${url}`
  return null
}

const integrationCardKey = (integration: Integration): string => {
  return integration.cardKey || mcpIdentityFor(integration) || `${integration.entryType || 'integration'}:${integration.configId || integration.id}`
}

const integrationApiId = (integration: Integration | string): string => {
  if (typeof integration === 'string') return integration.includes(':') ? integration.split(':').slice(1).join(':') : integration
  return integration.configId || (integration.id.includes(':') ? integration.id.split(':').slice(1).join(':') : integration.id)
}

const dedupeIntegrations = <T extends Integration>(integrations: T[]): T[] => {
  const seen = new Set<string>()
  const deduped: T[] = []

  for (const integration of integrations) {
    const identity = integrationCardKey(integration)
    if (seen.has(identity)) continue
    seen.add(identity)
    deduped.push(integration)
  }

  return deduped
}

const mergeIntegrationCard = (incoming: Integration) => {
  const category = categoryFor(incoming.category || 'data', incoming.icon)
  const incomingMcpIdentity = mcpIdentityFor(incoming)
  const incomingKey = integrationCardKey(incoming)
  const existing = integrationCategories.value
    .flatMap(c => c.integrations)
    .find(i => integrationCardKey(i) === incomingKey || (incomingMcpIdentity && mcpIdentityFor(i) === incomingMcpIdentity))

  if (existing) {
    Object.assign(existing, {
      ...incoming,
      installed: incoming.installed ?? existing.installed,
      enabled: incoming.enabled ?? existing.enabled,
      configured: incoming.configured ?? existing.configured,
      runnable: incoming.runnable ?? existing.runnable,
      packageInstalled: incoming.packageInstalled ?? existing.packageInstalled,
      configurable: incoming.configurable ?? existing.configurable,
      badge: incoming.badge || existing.badge,
      catalog: existing.catalog || incoming.catalog,
      url: incoming.url || existing.url,
      mcpServerId: incoming.mcpServerId || existing.mcpServerId,
      toolCount: incoming.toolCount ?? existing.toolCount,
    })
    return existing
  }

  category.integrations.push(incoming)
  return incoming
}

const loadIntegrationCatalog = async (options: { reset?: boolean; search?: string; category?: string } = {}) => {
  if (catalogLoading.value) return

  const search = options.search ?? catalogMeta.search
  const category = options.category ?? catalogMeta.category
  const page = options.reset ? 1 : catalogMeta.page + 1

  catalogLoading.value = true
  catalogError.value = null

  try {
    const response = await wayfinderRequest<any>(integrationCatalogIndex({
      query: {
        page,
        perPage: catalogMeta.perPage,
        search: search || undefined,
        category: category || undefined,
      },
    }))

    catalogAvailable.value = response.data.available ?? null
    catalogMeta.page = response.data.meta?.page || page
    catalogMeta.perPage = response.data.meta?.perPage || catalogMeta.perPage
    catalogMeta.total = response.data.meta?.total || 0
    catalogMeta.hasMore = Boolean(response.data.meta?.hasMore)
    catalogMeta.search = search
    catalogMeta.category = category

    for (const item of response.data.data || []) {
      mergeIntegrationCard({
        id: `integration:${item.slug || item.id}`,
        configId: item.slug || item.id,
        cardKey: `integration:${item.slug || item.id}`,
        entryType: 'integration',
        source: 'catalog',
        name: item.name,
        icon: item.icon || 'ph:puzzle-piece',
        description: item.description,
        category: item.category || 'data',
        installed: item.enabled || false,
        enabled: item.enabled || false,
        configured: item.configured || false,
        runnable: item.runnable || false,
        configurable: item.configurable || false,
        badge: item.badge || undefined,
        catalog: true,
        packageInstalled: item.packageInstalled,
        toolCount: item.toolCount,
        docsUrl: item.docsUrl || null,
      })
    }
  } catch (error) {
    console.error('Failed to load integration catalog:', error)
    catalogError.value = 'Catalog unavailable'
  } finally {
    catalogLoading.value = false
  }
}

const loadMoreCatalog = () => {
  loadIntegrationCatalog({
    search: searchQuery.value.trim(),
    category: catalogCategoryFor(activeCategory.value, searchQuery.value),
  })
}

const loadApiKeys = async () => {
  apiKeysError.value = null

  try {
    const { data } = await wayfinderRequest<any[]>(aiGatewayApiKeys())
    apiKeys.value = data.map((key: any) => ({
      id: key.id,
      name: key.name,
      maskedKey: key.masked_key,
      createdAt: key.created_at ? formatRelativeDate(key.created_at) : 'Unknown',
      lastUsed: key.last_used_at ? formatRelativeDate(key.last_used_at) : undefined,
    }))
  } catch (error) {
    console.error('Failed to load API keys:', error)
    apiKeysError.value = 'Could not load API keys. Refresh the page or try again later.'
  }
}

const formatRelativeDate = (dateStr: string): string => {
  try {
    const date = new Date(dateStr)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    if (diffMins < 1) return 'just now'
    if (diffMins < 60) return `${diffMins}m ago`
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours}h ago`
    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 30) return `${diffDays}d ago`
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return dateStr
  }
}

const loadIntegrationStatus = async () => {
  integrationStatusError.value = null

  try {
    const response = await wayfinderRequest<any[]>(integrationsIndex())
    if (response.status === 200) {
      const integrations = response.data
      for (const integration of integrations) {
        // Store configurable integration data for dynamic modals
        if (integration.configurable) {
          configurableIntegrations.value[integration.id] = integration
        }

        if (integration.type === 'mcp') {
          const mcpCat = integrationCategories.value.find(c => c.id === 'mcp-servers')
          const suggested = mcpCat?.integrations.find(
            i => i.suggestedMcpConfig && i.suggestedMcpConfig.url === integration.url
          )

          if (suggested) {
            Object.assign(suggested, {
              ...integration,
              id: suggested.id,
              installed: integration.enabled,
              enabled: integration.enabled,
              configured: integration.configured,
              runnable: true,
              badge: suggested.badge || integration.badge || 'mcp',
              configurable: integration.configurable ?? false,
              packageInstalled: true,
              type: 'mcp' as const,
              url: integration.url,
              mcpServerId: integration.mcpServerId,
              toolCount: integration.toolCount,
            })
            continue
          }
        }

        mergeIntegrationCard({
          id: integration.id,
          configId: integration.configId || integration.id,
          cardKey: integration.cardKey,
          entryType: integration.entryType,
          source: integration.source,
          name: integration.name,
          icon: integration.icon || 'ph:puzzle-piece',
          description: integration.description,
          category: integration.type === 'mcp' ? 'mcp-servers' : (integration.category || 'other'),
          installed: integration.enabled,
          enabled: integration.enabled,
          configured: integration.configured,
          runnable: true,
          badge: integration.badge || undefined,
          configurable: integration.configurable ?? false,
          packageInstalled: true,
          type: integration.type || 'native',
          url: integration.url,
          mcpServerId: integration.mcpServerId,
          toolCount: integration.toolCount,
        })
      }
    }
  } catch (error) {
    console.error('Failed to load integration status:', error)
    integrationStatusError.value = 'Could not load connected services. Refresh the page or try again later.'
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null

const catalogCategoryFor = (category: string, search: string) => {
  if (search.trim()) return ''
  return category !== 'all' && category !== 'installed' && category !== 'mcp-servers'
    ? category
    : ''
}

const replaceIntegrationUrl = () => {
  const params = new URLSearchParams()
  if (activeCategory.value !== 'installed') {
    params.set('category', activeCategory.value)
  }
  if (searchQuery.value.trim()) {
    params.set('q', searchQuery.value.trim())
  }

  const next = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`
  window.history.replaceState(window.history.state, '', next)
}

const setCategory = (category: string) => {
  activeCategory.value = category
  searchQuery.value = ''
}

watch(searchQuery, (query) => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    replaceIntegrationUrl()
    loadIntegrationCatalog({ reset: true, search: query.trim(), category: '' })
  }, 250)
})

watch(activeCategory, (category) => {
  replaceIntegrationUrl()

  if (category === 'all' || category === 'installed' || category === 'mcp-servers') {
    return
  }

  loadIntegrationCatalog({ reset: true, search: '', category })
})

const webhooks = ref<Webhook[]>([])

// API Keys (loaded from backend)
const apiKeys = ref<ApiKey[]>([])

// Integration categories
const integrationCategories = ref<IntegrationCategory[]>([
  {
    id: 'ai-models',
    name: 'AI Models',
    icon: 'ph:brain',
    integrations: [],  // populated from backend
  },
  {
    id: 'analytics',
    name: 'Analytics',
    icon: 'ph:chart-line-up',
    integrations: [],
  },
  {
    id: 'chat-platforms',
    name: 'Chat Platforms',
    icon: 'ph:chat-dots',
    integrations: [],
  },
  {
    id: 'developer',
    name: 'Developer Tools',
    icon: 'ph:code',
    integrations: [],
  },
  {
    id: 'productivity',
    name: 'Productivity',
    icon: 'ph:briefcase',
    integrations: [],
  },
  {
    id: 'data',
    name: 'Data & APIs',
    icon: 'ph:database',
    integrations: [],
  },
  {
    id: 'web-providers',
    name: 'Web Providers',
    icon: 'ph:globe',
    integrations: [],
  },
  {
    id: 'built-in-tools',
    name: 'Built-in Tools',
    icon: 'ph:wrench',
    integrations: [],  // populated from backend
  },
  {
    id: 'mcp-servers',
    name: 'MCP Servers',
    icon: 'ph:plugs-connected',
    integrations: [
      {
        id: 'mcp-suggested-deepwiki',
        name: 'DeepWiki',
        icon: 'ph:book-open',
        description: 'GitHub repo documentation & code understanding',
        installed: false,
        badge: 'mcp' as const,
        type: 'mcp' as const,
        suggestedMcpConfig: {
          url: 'https://mcp.deepwiki.com/mcp',
          auth_type: 'none' as const,
          icon: 'ph:book-open',
          description: 'GitHub repo documentation & code understanding',
        },
      },
      {
        id: 'mcp-suggested-context7',
        name: 'Context7',
        icon: 'ph:books',
        description: 'Library & framework documentation search',
        installed: false,
        badge: 'mcp' as const,
        type: 'mcp' as const,
        suggestedMcpConfig: {
          url: 'https://context7.liam.sh/mcp',
          auth_type: 'none' as const,
          icon: 'ph:books',
          description: 'Library & framework documentation search',
        },
      },
      {
        id: 'mcp-suggested-cloudflare',
        name: 'Cloudflare Docs',
        icon: 'ph:cloud',
        description: 'Cloudflare technical documentation',
        installed: false,
        badge: 'mcp' as const,
        type: 'mcp' as const,
        suggestedMcpConfig: {
          url: 'https://docs.mcp.cloudflare.com/mcp',
          auth_type: 'none' as const,
          icon: 'ph:cloud',
          description: 'Cloudflare technical documentation',
        },
      },
      {
        id: 'mcp-suggested-exa',
        name: 'Exa Search',
        icon: 'ph:magnifying-glass',
        description: 'Web search, company research & code discovery',
        installed: false,
        badge: 'mcp' as const,
        type: 'mcp' as const,
        suggestedMcpConfig: {
          url: 'https://mcp.exa.ai/mcp',
          auth_type: 'none' as const,
          icon: 'ph:magnifying-glass',
          description: 'Web search, company research & code discovery',
        },
      },
    ],
  },
])

// Computed - Native categories (exclude MCP)
const nativeCategories = computed(() => {
  return integrationCategories.value.filter(c => c.id !== 'mcp-servers')
})

const visibleNativeCategories = computed(() => {
  return nativeCategories.value.filter(category => category.integrations.length > 0)
})

// Computed - MCP category
const mcpCategory = computed(() => {
  const category = integrationCategories.value.find(c => c.id === 'mcp-servers')
  if (!category) return undefined

  return {
    ...category,
    integrations: dedupeIntegrations(category.integrations),
  }
})

// Computed - Selected category
const selectedCategory = computed(() => {
  if (activeCategory.value === 'mcp-servers') {
    return mcpCategory.value
  }

  return integrationCategories.value.find(c => c.id === activeCategory.value)
})

// Computed - Total integration count
const totalIntegrationCount = computed(() => {
  return integrationCategories.value.reduce((sum, cat) => sum + cat.integrations.length, 0)
})

// Computed - Connected services (installed integrations)
const connectedServices = computed<Service[]>(() => {
  const installed: Integration[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (integration.installed) {
        installed.push(integration)
      }
    }
  }
  return dedupeIntegrations(installed).map(integration => ({
    id: integration.id,
    name: integration.name,
    icon: integration.icon,
    description: integration.description,
    connected: true,
  }))
})

// Computed - Installed integrations as Integration objects (for cards)
const installedIntegrations = computed(() => {
  const installed: Integration[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (integration.installed) {
        installed.push(integration)
      }
    }
  }
  return dedupeIntegrations(installed)
})

// Computed - Installed count
const installedCount = computed(() => {
  return connectedServices.value.length
})

const telegramIntegrationInstalled = computed(() => {
  return installedIntegrations.value.some(integration => integrationApiId(integration) === 'telegram')
})

const telegramMetrics = computed(() => telegramOperations.value?.metrics || {})

const telegramDeliveryLogs = computed(() => telegramOperations.value?.logs?.deliveries || [])

const telegramReceiptLogs = computed(() => telegramOperations.value?.logs?.receipts || [])

const telegramRepairCount = computed(() => {
  const candidates = telegramOperations.value?.repairCandidates || {}
  let count = 0
  for (const value of Object.values(candidates)) {
    if (Array.isArray(value)) {
      count += value.length
    } else if (value) {
      count += 1
    }
  }
  return count
})

const telegramStats = computed(() => [
  { label: 'Health', value: telegramProfile.value?.health_status || (telegramMetrics.value.stale_health_profile ? 'stale' : 'unknown') },
  { label: 'Failed receipts', value: telegramMetrics.value.failed_receipts_24h ?? 0 },
  { label: 'Failed deliveries', value: telegramMetrics.value.failed_deliveries_24h ?? 0 },
  { label: 'Repair candidates', value: telegramRepairCount.value },
])

// Computed - Search results
const searchResults = computed(() => {
  if (!searchQuery.value) return []
  const query = searchQuery.value.toLowerCase()
  const results: Integration[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (
        integration.name.toLowerCase().includes(query) ||
        integration.description.toLowerCase().includes(query)
      ) {
        results.push(integration)
      }
    }
  }
  return dedupeIntegrations(results)
})

const showCatalogControls = computed(() => {
  if (activeCategory.value === 'installed' || activeCategory.value === 'mcp-servers') {
    return false
  }

  return catalogLoading.value
    || Boolean(catalogError.value)
    || catalogAvailable.value === false
    || catalogMeta.hasMore
})

// Webhook handlers
const resetWebhookForm = () => {
  webhookEditingId.value = null
  webhookForm.name = ''
  webhookForm.targetType = 'agent'
  webhookForm.targetId = ''
}

const openNewWebhook = () => {
  resetWebhookForm()
  showWebhookModal.value = true
}

const closeWebhookModal = () => {
  showWebhookModal.value = false
  resetWebhookForm()
}

const loadWebhooks = async () => {
  webhooksError.value = null

  try {
    const { data } = await wayfinderRequest<{ data?: Webhook[] }>(integrationWebhooksIndex())
    webhooks.value = data.data || []
  } catch (error) {
    console.error('Failed to load webhooks:', error)
    webhooks.value = []
    webhooksError.value = 'Could not load webhooks. Refresh the page or try again later.'
  }
}

const editWebhook = (webhook: Webhook) => {
  webhookEditingId.value = webhook.id
  webhookForm.name = webhook.name
  webhookForm.targetType = webhook.targetType
  webhookForm.targetId = webhook.targetId || ''
  showWebhookModal.value = true
}

const deleteWebhook = async (id: string) => {
  try {
    await wayfinderRequest(destroyIntegrationWebhook(id))
    webhooks.value = webhooks.value.filter(w => w.id !== id)
  } catch (error) {
    console.error('Failed to delete webhook:', error)
  }
}

const saveWebhook = async () => {
  try {
    const payload = {
      name: webhookForm.name,
      enabled: true,
      targetType: webhookForm.targetType,
      targetId: webhookForm.targetId || null,
    }
    if (webhookEditingId.value) {
      const { data } = await wayfinderRequest<{ webhook: Webhook }>(updateIntegrationWebhook(webhookEditingId.value), { data: payload })
      const index = webhooks.value.findIndex(w => w.id === webhookEditingId.value)
      if (index >= 0) webhooks.value[index] = data.webhook
    } else {
      const { data } = await wayfinderRequest<{ webhook: Webhook }>(storeIntegrationWebhook(), { data: payload })
      webhooks.value.push(data.webhook)
    }

    closeWebhookModal()
  } catch (error) {
    console.error('Failed to save webhook:', error)
  }
}

const resetTelegramMessages = () => {
  telegramError.value = null
  telegramNotice.value = null
}

const loadTelegramOperations = async () => {
  if (!telegramIntegrationInstalled.value) return

  try {
    const { data } = await wayfinderRequest<TelegramOperations & { success?: boolean }>(telegramOperationsIndex({
      query: { limit: 5 },
    }))
    telegramOperations.value = data
  } catch (error) {
    console.error('Failed to load Telegram operations:', error)
    telegramError.value = 'Could not load Telegram operations.'
  }
}

const runTelegramAction = async (
  action: typeof telegramAction.value,
  handler: () => Promise<string>,
) => {
  if (!action || telegramAction.value) return

  telegramAction.value = action
  resetTelegramMessages()
  try {
    telegramNotice.value = await handler()
    await loadTelegramOperations()
  } catch (error: any) {
    console.error(`Telegram ${action} action failed:`, error)
    telegramError.value = error.response?.data?.error || error.message || 'Telegram action failed.'
  } finally {
    telegramAction.value = null
  }
}

const runTelegramHealthCheck = () => runTelegramAction('health', async () => {
  const { data } = await wayfinderRequest<any>(telegramHealthCheck())
  telegramProfile.value = data.profile || null

  return `Telegram health is ${data.status || data.profile?.health_status || 'unknown'}.`
})

const syncTelegramBotProfile = () => runTelegramAction('sync', async () => {
  const { data } = await wayfinderRequest<any>(syncTelegramBotProfileAction())
  telegramProfile.value = data.profile || null
  const commandStatus = data.commands?.status || data.profile?.command_sync_status || 'synced'
  const profileStatus = data.profile_sync?.status || data.profile?.profile_sync_status || 'synced'

  return `Telegram commands ${commandStatus}; profile ${profileStatus}.`
})

const sendTelegramTest = () => runTelegramAction('test', async () => {
  const { data } = await wayfinderRequest<any>(sendTelegramTestMessage())

  return data.target ? `Telegram test sent to ${data.target}.` : 'Telegram test sent.'
})

const setupTelegramWebhook = () => runTelegramAction('webhook', async () => {
  if (!window.confirm('Reset the live Telegram webhook to this OpenCompany URL?')) {
    return 'Telegram webhook reset cancelled.'
  }

  const { data } = await wayfinderRequest<any>(setupIntegrationWebhook('telegram'))
  telegramProfile.value = data.profile || null

  return data.webhookUrl ? `Telegram webhook reset to ${data.webhookUrl}.` : 'Telegram webhook reset.'
})

const rotateTelegramSecret = () => runTelegramAction('rotate', async () => {
  if (!window.confirm('Rotate the live Telegram webhook secret and re-register the webhook now?')) {
    return 'Telegram webhook secret rotation cancelled.'
  }

  const { data } = await wayfinderRequest<any>(rotateTelegramWebhookSecret())
  telegramProfile.value = data.profile || null

  return 'Telegram webhook secret rotated and registered.'
})

const repairTelegramLocalState = () => runTelegramAction('repair', async () => {
  const { data } = await wayfinderRequest<any>(repairTelegramOperations(), {
    data: {
      actions: ['expire_interactions', 'repair_conversations'],
    },
  })
  telegramOperations.value = {
    metrics: data.metrics,
    repairCandidates: data.repairCandidates,
    logs: telegramOperations.value?.logs,
  }

  return 'Telegram local state repair completed.'
})

const retryTelegramDelivery = (deliveryId: string) => runTelegramAction('retry', async () => {
  await wayfinderRequest<any>(retryTelegramDeliveryAction(deliveryId))

  return 'Telegram delivery retried.'
})

const replayTelegramReceipt = (receiptId: string) => runTelegramAction('replay', async () => {
  await wayfinderRequest<any>(replayTelegramReceiptAction(receiptId))

  return 'Telegram webhook receipt replayed.'
})

// API Key handlers
const generateApiKey = () => {
  // Open AI Gateway modal for key management
  showAiGatewayConfigModal.value = true
}

const revokeApiKey = async (id: string) => {
  try {
    await wayfinderRequest(deleteApiKey(id))
    apiKeys.value = apiKeys.value.filter(k => k.id !== id)
  } catch (error) {
    console.error('Failed to revoke API key:', error)
  }
}

// Open the dynamic config modal for a configurable integration.
// Fetch by id so catalog cards do not depend on the large /api/integrations cache.
const openDynamicModal = async (integrationOrId: Integration | string) => {
  const integrationId = integrationApiId(integrationOrId)
  const cardId = typeof integrationOrId === 'string' ? integrationOrId : integrationOrId.id
  const fallback = typeof integrationOrId === 'string'
    ? configurableIntegrations.value[integrationOrId] || configurableIntegrations.value[`integration:${integrationId}`] || configurableIntegrations.value[`chat:${integrationId}`]
    : integrationOrId

  try {
    const { data } = await wayfinderRequest<any>(showIntegrationConfig(integrationId))
    const schema = data.configSchema || fallback?.configSchema || []
    const merged = {
      ...fallback,
      ...data,
      id: cardId,
      configId: integrationId,
      configSchema: schema,
      docsUrl: data.docsUrl || fallback?.docsUrl,
      icon: data.icon || fallback?.icon || 'ph:gear',
    }

    configurableIntegrations.value[cardId] = merged
    dynamicIntegrationId.value = integrationId
    dynamicIntegrationCardId.value = cardId
    dynamicConfigSchema.value = schema
    dynamicIntegrationMeta.value = {
      name: merged.name || fallback?.name || integrationId,
      description: merged.description || fallback?.description || '',
      icon: merged.icon,
      logo: merged.logo,
      docs_url: merged.docsUrl,
    }
    showDynamicConfigModal.value = true
  } catch (error) {
    console.error(`Failed to load config for ${integrationId}:`, error)
    alert(`Failed to load ${fallback?.name || integrationId} configuration.`)
  }
}

// Quick install for suggested MCP servers (one-click)
const installingMcpId = ref<string | null>(null)

const handleQuickInstallMcp = async (integration: Integration) => {
  if (!integration.suggestedMcpConfig || installingMcpId.value) return

  installingMcpId.value = integration.id
  try {
    const { data } = await wayfinderRequest<any>(storeMcpServer(), {
      data: {
        name: integration.name,
        url: integration.suggestedMcpConfig.url,
        auth_type: integration.suggestedMcpConfig.auth_type,
        icon: integration.suggestedMcpConfig.icon,
        description: integration.suggestedMcpConfig.description,
      },
    })

    // Update the suggested entry to show as installed
    integration.installed = true
    integration.mcpServerId = data.server?.id || data.id
    integration.toolCount = data.server?.discovered_tools?.length || data.toolCount || 0
    if (data.warning) {
      console.warn(`MCP install warning for ${integration.name}:`, data.warning)
    }
  } catch (error: any) {
    console.error(`Failed to install MCP server ${integration.name}:`, error)
    const message = error.response?.data?.message || error.message || 'Unknown error'
    alert(`Failed to install ${integration.name}: ${message}`)
  } finally {
    installingMcpId.value = null
  }
}

// Integration handlers
const handleInstall = async (integration: Integration) => {
  if (integration.catalog && integration.packageInstalled === false) {
    if (integration.docsUrl) {
      window.open(integration.docsUrl, '_blank', 'noopener,noreferrer')
      return
    }

    alert(`${integration.name} is present in the integrations catalog, but its runtime package is not installed in this OpenCompany app yet.`)
    return
  }

  // Suggested MCP server — one-click install
  if (integration.suggestedMcpConfig && !integration.mcpServerId) {
    handleQuickInstallMcp(integration)
    return
  }

  // MCP integrations go to MCP modal
  if (integration.type === 'mcp' && integration.mcpServerId) {
    activeMcpServerId.value = integration.mcpServerId
    showMcpConfigModal.value = true
    return
  }

  if (integration.configId === 'codex' || integration.id === 'codex' || integration.id === 'ai_provider:codex') {
    showCodexConfigModal.value = true
    return
  }

  // AI providers with API key config (category driven from backend)
  if (integration.category === 'ai-models') {
    activeProviderId.value = integrationApiId(integration)
    showProviderConfigModal.value = true
    return
  }

  // Check if this is a configurable integration (chat platforms, package-provided, etc.)
  if (integration.configurable || configurableIntegrations.value[integration.id]) {
    await openDynamicModal(integration)
    return
  }

  // Non-configurable integration — toggle via API
  try {
    await wayfinderRequest(toggleIntegration(integrationApiId(integration)), { data: { enabled: true } })
    for (const category of integrationCategories.value) {
      const found = category.integrations.find(i => i.id === integration.id)
      if (found) {
        found.installed = true
        break
      }
    }
  } catch (error) {
    console.error(`Failed to install ${integration.id}:`, error)
  }
}

const handleProviderSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => integrationApiId(i) === activeProviderId.value && i.category === 'ai-models')
    if (found) {
      found.installed = result.enabled
      found.enabled = result.enabled
      found.configured = result.configured
      break
    }
  }
}

const handleCodexSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => integrationApiId(i) === 'codex')
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleConfigure = async (integration: Integration) => {
  if (integration.type === 'mcp' && integration.mcpServerId) {
    activeMcpServerId.value = integration.mcpServerId
    showMcpConfigModal.value = true
  } else if (integration.configId === 'codex' || integration.id === 'codex' || integration.id === 'ai_provider:codex') {
    showCodexConfigModal.value = true
  } else if (integration.category === 'ai-models') {
    activeProviderId.value = integrationApiId(integration)
    showProviderConfigModal.value = true
  } else if (integration.configurable || configurableIntegrations.value[integration.id]) {
    await openDynamicModal(integration)
  }
}

const handleDynamicSaved = (result: { enabled: boolean; configured: boolean }) => {
  const id = dynamicIntegrationCardId.value
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === id)
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleUninstall = async (integration: Integration) => {
  if (integration.type === 'mcp' && integration.mcpServerId) {
    // For MCP, delete via API
    wayfinderRequest(destroyMcpServer(integration.mcpServerId))
      .then(() => {
        // If it's a suggested entry, reset it back to uninstalled
        if (integration.suggestedMcpConfig) {
          integration.installed = false
          integration.mcpServerId = undefined
          integration.toolCount = undefined
        } else {
          // Dynamic MCP server — remove from list entirely
          loadIntegrationStatus()
        }
      })
      .catch(console.error)
    return
  }

  // Disable via toggle API, then update local state
  try {
    await wayfinderRequest(toggleIntegration(integrationApiId(integration)), { data: { enabled: false } })
  } catch (error) {
    console.error(`Failed to uninstall ${integration.id}:`, error)
  }

  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === integration.id)
    if (found) {
      found.installed = false
      break
    }
  }
}

const openAddMcpServer = () => {
  activeMcpServerId.value = undefined
  showMcpConfigModal.value = true
}

const handleMcpSaved = () => {
  loadIntegrationStatus()
}

const handleAiGatewaySaved = () => {
  loadApiKeys()
}

const handleMcpDeleted = () => {
  loadIntegrationStatus()
}
</script>
