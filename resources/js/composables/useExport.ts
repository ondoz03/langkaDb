import { ref } from 'vue'
import { toast } from 'vue-sonner'

export interface ExportResult {
  sql: string
  filename: string
  database?: string
  table_count?: number
  relation_count?: number
}

export function useExport() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  function downloadAsFile(content: string, filename: string, mimeType = 'text/plain') {
    const blob = new Blob([content], { type: mimeType })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  async function exportSql(connectionId: string): Promise<ExportResult | null> {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections/${connectionId}/export/sql`)
      if (!res.ok) throw new Error('Failed to export SQL')
      const json = await res.json()
      const data: ExportResult = json.data

      downloadAsFile(data.sql, data.filename, 'application/sql')

      toast.success(`Exported ${data.table_count} tables as SQL`)
      return data
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Export failed'
      error.value = msg
      toast.error(msg)
      return null
    } finally {
      loading.value = false
    }
  }

  async function exportTableSql(connectionId: string, tableName: string): Promise<ExportResult | null> {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections/${connectionId}/export/sql/${encodeURIComponent(tableName)}`)
      if (!res.ok) throw new Error('Failed to export table SQL')
      const json = await res.json()
      const data: ExportResult = json.data

      downloadAsFile(data.sql, data.filename, 'application/sql')

      toast.success(`Exported table "${tableName}" as SQL`)
      return data
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Export failed'
      error.value = msg
      toast.error(msg)
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    exportSql,
    exportTableSql,
    downloadAsFile,
  }
}
