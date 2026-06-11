<script setup lang="ts">
import { ref } from 'vue'
import { Sparkles, X, Upload } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { parseSql } from '@/lib/parsers/SqlParser'

interface GeneratedSchema {
  sql: string
  tables: string[]
  prompt: string
  fallback?: boolean
}

defineProps<{ open: boolean }>()
const emit = defineEmits<{
  close: []
  imported: [result: { sql: string; tables: any[]; relations: any[]; summary: any }]
}>()

const prompt = ref('')
const loading = ref(false)
const generated = ref<GeneratedSchema | null>(null)

const examples = [
  'Buatkan skema database untuk sistem toko online dengan user, produk, kategori, dan pesanan',
  'Database untuk sistem manajemen proyek dengan tim, task, dan milestone',
  'Sistem perpustakaan dengan anggota, buku, dan peminjaman',
  'Platform e-learning dengan kursus, materi, dan siswa',
]

function pickExample(ex: string) {
  prompt.value = ex
  generated.value = null
}

async function handleGenerate() {
  if (!prompt.value.trim()) return
  loading.value = true
  generated.value = null

  try {
    const res = await fetch('/api/ai/generate-schema', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ prompt: prompt.value }),
    })

    if (!res.ok) {
      const err = await res.json()
      throw new Error(err.message || 'Failed to generate schema')
    }

    const json = await res.json()
    generated.value = json.data

    if (json.data.fallback) {
      toast.info('AI unavailable — used template fallback')
    } else {
      toast.success('Schema generated successfully')
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
  close()
}

function close() {
  prompt.value = ''
  generated.value = null
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

        <!-- Generated SQL preview -->
        <div v-if="generated" class="mt-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-foreground">Generated Schema</span>
            <span class="text-[10px] text-muted-foreground">{{ generated.tables.length }} tables</span>
          </div>
          <pre class="mt-2 max-h-48 overflow-auto rounded-md border border-border bg-black/30 p-3 font-mono text-[10px] text-foreground/90 leading-relaxed">{{ generated.sql }}</pre>
          <div v-if="generated.fallback" class="mt-1 flex items-center gap-1 text-[10px] text-amber-500">
            <span>Template fallback — connect an AI provider in Settings for better results</span>
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
          {{ loading ? 'Generating...' : 'Generate Schema' }}
        </button>
        <button
          v-else
          class="flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-xs font-medium text-background hover:opacity-90"
          @click="handleImport"
        >
          <Upload class="h-3.5 w-3.5" />
          Add to Graph
        </button>
      </div>
    </div>
  </div>
</template>
