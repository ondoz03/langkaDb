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
  status: 'connected' | 'disconnected' | 'error'
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
    setActive,
    setLoading,
  }
})
