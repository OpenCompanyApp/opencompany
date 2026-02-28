interface ModelPricing {
  inputPer1M: number
  outputPer1M: number
  cacheReadPer1M?: number
  cacheWritePer1M?: number
}

const MODEL_PRICING: Record<string, ModelPricing> = {
  // Anthropic
  'claude-sonnet-4-5': { inputPer1M: 3, outputPer1M: 15, cacheReadPer1M: 0.30, cacheWritePer1M: 3.75 },
  'claude-sonnet-4': { inputPer1M: 3, outputPer1M: 15, cacheReadPer1M: 0.30, cacheWritePer1M: 3.75 },
  'claude-haiku-3-5': { inputPer1M: 0.80, outputPer1M: 4, cacheReadPer1M: 0.08, cacheWritePer1M: 1 },
  'claude-haiku-4': { inputPer1M: 0.80, outputPer1M: 4, cacheReadPer1M: 0.08, cacheWritePer1M: 1 },
  'claude-opus-4': { inputPer1M: 15, outputPer1M: 75, cacheReadPer1M: 1.50, cacheWritePer1M: 18.75 },
  // OpenAI
  'gpt-4o': { inputPer1M: 2.50, outputPer1M: 10 },
  'gpt-4o-mini': { inputPer1M: 0.15, outputPer1M: 0.60 },
  'gpt-4.1': { inputPer1M: 2, outputPer1M: 8 },
  'gpt-4.1-mini': { inputPer1M: 0.40, outputPer1M: 1.60 },
  'gpt-4.1-nano': { inputPer1M: 0.10, outputPer1M: 0.40 },
  // Google
  'gemini-2': { inputPer1M: 1.25, outputPer1M: 10 },
  'gemini-2.5-pro': { inputPer1M: 1.25, outputPer1M: 10 },
  'gemini-2.5-flash': { inputPer1M: 0.15, outputPer1M: 0.60 },
  // DeepSeek
  'deepseek': { inputPer1M: 0.27, outputPer1M: 1.10 },
  // Groq
  'llama': { inputPer1M: 0.05, outputPer1M: 0.08 },
  // xAI
  'grok': { inputPer1M: 3, outputPer1M: 15 },
}

const DEFAULT_PRICING: ModelPricing = { inputPer1M: 3, outputPer1M: 15, cacheReadPer1M: 0.30, cacheWritePer1M: 3.75 }

export function getModelPricing(model: string): ModelPricing {
  // Try partial match against known model prefixes
  const key = Object.keys(MODEL_PRICING).find(k => model.toLowerCase().includes(k.toLowerCase()))
  return key ? MODEL_PRICING[key] : DEFAULT_PRICING
}

export function estimateCost(
  promptTokens: number,
  completionTokens: number,
  model?: string,
  cacheReadTokens = 0,
  cacheWriteTokens = 0,
): number {
  const pricing = getModelPricing(model || 'default')
  const inputCost = (promptTokens / 1_000_000) * pricing.inputPer1M
  const outputCost = (completionTokens / 1_000_000) * pricing.outputPer1M
  const cacheReadCost = (cacheReadTokens / 1_000_000) * (pricing.cacheReadPer1M || 0)
  const cacheWriteCost = (cacheWriteTokens / 1_000_000) * (pricing.cacheWritePer1M || 0)
  return inputCost + outputCost + cacheReadCost + cacheWriteCost
}

export function formatCost(cost: number): string {
  if (cost < 0.01) return `$${cost.toFixed(4)}`
  if (cost < 1) return `$${cost.toFixed(3)}`
  return `$${cost.toFixed(2)}`
}

export function formatTokens(count: number): string {
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`
  return count.toString()
}
