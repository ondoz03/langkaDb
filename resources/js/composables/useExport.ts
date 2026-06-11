import { ref } from 'vue'
import { toast } from 'vue-sonner'

export interface ExportResult {
  sql: string
  filename: string
  database?: string
  table_count?: number
  relation_count?: number
}

interface DocxTableColumn {
  name: string
  type: string
  nullable: boolean
  primary: boolean
  default: string | null
  comment: string | null
}

interface DocxTable {
  name: string
  columns: DocxTableColumn[]
  indexes: { name: string; columns: string[]; type: string }[]
  comment?: string | null
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

  async function exportSchemaDocx(connectionId: string): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections/${connectionId}/schema`)
      if (!res.ok) throw new Error('Failed to load schema')
      const json = await res.json()
      const data = json.data

      const { Document, Packer, Paragraph, Table, TableRow, TableCell, TextRun, WidthType, AlignmentType, HeadingLevel, BorderStyle } = await import('docx')

      const tables: DocxTable[] = data.tables.map((t: any) => ({
        name: t.name,
        columns: (t.columns ?? []).map((c: any) => ({
          name: c.name,
          type: c.type,
          nullable: c.nullable ?? false,
          primary: c.primary ?? false,
          default: c.default ?? null,
          comment: c.comment ?? null,
        })),
        indexes: (t.indexes ?? []).map((i: any) => ({
          name: i.name,
          columns: i.columns ?? [],
          type: i.type ?? '',
        })),
        comment: t.comment ?? null,
      }))

      const docChildren: any[] = []

      // Title
      docChildren.push(
        new Paragraph({ children: [new TextRun({ text: 'Data Dictionary', bold: true, size: 52 })], heading: HeadingLevel.TITLE }),
        new Paragraph({ children: [new TextRun({ text: `Database: ${data.database ?? 'N/A'}`, size: 24 })] }),
        new Paragraph({ children: [new TextRun({ text: `Generated: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}`, size: 20 })] }),
        new Paragraph({ spacing: { after: 400 }, children: [] }),
      )

      // Summary
      docChildren.push(
        new Paragraph({ children: [new TextRun({ text: 'Summary', bold: true, size: 28 })] }),
        new Paragraph({ children: [new TextRun({ text: `Total Tables: ${tables.length}`, size: 22 })] }),
        new Paragraph({ children: [new TextRun({ text: `Total Relations: ${data.relations?.length ?? 0}`, size: 22 })] }),
        new Paragraph({ spacing: { after: 400 }, children: [] }),
      )

      for (const table of tables) {
        docChildren.push(
          new Paragraph({
            children: [new TextRun({ text: table.name, bold: true, size: 28 })],
            heading: HeadingLevel.HEADING_2,
            spacing: { before: 400 },
          }),
        )

        if (table.comment) {
          docChildren.push(
            new Paragraph({ children: [new TextRun({ text: table.comment, size: 20, italics: true })] }),
          )
        }

        // Columns table
        const headerRow = new TableRow({
          tableHeader: true,
          children: ['Column', 'Type', 'Nullable', 'Primary', 'Default', 'Comment'].map(text =>
            new TableCell({
              children: [new Paragraph({ children: [new TextRun({ text, bold: true, size: 18 })] })],
              width: { size: text === 'Column' || text === 'Comment' ? 25 : 15, type: WidthType.PERCENTAGE },
            })
          ),
        })

        const dataRows = table.columns.map(col =>
          new TableRow({
            children: [
              col.name, col.type, col.nullable ? 'YES' : 'NO', col.primary ? 'YES' : 'NO',
              col.default ?? '—', col.comment ?? '—',
            ].map(text =>
              new TableCell({
                children: [new Paragraph({ children: [new TextRun({ text: String(text), size: 18 })] })],
              })
            ),
          })
        )

        docChildren.push(
          new Table({
            rows: [headerRow, ...dataRows],
            width: { size: 100, type: WidthType.PERCENTAGE },
          }),
        )

        // Indexes
        if (table.indexes.length > 0) {
          docChildren.push(
            new Paragraph({ spacing: { before: 200 }, children: [new TextRun({ text: 'Indexes', bold: true, size: 20 })] }),
          )

          for (const idx of table.indexes) {
            docChildren.push(
              new Paragraph({ children: [new TextRun({ text: `  • ${idx.name} on (${idx.columns.join(', ')})`, size: 18 })] }),
            )
          }
        }
      }

      const doc = new Document({ sections: [{ children: docChildren }] })
      const blob = await Packer.toBlob(doc)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `datadictionary-${connectionId}.docx`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)

      toast.success(`Exported data dictionary for ${tables.length} tables`)
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Export failed'
      error.value = msg
      toast.error(msg)
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    exportSql,
    exportTableSql,
    exportDocx: exportSchemaDocx,
    downloadAsFile,
  }
}
