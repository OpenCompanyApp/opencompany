<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-6xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Integrations</h1>
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
              @click="activeCategory = 'all'; searchQuery = ''"
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
              @click="activeCategory = 'installed'; searchQuery = ''"
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
              v-for="category in nativeCategories"
              :key="'mobile-' + category.id"
              type="button"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
                activeCategory === category.id && !searchQuery
                  ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                  : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
              ]"
              @click="activeCategory = category.id; searchQuery = ''"
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
              @click="activeCategory = 'mcp-servers'; searchQuery = ''"
            >
              <Icon name="ph:plugs-connected" class="w-3.5 h-3.5" />
              MCP
            </button>
            <button
              type="button"
              class="prism-btn flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0"
              @click="showPrismServerConfigModal = true"
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
            @click="activeCategory = 'all'; searchQuery = ''"
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
            @click="activeCategory = 'installed'; searchQuery = ''"
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
            v-for="category in nativeCategories"
            :key="category.id"
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeCategory === category.id && !searchQuery
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeCategory = category.id; searchQuery = ''"
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
            @click="activeCategory = 'mcp-servers'; searchQuery = ''"
          >
            <Icon name="ph:plugs-connected" class="w-4 h-4" />
            MCP Servers
            <span v-if="mcpCategory" class="ml-auto text-[10px] opacity-60">{{ mcpCategory.integrations.length }}</span>
          </button>

          <!-- Prism Server -->
          <button
            type="button"
            class="prism-btn flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full"
            @click="showPrismServerConfigModal = true"
          >
            <Icon name="ph:diamond" class="w-4 h-4" />
            Prism Server
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

              <div v-if="connectedServices.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                <IntegrationCard
                  v-for="service in installedIntegrations"
                  :key="service.id"
                  :integration="service"
                  @install="handleInstall"
                  @uninstall="handleUninstall"
                  @configure="handleConfigure"
                />
              </div>

              <div v-else class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 px-4 py-8 text-center mb-6">
                <Icon name="ph:plugs-connected" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                <p class="text-sm text-neutral-500 dark:text-neutral-400">No connected services</p>
                <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                  Browse the
                  <button type="button" class="text-neutral-900 dark:text-white underline" @click="activeCategory = 'all'">
                    library
                  </button>
                  to connect integrations
                </p>
              </div>
            </section>

            <!-- Webhooks Section -->
            <section class="mb-8">
              <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-medium text-neutral-900 dark:text-white">Webhooks</h2>
                <button
                  type="button"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100 transition-colors duration-150"
                  @click="showWebhookModal = true"
                >
                  <Icon name="ph:plus" class="w-3.5 h-3.5" />
                  Add webhook
                </button>
              </div>

              <div v-if="webhooks.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
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
                        POST /api/webhooks/{{ webhook.id }}
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

              <div v-if="apiKeys.length > 0" class="rounded-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-100 dark:divide-neutral-800">
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
            <section v-for="category in nativeCategories" :key="category.id" class="mb-8 last:mb-0">
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
        </main>
      </div>
    </div>

    <!-- GLM Config Modal -->
    <GlmConfigModal
      v-model:open="showGlmConfigModal"
      :integration-id="activeGlmIntegrationId"
      @saved="handleGlmSaved"
    />

    <!-- Codex Config Modal -->
    <CodexConfigModal
      v-model:open="showCodexConfigModal"
      @saved="handleCodexSaved"
    />

    <!-- Telegram Config Modal -->
    <TelegramConfigModal
      v-model:open="showTelegramConfigModal"
      @saved="handleTelegramSaved"
    />

    <!-- Dynamic Config Modal (for package-provided integrations) -->
    <DynamicConfigModal
      v-model:open="showDynamicConfigModal"
      :integration-id="dynamicIntegrationId"
      :schema="dynamicConfigSchema"
      :meta="dynamicIntegrationMeta"
      @saved="handleDynamicSaved"
    />

    <!-- Prism Server Config Modal -->
    <PrismServerConfigModal
      v-model:open="showPrismServerConfigModal"
      @saved="handlePrismServerSaved"
    />

    <!-- MCP Server Config Modal -->
    <McpConfigModal
      v-model:open="showMcpConfigModal"
      :server-id="activeMcpServerId"
      @saved="handleMcpSaved"
      @deleted="handleMcpDeleted"
    />

    <!-- Webhook Modal -->
    <Modal v-model:open="showWebhookModal" title="Add Webhook">
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
            @click="showWebhookModal = false"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
            @click="saveWebhook"
          >
            Create Webhook
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import IntegrationCard from '@/Components/integrations/IntegrationCard.vue'
import GlmConfigModal from '@/Components/integrations/GlmConfigModal.vue'
import CodexConfigModal from '@/Components/integrations/CodexConfigModal.vue'
import TelegramConfigModal from '@/Components/integrations/TelegramConfigModal.vue'
import DynamicConfigModal from '@/Components/integrations/DynamicConfigModal.vue'
import McpConfigModal from '@/Components/integrations/McpConfigModal.vue'
import PrismServerConfigModal from '@/Components/integrations/PrismServerConfigModal.vue'
import type { Integration } from '@/Components/integrations/IntegrationCard.vue'

// Sidebar state
const activeCategory = ref<string>('all')
const searchQuery = ref('')

// Interfaces
interface Webhook {
  id: string
  name: string
  enabled: boolean
  targetType: 'agent' | 'channel' | 'task'
  targetId: string
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

// Webhook state
const showWebhookModal = ref(false)
const webhookForm = reactive({
  name: '',
  targetType: 'agent' as 'agent' | 'channel' | 'task',
  targetId: '',
})

// GLM Config modal
const showGlmConfigModal = ref(false)
const activeGlmIntegrationId = ref<'glm' | 'glm-coding'>('glm-coding')

// Codex Config modal
const showCodexConfigModal = ref(false)

// Telegram Config modal
const showTelegramConfigModal = ref(false)

// Dynamic Config modal (for package-provided integrations)
const showDynamicConfigModal = ref(false)
const dynamicIntegrationId = ref('')
const dynamicConfigSchema = ref<any[]>([])
const dynamicIntegrationMeta = ref<any>({ name: '', description: '', icon: 'ph:gear' })

// Prism Server Config modal
const showPrismServerConfigModal = ref(false)

// MCP Config modal
const showMcpConfigModal = ref(false)
const activeMcpServerId = ref<string | undefined>(undefined)

// Type filter
const typeFilter = ref<'all' | 'native' | 'mcp'>('all')

// Track which integrations are configurable (loaded from API)
const configurableIntegrations = ref<Record<string, any>>({})

// Load integration status from backend
onMounted(async () => {
  await Promise.all([loadIntegrationStatus(), loadApiKeys()])
})

const loadApiKeys = async () => {
  try {
    const { data } = await axios.get('/api/prism-server/api-keys')
    apiKeys.value = data.map((key: any) => ({
      id: key.id,
      name: key.name,
      maskedKey: key.masked_key,
      createdAt: key.created_at ? formatRelativeDate(key.created_at) : 'Unknown',
      lastUsed: key.last_used_at ? formatRelativeDate(key.last_used_at) : undefined,
    }))
  } catch (error) {
    console.error('Failed to load API keys:', error)
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
  try {
    const response = await axios.get('/api/integrations')
    if (response.status === 200) {
      const integrations = response.data
      for (const integration of integrations) {
        // Store configurable integration data for dynamic modals
        if (integration.configurable) {
          configurableIntegrations.value[integration.id] = integration
        }

        // Update existing category entries
        let found = false
        for (const category of integrationCategories.value) {
          const entry = category.integrations.find(i => i.id === integration.id)
          if (entry) {
            entry.installed = integration.enabled
            entry.configurable = integration.configurable
            found = true
            break
          }
        }

        // For MCP integrations, try to match against suggested entries by URL
        if (!found && integration.type === 'mcp') {
          const mcpCat = integrationCategories.value.find(c => c.id === 'mcp-servers')
          if (mcpCat) {
            const suggested = mcpCat.integrations.find(
              i => i.suggestedMcpConfig && i.suggestedMcpConfig.url === integration.url
            )
            if (suggested) {
              suggested.installed = true
              suggested.mcpServerId = integration.mcpServerId
              suggested.toolCount = integration.toolCount
              found = true
            }
          }
        }

        // Add dynamic integrations not in static categories
        if (!found && (integration.configurable || integration.type === 'mcp')) {
          const categoryId = integration.type === 'mcp' ? 'mcp-servers' : (integration.category || 'other')
          let category = integrationCategories.value.find(c => c.id === categoryId)
          if (!category) {
            category = {
              id: categoryId,
              name: categoryId.charAt(0).toUpperCase() + categoryId.slice(1),
              icon: integration.icon || 'ph:puzzle-piece',
              integrations: [],
            }
            integrationCategories.value.push(category)
          }
          // Avoid duplicates
          if (!category.integrations.find(i => i.id === integration.id)) {
            category.integrations.push({
              id: integration.id,
              name: integration.name,
              icon: integration.icon,
              description: integration.description,
              installed: integration.enabled,
              badge: integration.badge || undefined,
              configurable: integration.configurable ?? false,
              type: integration.type || 'native',
              mcpServerId: integration.mcpServerId,
              toolCount: integration.toolCount,
            })
          }
        }
      }
    }
  } catch (error) {
    console.error('Failed to load integration status:', error)
  }
}

// Mock data - Webhooks
const webhooks = ref<Webhook[]>([
  {
    id: 'wh-1',
    name: 'GitHub PR Notifications',
    enabled: true,
    targetType: 'agent',
    targetId: 'agent-1',
    lastTriggered: '2h ago',
    callCount: 47,
  },
  {
    id: 'wh-2',
    name: 'Stripe Payment Events',
    enabled: false,
    targetType: 'channel',
    targetId: 'channel-1',
    lastTriggered: '3d ago',
    callCount: 12,
  },
])

// API Keys (loaded from backend)
const apiKeys = ref<ApiKey[]>([])

// Integration categories
const integrationCategories = ref<IntegrationCategory[]>([
  {
    id: 'ai-models',
    name: 'AI Models',
    icon: 'ph:brain',
    integrations: [
      { id: 'glm', name: 'GLM (Zhipu AI)', icon: 'ph:brain', description: 'General-purpose Chinese LLM', installed: false, badge: 'verified' },
      { id: 'glm-coding', name: 'GLM Coding Plan', icon: 'ph:code', description: 'Specialized coding LLM', installed: false, badge: 'verified' },
      { id: 'codex', name: 'OpenAI Codex', icon: 'ph:open-ai-logo', description: 'ChatGPT Pro/Plus subscription — $0 token costs', installed: false, badge: 'verified' },
    ],
  },
  {
    id: 'analytics',
    name: 'Analytics',
    icon: 'ph:chart-line-up',
    integrations: [
      { id: 'plausible', name: 'Plausible Analytics', icon: 'ph:chart-line-up', description: 'Privacy-friendly website analytics', installed: false, badge: 'verified' },
      { id: 'google-analytics', name: 'Google Analytics', icon: 'ph:google-logo', description: 'Website traffic analytics', installed: false },
    ],
  },
  {
    id: 'communication',
    name: 'Communication',
    icon: 'ph:chat-circle',
    integrations: [
      { id: 'slack', name: 'Slack', icon: 'ph:slack-logo', description: 'Team messaging and notifications', installed: false },
      { id: 'discord', name: 'Discord', icon: 'ph:discord-logo', description: 'Community chat and voice', installed: false },
      { id: 'teams', name: 'Microsoft Teams', icon: 'ph:microsoft-teams-logo', description: 'Enterprise collaboration', installed: false },
      { id: 'telegram', name: 'Telegram', icon: 'ph:telegram-logo', description: 'Secure messaging', installed: false, badge: 'verified' },
      { id: 'matrix', name: 'Matrix', icon: 'ph:chat-centered-dots', description: 'Decentralized chat (self-hosted)', installed: false },
    ],
  },
  {
    id: 'developer',
    name: 'Developer Tools',
    icon: 'ph:code',
    integrations: [
      { id: 'github', name: 'GitHub', icon: 'ph:github-logo', description: 'Repos, issues, PRs, actions', installed: true },
      { id: 'gitlab', name: 'GitLab', icon: 'ph:gitlab-logo', description: 'Git hosting and CI/CD', installed: false },
      { id: 'linear', name: 'Linear', icon: 'ph:square-split-horizontal', description: 'Issue tracking', installed: false },
      { id: 'jira', name: 'Jira', icon: 'ph:kanban', description: 'Project management', installed: false },
    ],
  },
  {
    id: 'productivity',
    name: 'Productivity',
    icon: 'ph:briefcase',
    integrations: [
      { id: 'notion', name: 'Notion', icon: 'ph:notebook', description: 'Docs and knowledge base', installed: false },
      { id: 'trello', name: 'Trello', icon: 'ph:trello-logo', description: 'Kanban boards', installed: false },
      { id: 'google-calendar', name: 'Google Calendar', icon: 'ph:calendar', description: 'Calendar sync', installed: false },
      { id: 'obsidian', name: 'Obsidian', icon: 'ph:vault', description: 'Knowledge management', installed: false },
      { id: 'google-drive', name: 'Google Drive', icon: 'ph:google-drive-logo', description: 'File storage and sharing', installed: false },
    ],
  },
  {
    id: 'automation',
    name: 'Automation',
    icon: 'ph:flow-arrow',
    integrations: [
      { id: 'n8n', name: 'n8n', icon: 'ph:flow-arrow', description: 'Open-source workflow automation', installed: false },
      { id: 'zapier', name: 'Zapier', icon: 'ph:lightning', description: 'Connect to 5,000+ apps', installed: false },
      { id: 'make', name: 'Make (Integromat)', icon: 'ph:circles-three-plus', description: 'Visual automation platform', installed: false },
    ],
  },
  {
    id: 'data',
    name: 'Data & APIs',
    icon: 'ph:database',
    integrations: [
      { id: 'webhooks', name: 'Webhooks', icon: 'ph:webhooks-logo', description: 'Custom HTTP webhooks', installed: true, badge: 'built-in' },
      { id: 'email', name: 'Email (SMTP)', icon: 'ph:envelope', description: 'Send and receive emails', installed: false, badge: 'built-in' },
      { id: 'rest-api', name: 'REST API', icon: 'ph:plug', description: 'Generic API connector', installed: false, badge: 'built-in' },
    ],
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

// Computed - MCP category
const mcpCategory = computed(() => {
  return integrationCategories.value.find(c => c.id === 'mcp-servers')
})

// Computed - Selected category
const selectedCategory = computed(() => {
  return integrationCategories.value.find(c => c.id === activeCategory.value)
})

// Computed - Total integration count
const totalIntegrationCount = computed(() => {
  return integrationCategories.value.reduce((sum, cat) => sum + cat.integrations.length, 0)
})

// Computed - Connected services (installed integrations)
const connectedServices = computed<Service[]>(() => {
  const installed: Service[] = []
  for (const category of integrationCategories.value) {
    for (const integration of category.integrations) {
      if (integration.installed) {
        installed.push({
          id: integration.id,
          name: integration.name,
          icon: integration.icon,
          description: integration.description,
          connected: true,
        })
      }
    }
  }
  return installed
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
  return installed
})

// Computed - Installed count
const installedCount = computed(() => {
  return connectedServices.value.length
})

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
  return results
})

// Webhook handlers
const editWebhook = (webhook: Webhook) => {
  webhookForm.name = webhook.name
  webhookForm.targetType = webhook.targetType
  webhookForm.targetId = webhook.targetId
  showWebhookModal.value = true
}

const deleteWebhook = (id: string) => {
  webhooks.value = webhooks.value.filter(w => w.id !== id)
}

const saveWebhook = () => {
  const newWebhook: Webhook = {
    id: `wh-${Date.now()}`,
    name: webhookForm.name,
    enabled: true,
    targetType: webhookForm.targetType,
    targetId: webhookForm.targetId,
  }
  webhooks.value.push(newWebhook)
  showWebhookModal.value = false
  webhookForm.name = ''
  webhookForm.targetType = 'agent'
  webhookForm.targetId = ''
}

// API Key handlers
const generateApiKey = () => {
  // Open Prism Server modal for key management
  showPrismServerConfigModal.value = true
}

const revokeApiKey = async (id: string) => {
  try {
    await axios.delete(`/api/prism-server/api-keys/${id}`)
    apiKeys.value = apiKeys.value.filter(k => k.id !== id)
  } catch (error) {
    console.error('Failed to revoke API key:', error)
  }
}

// Open the dynamic config modal for a configurable integration
const openDynamicModal = (integrationId: string) => {
  const data = configurableIntegrations.value[integrationId]
  if (!data) return
  dynamicIntegrationId.value = integrationId
  dynamicConfigSchema.value = data.configSchema || []
  dynamicIntegrationMeta.value = {
    name: data.name,
    description: data.description,
    icon: data.icon,
    logo: data.logo,
    docs_url: data.docsUrl,
  }
  showDynamicConfigModal.value = true
}

// Quick install for suggested MCP servers (one-click)
const installingMcpId = ref<string | null>(null)

const handleQuickInstallMcp = async (integration: Integration) => {
  if (!integration.suggestedMcpConfig || installingMcpId.value) return

  installingMcpId.value = integration.id
  try {
    const { data } = await axios.post('/api/mcp-servers', {
      name: integration.name,
      url: integration.suggestedMcpConfig.url,
      auth_type: integration.suggestedMcpConfig.auth_type,
      icon: integration.suggestedMcpConfig.icon,
      description: integration.suggestedMcpConfig.description,
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
const handleInstall = (integration: Integration) => {
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

  if (integration.id === 'glm' || integration.id === 'glm-coding') {
    activeGlmIntegrationId.value = integration.id as 'glm' | 'glm-coding'
    showGlmConfigModal.value = true
    return
  }

  if (integration.id === 'codex') {
    showCodexConfigModal.value = true
    return
  }

  if (integration.id === 'telegram') {
    showTelegramConfigModal.value = true
    return
  }

  // Check if this is a configurable package-provided integration
  if (configurableIntegrations.value[integration.id]) {
    openDynamicModal(integration.id)
    return
  }

  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === integration.id)
    if (found) {
      found.installed = true
      break
    }
  }
}

const handleGlmSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === activeGlmIntegrationId.value)
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleCodexSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === 'codex')
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleConfigure = (integration: Integration) => {
  if (integration.type === 'mcp' && integration.mcpServerId) {
    activeMcpServerId.value = integration.mcpServerId
    showMcpConfigModal.value = true
  } else if (integration.id === 'glm' || integration.id === 'glm-coding') {
    activeGlmIntegrationId.value = integration.id as 'glm' | 'glm-coding'
    showGlmConfigModal.value = true
  } else if (integration.id === 'codex') {
    showCodexConfigModal.value = true
  } else if (integration.id === 'telegram') {
    showTelegramConfigModal.value = true
  } else if (configurableIntegrations.value[integration.id]) {
    openDynamicModal(integration.id)
  }
}

const handleDynamicSaved = (result: { enabled: boolean; configured: boolean }) => {
  const id = dynamicIntegrationId.value
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === id)
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleTelegramSaved = (result: { enabled: boolean; configured: boolean }) => {
  for (const category of integrationCategories.value) {
    const found = category.integrations.find(i => i.id === 'telegram')
    if (found) {
      found.installed = result.enabled
      break
    }
  }
}

const handleUninstall = (integration: Integration) => {
  if (integration.type === 'mcp' && integration.mcpServerId) {
    // For MCP, delete via API
    axios.delete(`/api/mcp-servers/${integration.mcpServerId}`)
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

const handlePrismServerSaved = () => {
  loadApiKeys()
}

const handleMcpDeleted = () => {
  loadIntegrationStatus()
}
</script>
