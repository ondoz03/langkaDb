import type { ProviderId } from './config'

const STORAGE_KEY = 'aetherdb_ai_keys'

export function getKeys(): Record<ProviderId, string> {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}')
  } catch {
    return {} as Record<ProviderId, string>
  }
}

export function getKey(provider: ProviderId): string | null {
  return getKeys()[provider] ?? null
}

export function setKey(provider: ProviderId, key: string): void {
  const keys = getKeys()
  keys[provider] = key
  localStorage.setItem(STORAGE_KEY, JSON.stringify(keys))
}

export function removeKey(provider: ProviderId): void {
  const keys = getKeys()
  delete keys[provider]
  localStorage.setItem(STORAGE_KEY, JSON.stringify(keys))
}

export function hasKey(provider: ProviderId): boolean {
  return !!getKey(provider)
}
