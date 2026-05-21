<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'

const sql = ref('SELECT * FROM users\nWHERE email LIKE \'%@example.com\'\nORDER BY created_at DESC\nLIMIT 100;')
const running = ref(false)
const result = ref<{ cols: string[]; rows: string[][] } | null>(null)

function runQuery() {
  running.value = true
  result.value = null

  setTimeout(() => {
    result.value = {
      cols: ['id', 'name', 'email', 'created_at'],
      rows: [
        ['1', 'John Doe', 'john@example.com', '2024-01-15 10:30:00'],
        ['2', 'Jane Smith', 'jane@example.com', '2024-01-16 14:20:00'],
        ['3', 'Bob Johnson', 'bob@example.com', '2024-01-17 09:15:00'],
      ],
    }
    running.value = false
  }, 800)
}
</script>

<template>
  <Head title="Query Analyzer" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 font-mono">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium text-foreground">Query Analyzer</h2>
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <span class="text-xs text-muted-foreground">SQL Query</span>
        <div class="flex items-center gap-2">
          <Button size="sm" variant="outline" @click="sql = ''">Clear</Button>
          <Button size="sm" :disabled="running" @click="runQuery">
            <Spinner v-if="running" />
            Run
          </Button>
        </div>
      </div>
      <textarea
        v-model="sql"
        class="h-32 resize-none border border-border bg-card p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground/50"
        placeholder="Enter SQL query..."
        spellcheck="false"
      />
    </div>

    <div v-if="running" class="flex items-center justify-center py-12 text-xs text-muted-foreground">
      Executing query...
    </div>

    <div v-else-if="result" class="flex flex-col gap-4">
      <div class="border border-border bg-card">
        <div class="border-b border-border bg-muted/30 px-3 py-1.5 text-xs font-medium text-foreground">
          Results — {{ result.rows.length }} rows
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-border bg-muted/20">
                <th
                  v-for="col in result.cols"
                  :key="col"
                  class="whitespace-nowrap px-3 py-2 text-left font-medium text-foreground"
                >{{ col }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, i) in result.rows"
                :key="i"
                class="border-b border-border last:border-0 hover:bg-accent/20"
              >
                <td
                  v-for="(cell, j) in row"
                  :key="j"
                  class="whitespace-nowrap px-3 py-2 text-muted-foreground"
                >{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div class="border border-border bg-card p-4">
          <span class="text-xs font-medium text-foreground">Execution Plan</span>
          <div class="mt-2 divide-y divide-border text-xs">
            <div class="flex items-center gap-2 py-1.5">
              <span class="h-2 w-2 rounded-full bg-amber-500" />
              <span class="text-muted-foreground">Full table scan on <code class="text-foreground">users</code></span>
            </div>
            <div class="flex items-center gap-2 py-1.5">
              <span class="h-2 w-2 rounded-full bg-green-500" />
              <span class="text-muted-foreground">Index lookup on <code class="text-foreground">users.email</code></span>
            </div>
            <div class="flex items-center gap-2 py-1.5">
              <span class="h-2 w-2 rounded-full bg-blue-500" />
              <span class="text-muted-foreground">Sort: <code class="text-foreground">created_at DESC</code></span>
            </div>
          </div>
        </div>
        <div class="border border-border bg-card p-4">
          <span class="text-xs font-medium text-foreground">Stats</span>
          <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
            <span class="text-muted-foreground">Duration</span>
            <span class="text-right text-foreground">0.042s</span>
            <span class="text-muted-foreground">Rows examined</span>
            <span class="text-right text-foreground">1,234</span>
            <span class="text-muted-foreground">Rows returned</span>
            <span class="text-right text-foreground">3</span>
            <span class="text-muted-foreground">Index used</span>
            <span class="text-right text-green-500">users_email_index</span>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="flex flex-1 items-center justify-center">
      <div class="text-center">
        <p class="text-muted-foreground">Write a query and click Run</p>
        <p class="mt-1 text-xs text-muted-foreground">Results and analysis will appear here</p>
      </div>
    </div>
  </div>
</template>
