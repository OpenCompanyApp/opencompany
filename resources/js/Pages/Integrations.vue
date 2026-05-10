<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-6xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <div class="flex items-center justify-between">
          <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Integrations</h1>
          <div class="flex items-center gap-1">
            <Link
              :href="workspacePath('/developer/tools')"
              class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-md transition-colors"
            >
              <Icon name="ph:code" class="w-3.5 h-3.5" />
              Tool Catalog
            </Link>
            <Link
              :href="workspacePath('/developer/lua-console')"
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
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import Modal from '@/Components/shared/Modal.vue'
import IntegrationCard from '@/Components/integrations/IntegrationCard.vue'
import ProviderConfigModal from '@/Components/integrations/ProviderConfigModal.vue'
import CodexConfigModal from '@/Components/integrations/CodexConfigModal.vue'
import DynamicConfigModal from '@/Components/integrations/DynamicConfigModal.vue'
import McpConfigModal from '@/Components/integrations/McpConfigModal.vue'
import PrismServerConfigModal from '@/Components/integrations/PrismServerConfigModal.vue'
import type { Integration } from '@/Components/integrations/IntegrationCard.vue'

const { workspacePath } = useWorkspace()

// Sidebar state
const activeCategory = ref<string>('all')
const searchQuery = ref('')
const catalogLoading = ref(false)
const catalogError = ref<string | null>(null)
const catalogAvailable = ref<boolean | null>(null)
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

// AI Provider Config modal
const showProviderConfigModal = ref(false)
const activeProviderId = ref('glm-coding')

// Codex Config modal
const showCodexConfigModal = ref(false)

// Dynamic Config modal (for package-provided integrations and chat platforms)
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
  await Promise.all([loadCatalogThenStatus(), loadApiKeys()])
})

const loadCatalogThenStatus = async () => {
  await loadIntegrationStatus()
  await loadIntegrationCatalog({ reset: true })
}

const categoryLabels: Record<string, { name: string; icon: string }> = {
  analytics: { name: 'Analytics', icon: 'ph:chart-line-up' },
  data: { name: 'Data & APIs', icon: 'ph:database' },
  productivity: { name: 'Productivity', icon: 'ph:briefcase' },
  rendering: { name: 'Rendering', icon: 'ph:paint-brush' },
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

const mergeIntegrationCard = (incoming: Integration) => {
  const category = categoryFor(incoming.category || 'data', incoming.icon)
  const existing = integrationCategories.value
    .flatMap(c => c.integrations)
    .find(i => i.id === incoming.id)

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
    const response = await axios.get('/api/integrations/catalog', {
      params: {
        page,
        perPage: catalogMeta.perPage,
        search: search || undefined,
        category: category || undefined,
      },
    })

    catalogAvailable.value = response.data.available ?? null
    catalogMeta.page = response.data.meta?.page || page
    catalogMeta.perPage = response.data.meta?.perPage || catalogMeta.perPage
    catalogMeta.total = response.data.meta?.total || 0
    catalogMeta.hasMore = Boolean(response.data.meta?.hasMore)
    catalogMeta.search = search
    catalogMeta.category = category

    for (const item of response.data.data || []) {
      mergeIntegrationCard({
        id: item.id,
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
  const category = activeCategory.value !== 'all' && activeCategory.value !== 'installed' && activeCategory.value !== 'mcp-servers'
    ? activeCategory.value
    : ''

  loadIntegrationCatalog({
    search: searchQuery.value.trim(),
    category: searchQuery.value.trim() ? '' : category,
  })
}

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

        mergeIntegrationCard({
          id: integration.id,
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
          mcpServerId: integration.mcpServerId,
          toolCount: integration.toolCount,
        })

        // For MCP integrations, try to match against suggested entries by URL
        if (integration.type === 'mcp') {
          const mcpCat = integrationCategories.value.find(c => c.id === 'mcp-servers')
          if (mcpCat) {
            const suggested = mcpCat.integrations.find(
              i => i.suggestedMcpConfig && i.suggestedMcpConfig.url === integration.url
            )
            if (suggested) {
              suggested.installed = true
              suggested.mcpServerId = integration.mcpServerId
              suggested.toolCount = integration.toolCount
            }
          }
        }
      }
    }
  } catch (error) {
    console.error('Failed to load integration status:', error)
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (query) => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    loadIntegrationCatalog({ reset: true, search: query.trim(), category: '' })
  }, 250)
})

watch(activeCategory, (category) => {
  if (category === 'all' || category === 'installed' || category === 'mcp-servers') {
    return
  }

  loadIntegrationCatalog({ reset: true, search: '', category })
})

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
    integrations: [],  // populated from backend
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
    id: 'chat-platforms',
    name: 'Chat Platforms',
    icon: 'ph:chat-dots',
    integrations: [
      { id: 'telegram', name: 'Telegram', icon: 'ph:telegram-logo', description: 'Telegram Bot for DMs, notifications, and approvals', installed: false, badge: 'verified' },
      { id: 'slack', name: 'Slack', icon: 'ph:slack-logo', description: 'Connect your Slack workspace for team chat', installed: false },
      { id: 'discord', name: 'Discord', icon: 'ph:discord-logo', description: 'Connect a Discord server', installed: false },
      { id: 'teams', name: 'Microsoft Teams', icon: 'ph:microsoft-teams-logo', description: 'Connect a Teams channel', installed: false },
      { id: 'google_chat', name: 'Google Chat', icon: 'ph:google-logo', description: 'Google Chat space integration', installed: false },
      { id: 'github_chat', name: 'GitHub', icon: 'ph:github-logo', description: 'Chat via GitHub issue/PR comments', installed: false },
      { id: 'linear_chat', name: 'Linear', icon: 'ph:line-segments', description: 'Chat via Linear issue comments', installed: false },
    ],
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
    integrations: [
      { id: 'google-calendar', name: 'Google Calendar', icon: 'ph:calendar', description: 'Calendar sync', installed: false },
      { id: 'google-drive', name: 'Google Drive', icon: 'ph:google-drive-logo', description: 'File storage and sharing', installed: false },
    ],
  },
  {
    id: 'data',
    name: 'Data & APIs',
    icon: 'ph:database',
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

// Open the dynamic config modal for a configurable integration.
// Fetch by id so catalog cards do not depend on the large /api/integrations cache.
const openDynamicModal = async (integrationOrId: Integration | string) => {
  const integrationId = typeof integrationOrId === 'string' ? integrationOrId : integrationOrId.id
  const fallback = typeof integrationOrId === 'string'
    ? configurableIntegrations.value[integrationId]
    : integrationOrId

  try {
    const { data } = await axios.get(`/api/integrations/${integrationId}/config`)
    const schema = data.configSchema || fallback?.configSchema || []
    const merged = {
      ...fallback,
      ...data,
      id: integrationId,
      configSchema: schema,
      docsUrl: data.docsUrl || fallback?.docsUrl,
      icon: data.icon || fallback?.icon || 'ph:gear',
    }

    configurableIntegrations.value[integrationId] = merged
    dynamicIntegrationId.value = integrationId
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

  // AI providers with API key config (category driven from backend)
  if (integration.category === 'ai-models' && integration.id !== 'codex') {
    activeProviderId.value = integration.id
    showProviderConfigModal.value = true
    return
  }

  if (integration.id === 'codex') {
    showCodexConfigModal.value = true
    return
  }

  // Check if this is a configurable integration (chat platforms, package-provided, etc.)
  if (integration.configurable || configurableIntegrations.value[integration.id]) {
    await openDynamicModal(integration)
    return
  }

  // Non-configurable integration — toggle via API
  try {
    await axios.post(`/api/integrations/${integration.id}/toggle`, { enabled: true })
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
    const found = category.integrations.find(i => i.id === activeProviderId.value)
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
    const found = category.integrations.find(i => i.id === 'codex')
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
  } else if (integration.category === 'ai-models' && integration.id !== 'codex') {
    activeProviderId.value = integration.id
    showProviderConfigModal.value = true
  } else if (integration.id === 'codex') {
    showCodexConfigModal.value = true
  } else if (integration.configurable || configurableIntegrations.value[integration.id]) {
    await openDynamicModal(integration)
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

const handleUninstall = async (integration: Integration) => {
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

  // Disable via toggle API, then update local state
  try {
    await axios.post(`/api/integrations/${integration.id}/toggle`, { enabled: false })
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

const handlePrismServerSaved = () => {
  loadApiKeys()
}

const handleMcpDeleted = () => {
  loadIntegrationStatus()
}
</script>
