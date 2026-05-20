import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface TableInfo {
  name: string
  columns: number
  rows: number
  size_mb: number
}

export const useSchemaStore = defineStore('schema', () => {
  const tables = ref<TableInfo[]>([])
  const loading = ref(false)
  const lastFetched = ref<string | null>(null)

  function setTables(list: TableInfo[]) {
    tables.value = list
  }

  function setLoading(val: boolean) {
    loading.value = val
  }

  return {
    tables,
    loading,
    lastFetched,
    setTables,
    setLoading,
  }
})
