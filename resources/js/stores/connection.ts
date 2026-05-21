import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export interface Connection {
  id: string
  name: string
  driver: 'mysql' | 'mariadb'
  host: string
  port: number
  database: string
  username: string
  ssl_enabled: boolean
  ssh_enabled: boolean
  ssh_host: string | null
  ssh_port: number | null
  ssh_user: string | null
  status: 'connected' | 'disconnected' | 'error'
  created_at?: string
  updated_at?: string
}

export const useConnectionStore = defineStore('connection', () => {
  const connections = ref<Connection[]>([])
  const activeConnectionId = ref<string | null>(null)
  const loading = ref(false)

  const activeConnection = computed(() =>
    connections.value.find((c) => c.id === activeConnectionId.value) ?? null,
  )

  const hasActiveConnection = computed(() => activeConnection.value !== null && activeConnection.value.status === 'connected')

  function setConnections(list: Connection[]) {
    connections.value = list
  }

  function addConnection(conn: Connection) {
    connections.value.unshift(conn)
  }

  function updateConnection(conn: Connection) {
    const idx = connections.value.findIndex((c) => c.id === conn.id)

    if (idx !== -1) {
      connections.value[idx] = conn
    }
  }

  function removeConnection(id: string) {
    connections.value = connections.value.filter((c) => c.id !== id)

    if (activeConnectionId.value === id) {
      activeConnectionId.value = null
    }
  }

  function setActive(id: string | null) {
    activeConnectionId.value = id

    if (id) {
      localStorage.setItem('active_connection_id', id)
    } else {
      localStorage.removeItem('active_connection_id')
    }
  }

  function restoreActive() {
    const saved = localStorage.getItem('active_connection_id')

    if (saved && connections.value.some((c) => c.id === saved)) {
      activeConnectionId.value = saved
    }
  }

  function setLoading(val: boolean) {
    loading.value = val
  }

  return {
    connections,
    activeConnectionId,
    activeConnection,
    hasActiveConnection,
    loading,
    setConnections,
    addConnection,
    updateConnection,
    removeConnection,
    setActive,
    restoreActive,
    setLoading,
  }
})
