import { ref, reactive, computed, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { apiFetch } from '@/utils/apiFetch'
import axios from 'axios'
import type {
  MemorySettingsData,
  ProviderInfo,
  EmbeddingModelOption,
  EmbeddingProviderGroup,
  RerankingModelOption,
  RerankCloudProviderGroup,
} from '@/Components/settings/types'

export function useMemorySettings(
  initialMemory: MemorySettingsData,
  onSave: (category: string, settings: Record<string, unknown>) => Promise<void>,
) {
  // --- Core settings state ---
  const memorySettings = reactive<MemorySettingsData>({ ...initialMemory })

  const newOverrideModel = ref('')
  const newOverrideTokens = ref<number | undefined>(undefined)

  // --- Summary model ---
  const allProviders = ref<ProviderInfo[]>([])
  const loadingProviders = ref(false)
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
    const models = availableSummaryModels.value
    selectedSummaryModel.value = models.length > 0 ? models[0].id : ''
    syncSummaryModel()
  }

  function syncSummaryModel() {
    if (selectedSummaryProvider.value && selectedSummaryModel.value) {
      memorySettings.memory_summary_model = `${selectedSummaryProvider.value}:${selectedSummaryModel.value}`
    }
  }

  // --- Embedding model ---
  const embeddingModelOptions = ref<EmbeddingModelOption[]>([])
  const loadingEmbeddingModels = ref(false)
  const embeddingSource = ref<'self-hosted' | 'cloud'>('self-hosted')
  const selectedOllamaModel = ref('')
  const selectedCloudProvider = ref('')
  const selectedCloudModel = ref('')
  const expandedOllamaModel = ref<string | null>(null)
  const pullingModel = ref<string | null>(null)
  const pullProgress = ref<{ percent: number; status: string } | null>(null)

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

  const ollamaModels = computed(() =>
    embeddingModelOptions.value.filter(m => m.provider === 'ollama')
  )

  function toggleOllamaModelExpand(modelId: string) {
    expandedOllamaModel.value = expandedOllamaModel.value === modelId ? null : modelId
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

  // --- Ollama status ---
  const ollamaStatus = ref<{ online: boolean; models: string[]; url: string }>({ online: false, models: [], url: '' })

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

  // --- Reranking model ---
  const rerankingModelOptions = ref<RerankingModelOption[]>([])
  const loadingRerankingModels = ref(false)
  const rerankingSource = ref<'self-hosted' | 'cloud' | 'llm'>('self-hosted')
  const selectedOllamaRerankModel = ref('')
  const selectedRerankCloudProvider = ref('')
  const selectedRerankCloudModel = ref('')
  const selectedRerankLlmProvider = ref('')
  const selectedRerankLlmModel = ref('')
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

  const ollamaRerankModels = computed(() =>
    rerankingModelOptions.value.filter(m => m.provider === 'ollama')
  )

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

  // --- Context window overrides ---
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

  // --- Auto-save ---
  let memoryInitialized = false

  const debouncedSaveMemory = useDebounceFn(() => {
    onSave('memory', { ...memorySettings })
  }, 600)

  watch(memorySettings, () => {
    if (!memoryInitialized) return
    debouncedSaveMemory()
  }, { deep: true })

  // --- Initialization ---
  function initialize() {
    parseSummaryModel(memorySettings.memory_summary_model)
    parseEmbeddingModel(memorySettings.memory_embedding_model)
    parseRerankingModel(memorySettings.memory_reranking_model)
    memoryInitialized = true
  }

  async function loadModelOptions() {
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

    checkOllamaStatus()
  }

  return {
    // Core state
    memorySettings,
    newOverrideModel,
    newOverrideTokens,

    // Summary model
    allProviders,
    loadingProviders,
    selectedSummaryProvider,
    selectedSummaryModel,
    availableSummaryModels,
    selectedProviderConfigured,
    onSummaryProviderChange,
    syncSummaryModel,

    // Embedding model
    embeddingModelOptions,
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

    // Reranking model
    rerankingModelOptions,
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

    // Context window overrides
    addOverride,
    deleteOverride,

    // Initialization
    initialize,
    loadModelOptions,
  }
}
