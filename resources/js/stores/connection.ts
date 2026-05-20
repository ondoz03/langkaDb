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
  }

  function setLoading(val: boolean) {
    loading.value = val
  }

  return {
    connections,
    activeConnectionId,
    activeConnection,
    loading,
    setConnections,
    addConnection,
    updateConnection,
    removeConnection,
    setActive,
    setLoading,
  }
})
