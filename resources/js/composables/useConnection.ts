import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { useConnectionStore } from '@/stores/connection'
import type { Connection } from '@/stores/connection'

const fetchOptions = {
  credentials: 'include' as RequestCredentials,
  headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
}

export function useConnection() {
  const store = useConnectionStore()
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchConnections() {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections?_=${Date.now()}`, fetchOptions)

      if (!res.ok) {
        throw new Error(res.status === 302 ? 'Session expired, please refresh' : 'Failed to fetch connections')
      }

      const json = await res.json()
      store.setConnections(json.data)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch connections'
    } finally {
      loading.value = false
    }
  }

  async function createConnection(data: Record<string, unknown>) {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/api/connections', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data),
      })

      if (!res.ok) {
        const text = await res.text()
        let msg = 'Failed to create connection'

        try {
          msg = JSON.parse(text).message ?? msg
        } catch {
          msg = text || msg
        }

        throw new Error(msg)
      }

      const json = await res.json()
      store.addConnection(json.data)
      toast.success('Connection created')

      return json.data as Connection
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Failed to create connection'
      error.value = msg
      toast.error(msg)

      return null
    } finally {
      loading.value = false
    }
  }

  async function updateConnection(id: string, data: Record<string, unknown>) {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections/${id}`, {
        method: 'PUT',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data),
      })

      if (!res.ok) {
        const text = await res.text()
        let msg = 'Failed to update connection'

        try {
          msg = JSON.parse(text).message ?? msg
        } catch {
          msg = text || msg
        }

        throw new Error(msg)
      }

      const json = await res.json()
      store.updateConnection(json.data)
      toast.success('Connection updated')

      return json.data as Connection
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Failed to update connection'
      error.value = msg
      toast.error(msg)

      return null
    } finally {
      loading.value = false
    }
  }

  async function deleteConnection(id: string) {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections/${id}`, {
        method: 'DELETE',
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      })

      if (!res.ok) {
        const text = await res.text()
        let msg = 'Failed to delete connection'

        try {
          msg = JSON.parse(text).message ?? msg
        } catch {
          msg = text || msg
        }

        throw new Error(msg)
      }

      store.removeConnection(id)
      toast.success('Connection deleted')

      return true
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Failed to delete connection'
      error.value = msg
      toast.error(msg)

      return false
    } finally {
      loading.value = false
    }
  }

  async function testConnection(id: string) {
    error.value = null

    try {
      const res = await fetch(`/api/connections/${id}/test`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      })

      const result = await res.json()

      if (result.success) {
        toast.success('Connection test successful')
      } else {
        toast.error(result.message ?? 'Connection test failed')
      }

      return result
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Test failed'
      error.value = msg
      toast.error(msg)

      return { success: false, message: error.value }
    }
  }

  async function connectConnection(id: string) {
    loading.value = true
    error.value = null

    try {
      const result = await testConnection(id)

      if (result.success) {
        store.setActive(id)
        toast.success('Connected')
      } else {
        toast.error(result.message ?? 'Connection failed')
      }

      return result
    } finally {
      loading.value = false
    }
  }

  function disconnectConnection() {
    store.setActive(null)
    toast.success('Disconnected')
  }

  return {
    connections: store.connections,
    activeConnection: store.activeConnection,
    activeConnectionId: store.activeConnectionId,
    hasActiveConnection: store.hasActiveConnection,
    loading,
    error,
    setActive: store.setActive,
    fetchConnections,
    createConnection,
    updateConnection,
    deleteConnection,
    testConnection,
    connectConnection,
    disconnectConnection,
  }
}
