export type ProviderId = 'openai' | 'anthropic' | 'google' | 'deepseek' | 'openrouter' | 'ollama'

export interface ProviderInfo {
  id: ProviderId
  label: string
  keyPrefix: string | null
  consoleUrl: string
  docsUrl: string
  local: boolean
}

export const PROVIDERS: ProviderInfo[] = [
  { id: 'openai', label: 'OpenAI', keyPrefix: 'sk-', consoleUrl: 'https://platform.openai.com/api-keys', docsUrl: 'https://platform.openai.com/docs', local: false },
  { id: 'anthropic', label: 'Anthropic', keyPrefix: 'sk-ant-', consoleUrl: 'https://console.anthropic.com/settings/keys', docsUrl: 'https://docs.anthropic.com', local: false },
  { id: 'google', label: 'Google Gemini', keyPrefix: null, consoleUrl: 'https://aistudio.google.com/apikey', docsUrl: 'https://ai.google.dev', local: false },
  { id: 'deepseek', label: 'DeepSeek', keyPrefix: 'sk-', consoleUrl: 'https://platform.deepseek.com/api_keys', docsUrl: 'https://platform.deepseek.com/docs', local: false },
  { id: 'openrouter', label: 'OpenRouter', keyPrefix: 'sk-or-', consoleUrl: 'https://openrouter.ai/keys', docsUrl: 'https://openrouter.ai/docs', local: false },
  { id: 'ollama', label: 'Ollama (Local)', keyPrefix: null, consoleUrl: 'https://ollama.com/download', docsUrl: 'https://github.com/ollama/ollama', local: true },
]

export interface ModelInfo {
  id: string
  provider: ProviderId
  label: string
  hint: string
}

export const MODELS: ModelInfo[] = [
  { id: 'gpt-4o-mini', provider: 'openai', label: 'GPT-4o Mini', hint: 'Fast' },
  { id: 'gpt-4o', provider: 'openai', label: 'GPT-4o', hint: 'Best' },
  { id: 'claude-sonnet-4-6', provider: 'anthropic', label: 'Claude Sonnet 4.6', hint: 'Balanced' },
  { id: 'claude-haiku-4-5', provider: 'anthropic', label: 'Claude Haiku 4.5', hint: 'Fast' },
  { id: 'gemini-2.5-flash', provider: 'google', label: 'Gemini 2.5 Flash', hint: 'Fast' },
  { id: 'gemini-2.5-pro', provider: 'google', label: 'Gemini 2.5 Pro', hint: 'Best' },
  { id: 'deepseek-v4-pro', provider: 'deepseek', label: 'DeepSeek V4 Pro', hint: 'Best' },
  { id: 'deepseek-v4-flash', provider: 'deepseek', label: 'DeepSeek V4 Flash', hint: 'Fast' },
  { id: 'deepseek-reasoner', provider: 'deepseek', label: 'DeepSeek Reasoner', hint: 'Thinking' },
  { id: 'openrouter/auto', provider: 'openrouter', label: 'OpenRouter Auto', hint: 'Auto' },
  { id: 'ollama-local', provider: 'ollama', label: 'Ollama Local', hint: 'Local' },
]

export function getProvider(id: ProviderId): ProviderInfo {
  const p = PROVIDERS.find(x => x.id === id)

  if (!p) {
throw new Error(`Unknown provider: ${id}`)
}

  return p
}

export function getModelsForProvider(provider: ProviderId): ModelInfo[] {
  return MODELS.filter(m => m.provider === provider)
}
