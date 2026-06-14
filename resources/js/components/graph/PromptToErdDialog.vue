<script setup lang="ts">
import { ref, computed } from 'vue'
import { Sparkles, X, Upload, Database, CheckCircle2, Loader2, Brain, ChevronDown, ToggleLeft, ToggleRight } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { parseSql } from '@/lib/parsers/SqlParser'
import { getKey } from '@/modules/ai/apiKeys'
import { MODELS, PROVIDERS } from '@/modules/ai/config'
import type { ProviderId } from '@/modules/ai/config'

interface GeneratedSchema {
  sql: string
  tables: string[]
  prompt: string
  fallback?: boolean
  progress?: SchemaProgress
}

interface SchemaProgress {
  detected_tables: string[]
  completed_tables: string[]
  current_table?: string
  message: string
}

const props = defineProps<{
  open: boolean
  existingTables?: string[]
}>()
const emit = defineEmits<{
  close: []
  imported: [result: { sql: string; tables: any[]; relations: any[]; summary: any }]
  exportToDb: [result: { sql: string; tables: any[]; relations: any[] }]
}>()

const prompt = ref('')
const loading = ref(false)
const generated = ref<GeneratedSchema | null>(null)
const selectedModelId = ref(localStorage.getItem('aetherdb_default_model') ?? 'deepseek-v4-flash')
const exportToDb = ref(false)

// Progress simulation
const progress = ref<{
  detectedTables: string[]
  currentTable: string | null
  completed: string[]
  message: string
}>({
  detectedTables: [],
  currentTable: null,
  completed: [],
  message: '',
})

const examples = [
  'Buatkan skema database untuk sistem toko online dengan user, produk, kategori, dan pesanan',
  'Database untuk sistem manajemen proyek dengan tim, task, dan milestone',
  'Sistem perpustakaan dengan anggota, buku, dan peminjaman',
  'Platform e-learning dengan kursus, materi, dan siswa',
]

const availableModels = computed(() => MODELS)

function getProviderLabel(providerId: string): string {
  const p = PROVIDERS.find(x => x.id === providerId)
  return p?.label ?? providerId
}

const currentModel = computed(() => {
  return MODELS.find(m => m.id === selectedModelId.value)
})

function pickExample(ex: string) {
  prompt.value = ex
  generated.value = null
}

async function handleGenerate() {
  if (!prompt.value.trim()) return
  loading.value = true
  generated.value = null
  progress.value = { detectedTables: [], currentTable: null, completed: [], message: 'Initializing AI engine...' }

  try {
    const model = MODELS.find(m => m.id === selectedModelId.value)
    if (!model) {
      toast.error('Please select an AI model first')
      loading.value = false
      return
    }
    const provider = model.provider
    const apiKey = getKey(provider as ProviderId)

    if (!apiKey) {
      toast.error(`No API key configured for ${getProviderLabel(provider)}. Go to Settings → AI Models.`)
      loading.value = false
      return
    }

    progress.value.message = `Connected to ${getProviderLabel(provider)}...`

    const body: Record<string, any> = {
      prompt: prompt.value,
      provider,
      model_id: model.id,
    }
    if (apiKey) body.api_key = apiKey

    const res = await fetch('/api/ai/generate-schema', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body),
    })

    if (!res.ok) {
      const err = await res.json()
      throw new Error(err.message || 'Failed to generate schema')
    }

    const json = await res.json()
    const sql = json.data.sql

    // Parse to detect tables
    const parsed = parseSql(sql)

    progress.value.detectedTables = parsed.tables.map(t => t.name)
    progress.value.message = 'Analyzing tables...'

    // Step through each detected table
    for (let i = 0; i < parsed.tables.length; i++) {
      const t = parsed.tables[i]
      progress.value.currentTable = t.name
      progress.value.message = `Processing table: ${t.name} (${i + 1}/${parsed.tables.length})`
      await new Promise(r => setTimeout(r, 150))
      progress.value.completed.push(t.name)
    }

    progress.value.message = 'Schema generated successfully'
    progress.value.currentTable = null

    json.data.progress = {
      detected_tables: parsed.tables.map(t => t.name),
      completed_tables: parsed.tables.map(t => t.name),
      message: 'Schema generated successfully',
    }
    generated.value = json.data

    if (json.data.fallback) {
      toast.info('AI unavailable — used template fallback. Check AI Settings.')
    } else {
      toast.success(`Schema generated: ${parsed.tables.length} tables`)
    }
  } catch (e) {
    toast.error(e instanceof Error ? e.message : 'Generation failed')
  } finally {
    loading.value = false
  }
}

function handleImport() {
  if (!generated.value?.sql) return

  const parsed = parseSql(generated.value.sql)

  if (parsed.tables.length === 0) {
    toast.error('Could not parse generated SQL')
    return
  }

  if (exportToDb.value) {
    emit('exportToDb', {
      sql: generated.value.sql,
      tables: parsed.tables,
      relations: parsed.relations,
    })
    toast.success('Schema sent to database engine')
  } else {
    emit('imported', {
      sql: generated.value.sql,
      tables: parsed.tables,
      relations: parsed.relations,
      summary: {
        total_tables: parsed.tables.length,
        total_relations: parsed.relations.length,
        total_indexes: parsed.tables.reduce((s, t) => s + t.indexes.length, 0),
      },
    })
    toast.success(`Added ${parsed.tables.length} tables to graph`)
  }

  close()
}

function selectModel(id: string) {
  selectedModelId.value = id
  localStorage.setItem('aetherdb_default_model', id)
}

function close() {
  prompt.value = ''
  generated.value = null
  loading.value = false
  exportToDb.value = false
  progress.value = { detectedTables: [], currentTable: null, completed: [], message: '' }
  emit('close')
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="close"
  >
    <div class="w-full max-w-2xl rounded-lg border border-border bg-card shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-border px-5 py-4">
        <div class="flex items-center gap-2">
          <Sparkles class="h-4 w-4 text-accent-brand" />
          <div>
            <h2 class="text-sm font-semibold text-foreground">Prompt to ERD</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">Describe your database in natural language, AI will generate the schema</p>
          </div>
        </div>
        <button class="rounded-md p-1 text-muted-foreground hover:text-foreground" @click="close">
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-5">
        <!-- AI Model Selection -->
        <div class="mb-4">
          <div class="flex items-center justify-between">
            <label class="text-xs font-medium text-foreground">AI Model</label>
          </div>
          <div class="mt-1 flex items-center gap-2">
            <div class="relative flex-1">
              <Brain class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
              <select
                v-model="selectedModelId"
                class="w-full appearance-none rounded-md border border-border bg-card py-2 pl-8 pr-8 text-xs text-foreground outline-none focus:border-ring"
                @change="selectModel(selectedModelId)"
              >
                <optgroup
                  v-for="providerId in [...new Set(availableModels.map(m => m.provider))]"
                  :key="providerId"
                  :label="getProviderLabel(providerId)"
                >
                  <option
                    v-for="m in availableModels.filter(x => x.provider === providerId)"
                    :key="m.id"
                    :value="m.id"
                  >
                    {{ m.label }} — {{ m.hint }}
                  </option>
                </optgroup>
              </select>
              <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
            </div>
            <a
              href="/settings/ai"
              class="shrink-0 rounded-md border border-border bg-card px-2.5 py-2 text-[10px] text-muted-foreground hover:text-foreground"
            >
              Settings
            </a>
          </div>
          <p v-if="currentModel" class="mt-1 text-[10px] text-muted-foreground">
            Using {{ getProviderLabel(currentModel.provider) }} — {{ currentModel.label }}
            <template v-if="!getKey(currentModel.provider as ProviderId)">
              <span class="text-amber-500">(no API key — will use fallback)</span>
            </template>
          </p>
        </div>

        <!-- Examples -->
        <div class="mb-4 flex flex-wrap gap-1.5">
          <button
            v-for="ex in examples"
            :key="ex"
            class="rounded-md border border-border/50 bg-black/10 px-2 py-1 text-[10px] text-muted-foreground transition-colors hover:border-accent-brand/30 hover:text-accent-brand"
            @click="pickExample(ex)"
          >
            {{ ex.length > 50 ? ex.slice(0, 50) + '...' : ex }}
          </button>
        </div>

        <!-- Input -->
        <div class="relative">
          <textarea
            v-model="prompt"
            class="h-28 w-full resize-none rounded-md border border-border bg-black/20 p-3 font-sans text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring"
            placeholder="Describe your database in natural language...&#10;Example: Buat skema database untuk sistem manajemen proyek dengan user, proyek, task, dan timeline"
          />
        </div>

        <!-- Loading Progress -->
        <div v-if="loading" class="mt-4 rounded-md border border-border/50 bg-black/10 p-3">
          <div class="flex items-center gap-2">
            <Loader2 class="h-4 w-4 animate-spin text-accent-brand" />
            <span class="text-xs text-foreground">{{ progress.message }}</span>
          </div>

          <!-- Detected tables progress -->
          <div v-if="progress.detectedTables.length > 0" class="mt-3 space-y-1">
            <div
              v-for="t in progress.detectedTables"
              :key="t"
              class="flex items-center gap-2 rounded px-2 py-1 text-[11px]"
              :class="{
                'text-green-500': progress.completed.includes(t),
                'text-accent-brand animate-pulse': progress.currentTable === t,
                'text-muted-foreground/50': !progress.completed.includes(t) && progress.currentTable !== t,
              }"
            >
              <CheckCircle2 v-if="progress.completed.includes(t)" class="h-3 w-3 shrink-0" />
              <Loader2 v-else-if="progress.currentTable === t" class="h-3 w-3 shrink-0 animate-spin" />
              <div v-else class="h-3 w-3 shrink-0 rounded-full border border-muted-foreground/30" />
              <span class="font-mono">{{ t }}</span>
            </div>
          </div>

          <div class="mt-2">
            <div class="h-1 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full bg-accent-brand transition-all duration-500"
                :style="{ width: progress.detectedTables.length > 0 ? (progress.completed.length / progress.detectedTables.length * 100) + '%' : '10%' }"
              />
            </div>
          </div>
        </div>

        <!-- Generated SQL preview -->
        <div v-if="generated && !loading" class="mt-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-foreground">Generated Schema</span>
            <span class="text-[10px] text-muted-foreground">{{ generated.tables.length }} tables</span>
          </div>
          <pre class="mt-2 max-h-48 overflow-auto rounded-md border border-border bg-black/30 p-3 font-mono text-[10px] text-foreground/90 leading-relaxed">{{ generated.sql }}</pre>

          <!-- Export mode toggle -->
          <div class="mt-3 flex items-center gap-3 rounded-md border border-border/50 bg-black/10 px-3 py-2">
            <label class="flex cursor-pointer items-center gap-2 text-xs transition-colors select-none"
              :class="exportToDb ? 'text-green-500' : 'text-muted-foreground'"
            >
              <input
                type="checkbox"
                :checked="exportToDb"
                class="h-4 w-4 rounded border-border bg-card text-green-500 accent-green-500"
                @change="exportToDb = ($event.target as HTMLInputElement).checked"
              />
              <Database class="h-3.5 w-3.5" />
              <span>Export directly to database</span>
            </label>
          </div>

          <div v-if="generated.fallback" class="mt-1 flex items-center gap-1 text-[10px] text-amber-500">
            <span>Template fallback — configure AI provider in <strong>Settings → AI Models</strong> for better results</span>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-3">
        <button
          class="rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground"
          @click="close"
        >
          Cancel
        </button>
        <button
          v-if="!generated"
          class="flex items-center gap-1.5 rounded-md bg-accent-brand px-3 py-1.5 text-xs font-medium text-accent-brand-foreground hover:opacity-90 disabled:opacity-50"
          :disabled="!prompt.trim() || loading"
          @click="handleGenerate"
        >
          <Sparkles class="h-3.5 w-3.5" />
          {{ loading ? 'Generating...' : 'Generate' }}
        </button>
        <button
          v-else
          class="flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-xs font-medium text-background hover:opacity-90"
          @click="handleImport"
        >
          <component :is="exportToDb ? Database : Upload" class="h-3.5 w-3.5" />
          {{ exportToDb ? 'Apply to Database' : 'Add to Graph' }}
        </button>
      </div>
    </div>
  </div>
</template>
