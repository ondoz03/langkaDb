<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref, computed } from 'vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ConnectionDialog from '@/components/ConnectionDialog.vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { useConnection } from '@/composables/useConnection'
import { useConnectionStore } from '@/stores/connection'
import type { Connection } from '@/stores/connection'
import { Database, Plus, Plug, PlugZap, Pencil, Trash2, Wifi, WifiOff } from 'lucide-vue-next'

const store = useConnectionStore()
const { deleteConnection, connectConnection, disconnectConnection } = useConnection()

const loading = ref(true)
const error = ref('')
const connecting = ref<string | null>(null)

onMounted(async () => { await loadConnections() })

async function loadConnections() {
  loading.value = true; error.value = ''
  try {
    const res = await fetch('/api/connections')
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const json = await res.json()
    if (json.data) { store.setConnections(json.data); store.restoreActive() }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to load connections'
    console.warn('[Connections] Fetch failed:', e)
  } finally { loading.value = false }
}

const dialogOpen = ref(false)
const editingConnection = ref<Connection | null>(null)
const confirmDelete = ref<Connection | null>(null)

function openAdd() { editingConnection.value = null; dialogOpen.value = true }
function openEdit(conn: Connection) { editingConnection.value = conn; dialogOpen.value = true }
function closeDialog() { dialogOpen.value = false; editingConnection.value = null; loadConnections() }

async function handleConnect(conn: Connection) {
  connecting.value = conn.id; await connectConnection(conn.id)
  connecting.value = null; loadConnections()
}
function handleDisconnect() { disconnectConnection(); loadConnections() }
function handleDelete(conn: Connection) { confirmDelete.value = conn }
async function confirmDeleteConnection() {
  if (!confirmDelete.value) return
  await deleteConnection(confirmDelete.value.id)
  confirmDelete.value = null; loadConnections()
}
function cancelDelete() { confirmDelete.value = null }
function isActive(conn: Connection) { return store.activeConnectionId === conn.id }

const connectionCount = computed(() => store.connections.length)
const activeCount = computed(() => store.connections.filter(c => c.status === 'connected').length)
</script>

<template>
  <Head title="Connections" />

  <div class="flex h-full flex-1 flex-col overflow-x-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div>
        <h1 class="text-base font-semibold text-foreground">Connections</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">
          {{ connectionCount }} total
          <template v-if="activeCount > 0"> · <span class="text-green-600 dark:text-green-400">{{ activeCount }} active</span></template>
        </p>
      </div>
      <Button size="sm" @click="openAdd">
        <Plus class="mr-1.5 h-4 w-4" />
        Add Connection
      </Button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground">
      <Spinner /> Loading connections...
    </div>

    <!-- Error -->
    <div v-else-if="error" class="mx-6 mt-6 flex items-center gap-3 rounded-lg border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-500">
      <span class="flex-1">{{ error }}</span>
      <button class="underline hover:text-red-400" @click="loadConnections">Retry</button>
    </div>

    <!-- Empty -->
    <div v-else-if="store.connections.length === 0" class="flex flex-1 items-center justify-center p-6">
      <div class="flex flex-col items-center gap-4 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-card">
          <Database class="h-6 w-6 text-muted-foreground" />
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">No connections yet</p>
          <p class="mt-1 text-sm text-muted-foreground">Add a database connection to get started.</p>
        </div>
        <Button variant="accent" @click="openAdd">
          <Plus class="mr-1.5 h-4 w-4" />
          Add Connection
        </Button>
      </div>
    </div>

    <!-- List -->
    <div v-else class="grid gap-2 p-6">
      <div
        v-for="conn in store.connections"
        :key="conn.id"
        class="flex items-center justify-between rounded-lg border p-4 transition-colors"
        :class="isActive(conn)
          ? 'border-border bg-card'
          : 'border-border bg-card hover:bg-accent/30'"
      >
        <div class="flex items-center gap-3 min-w-0">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded border border-border bg-muted/50 text-xs font-medium text-muted-foreground">
            {{ conn.name.charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2.5">
              <p class="truncate text-sm font-medium text-foreground">{{ conn.name }}</p>
              <span
                class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium"
                :class="isActive(conn)
                  ? 'bg-green-500/10 text-green-600 dark:text-green-400'
                  : 'bg-muted/50 text-muted-foreground'"
              >
                <component :is="isActive(conn) ? Wifi : WifiOff" class="h-3 w-3" />
                {{ isActive(conn) ? 'connected' : 'disconnected' }}
              </span>
            </div>
            <p class="mt-0.5 text-xs text-muted-foreground font-mono">{{ conn.driver }} · {{ conn.host }}:{{ conn.port }} · {{ conn.database }}</p>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
          <template v-if="isActive(conn)">
            <Button variant="outline" size="sm" class="h-8 text-xs" @click="handleDisconnect">
              <PlugZap class="mr-1 h-3.5 w-3.5" />Disconnect
            </Button>
          </template>
          <template v-else>
            <Button variant="outline" size="sm" class="h-8 text-xs" :disabled="connecting !== null" @click="handleConnect(conn)">
              <Spinner v-if="connecting === conn.id" class="mr-1 h-3 w-3" />
              <Plug v-else class="mr-1 h-3.5 w-3.5" />Connect
            </Button>
          </template>

          <button class="flex h-8 w-8 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="openEdit(conn)">
            <Pencil class="h-3.5 w-3.5" />
          </button>
          <button class="flex h-8 w-8 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-red-500/10 hover:text-red-500" @click="handleDelete(conn)">
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>

  <ConnectionDialog :open="dialogOpen" :connection="editingConnection" @close="closeDialog" />
  <ConfirmDialog
    :open="confirmDelete !== null"
    title="Delete Connection"
    :description="`Are you sure you want to delete &quot;${confirmDelete?.name}&quot;?`"
    confirm-text="Delete"
    @confirm="confirmDeleteConnection"
    @cancel="cancelDelete"
  />
</template>
