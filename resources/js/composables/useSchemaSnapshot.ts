import { ref } from 'vue'
import { toast } from 'vue-sonner'

export interface Snapshot {
  id: number
  label: string
  created_at: string
}

export interface TableDiff {
  name: string
  columns: any[]
  indexes?: any[]
  row_count: number
  size_mb: number
}

export interface DiffResult {
  new_tables: TableDiff[]
  deleted_tables: TableDiff[]
  modified_tables: {
    table: TableDiff
    column_changes: any[]
    index_changes: any[]
  }[]
  summary: {
    additions: number
    deletions: number
    modifications: number
  }
}

export function useSchemaSnapshot() {
  const snapshots = ref<Snapshot[]>([])
  const loading = ref(false)
  const diffResult = ref<DiffResult | null>(null)

  async function fetchSnapshots(connectionId: string) {
    loading.value = true
    try {
      const res = await fetch(`/api/connections/${connectionId}/snapshots`)
      if (!res.ok) throw new Error('Failed to fetch snapshots')
      const json = await res.json()
      snapshots.value = json.data
    } catch (e) {
      snapshots.value = []
    } finally {
      loading.value = false
    }
  }

  async function createSnapshot(connectionId: string, label: string): Promise<Snapshot | null> {
    loading.value = true
    try {
      const res = await fetch(`/api/connections/${connectionId}/snapshots`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ label }),
      })
      if (!res.ok) throw new Error('Failed to create snapshot')
      const json = await res.json()
      const snapshot: Snapshot = json.data
      snapshots.value.unshift(snapshot)
      toast.success(`Snapshot "${label}" created`)
      return snapshot
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to create snapshot')
      return null
    } finally {
      loading.value = false
    }
  }

  async function deleteSnapshot(id: number) {
    loading.value = true
    try {
      const res = await fetch(`/api/snapshots/${id}`, { method: 'DELETE' })
      if (!res.ok) throw new Error('Failed to delete snapshot')
      snapshots.value = snapshots.value.filter((s) => s.id !== id)
      toast.success('Snapshot deleted')
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to delete snapshot')
    } finally {
      loading.value = false
    }
  }

  async function computeDiff(snapshotId: number, compareWithId: number) {
    loading.value = true
    try {
      const res = await fetch(`/api/snapshots/${snapshotId}/diff?compare_with=${compareWithId}`)
      if (!res.ok) throw new Error('Failed to compute diff')
      const json = await res.json()
      diffResult.value = json.data
      return json.data as DiffResult
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Failed to compute diff')
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    snapshots,
    loading,
    diffResult,
    fetchSnapshots,
    createSnapshot,
    deleteSnapshot,
    computeDiff,
  }
}
