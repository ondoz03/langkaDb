<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ConnectionDialog from '@/components/ConnectionDialog.vue'
import { Button } from '@/components/ui/button'
import { useConnection } from '@/composables/useConnection'
import type { Connection } from '@/stores/connection'

const { testConnection, deleteConnection, setActive, activeConnectionId } = useConnection()

const list = ref<Connection[]>([])
const loading = ref(true)

onMounted(async () => {
  await loadConnections()
})

async function loadConnections() {
  loading.value = true

  try {
    const res = await fetch('/api/connections')
    const json = await res.json()

    if (json.data) {
      list.value = json.data
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

async function handleTest(conn: Connection) {
  await testConnection(conn.id)
}

function statusBadge(status: string) {
  switch (status) {
    case 'connected':
      return 'bg-green-500/10 text-green-600 dark:text-green-400'
    case 'error':
      return 'bg-red-500/10 text-red-600 dark:text-red-400'
    default:
      return 'bg-gray-500/10 text-gray-600 dark:text-gray-400'
  }
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

    <div v-else-if="list.length === 0" class="flex flex-1 items-center justify-center">
      <div class="text-center">
        <p class="text-muted-foreground">No connections yet</p>
        <p class="mt-1 text-sm text-muted-foreground">Add a database connection to get started</p>
      </div>
    </div>

    <div v-else class="grid gap-3">
      <div
        v-for="conn in list"
        :key="conn.id"
        class="flex cursor-pointer items-center justify-between border border-border bg-card p-4 transition-colors hover:bg-accent/50"
        :class="{ 'border-primary': activeConnectionId === conn.id }"
        @click="setActive(conn.id)"
      >
        <div class="flex items-center gap-3">
          <div class="flex h-8 w-8 items-center justify-center bg-primary/10 font-mono text-xs font-medium text-primary">
            {{ conn.name.charAt(0).toUpperCase() }}
          </div>
          <div>
            <p class="text-sm font-medium text-foreground">{{ conn.name }}</p>
            <p class="text-xs text-muted-foreground">{{ conn.driver }} — {{ conn.host }}:{{ conn.port }}/{{ conn.database }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 text-xs font-medium" :class="statusBadge(conn.status)">
            {{ conn.status }}
          </span>
          <Button variant="outline" size="sm" @click.stop="openEdit(conn)">
            Edit
          </Button>
          <Button variant="outline" size="sm" @click.stop="handleTest(conn)">
            Test
          </Button>
          <Button variant="outline" size="sm" @click.stop="handleDelete(conn)">
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
