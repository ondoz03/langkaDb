<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ConnectionDialog from '@/components/ConnectionDialog.vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { useConnection } from '@/composables/useConnection'
import { useConnectionStore } from '@/stores/connection'
import type { Connection } from '@/stores/connection'

const store = useConnectionStore()
const { deleteConnection, connectConnection, disconnectConnection } = useConnection()

const loading = ref(true)
const connecting = ref<string | null>(null)

onMounted(async () => {
  await loadConnections()
})

async function loadConnections() {
  loading.value = true

  try {
    const res = await fetch('/api/connections')
    const json = await res.json()

    if (json.data) {
      store.setConnections(json.data)
      store.restoreActive()
    }
  } catch {
    // silent
  } finally {
    loading.value = false
  }
}

const dialogOpen = ref(false)
const editingConnection = ref<Connection | null>(null)
const confirmDelete = ref<Connection | null>(null)

function openAdd() {
  editingConnection.value = null
  dialogOpen.value = true
}

function openEdit(conn: Connection) {
  editingConnection.value = conn
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  editingConnection.value = null
  loadConnections()
}

async function handleConnect(conn: Connection) {
  connecting.value = conn.id
  await connectConnection(conn.id)
  connecting.value = null
  loadConnections()
}

function handleDisconnect() {
  disconnectConnection()
  loadConnections()
}

function handleDelete(conn: Connection) {
  confirmDelete.value = conn
}

async function confirmDeleteConnection() {
  if (!confirmDelete.value) {
    return
  }

  await deleteConnection(confirmDelete.value.id)
  confirmDelete.value = null
  loadConnections()
}

function cancelDelete() {
  confirmDelete.value = null
}

function isActive(conn: Connection) {
  return store.activeConnectionId === conn.id
}
</script>

<template>
  <Head title="Connections" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 font-mono">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium text-foreground">Database Connections</h2>
      <Button size="sm" @click="openAdd">Add Connection</Button>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12 text-muted-foreground">
      Loading connections...
    </div>

    <div v-else-if="store.connections.length === 0" class="flex flex-1 items-center justify-center">
      <div class="text-center">
        <p class="text-muted-foreground">No connections yet</p>
        <p class="mt-1 text-sm text-muted-foreground">Add a database connection to get started</p>
      </div>
    </div>

    <div v-else class="grid gap-3">
      <div
        v-for="conn in store.connections"
        :key="conn.id"
        class="flex items-center justify-between border border-border bg-card p-4"
        :class="{ 'border-primary': isActive(conn) }"
      >
        <div class="flex items-center gap-3">
          <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-brand/10 font-mono text-xs font-medium text-accent-brand">
            {{ conn.name.charAt(0).toUpperCase() }}
          </div>
          <div>
            <p class="text-sm font-medium text-foreground">{{ conn.name }}</p>
            <p class="text-xs text-muted-foreground">{{ conn.driver }} — {{ conn.host }}:{{ conn.port }}/{{ conn.database }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span
            v-if="isActive(conn)"
            class="px-2 py-0.5 text-xs font-medium bg-green-500/10 text-green-600 dark:text-green-400"
          >connected</span>
          <span
            v-else
            class="px-2 py-0.5 text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400"
          >disconnected</span>

          <template v-if="isActive(conn)">
            <Button variant="outline" size="sm" @click="handleDisconnect">
              Disconnect
            </Button>
          </template>
          <template v-else>
            <Button
              variant="outline"
              size="sm"
              :disabled="connecting !== null"
              @click="handleConnect(conn)"
            >
              <Spinner v-if="connecting === conn.id" />
              Connect
            </Button>
          </template>

          <Button variant="outline" size="sm" @click="openEdit(conn)">
            Edit
          </Button>
          <Button variant="outline" size="sm" @click="handleDelete(conn)">
            Delete
          </Button>
        </div>
      </div>
    </div>
  </div>

  <ConnectionDialog
    :open="dialogOpen"
    :connection="editingConnection"
    @close="closeDialog"
  />

  <ConfirmDialog
    :open="confirmDelete !== null"
    title="Delete Connection"
    :description="`Are you sure you want to delete &quot;${confirmDelete?.name}&quot;?`"
    confirm-text="Delete"
    @confirm="confirmDeleteConnection"
    @cancel="cancelDelete"
  />
</template>
