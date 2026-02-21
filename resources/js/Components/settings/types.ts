export interface ActionPolicy {
  id: string
  name: string
  pattern: string
  level: 'allow' | 'require_approval' | 'block'
  costThreshold?: number
}

export interface ProviderInfo {
  id: string
  name: string
  icon: string
  configured: boolean
  source: 'prism' | 'integration' | 'oauth'
  models: { id: string; name: string }[]
}

export interface EmbeddingModelOption {
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

export interface EmbeddingProviderGroup {
  provider: string
  providerName: string
  configured: boolean
  type: 'cloud' | 'local'
  models: EmbeddingModelOption[]
}

export interface RerankingModelOption {
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

export interface RerankCloudProviderGroup {
  provider: string
  providerName: string
  configured: boolean
  models: RerankingModelOption[]
}

export interface MemorySettingsData {
  memory_embedding_model: string
  memory_summary_model: string
  memory_compaction_enabled: boolean
  memory_reranking_enabled: boolean
  memory_reranking_model: string
  model_context_windows: Record<string, number>
}
