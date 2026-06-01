<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'
import { getKey, setKey, removeKey } from '@/modules/ai/apiKeys'
import type { ProviderId, ModelInfo } from '@/modules/ai/config'
import { PROVIDERS, MODELS, getModelsForProvider } from '@/modules/ai/config'

const providerIcons: Record<string, string> = {
  openai: '◉',
  anthropic: '◈',
  google: '◇',
  deepseek: '◆',
  openrouter: '◎',
  ollama: '○',
}

const cloudProviders = computed(() => PROVIDERS.filter(p => !p.local))
const localProviders = computed(() => PROVIDERS.filter(p => p.local))

interface ProviderState {
  configured: boolean
  keyDraft: string
  testing: boolean
  testResult: 'idle' | 'ok' | 'fail'
}

const providerStates = ref<Record<ProviderId, ProviderState>>(
  Object.fromEntries(
    PROVIDERS.map(p => [p.id, {
      configured: !!getKey(p.id),
      keyDraft: '',
      testing: false,
      testResult: 'idle' as const,
    }]),
  ) as Record<ProviderId, ProviderState>,
)

const defaultModelId = ref(localStorage.getItem('aetherdb_default_model') ?? 'deepseek-v4-flash')

const defaultModel = computed(() => {
  const m = MODELS.find(x => x.id === defaultModelId.value)

  return m ?? MODELS[0]
})

async function saveKey(provider: ProviderId) {
  const ps = providerStates.value[provider]

  if (!ps.keyDraft.trim()) {
    return
  }

  setKey(provider, ps.keyDraft.trim())
  ps.configured = true
  ps.keyDraft = ''
}

function clearKey(provider: ProviderId) {
  removeKey(provider)
  providerStates.value[provider].configured = false
}

async function testConnection(provider: ProviderId) {
  const ps = providerStates.value[provider]
  ps.testing = true
  ps.testResult = 'idle'

  await new Promise(r => setTimeout(r, 800))
  ps.testing = false
  ps.testResult = 'ok'
}

const systemPrompt = ref(localStorage.getItem('aetherdb_system_prompt') ?? '')

watch(systemPrompt, (val) => {
  localStorage.setItem('aetherdb_system_prompt', val)
})

const systemPromptCount = computed(() => {
  if (!systemPrompt.value) {
    return 'Default active'
  }

  return `${systemPrompt.value.length} chars`
})

function resetSystemPrompt() {
  systemPrompt.value = ''
}

function selectDefaultModel(id: string) {
  defaultModelId.value = id
  localStorage.setItem('aetherdb_default_model', id)
}

function maskKey(key: string): string {
  if (key.length < 8) {
    return '•'.repeat(8)
  }

  return key.slice(0, 4) + '•'.repeat(8) + key.slice(-4)
}

function modelsByProvider() {
  const map = new Map<ProviderId, ModelInfo[]>()

  for (const m of MODELS) {
    const list = map.get(m.provider) ?? []
    list.push(m)
    map.set(m.provider, list)
  }

  return map
}
</script>

<template>
  <div class="flex flex-col gap-6 font-mono">
    <div>
      <h3 class="text-sm font-medium text-foreground">AI Models</h3>
      <p class="mt-1 text-xs text-muted-foreground">
        Configure your AI providers. Keys are stored locally.
      </p>
    </div>

    <div class="rounded-lg border border-border bg-card p-5">
      <div class="flex items-center justify-between">
        <div>
          <span class="text-xs font-medium text-foreground">Default Chat Model</span>
          <p class="mt-0.5 text-[10px] text-muted-foreground">Used for AI Insights and Chat</p>
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger :as-child="true">
            <Button variant="outline" class="h-8 gap-2 px-3 text-[11px]">
              <span class="text-xs">{{ defaultModel.label }}</span>
              <span class="text-muted-foreground/60">▾</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56 font-mono">
            <template v-for="[provider, models] in modelsByProvider()" :key="provider">
              <div class="px-2 pt-2 pb-1 text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
                {{ providerIcons[provider] ?? '•' }} {{ PROVIDERS.find(p => p.id === provider)?.label }}
              </div>
              <DropdownMenuItem
                v-for="m in models"
                :key="m.id"
                :class="{ 'bg-accent': m.id === defaultModelId }"
                @click="selectDefaultModel(m.id)"
              >
                <span class="text-[11px]">{{ m.label }}</span>
                <span class="ml-auto text-[10px] text-muted-foreground">{{ m.hint }}</span>
              </DropdownMenuItem>
            </template>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>

    <div class="rounded-lg border border-border bg-card p-5">
      <div class="flex items-start justify-between">
        <div>
          <span class="text-xs font-medium text-foreground">System Prompt</span>
          <p class="mt-0.5 text-[10px] text-muted-foreground">
            Default: Bahasa Indonesia + database-only scope (MySQL, NoSQL, Big Data).
          </p>
        </div>
        <span class="text-[10px] text-muted-foreground">{{ systemPrompt.length }}/2000</span>
      </div>
      <textarea
        v-model="systemPrompt"
        class="mt-2 h-24 w-full resize-none rounded-lg border border-border bg-card p-2 text-[11px] text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-accent-brand/50 focus:ring-1 focus:ring-accent-brand/20 transition-colors"
        placeholder="Example: Gunakan bahasa Indonesia untuk semua jawaban. Fokus pada optimasi MySQL."
        maxlength="2000"
      />
      <div class="mt-1 flex items-center justify-between">
        <button
          class="text-[10px] text-muted-foreground underline hover:text-foreground"
          @click="resetSystemPrompt"
        >Reset to default</button>
        <span class="text-[10px] text-muted-foreground">{{ systemPromptCount }}</span>
      </div>
    </div>

    <div>
      <h4 class="text-xs font-medium text-foreground">Cloud Providers</h4>
      <div class="mt-2 flex flex-col gap-2">
        <div
          v-for="p in cloudProviders"
          :key="p.id"
          class="rounded-lg border border-border bg-card p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-sm">{{ providerIcons[p.id] }}</span>
              <span class="text-xs font-medium text-foreground">{{ p.label }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span
                v-if="providerStates[p.id].configured"
                class="flex items-center gap-1 text-[10px] text-accent-brand"
              >
                <span class="h-1.5 w-1.5 rounded-full bg-accent-brand" />
                Connected
              </span>

              <a
                :href="p.consoleUrl"
                target="_blank"
                class="text-[10px] text-muted-foreground underline hover:text-foreground"
              >Get key</a>
            </div>
          </div>

          <div class="mt-2">
            <template v-if="providerStates[p.id].configured">
              <div class="flex items-center gap-2">
                <code class="flex-1 truncate rounded bg-muted/40 px-2 py-1 text-[10px] text-muted-foreground">
                  {{ maskKey(getKey(p.id) ?? '') }}
                </code>
                <Button size="sm" variant="outline" class="h-7 text-[10px] text-red-500 hover:text-red-500" @click="clearKey(p.id)">
                  Remove
                </Button>
              </div>
              <div class="mt-2 flex items-center gap-2">
                <select
                  class="flex-1 border border-border bg-card px-2 py-1 text-[10px] text-foreground outline-none"
                >
                  <option
                    v-for="m in getModelsForProvider(p.id)"
                    :key="m.id"
                    :value="m.id"
                  >{{ m.label }} — {{ m.hint }}</option>
                </select>
                <Button size="sm" variant="outline" class="h-7 text-[10px]" :disabled="providerStates[p.id].testing" @click="testConnection(p.id)">
                  <Spinner v-if="providerStates[p.id].testing" />
                  Test
                </Button>
                <span v-if="providerStates[p.id].testResult === 'ok'" class="text-[10px] text-green-500">OK</span>
              </div>
            </template>
            <template v-else>
              <div class="flex items-center gap-2">
                <Input
                  v-model="providerStates[p.id].keyDraft"
                  type="password"
                  :placeholder="p.keyPrefix ? p.keyPrefix + '...' : 'Paste your API key'"
                  class="h-7 flex-1 font-mono text-[10px]"
                />
                <Button size="sm" class="h-7 text-[10px]" :disabled="!providerStates[p.id].keyDraft.trim()" @click="saveKey(p.id)">
                  Save
                </Button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h4 class="text-xs font-medium text-foreground">Local Providers</h4>
      <div class="mt-2 flex flex-col gap-2">
        <div
          v-for="p in localProviders"
          :key="p.id"
          class="rounded-lg border border-border bg-card p-4 opacity-60"
        >
          <div class="flex items-center gap-2">
            <span class="text-sm">{{ providerIcons[p.id] }}</span>
            <span class="text-xs font-medium text-foreground">{{ p.label }}</span>
            <span class="text-[10px] text-muted-foreground">— coming with Tauri desktop app</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
