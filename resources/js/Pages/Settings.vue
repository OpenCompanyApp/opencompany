<template>
  <div class="h-full overflow-hidden flex flex-col">
    <div class="max-w-5xl mx-auto w-full p-4 md:p-6 flex flex-col flex-1 min-h-0">
      <!-- Header -->
      <header class="mb-4 md:mb-6 shrink-0">
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-white">Settings</h1>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1">
          Manage your organization and agent configuration
        </p>
      </header>

      <!-- Mobile Nav -->
      <div class="flex gap-1.5 overflow-x-auto pb-3 -mx-4 px-4 md:hidden shrink-0" style="-ms-overflow-style: none; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
        <button
          v-for="section in sections"
          :key="'mobile-' + section.id"
          type="button"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors whitespace-nowrap shrink-0',
            activeSection === section.id
              ? section.id === 'danger' ? 'bg-red-600 text-white' : 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
              : section.id === 'danger' ? 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400',
          ]"
          @click="activeSection = section.id"
        >
          <Icon :name="section.icon" class="w-3.5 h-3.5" />
          {{ section.name }}
        </button>
      </div>

      <!-- Sidebar + Content -->
      <div class="flex flex-col md:flex-row gap-4 md:gap-6 flex-1 min-h-0">
        <!-- Desktop Sidebar -->
        <nav class="hidden md:flex w-52 shrink-0 flex-col gap-1">
          <button
            v-for="section in sections.filter(s => s.id !== 'danger')"
            :key="section.id"
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === section.id
                ? 'bg-neutral-900 dark:bg-white text-white dark:text-neutral-900'
                : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
            ]"
            @click="activeSection = section.id"
          >
            <Icon :name="section.icon" class="w-4 h-4" />
            {{ section.name }}
          </button>

          <!-- Divider -->
          <div class="border-t border-neutral-200 dark:border-neutral-700 my-2" />

          <!-- Danger Zone -->
          <button
            type="button"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium transition-colors text-left w-full',
              activeSection === 'danger'
                ? 'bg-red-600 text-white'
                : 'text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            ]"
            @click="activeSection = 'danger'"
          >
            <Icon name="ph:warning" class="w-4 h-4" />
            Danger Zone
          </button>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 overflow-y-auto">
          <!-- Loading -->
          <div v-if="loading" class="flex items-center justify-center py-16">
            <Icon name="ph:spinner" class="w-6 h-6 text-neutral-400 animate-spin" />
          </div>

          <template v-else>
            <!-- Organization -->
            <template v-if="activeSection === 'organization'">
              <SettingsSection title="Organization" icon="ph:buildings">
                <div class="space-y-4">
                  <SettingsField label="Organization Name">
                    <input
                      v-model="orgSettings.org_name"
                      type="text"
                      class="settings-input"
                      placeholder="Enter organization name"
                    />
                  </SettingsField>

                  <SettingsField label="Organization Email">
                    <input
                      v-model="orgSettings.org_email"
                      type="email"
                      class="settings-input"
                      placeholder="org@example.com"
                    />
                  </SettingsField>

                  <SettingsField label="Timezone">
                    <select v-model="orgSettings.org_timezone" class="settings-input">
                      <option value="UTC">UTC</option>
                      <option value="America/New_York">Eastern Time (ET)</option>
                      <option value="America/Chicago">Central Time (CT)</option>
                      <option value="America/Denver">Mountain Time (MT)</option>
                      <option value="America/Los_Angeles">Pacific Time (PT)</option>
                      <option value="Europe/London">London (GMT)</option>
                      <option value="Europe/Amsterdam">Amsterdam (CET)</option>
                      <option value="Europe/Berlin">Berlin (CET)</option>
                      <option value="Asia/Tokyo">Tokyo (JST)</option>
                      <option value="Asia/Shanghai">Shanghai (CST)</option>
                    </select>
                  </SettingsField>
                </div>

                <template #actions>
                  <SaveButton :saving="saving === 'organization'" :saved="saved === 'organization'" @click="saveCategory('organization', orgSettings)" />
                </template>
              </SettingsSection>
            </template>

            <!-- Agent Defaults -->
            <template v-if="activeSection === 'agents'">
              <SettingsSection title="Agent Defaults" icon="ph:robot">
                <div class="space-y-4">
                  <SettingsField label="Default Agent Behavior" description="Controls how newly created agents behave by default">
                    <select v-model="agentSettings.default_behavior" class="settings-input">
                      <option value="autonomous">Autonomous (minimal supervision)</option>
                      <option value="supervised">Supervised (ask before actions)</option>
                      <option value="strict">Strict (require approval for everything)</option>
                    </select>
                  </SettingsField>

                  <SettingsField label="Auto-spawn Agents">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <div class="relative">
                        <input
                          v-model="agentSettings.auto_spawn"
                          type="checkbox"
                          class="sr-only"
                        />
                        <div
                          class="w-11 h-6 rounded-full transition-colors"
                          :class="agentSettings.auto_spawn ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'"
                        >
                          <div
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform"
                            :class="{ 'translate-x-5': agentSettings.auto_spawn }"
                          />
                        </div>
                      </div>
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Allow manager agents to spawn temporary agents
                      </span>
                    </label>
                  </SettingsField>

                  <SettingsField label="Budget Approval Threshold" description="Auto-approve budget actions below this amount. Set to 0 to always require approval.">
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">$</span>
                      <input
                        v-model.number="agentSettings.budget_approval_threshold"
                        type="number"
                        class="settings-input"
                        placeholder="0"
                        min="0"
                        step="1"
                      />
                    </div>
                  </SettingsField>
                </div>

                <template #actions>
                  <SaveButton :saving="saving === 'agents'" :saved="saved === 'agents'" @click="saveCategory('agents', agentSettings)" />
                </template>
              </SettingsSection>
            </template>

            <!-- Action Policies -->
            <template v-if="activeSection === 'policies'">
              <SettingsSection title="Action Policies" icon="ph:shield-check">
                <template #actions>
                  <div class="flex items-center gap-2">
                    <SaveButton :saving="saving === 'policies'" :saved="saved === 'policies'" @click="savePolicies" />
                    <button
                      type="button"
                      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors duration-150"
                      @click="showPolicyModal = true"
                    >
                      <Icon name="ph:plus" class="w-3.5 h-3.5" />
                      Add policy
                    </button>
                  </div>
                </template>

                <div v-if="actionPolicies.length > 0" class="space-y-3">
                  <div
                    v-for="policy in actionPolicies"
                    :key="policy.id"
                    class="flex items-start gap-3 p-3 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700"
                  >
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ policy.name }}</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5 font-mono">{{ policy.pattern }}</p>
                      <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">
                        {{ getPolicyLevelText(policy) }}
                      </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                      <button
                        type="button"
                        class="p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                        @click="editPolicy(policy)"
                      >
                        <Icon name="ph:pencil-simple" class="w-4 h-4" />
                      </button>
                      <button
                        type="button"
                        class="p-1.5 text-neutral-400 hover:text-red-500 transition-colors"
                        @click="deletePolicy(policy.id)"
                      >
                        <Icon name="ph:trash" class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <div v-else class="py-6 text-center">
                  <Icon name="ph:shield" class="w-8 h-8 text-neutral-300 dark:text-neutral-600 mx-auto mb-2" />
                  <p class="text-sm text-neutral-500 dark:text-neutral-400">No action policies configured</p>
                  <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Add policies to control which agent actions require approval</p>
                </div>
              </SettingsSection>
            </template>

            <!-- Notifications -->
            <template v-if="activeSection === 'notifications'">
              <SettingsSection title="Notifications" icon="ph:bell">
                <div class="space-y-4">
                  <SettingsField label="Email Notifications">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="notificationSettings.email_notifications"
                        type="checkbox"
                        class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                      />
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Receive email notifications for approval requests
                      </span>
                    </label>
                  </SettingsField>

                  <SettingsField label="Slack Integration">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="notificationSettings.slack_notifications"
                        type="checkbox"
                        class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                      />
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Send notifications to Slack channel
                      </span>
                    </label>
                  </SettingsField>

                  <SettingsField label="Daily Summary">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <input
                        v-model="notificationSettings.daily_summary"
                        type="checkbox"
                        class="w-4 h-4 rounded text-neutral-900 focus:ring-neutral-900"
                      />
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Receive a daily summary of agent activities
                      </span>
                    </label>
                  </SettingsField>
                </div>

                <template #actions>
                  <SaveButton :saving="saving === 'notifications'" :saved="saved === 'notifications'" @click="saveCategory('notifications', notificationSettings)" />
                </template>
              </SettingsSection>
            </template>

            <!-- Memory -->
            <template v-if="activeSection === 'memory'">
              <SettingsSection title="Memory" icon="ph:brain" description="Configure AI models and parameters for the memory system">
                <div class="space-y-4">
                  <SettingsField label="Embedding Model" description="Provider and model used for document embeddings">
                    <div v-if="loadingEmbeddingModels" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 py-2">
                      <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
                      Loading embedding models...
                    </div>
                    <template v-else>
                      <!-- Self-hosted section (always visible, prominent) -->
                      <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-800/50 p-4 space-y-3">
                        <div class="flex items-center gap-2">
                          <Icon name="ph:cube" class="w-4 h-4 text-neutral-900 dark:text-white" />
                          <span class="text-sm font-medium text-neutral-900 dark:text-white">Self-Hosted</span>
                          <span class="text-[10px] font-medium uppercase tracking-wider px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400">Included</span>
                        </div>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                          Ollama runs locally — no API keys, no costs. Just download a model to get started.
                        </p>

                        <!-- Connection status -->
                        <div class="flex items-center gap-2 text-xs">
                          <span
                            class="w-2 h-2 rounded-full shrink-0"
                            :class="ollamaStatus.online ? 'bg-green-500' : 'bg-red-400'"
                          />
                          <span :class="ollamaStatus.online ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                            {{ ollamaStatus.online ? `Connected at ${ollamaStatus.url}` : 'Ollama not running' }}
                          </span>
                          <button
                            type="button"
                            class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                            @click="checkOllamaStatus"
                          >
                            <Icon name="ph:arrow-clockwise" class="w-3.5 h-3.5" />
                          </button>
                        </div>

                        <!-- Model list (always visible) -->
                        <div class="space-y-1.5">
                          <div
                            v-for="model in ollamaModels"
                            :key="model.id"
                          >
                            <!-- Row (always visible) -->
                            <div
                              class="flex items-center gap-2.5 px-3 py-2 rounded-lg border transition-colors cursor-pointer"
                              :class="[
                                embeddingSource === 'self-hosted' && selectedOllamaModel === model.model
                                  ? 'bg-white dark:bg-neutral-800 border-neutral-900 dark:border-white'
                                  : 'bg-white/50 dark:bg-neutral-800/50 border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600',
                                expandedOllamaModel === model.model && 'rounded-b-none',
                              ]"
                              @click="model.downloaded && ollamaStatus.online ? selectOllamaModel(model.model) : undefined"
                            >
                              <!-- Expand/collapse chevron -->
                              <button
                                type="button"
                                class="p-0.5 -ml-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors shrink-0"
                                @click.stop="toggleOllamaModelExpand(model.model)"
                              >
                                <Icon
                                  name="ph:caret-right"
                                  class="w-3.5 h-3.5 transition-transform duration-150"
                                  :class="{ 'rotate-90': expandedOllamaModel === model.model }"
                                />
                              </button>
                              <!-- Radio indicator -->
                              <div
                                class="w-3.5 h-3.5 rounded-full border-2 shrink-0 flex items-center justify-center"
                                :class="embeddingSource === 'self-hosted' && selectedOllamaModel === model.model
                                  ? 'border-neutral-900 dark:border-white'
                                  : 'border-neutral-300 dark:border-neutral-600'"
                              >
                                <div
                                  v-if="embeddingSource === 'self-hosted' && selectedOllamaModel === model.model"
                                  class="w-1.5 h-1.5 rounded-full bg-neutral-900 dark:bg-white"
                                />
                              </div>
                              <span class="flex-1 text-sm font-mono text-neutral-900 dark:text-white truncate">{{ model.model }}</span>
                              <span v-if="model.model === 'snowflake-arctic-embed2'" class="text-[10px] font-medium uppercase tracking-wider px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 whitespace-nowrap">Recommended</span>
                              <span class="text-xs text-neutral-400 dark:text-neutral-500 whitespace-nowrap">{{ model.size }}</span>
                              <!-- Downloaded / Ready -->
                              <span v-if="model.downloaded" class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400 whitespace-nowrap">
                                <Icon name="ph:check-circle" class="w-3.5 h-3.5" />
                                Ready
                              </span>
                              <!-- Downloading with progress -->
                              <div v-else-if="pullingModel === model.model" class="flex items-center gap-2 min-w-[140px]">
                                <div class="flex-1 h-1.5 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                  <div
                                    class="h-full bg-indigo-500 rounded-full transition-all duration-300 ease-out"
                                    :style="{ width: `${pullProgress?.percent ?? 0}%` }"
                                  />
                                </div>
                                <span class="text-[11px] text-neutral-500 dark:text-neutral-400 tabular-nums whitespace-nowrap">
                                  {{ pullProgress?.percent ?? 0 }}%
                                </span>
                              </div>
                              <!-- Download button -->
                              <button
                                v-else
                                type="button"
                                class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors whitespace-nowrap"
                                :disabled="pullingModel !== null || !ollamaStatus.online"
                                @click.stop="pullOllamaModel(model.model)"
                              >
                                <Icon name="ph:download-simple" class="w-3.5 h-3.5" />
                                Download
                              </button>
                            </div>

                            <!-- Expanded detail panel -->
                            <Transition
                              enter-active-class="transition-all duration-150 ease-out overflow-hidden"
                              enter-from-class="max-h-0 opacity-0"
                              enter-to-class="max-h-[300px] opacity-100"
                              leave-active-class="transition-all duration-150 ease-out overflow-hidden"
                              leave-from-class="max-h-[300px] opacity-100"
                              leave-to-class="max-h-0 opacity-0"
                            >
                              <div
                                v-if="expandedOllamaModel === model.model"
                                class="px-4 py-3 rounded-b-lg border border-t-0"
                                :class="embeddingSource === 'self-hosted' && selectedOllamaModel === model.model
                                  ? 'bg-white dark:bg-neutral-800 border-neutral-900 dark:border-white'
                                  : 'bg-neutral-50/80 dark:bg-neutral-800/30 border-neutral-200 dark:border-neutral-700'"
                              >
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-2.5">
                                  <div>
                                    <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Parameters</span>
                                    <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.parameters }}</p>
                                  </div>
                                  <div>
                                    <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Memory</span>
                                    <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.memory }}</p>
                                  </div>
                                  <div>
                                    <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Dimensions</span>
                                    <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.dimensions }}d</p>
                                  </div>
                                  <div>
                                    <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Context</span>
                                    <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.context?.toLocaleString() }} tokens</p>
                                  </div>
                                </div>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">{{ model.description }}</p>
                              </div>
                            </Transition>
                          </div>
                        </div>

                        <!-- Offline hint -->
                        <p v-if="!ollamaStatus.online" class="text-xs text-neutral-400 dark:text-neutral-500 flex items-center gap-1">
                          <Icon name="ph:info" class="w-3.5 h-3.5" />
                          Start Ollama to manage and download models
                        </p>

                      </div>

                      <!-- Divider -->
                      <div class="flex items-center gap-3 mt-4 mb-3">
                        <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
                        <span class="text-xs text-neutral-400 dark:text-neutral-500">or use a cloud provider</span>
                        <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
                      </div>

                      <!-- Cloud provider section -->
                      <div class="flex gap-2">
                        <select v-model="selectedCloudProvider" class="settings-input flex-1" @change="onCloudProviderChange">
                          <option value="" disabled>Select provider</option>
                          <option
                            v-for="group in cloudProviders"
                            :key="group.provider"
                            :value="group.provider"
                          >
                            {{ group.providerName }}{{ !group.configured ? ' (not configured)' : '' }}
                          </option>
                        </select>
                        <select v-model="selectedCloudModel" class="settings-input flex-1" @change="onCloudModelChange" :disabled="!selectedCloudProvider || availableCloudModels.length === 0">
                          <option value="" disabled>Select model</option>
                          <option
                            v-for="model in availableCloudModels"
                            :key="model.id"
                            :value="model.model"
                            :disabled="!model.configured"
                          >
                            {{ model.name }}{{ !model.configured ? ' (requires API key)' : '' }}
                          </option>
                        </select>
                      </div>

                      <!-- Cloud provider warning -->
                      <p v-if="selectedCloudProviderInfo && !selectedCloudProviderInfo.configured" class="text-xs text-amber-600 dark:text-amber-400 mt-1.5 flex items-center gap-1">
                        <Icon name="ph:warning" class="w-3.5 h-3.5" />
                        This provider needs an API key. Configure it in <a :href="workspacePath('/integrations')" class="underline hover:no-underline">Integrations</a> or via environment variables.
                      </p>
                    </template>
                  </SettingsField>

                  <SettingsField label="Summary Model" description="Model used to summarize conversations during compaction">
                    <div v-if="loadingProviders" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 py-2">
                      <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
                      Loading providers...
                    </div>
                    <template v-else-if="allProviders.length > 0">
                      <div class="flex gap-2">
                        <select v-model="selectedSummaryProvider" class="settings-input flex-1" @change="onSummaryProviderChange">
                          <option value="" disabled>Select provider</option>
                          <option
                            v-for="provider in allProviders"
                            :key="provider.id"
                            :value="provider.id"
                            :disabled="!provider.configured"
                          >
                            {{ provider.name }}{{ !provider.configured ? ' (not configured)' : '' }}
                          </option>
                        </select>
                        <select v-model="selectedSummaryModel" class="settings-input flex-1" @change="syncSummaryModel" :disabled="!selectedSummaryProvider || availableSummaryModels.length === 0">
                          <option value="" disabled>Select model</option>
                          <option
                            v-for="model in availableSummaryModels"
                            :key="model.id"
                            :value="model.id"
                          >
                            {{ model.name }}
                          </option>
                          <!-- Show current model if not in the list -->
                          <option
                            v-if="selectedSummaryModel && !availableSummaryModels.some(m => m.id === selectedSummaryModel)"
                            :value="selectedSummaryModel"
                          >
                            {{ selectedSummaryModel }} (current)
                          </option>
                        </select>
                      </div>
                      <p v-if="selectedSummaryProvider && !selectedProviderConfigured" class="text-xs text-amber-600 dark:text-amber-400 mt-1.5 flex items-center gap-1">
                        <Icon name="ph:warning" class="w-3.5 h-3.5" />
                        This provider needs an API key. Configure it in <a :href="workspacePath('/integrations')" class="underline hover:no-underline">Integrations</a> or via environment variables.
                      </p>
                    </template>
                    <div v-else class="text-sm text-neutral-500 dark:text-neutral-400 py-2">
                      <p>No AI providers available. <a :href="workspacePath('/integrations')" class="text-neutral-900 dark:text-white underline hover:no-underline">Configure integrations</a> first.</p>
                    </div>
                  </SettingsField>

                  <SettingsField label="Search Reranking" description="Re-score search results with a cross-encoder model for better relevance">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <div class="relative">
                        <input
                          v-model="memorySettings.memory_reranking_enabled"
                          type="checkbox"
                          class="sr-only"
                        />
                        <div
                          class="w-11 h-6 rounded-full transition-colors"
                          :class="memorySettings.memory_reranking_enabled ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'"
                        >
                          <div
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform"
                            :class="{ 'translate-x-5': memorySettings.memory_reranking_enabled }"
                          />
                        </div>
                      </div>
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Enable search reranking
                      </span>
                    </label>

                    <!-- Model selection (visible when enabled) -->
                    <Transition
                      enter-active-class="transition-all duration-150 ease-out overflow-hidden"
                      enter-from-class="max-h-0 opacity-0"
                      enter-to-class="max-h-[800px] opacity-100"
                      leave-active-class="transition-all duration-150 ease-out overflow-hidden"
                      leave-from-class="max-h-[800px] opacity-100"
                      leave-to-class="max-h-0 opacity-0"
                    >
                      <div v-if="memorySettings.memory_reranking_enabled" class="mt-3 space-y-3">
                        <div v-if="loadingRerankingModels" class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400 py-2">
                          <Icon name="ph:spinner" class="w-4 h-4 animate-spin" />
                          Loading reranking models...
                        </div>
                        <template v-else>
                          <!-- Self-hosted section -->
                          <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 bg-neutral-50/50 dark:bg-neutral-800/50 p-4 space-y-3">
                            <div class="flex items-center gap-2">
                              <Icon name="ph:cube" class="w-4 h-4 text-neutral-900 dark:text-white" />
                              <span class="text-sm font-medium text-neutral-900 dark:text-white">Self-Hosted</span>
                              <span class="text-[10px] font-medium uppercase tracking-wider px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400">Included</span>
                            </div>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                              Qwen3-Reranker runs locally via Ollama — no API keys, no costs.
                            </p>

                            <!-- Connection status (shared with embedding) -->
                            <div class="flex items-center gap-2 text-xs">
                              <span
                                class="w-2 h-2 rounded-full shrink-0"
                                :class="ollamaStatus.online ? 'bg-green-500' : 'bg-red-400'"
                              />
                              <span :class="ollamaStatus.online ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                {{ ollamaStatus.online ? `Connected at ${ollamaStatus.url}` : 'Ollama not running' }}
                              </span>
                              <button
                                type="button"
                                class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors"
                                @click="checkOllamaStatus"
                              >
                                <Icon name="ph:arrow-clockwise" class="w-3.5 h-3.5" />
                              </button>
                            </div>

                            <!-- Model list -->
                            <div class="space-y-1.5">
                              <div
                                v-for="model in ollamaRerankModels"
                                :key="model.id"
                              >
                                <!-- Row -->
                                <div
                                  class="flex items-center gap-2.5 px-3 py-2 rounded-lg border transition-colors cursor-pointer"
                                  :class="[
                                    rerankingSource === 'self-hosted' && selectedOllamaRerankModel === model.model
                                      ? 'bg-white dark:bg-neutral-800 border-neutral-900 dark:border-white'
                                      : 'bg-white/50 dark:bg-neutral-800/50 border-neutral-200 dark:border-neutral-700 hover:border-neutral-300 dark:hover:border-neutral-600',
                                    expandedOllamaRerankModel === model.model && 'rounded-b-none',
                                  ]"
                                  @click="model.downloaded && ollamaStatus.online ? selectOllamaRerankModel(model.model) : undefined"
                                >
                                  <!-- Expand/collapse chevron -->
                                  <button
                                    type="button"
                                    class="p-0.5 -ml-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors shrink-0"
                                    @click.stop="toggleOllamaRerankModelExpand(model.model)"
                                  >
                                    <Icon
                                      name="ph:caret-right"
                                      class="w-3.5 h-3.5 transition-transform duration-150"
                                      :class="{ 'rotate-90': expandedOllamaRerankModel === model.model }"
                                    />
                                  </button>
                                  <!-- Radio indicator -->
                                  <div
                                    class="w-3.5 h-3.5 rounded-full border-2 shrink-0 flex items-center justify-center"
                                    :class="rerankingSource === 'self-hosted' && selectedOllamaRerankModel === model.model
                                      ? 'border-neutral-900 dark:border-white'
                                      : 'border-neutral-300 dark:border-neutral-600'"
                                  >
                                    <div
                                      v-if="rerankingSource === 'self-hosted' && selectedOllamaRerankModel === model.model"
                                      class="w-1.5 h-1.5 rounded-full bg-neutral-900 dark:bg-white"
                                    />
                                  </div>
                                  <span class="flex-1 text-sm font-mono text-neutral-900 dark:text-white truncate">{{ model.model }}</span>
                                  <span v-if="model.model.startsWith('dengcao/Qwen3-Reranker-0.6B')" class="text-[10px] font-medium uppercase tracking-wider px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 whitespace-nowrap">Recommended</span>
                                  <span class="text-xs text-neutral-400 dark:text-neutral-500 whitespace-nowrap">{{ model.size }}</span>
                                  <!-- Downloaded / Ready -->
                                  <span v-if="model.downloaded" class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400 whitespace-nowrap">
                                    <Icon name="ph:check-circle" class="w-3.5 h-3.5" />
                                    Ready
                                  </span>
                                  <!-- Downloading with progress -->
                                  <div v-else-if="pullingRerankModel === model.model" class="flex items-center gap-2 min-w-[140px]">
                                    <div class="flex-1 h-1.5 bg-neutral-200 dark:bg-neutral-700 rounded-full overflow-hidden">
                                      <div
                                        class="h-full bg-indigo-500 rounded-full transition-all duration-300 ease-out"
                                        :style="{ width: `${pullRerankProgress?.percent ?? 0}%` }"
                                      />
                                    </div>
                                    <span class="text-[11px] text-neutral-500 dark:text-neutral-400 tabular-nums whitespace-nowrap">
                                      {{ pullRerankProgress?.percent ?? 0 }}%
                                    </span>
                                  </div>
                                  <!-- Download button -->
                                  <button
                                    v-else
                                    type="button"
                                    class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors whitespace-nowrap"
                                    :disabled="pullingRerankModel !== null || !ollamaStatus.online"
                                    @click.stop="pullOllamaRerankModel(model.model)"
                                  >
                                    <Icon name="ph:download-simple" class="w-3.5 h-3.5" />
                                    Download
                                  </button>
                                </div>

                                <!-- Expanded detail panel -->
                                <Transition
                                  enter-active-class="transition-all duration-150 ease-out overflow-hidden"
                                  enter-from-class="max-h-0 opacity-0"
                                  enter-to-class="max-h-[300px] opacity-100"
                                  leave-active-class="transition-all duration-150 ease-out overflow-hidden"
                                  leave-from-class="max-h-[300px] opacity-100"
                                  leave-to-class="max-h-0 opacity-0"
                                >
                                  <div
                                    v-if="expandedOllamaRerankModel === model.model"
                                    class="px-4 py-3 rounded-b-lg border border-t-0"
                                    :class="rerankingSource === 'self-hosted' && selectedOllamaRerankModel === model.model
                                      ? 'bg-white dark:bg-neutral-800 border-neutral-900 dark:border-white'
                                      : 'bg-neutral-50/80 dark:bg-neutral-800/30 border-neutral-200 dark:border-neutral-700'"
                                  >
                                    <div class="grid grid-cols-3 gap-3 mb-2.5">
                                      <div>
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Parameters</span>
                                        <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.parameters }}</p>
                                      </div>
                                      <div>
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Memory</span>
                                        <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.memory }}</p>
                                      </div>
                                      <div>
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500">Download Size</span>
                                        <p class="text-sm font-mono text-neutral-900 dark:text-white mt-0.5">{{ model.size }}</p>
                                      </div>
                                    </div>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">{{ model.description }}</p>
                                  </div>
                                </Transition>
                              </div>
                            </div>

                            <!-- Offline hint -->
                            <p v-if="!ollamaStatus.online" class="text-xs text-neutral-400 dark:text-neutral-500 flex items-center gap-1">
                              <Icon name="ph:info" class="w-3.5 h-3.5" />
                              Start Ollama to manage and download models
                            </p>
                          </div>

                          <!-- Divider -->
                          <div class="flex items-center gap-3">
                            <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
                            <span class="text-xs text-neutral-400 dark:text-neutral-500">or use a cloud provider</span>
                            <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
                          </div>

                          <!-- Cloud provider section -->
                          <div class="flex gap-2">
                            <select v-model="selectedRerankCloudProvider" class="settings-input flex-1" @change="onRerankCloudProviderChange">
                              <option value="" disabled>Select provider</option>
                              <option
                                v-for="group in rerankCloudProviders"
                                :key="group.provider"
                                :value="group.provider"
                              >
                                {{ group.providerName }}{{ !group.configured ? ' (not configured)' : '' }}
                              </option>
                            </select>
                            <select v-model="selectedRerankCloudModel" class="settings-input flex-1" @change="onRerankCloudModelChange" :disabled="!selectedRerankCloudProvider || availableRerankCloudModels.length === 0">
                              <option value="" disabled>Select model</option>
                              <option
                                v-for="model in availableRerankCloudModels"
                                :key="model.id"
                                :value="model.model"
                              >
                                {{ model.name }}{{ !model.configured ? ' (requires API key)' : '' }}
                              </option>
                            </select>
                          </div>

                          <!-- Cloud provider warning -->
                          <p v-if="selectedRerankCloudProviderInfo && !selectedRerankCloudProviderInfo.configured" class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                            <Icon name="ph:warning" class="w-3.5 h-3.5" />
                            This provider needs an API key. Configure it in <a :href="workspacePath('/integrations')" class="underline hover:no-underline">Integrations</a> or via environment variables.
                          </p>

                          <!-- Divider -->
                          <div v-if="rerankLlmProviders.length > 0" class="flex items-center gap-3">
                            <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
                            <span class="text-xs text-neutral-400 dark:text-neutral-500">or use any AI model</span>
                            <div class="flex-1 h-px bg-neutral-200 dark:bg-neutral-700" />
                          </div>

                          <!-- LLM provider section -->
                          <template v-if="rerankLlmProviders.length > 0">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                              Use any configured AI model for reranking. The model judges each document's relevance to the query.
                            </p>
                            <div class="flex gap-2">
                              <select v-model="selectedRerankLlmProvider" class="settings-input flex-1" @change="onRerankLlmProviderChange">
                                <option value="" disabled>Select provider</option>
                                <option
                                  v-for="group in rerankLlmProviders"
                                  :key="group.provider"
                                  :value="group.provider"
                                  :disabled="!group.configured"
                                >
                                  {{ group.providerName }}{{ !group.configured ? ' (not configured)' : '' }}
                                </option>
                              </select>
                              <select v-model="selectedRerankLlmModel" class="settings-input flex-1" @change="onRerankLlmModelChange" :disabled="!selectedRerankLlmProvider || availableRerankLlmModels.length === 0">
                                <option value="" disabled>Select model</option>
                                <option
                                  v-for="model in availableRerankLlmModels"
                                  :key="model.id"
                                  :value="model.model"
                                >
                                  {{ model.name }}
                                </option>
                              </select>
                            </div>

                            <!-- LLM provider warning -->
                            <p v-if="selectedRerankLlmProviderInfo && !selectedRerankLlmProviderInfo.configured" class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                              <Icon name="ph:warning" class="w-3.5 h-3.5" />
                              This provider needs an API key. Configure it in <a :href="workspacePath('/integrations')" class="underline hover:no-underline">Integrations</a> or via environment variables.
                            </p>
                          </template>
                        </template>
                      </div>
                    </Transition>
                  </SettingsField>

                  <SettingsField label="Conversation Compaction" description="Automatically summarize older messages when the context window fills up">
                    <label class="flex items-center gap-3 cursor-pointer">
                      <div class="relative">
                        <input
                          v-model="memorySettings.memory_compaction_enabled"
                          type="checkbox"
                          class="sr-only"
                        />
                        <div
                          class="w-11 h-6 rounded-full transition-colors"
                          :class="memorySettings.memory_compaction_enabled ? 'bg-neutral-900 dark:bg-white' : 'bg-neutral-200 dark:bg-neutral-700'"
                        >
                          <div
                            class="absolute top-0.5 left-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition-transform"
                            :class="{ 'translate-x-5': memorySettings.memory_compaction_enabled }"
                          />
                        </div>
                      </div>
                      <span class="text-sm text-neutral-500 dark:text-neutral-400">
                        Enable conversation compaction
                      </span>
                    </label>
                  </SettingsField>

                  <SettingsField label="Context Window Overrides" description="Override context window sizes for models not in the built-in registry" hint="These take priority over the built-in model registry and Levenshtein matching">
                    <div class="space-y-2">
                      <!-- Existing overrides -->
                      <div
                        v-for="(tokens, model) in memorySettings.model_context_windows"
                        :key="model"
                        class="flex items-center gap-2 p-2.5 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700"
                      >
                        <span class="flex-1 text-sm font-mono text-neutral-900 dark:text-white truncate">{{ model }}</span>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap tabular-nums">{{ Number(tokens).toLocaleString() }} tokens</span>
                        <button
                          type="button"
                          class="p-1 text-neutral-400 hover:text-red-500 transition-colors shrink-0"
                          @click="deleteOverride(model as string)"
                        >
                          <Icon name="ph:x" class="w-3.5 h-3.5" />
                        </button>
                      </div>

                      <!-- Empty state -->
                      <div
                        v-if="Object.keys(memorySettings.model_context_windows).length === 0"
                        class="py-3 text-center text-xs text-neutral-400 dark:text-neutral-500"
                      >
                        No overrides configured
                      </div>

                      <!-- Add new override -->
                      <div class="flex items-center gap-2">
                        <input
                          v-model="newOverrideModel"
                          type="text"
                          class="flex-1 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-sm font-mono text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 outline-none transition-colors"
                          placeholder="model name"
                        />
                        <input
                          v-model.number="newOverrideTokens"
                          type="number"
                          class="w-32 px-3 py-1.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-lg text-sm text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 outline-none transition-colors tabular-nums"
                          placeholder="tokens"
                          min="1"
                        />
                        <button
                          type="button"
                          class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-700 transition-colors shrink-0"
                          :disabled="!newOverrideModel || !newOverrideTokens"
                          @click="addOverride"
                        >
                          <Icon name="ph:plus" class="w-3.5 h-3.5" />
                          Add
                        </button>
                      </div>
                    </div>
                  </SettingsField>
                </div>

                <template #actions>
                  <span v-if="saved === 'memory'" class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                    <Icon name="ph:check" class="w-3.5 h-3.5" />
                    Saved
                  </span>
                  <span v-else-if="saving === 'memory'" class="text-xs text-neutral-400 flex items-center gap-1">
                    <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                    Saving...
                  </span>
                </template>
              </SettingsSection>
            </template>

            <!-- Danger Zone -->
            <template v-if="activeSection === 'danger'">
              <SettingsSection title="Danger Zone" icon="ph:warning" variant="danger">
                <div class="space-y-3">
                  <div class="flex items-center justify-between py-2">
                    <div>
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">Pause All Agents</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Immediately pause all running agent tasks</p>
                    </div>
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
                      :disabled="dangerLoading !== null"
                      @click="confirmDangerAction('pause_agents', 'Pause All Agents', 'This will immediately pause all running agents. They can be resumed individually.')"
                    >
                      <span v-if="dangerLoading === 'pause_agents'" class="flex items-center gap-1">
                        <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                        Pausing...
                      </span>
                      <span v-else>Pause All</span>
                    </button>
                  </div>

                  <div class="border-t border-neutral-100 dark:border-neutral-800" />

                  <div class="flex items-center justify-between py-2">
                    <div>
                      <p class="text-sm font-medium text-neutral-900 dark:text-white">Reset Agent Memory</p>
                      <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Clear all agent memory and learned behaviors</p>
                    </div>
                    <button
                      type="button"
                      class="px-3 py-1.5 text-xs font-medium rounded-md text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
                      :disabled="dangerLoading !== null"
                      @click="confirmDangerAction('reset_memory', 'Reset Agent Memory', 'This will clear all agent memory files. This action cannot be undone.')"
                    >
                      <span v-if="dangerLoading === 'reset_memory'" class="flex items-center gap-1">
                        <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
                        Resetting...
                      </span>
                      <span v-else>Reset</span>
                    </button>
                  </div>
                </div>
              </SettingsSection>
            </template>
          </template>
        </main>
      </div>

      <!-- Policy Modal -->
      <Modal v-model:open="showPolicyModal" :title="editingPolicy ? 'Edit Policy' : 'Add Policy'">
        <template #body>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Name</label>
              <input
                v-model="policyForm.name"
                type="text"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
                placeholder="e.g., Document Operations"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Pattern</label>
              <input
                v-model="policyForm.pattern"
                type="text"
                class="w-full px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm font-mono focus:outline-none focus:border-neutral-400"
                placeholder="e.g., write:documents/*"
              />
              <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Use * as wildcard. Examples: read:*, execute:external/*</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Policy Level</label>
              <div class="space-y-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="policyForm.level" type="radio" value="allow" class="text-neutral-900" />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">Allow without approval</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="policyForm.level" type="radio" value="require_approval" class="text-neutral-900" />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">Require approval</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="policyForm.level" type="radio" value="block" class="text-neutral-900" />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">Block entirely</span>
                </label>
              </div>
            </div>
            <div v-if="policyForm.level === 'require_approval'">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">Cost Threshold (optional)</label>
              <div class="flex items-center gap-2">
                <span class="text-neutral-500">$</span>
                <input
                  v-model.number="policyForm.costThreshold"
                  type="number"
                  class="flex-1 px-3 py-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-white text-sm focus:outline-none focus:border-neutral-400"
                  placeholder="0"
                  min="0"
                />
              </div>
              <p class="text-xs text-neutral-400 dark:text-neutral-500 mt-1">Only require approval when cost exceeds this amount</p>
            </div>
          </div>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              @click="closePolicyModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-neutral-900 dark:bg-white text-white dark:text-neutral-900 hover:bg-neutral-800 dark:hover:bg-neutral-100"
              @click="savePolicy"
            >
              {{ editingPolicy ? 'Save Changes' : 'Create Policy' }}
            </button>
          </div>
        </template>
      </Modal>

      <!-- Danger Confirmation Modal -->
      <Modal v-model:open="showDangerModal" :title="dangerModalTitle">
        <template #body>
          <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ dangerModalMessage }}</p>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <button
              type="button"
              class="px-3 py-1.5 text-sm rounded-md text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800"
              @click="showDangerModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-3 py-1.5 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700"
              @click="executeDangerAction"
            >
              Confirm
            </button>
          </div>
        </template>
      </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, h } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { apiFetch } from '@/utils/apiFetch'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import Icon from '@/Components/shared/Icon.vue'
import Modal from '@/Components/shared/Modal.vue'
import { useApi } from '@/composables/useApi'
import { useWorkspace } from '@/composables/useWorkspace'
import axios from 'axios'

const { workspacePath } = useWorkspace()

// --- Sidebar sections ---
const sections = [
  { id: 'organization', name: 'Organization', icon: 'ph:buildings' },
  { id: 'agents', name: 'Agent Defaults', icon: 'ph:robot' },
  { id: 'policies', name: 'Action Policies', icon: 'ph:shield-check' },
  { id: 'notifications', name: 'Notifications', icon: 'ph:bell' },
  { id: 'memory', name: 'Memory', icon: 'ph:brain' },
  { id: 'danger', name: 'Danger Zone', icon: 'ph:warning' },
]

const activeSection = ref('organization')

// --- API ---
const { fetchSettings, updateSettings, dangerAction } = useApi()
const loading = ref(true)
const saving = ref<string | null>(null)
const saved = ref<string | null>(null)

// --- Settings state ---
const orgSettings = reactive({
  org_name: '',
  org_email: '',
  org_timezone: 'UTC',
})

const agentSettings = reactive({
  default_behavior: 'supervised',
  auto_spawn: false,
  budget_approval_threshold: 0,
})

const notificationSettings = reactive({
  email_notifications: true,
  slack_notifications: false,
  daily_summary: true,
})

const memorySettings = reactive({
  memory_embedding_model: 'openai:text-embedding-3-small',
  memory_summary_model: 'anthropic:claude-sonnet-4-5-20250929',
  memory_compaction_enabled: true,
  memory_reranking_enabled: true,
  memory_reranking_model: 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0',
  model_context_windows: {} as Record<string, number>,
})

const newOverrideModel = ref('')
const newOverrideTokens = ref<number | undefined>(undefined)

// --- Model options (from integrations API) ---
interface ModelOption {
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  icon?: string
  configured?: boolean
}

interface ModelGroup {
  provider: string
  providerName: string
  configured?: boolean
  models: ModelOption[]
}

// --- Provider / model selector for summary model ---
interface ProviderInfo {
  id: string
  name: string
  icon: string
  configured: boolean
  source: 'prism' | 'integration' | 'oauth'
  models: { id: string; name: string }[]
}

const allProviders = ref<ProviderInfo[]>([])
const loadingProviders = ref(false)

// Derived from memorySettings.memory_summary_model
const selectedSummaryProvider = ref('')
const selectedSummaryModel = ref('')

function parseSummaryModel(value: string) {
  const parts = value.split(':', 2)
  selectedSummaryProvider.value = parts[0] || ''
  selectedSummaryModel.value = parts[1] || ''
}

const availableSummaryModels = computed(() => {
  const provider = allProviders.value.find(p => p.id === selectedSummaryProvider.value)
  return provider?.models ?? []
})

const selectedProviderConfigured = computed(() => {
  const provider = allProviders.value.find(p => p.id === selectedSummaryProvider.value)
  return provider?.configured ?? false
})

function onSummaryProviderChange() {
  // When provider changes, auto-select first model
  const models = availableSummaryModels.value
  selectedSummaryModel.value = models.length > 0 ? models[0].id : ''
  syncSummaryModel()
}

function syncSummaryModel() {
  if (selectedSummaryProvider.value && selectedSummaryModel.value) {
    memorySettings.memory_summary_model = `${selectedSummaryProvider.value}:${selectedSummaryModel.value}`
  }
}

// --- Reranking model options ---
interface RerankingModelOption {
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  configured?: boolean
  type: 'cloud' | 'local' | 'llm'
  downloaded?: boolean
  size?: string
  parameters?: string
  memory?: string
  description?: string
}

const rerankingModelOptions = ref<RerankingModelOption[]>([])
const loadingRerankingModels = ref(false)

// Source-based selection: self-hosted (Ollama) vs cloud vs llm
const rerankingSource = ref<'self-hosted' | 'cloud' | 'llm'>('self-hosted')
const selectedOllamaRerankModel = ref('')
const selectedRerankCloudProvider = ref('')
const selectedRerankCloudModel = ref('')
const selectedRerankLlmProvider = ref('')
const selectedRerankLlmModel = ref('')

// Accordion + pull state (separate from embedding)
const expandedOllamaRerankModel = ref<string | null>(null)
const pullingRerankModel = ref<string | null>(null)
const pullRerankProgress = ref<{ percent: number; status: string } | null>(null)

function toggleOllamaRerankModelExpand(modelId: string) {
  expandedOllamaRerankModel.value = expandedOllamaRerankModel.value === modelId ? null : modelId
}

function parseRerankingModel(value: string) {
  const [provider, model] = value.split(':', 2)
  if (provider === 'ollama') {
    rerankingSource.value = 'self-hosted'
    selectedOllamaRerankModel.value = model || ''
  } else if (provider === 'cohere' || provider === 'jina') {
    rerankingSource.value = 'cloud'
    selectedRerankCloudProvider.value = provider || ''
    selectedRerankCloudModel.value = model || ''
  } else {
    rerankingSource.value = 'llm'
    selectedRerankLlmProvider.value = provider || ''
    selectedRerankLlmModel.value = model || ''
  }
}

function syncRerankingModel() {
  if (rerankingSource.value === 'self-hosted' && selectedOllamaRerankModel.value) {
    memorySettings.memory_reranking_model = `ollama:${selectedOllamaRerankModel.value}`
  } else if (rerankingSource.value === 'cloud' && selectedRerankCloudProvider.value && selectedRerankCloudModel.value) {
    memorySettings.memory_reranking_model = `${selectedRerankCloudProvider.value}:${selectedRerankCloudModel.value}`
  } else if (rerankingSource.value === 'llm' && selectedRerankLlmProvider.value && selectedRerankLlmModel.value) {
    memorySettings.memory_reranking_model = `${selectedRerankLlmProvider.value}:${selectedRerankLlmModel.value}`
  }
}

function selectOllamaRerankModel(modelId: string) {
  rerankingSource.value = 'self-hosted'
  selectedOllamaRerankModel.value = modelId
  syncRerankingModel()
}

// Ollama reranking models
const ollamaRerankModels = computed(() =>
  rerankingModelOptions.value.filter(m => m.provider === 'ollama')
)

// Cloud reranking providers (grouped)
interface RerankCloudProviderGroup {
  provider: string
  providerName: string
  configured: boolean
  models: RerankingModelOption[]
}

const rerankCloudProviders = computed<RerankCloudProviderGroup[]>(() => {
  const groups = new Map<string, RerankCloudProviderGroup>()
  for (const model of rerankingModelOptions.value) {
    if (model.type !== 'cloud') continue
    if (!groups.has(model.provider)) {
      groups.set(model.provider, {
        provider: model.provider,
        providerName: model.providerName,
        configured: model.configured ?? false,
        models: [],
      })
    }
    groups.get(model.provider)!.models.push(model)
  }
  return Array.from(groups.values())
})

const availableRerankCloudModels = computed(() =>
  rerankingModelOptions.value.filter(m => m.provider === selectedRerankCloudProvider.value && m.type === 'cloud')
)

const selectedRerankCloudProviderInfo = computed(() =>
  rerankCloudProviders.value.find(g => g.provider === selectedRerankCloudProvider.value)
)

function onRerankCloudProviderChange() {
  rerankingSource.value = 'cloud'
  selectedOllamaRerankModel.value = ''
  const models = availableRerankCloudModels.value
  selectedRerankCloudModel.value = models[0]?.model ?? ''
  syncRerankingModel()
}

function onRerankCloudModelChange() {
  rerankingSource.value = 'cloud'
  selectedOllamaRerankModel.value = ''
  syncRerankingModel()
}

// LLM reranking providers (grouped)
const rerankLlmProviders = computed<RerankCloudProviderGroup[]>(() => {
  const groups = new Map<string, RerankCloudProviderGroup>()
  for (const model of rerankingModelOptions.value) {
    if (model.type !== 'llm') continue
    if (!groups.has(model.provider)) {
      groups.set(model.provider, {
        provider: model.provider,
        providerName: model.providerName,
        configured: model.configured ?? false,
        models: [],
      })
    }
    groups.get(model.provider)!.models.push(model)
  }
  return Array.from(groups.values())
})

const availableRerankLlmModels = computed(() =>
  rerankingModelOptions.value.filter(m => m.provider === selectedRerankLlmProvider.value && m.type === 'llm')
)

const selectedRerankLlmProviderInfo = computed(() =>
  rerankLlmProviders.value.find(g => g.provider === selectedRerankLlmProvider.value)
)

function onRerankLlmProviderChange() {
  rerankingSource.value = 'llm'
  selectedOllamaRerankModel.value = ''
  selectedRerankCloudProvider.value = ''
  selectedRerankCloudModel.value = ''
  const models = availableRerankLlmModels.value
  selectedRerankLlmModel.value = models[0]?.model ?? ''
  syncRerankingModel()
}

function onRerankLlmModelChange() {
  rerankingSource.value = 'llm'
  selectedOllamaRerankModel.value = ''
  selectedRerankCloudProvider.value = ''
  selectedRerankCloudModel.value = ''
  syncRerankingModel()
}

async function pullOllamaRerankModel(modelId: string) {
  pullingRerankModel.value = modelId
  pullRerankProgress.value = { percent: 0, status: 'starting' }
  try {
    const response = await apiFetch('/api/integrations/ollama/pull', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({ model: modelId }),
    })

    if (!response.ok || !response.body) {
      throw new Error(`HTTP ${response.status}`)
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    let success = false

    while (true) {
      const { done, value } = await reader.read()
      if (done) break

      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop() || ''

      for (const line of lines) {
        if (!line.startsWith('data: ')) continue
        try {
          const data = JSON.parse(line.slice(6))
          if (data.percent !== undefined) {
            pullRerankProgress.value = { percent: data.percent, status: data.status || 'downloading' }
          }
          if (data.done) {
            success = data.success
          }
        } catch { /* ignore parse errors */ }
      }
    }

    if (success) {
      pullRerankProgress.value = { percent: 100, status: 'complete' }
      await Promise.all([refreshRerankingModels(), checkOllamaStatus()])
      selectOllamaRerankModel(modelId)
    }
  } catch (e) {
    console.error('Failed to pull reranking model:', e)
  } finally {
    pullingRerankModel.value = null
    pullRerankProgress.value = null
  }
}

async function refreshRerankingModels() {
  try {
    const { data } = await axios.get('/api/integrations/reranking-models')
    rerankingModelOptions.value = data
  } catch { /* ignore */ }
}

// --- Embedding model options ---
interface EmbeddingModelOption {
  id: string
  provider: string
  providerName: string
  model: string
  name: string
  configured?: boolean
  type: 'cloud' | 'local'
  downloaded?: boolean
  size?: string
  parameters?: string
  memory?: string
  dimensions?: number
  context?: number
  description?: string
}

const embeddingModelOptions = ref<EmbeddingModelOption[]>([])
const loadingEmbeddingModels = ref(false)

// Source-based selection: self-hosted (Ollama) vs cloud
const embeddingSource = ref<'self-hosted' | 'cloud'>('self-hosted')
const selectedOllamaModel = ref('')
const selectedCloudProvider = ref('')
const selectedCloudModel = ref('')

function parseEmbeddingModel(value: string) {
  const [provider, model] = value.split(':', 2)
  if (provider === 'ollama') {
    embeddingSource.value = 'self-hosted'
    selectedOllamaModel.value = model || ''
  } else {
    embeddingSource.value = 'cloud'
    selectedCloudProvider.value = provider || ''
    selectedCloudModel.value = model || ''
  }
}

function syncEmbeddingModel() {
  if (embeddingSource.value === 'self-hosted' && selectedOllamaModel.value) {
    memorySettings.memory_embedding_model = `ollama:${selectedOllamaModel.value}`
  } else if (embeddingSource.value === 'cloud' && selectedCloudProvider.value && selectedCloudModel.value) {
    memorySettings.memory_embedding_model = `${selectedCloudProvider.value}:${selectedCloudModel.value}`
  }
}

// Ollama models (self-hosted)
const ollamaModels = computed(() =>
  embeddingModelOptions.value.filter(m => m.provider === 'ollama')
)

// Track which Ollama model row is expanded (accordion: only one at a time)
const expandedOllamaModel = ref<string | null>(null)

function toggleOllamaModelExpand(modelId: string) {
  expandedOllamaModel.value = expandedOllamaModel.value === modelId ? null : modelId
}

// Cloud providers (grouped, excluding ollama)
interface EmbeddingProviderGroup {
  provider: string
  providerName: string
  configured: boolean
  type: 'cloud' | 'local'
  models: EmbeddingModelOption[]
}

const cloudProviders = computed<EmbeddingProviderGroup[]>(() => {
  const groups = new Map<string, EmbeddingProviderGroup>()
  for (const model of embeddingModelOptions.value) {
    if (model.provider === 'ollama') continue
    if (!groups.has(model.provider)) {
      groups.set(model.provider, {
        provider: model.provider,
        providerName: model.providerName,
        configured: model.configured ?? false,
        type: model.type,
        models: [],
      })
    }
    groups.get(model.provider)!.models.push(model)
  }
  return Array.from(groups.values())
})

const availableCloudModels = computed(() =>
  embeddingModelOptions.value.filter(m => m.provider === selectedCloudProvider.value)
)

const selectedCloudProviderInfo = computed(() =>
  cloudProviders.value.find(g => g.provider === selectedCloudProvider.value)
)

function selectOllamaModel(modelId: string) {
  embeddingSource.value = 'self-hosted'
  selectedOllamaModel.value = modelId
  syncEmbeddingModel()
}

function onCloudProviderChange() {
  embeddingSource.value = 'cloud'
  selectedOllamaModel.value = ''
  const models = availableCloudModels.value
  selectedCloudModel.value = models[0]?.model ?? ''
  syncEmbeddingModel()
}

function onCloudModelChange() {
  embeddingSource.value = 'cloud'
  selectedOllamaModel.value = ''
  syncEmbeddingModel()
}

// --- Ollama status & model pulling ---
const ollamaStatus = ref<{ online: boolean; models: string[]; url: string }>({ online: false, models: [], url: '' })
const pullingModel = ref<string | null>(null)
const pullProgress = ref<{ percent: number; status: string } | null>(null)

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function checkOllamaStatus() {
  try {
    const { data } = await axios.get('/api/integrations/ollama/status')
    ollamaStatus.value = data
  } catch {
    ollamaStatus.value = { online: false, models: [], url: '' }
  }
}

async function pullOllamaModel(modelId: string) {
  pullingModel.value = modelId
  pullProgress.value = { percent: 0, status: 'starting' }
  try {
    const response = await apiFetch('/api/integrations/ollama/pull', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({ model: modelId }),
    })

    if (!response.ok || !response.body) {
      throw new Error(`HTTP ${response.status}`)
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    let success = false

    while (true) {
      const { done, value } = await reader.read()
      if (done) break

      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop() || ''

      for (const line of lines) {
        if (!line.startsWith('data: ')) continue
        try {
          const data = JSON.parse(line.slice(6))
          if (data.percent !== undefined) {
            pullProgress.value = { percent: data.percent, status: data.status || 'downloading' }
          }
          if (data.done) {
            success = data.success
          }
        } catch { /* ignore parse errors */ }
      }
    }

    if (success) {
      pullProgress.value = { percent: 100, status: 'complete' }
      await Promise.all([refreshEmbeddingModels(), checkOllamaStatus()])
      selectOllamaModel(modelId)
    }
  } catch (e) {
    console.error('Failed to pull model:', e)
  } finally {
    pullingModel.value = null
    pullProgress.value = null
  }
}

async function refreshEmbeddingModels() {
  try {
    const { data } = await axios.get('/api/integrations/embedding-models')
    embeddingModelOptions.value = data
  } catch { /* ignore */ }
}

interface ActionPolicy {
  id: string
  name: string
  pattern: string
  level: 'allow' | 'require_approval' | 'block'
  costThreshold?: number
}

const actionPolicies = ref<ActionPolicy[]>([])

// --- Load settings ---
onMounted(async () => {
  try {
    const { data, promise } = fetchSettings()
    await promise
    if (data.value) {
      const s = data.value
      // Organization
      if (s.organization) {
        orgSettings.org_name = (s.organization.org_name as string) ?? ''
        orgSettings.org_email = (s.organization.org_email as string) ?? ''
        orgSettings.org_timezone = (s.organization.org_timezone as string) ?? 'UTC'
      }
      // Agents
      if (s.agents) {
        agentSettings.default_behavior = (s.agents.default_behavior as string) ?? 'supervised'
        agentSettings.auto_spawn = !!s.agents.auto_spawn
        agentSettings.budget_approval_threshold = Number(s.agents.budget_approval_threshold) || 0
      }
      // Notifications
      if (s.notifications) {
        notificationSettings.email_notifications = s.notifications.email_notifications !== false
        notificationSettings.slack_notifications = !!s.notifications.slack_notifications
        notificationSettings.daily_summary = s.notifications.daily_summary !== false
      }
      // Policies
      if (s.policies?.action_policies) {
        actionPolicies.value = s.policies.action_policies as ActionPolicy[]
      }
      // Memory
      if (s.memory) {
        memorySettings.memory_embedding_model = (s.memory.memory_embedding_model as string) ?? 'openai:text-embedding-3-small'
        memorySettings.memory_summary_model = (s.memory.memory_summary_model as string) ?? 'anthropic:claude-sonnet-4-5-20250929'
        memorySettings.memory_compaction_enabled = s.memory.memory_compaction_enabled !== false
        memorySettings.memory_reranking_enabled = s.memory.memory_reranking_enabled !== false
        memorySettings.memory_reranking_model = (s.memory.memory_reranking_model as string) ?? 'ollama:dengcao/Qwen3-Reranker-0.6B:Q8_0'
        memorySettings.model_context_windows = (s.memory.model_context_windows as Record<string, number>) ?? {}
      }
    }
  } finally {
    loading.value = false
  }

  // Parse summary + embedding + reranking models into provider + model
  parseSummaryModel(memorySettings.memory_summary_model)
  parseEmbeddingModel(memorySettings.memory_embedding_model)
  parseRerankingModel(memorySettings.memory_reranking_model)

  // Enable auto-save now that settings are hydrated
  memoryInitialized = true

  // Load available models for dropdowns (in parallel)
  loadingProviders.value = true
  loadingEmbeddingModels.value = true
  loadingRerankingModels.value = true

  const [providersRes, embeddingRes, rerankingRes] = await Promise.allSettled([
    axios.get('/api/integrations/all-providers'),
    axios.get('/api/integrations/embedding-models'),
    axios.get('/api/integrations/reranking-models'),
  ])

  if (providersRes.status === 'fulfilled') {
    allProviders.value = providersRes.value.data
  }
  loadingProviders.value = false

  if (embeddingRes.status === 'fulfilled') {
    embeddingModelOptions.value = embeddingRes.value.data
  }
  loadingEmbeddingModels.value = false

  if (rerankingRes.status === 'fulfilled') {
    rerankingModelOptions.value = rerankingRes.value.data
  }
  loadingRerankingModels.value = false

  // Check Ollama status
  checkOllamaStatus()
})

// --- Save ---
async function saveCategory(category: string, settings: Record<string, unknown>) {
  saving.value = category
  saved.value = null
  try {
    await updateSettings(category, { ...settings })
    saved.value = category
    setTimeout(() => { if (saved.value === category) saved.value = null }, 2000)
  } catch (e) {
    console.error('Failed to save settings:', e)
  } finally {
    saving.value = null
  }
}

// --- Auto-save memory settings ---
let memoryInitialized = false

const debouncedSaveMemory = useDebounceFn(() => {
  saveCategory('memory', { ...memorySettings })
}, 600)

watch(memorySettings, () => {
  if (!memoryInitialized) return
  debouncedSaveMemory()
}, { deep: true })

async function savePolicies() {
  await saveCategory('policies', { action_policies: actionPolicies.value })
}

// --- Policy CRUD ---
const showPolicyModal = ref(false)
const editingPolicy = ref<ActionPolicy | null>(null)
const policyForm = reactive({
  name: '',
  pattern: '',
  level: 'require_approval' as 'allow' | 'require_approval' | 'block',
  costThreshold: undefined as number | undefined,
})

const getPolicyLevelText = (policy: ActionPolicy): string => {
  if (policy.level === 'allow') return 'Allowed without approval'
  if (policy.level === 'block') return 'Blocked'
  if (policy.costThreshold) return `Require approval above $${policy.costThreshold}`
  return 'Require approval'
}

const editPolicy = (policy: ActionPolicy) => {
  editingPolicy.value = policy
  policyForm.name = policy.name
  policyForm.pattern = policy.pattern
  policyForm.level = policy.level
  policyForm.costThreshold = policy.costThreshold
  showPolicyModal.value = true
}

const deletePolicy = (id: string) => {
  actionPolicies.value = actionPolicies.value.filter(p => p.id !== id)
}

const savePolicy = () => {
  if (editingPolicy.value) {
    const index = actionPolicies.value.findIndex(p => p.id === editingPolicy.value!.id)
    if (index !== -1) {
      actionPolicies.value[index] = {
        ...actionPolicies.value[index],
        name: policyForm.name,
        pattern: policyForm.pattern,
        level: policyForm.level,
        costThreshold: policyForm.costThreshold,
      }
    }
  } else {
    actionPolicies.value.push({
      id: `policy-${Date.now()}`,
      name: policyForm.name,
      pattern: policyForm.pattern,
      level: policyForm.level,
      costThreshold: policyForm.costThreshold,
    })
  }
  closePolicyModal()
}

const closePolicyModal = () => {
  showPolicyModal.value = false
  editingPolicy.value = null
  policyForm.name = ''
  policyForm.pattern = ''
  policyForm.level = 'require_approval'
  policyForm.costThreshold = undefined
}

// --- Context Window Overrides ---
function addOverride() {
  if (!newOverrideModel.value || !newOverrideTokens.value) return
  memorySettings.model_context_windows = {
    ...memorySettings.model_context_windows,
    [newOverrideModel.value]: newOverrideTokens.value,
  }
  newOverrideModel.value = ''
  newOverrideTokens.value = undefined
}

function deleteOverride(model: string) {
  const { [model]: _, ...rest } = memorySettings.model_context_windows
  memorySettings.model_context_windows = rest
}

// --- Danger Zone ---
const showDangerModal = ref(false)
const dangerModalTitle = ref('')
const dangerModalMessage = ref('')
const pendingDangerAction = ref('')
const dangerLoading = ref<string | null>(null)

function confirmDangerAction(action: string, title: string, message: string) {
  pendingDangerAction.value = action
  dangerModalTitle.value = title
  dangerModalMessage.value = message
  showDangerModal.value = true
}

async function executeDangerAction() {
  showDangerModal.value = false
  const action = pendingDangerAction.value
  dangerLoading.value = action
  try {
    await dangerAction(action)
  } catch (e) {
    console.error('Danger action failed:', e)
  } finally {
    dangerLoading.value = null
  }
}

// --- SaveButton component (inline, using render function for runtime-only Vue) ---
const SaveButton = {
  props: {
    saving: Boolean,
    saved: Boolean,
  },
  emits: ['click'],
  setup(props: { saving: boolean; saved: boolean }, { emit }: { emit: (e: string) => void }) {
    return () => {
      const iconClass = 'w-3.5 h-3.5'
      let icon
      if (props.saving) {
        icon = h('svg', { class: `${iconClass} animate-spin`, viewBox: '0 0 24 24', fill: 'none', innerHTML: '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="opacity-25" /><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" />' })
      } else if (props.saved) {
        icon = h('svg', { class: iconClass, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', innerHTML: '<path d="M20 6L9 17l-5-5" />' })
      } else {
        icon = h('svg', { class: iconClass, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', innerHTML: '<path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" /><polyline points="17,21 17,13 7,13 7,21" /><polyline points="7,3 7,8 15,8" />' })
      }
      const label = props.saving ? 'Saving...' : props.saved ? 'Saved' : 'Save'
      return h('button', {
        type: 'button',
        class: [
          'flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors duration-150',
          props.saved
            ? 'text-green-600 dark:text-green-400'
            : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800',
        ],
        disabled: props.saving,
        onClick: () => emit('click'),
      }, [icon, label])
    }
  },
}
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
