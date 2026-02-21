<template>
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
      <span v-if="savedCategory === 'memory'" class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
        <Icon name="ph:check" class="w-3.5 h-3.5" />
        Saved
      </span>
      <span v-else-if="savingCategory === 'memory'" class="text-xs text-neutral-400 flex items-center gap-1">
        <Icon name="ph:spinner" class="w-3.5 h-3.5 animate-spin" />
        Saving...
      </span>
    </template>
  </SettingsSection>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import SettingsSection from '@/Components/settings/SettingsSection.vue'
import SettingsField from '@/Components/settings/SettingsField.vue'
import Icon from '@/Components/shared/Icon.vue'
import { useWorkspace } from '@/composables/useWorkspace'
import { useMemorySettings } from '@/composables/useMemorySettings'
import type { MemorySettingsData } from '@/Components/settings/types'

const props = defineProps<{
  initialMemory: MemorySettingsData
  savingCategory: string | null
  savedCategory: string | null
}>()

const emit = defineEmits<{
  save: [category: string, settings: Record<string, unknown>]
}>()

const { workspacePath } = useWorkspace()

const {
  memorySettings,
  newOverrideModel,
  newOverrideTokens,

  // Summary
  allProviders,
  loadingProviders,
  selectedSummaryProvider,
  selectedSummaryModel,
  availableSummaryModels,
  selectedProviderConfigured,
  onSummaryProviderChange,
  syncSummaryModel,

  // Embedding
  loadingEmbeddingModels,
  embeddingSource,
  selectedOllamaModel,
  selectedCloudProvider,
  selectedCloudModel,
  expandedOllamaModel,
  pullingModel,
  pullProgress,
  ollamaModels,
  cloudProviders,
  availableCloudModels,
  selectedCloudProviderInfo,
  toggleOllamaModelExpand,
  selectOllamaModel,
  onCloudProviderChange,
  onCloudModelChange,
  pullOllamaModel,

  // Ollama status
  ollamaStatus,
  checkOllamaStatus,

  // Reranking
  loadingRerankingModels,
  rerankingSource,
  selectedOllamaRerankModel,
  selectedRerankCloudProvider,
  selectedRerankCloudModel,
  selectedRerankLlmProvider,
  selectedRerankLlmModel,
  expandedOllamaRerankModel,
  pullingRerankModel,
  pullRerankProgress,
  ollamaRerankModels,
  rerankCloudProviders,
  availableRerankCloudModels,
  selectedRerankCloudProviderInfo,
  rerankLlmProviders,
  availableRerankLlmModels,
  selectedRerankLlmProviderInfo,
  toggleOllamaRerankModelExpand,
  selectOllamaRerankModel,
  onRerankCloudProviderChange,
  onRerankCloudModelChange,
  onRerankLlmProviderChange,
  onRerankLlmModelChange,
  pullOllamaRerankModel,

  // Context overrides
  addOverride,
  deleteOverride,

  // Init
  initialize,
  loadModelOptions,
} = useMemorySettings(props.initialMemory, async (category, settings) => {
  emit('save', category, settings)
})

onMounted(() => {
  initialize()
  loadModelOptions()
})
</script>

<style scoped>
@reference "tailwindcss";

.settings-input {
  @apply w-full px-4 py-2.5 bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 rounded-xl text-neutral-900 dark:text-white focus:border-neutral-400 dark:focus:border-neutral-500 focus:ring-1 focus:ring-neutral-400 dark:focus:ring-neutral-500 outline-none transition-colors;
}
</style>
