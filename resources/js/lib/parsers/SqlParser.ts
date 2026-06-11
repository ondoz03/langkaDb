export interface ParsedColumn {
  name: string
  type: string
  nullable: boolean
  default: string | null
  primary: boolean
  comment: string | null
}

export interface ParsedIndex {
  name: string
  columns: string[]
  unique: boolean
  type: 'primary' | 'unique' | 'index'
}

export interface ParsedRelation {
  name: string
  fromTable: string
  fromColumn: string
  toTable: string
  toColumn: string
  type: string
}

export interface ParsedTable {
  name: string
  columns: ParsedColumn[]
  indexes: ParsedIndex[]
  relations: ParsedRelation[]
}

export interface ParseResult {
  tables: ParsedTable[]
  relations: ParsedRelation[]
}

const TYPE_MAP: Record<string, string> = {
  INT: 'Integer',
  INTEGER: 'Integer',
  BIGINT: 'BigInt',
  SMALLINT: 'SmallInt',
  TINYINT: 'TinyInt',
  FLOAT: 'Float',
  DOUBLE: 'Double',
  DECIMAL: 'Decimal',
  NUMERIC: 'Decimal',
  VARCHAR: 'String',
  CHAR: 'String',
  TEXT: 'Text',
  TINYTEXT: 'Text',
  MEDIUMTEXT: 'Text',
  LONGTEXT: 'Text',
  BOOLEAN: 'Boolean',
  DATETIME: 'DateTime',
  DATE: 'Date',
  TIME: 'Time',
  TIMESTAMP: 'Timestamp',
  YEAR: 'Year',
  BINARY: 'Binary',
  BLOB: 'Blob',
  JSON: 'Json',
}

function normalizeType(rawType: string): string {
  const base = rawType.replace(/\(.*\)/, '').replace(/\s+(UNSIGNED|ZEROFILL)/gi, '').trim().toUpperCase()
  if (base === 'TINYINT' && rawType.toUpperCase().includes('(1)')) {
    return 'Boolean'
  }
  return TYPE_MAP[base] ?? base
}

function splitByCommaOutsideParens(body: string): string[] {
  const parts: string[] = []
  let depth = 0
  let current = ''
  for (let i = 0; i < body.length; i++) {
    const ch = body[i]
    if (ch === '(' || ch === '[') { depth++; current += ch }
    else if (ch === ')' || ch === ']') { depth--; current += ch }
    else if (ch === ',' && depth === 0) { parts.push(current); current = '' }
    else { current += ch }
  }
  if (current.trim()) parts.push(current)
  return parts
}

function parseColumnList(list: string): string[] {
  return list.split(',').map(c => c.trim().replace(/[`'"]/g, '')).filter(Boolean)
}

function parseColumnDefinition(part: string, primaryColumns: string[]): ParsedColumn | null {
  const m = part.match(/^`?(\w+)`?\s+(\w+(?:\s*\([^)]*\))?(?:\s+UNSIGNED)?(?:\s+ZEROFILL)?)(.*)$/i)
  if (!m) return null

  const colName = m[1]
  const rawType = m[2]
  const suffix = m[3].toUpperCase()

  const type = normalizeType(rawType)
  const nullable = !suffix.includes('NOT NULL') && !suffix.includes('PRIMARY KEY')
  const isPrimary = primaryColumns.includes(colName) || suffix.includes('PRIMARY KEY')

  let defaultVal: string | null = null
  const dm = part.match(/DEFAULT\s+(\S+)/i)
  if (dm) defaultVal = dm[1].replace(/['"]/g, '')

  const cdm = part.match(/DEFAULT\s+(CURRENT_TIMESTAMP|NULL|TRUE|FALSE)\b/i)
  if (cdm) defaultVal = cdm[1].toUpperCase()

  let comment: string | null = null
  const cm = part.match(/COMMENT\s+'([^']*)'/i)
  if (cm) comment = cm[1]

  return { name: colName, type, nullable: nullable && !isPrimary, default: defaultVal, primary: isPrimary, comment }
}

export function parseSql(sql: string): ParseResult {
  const tables: ParsedTable[] = []
  const allRelations: ParsedRelation[] = []

  const pattern = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`?(\w+)`?\s*)?\((.*?)\)\s*(?:ENGINE\s*=\s*\w+)?\s*(?:DEFAULT\s+CHARSET\s*=\s*\w+)?\s*(?:COLLATE\s*=\s*\w+)?\s*;/gims

  let match: RegExpExecArray | null
  while ((match = pattern.exec(sql)) !== null) {
    const tableName = match[1]
    const body = match[2]
    const result = parseCreateTableBody(tableName, body)
    if (result) {
      tables.push(result)
      allRelations.push(...result.relations)
    }
  }

  return { tables, relations: allRelations }
}

function parseCreateTableBody(tableName: string, body: string): ParsedTable | null {
  const columns: ParsedColumn[] = []
  const indexes: ParsedIndex[] = []
  const relations: ParsedRelation[] = []
  const primaryColumns: string[] = []

  const parts = splitByCommaOutsideParens(body)

  for (const part of parts) {
    const trimmed = part.trim()
    if (!trimmed) continue
    const upper = trimmed.toUpperCase()

    // FOREIGN KEY
    const fkMatch = trimmed.match(/^\s*(?:CONSTRAINT\s+`?\w+`?\s+)?FOREIGN\s+KEY\s*\(`?(\w+)`?\)\s*REFERENCES\s+`?(\w+)`?\s*\(`?(\w+)`?\)/i)
    if (fkMatch) {
      relations.push({
        name: `fk_${tableName}_${fkMatch[1]}`,
        fromTable: tableName,
        fromColumn: fkMatch[1],
        toTable: fkMatch[2],
        toColumn: fkMatch[3],
        type: 'belongs_to',
      })
      continue
    }

    // PRIMARY KEY
    const pkMatch = trimmed.match(/^\s*(?:PRIMARY\s+KEY)\s*(?:`?\w+`?)?\s*\(([^)]+)\)/i)
    if (pkMatch) {
      const cols = parseColumnList(pkMatch[1])
      primaryColumns.push(...cols)
      indexes.push({ name: 'PRIMARY', columns: cols, unique: true, type: 'primary' })
      continue
    }

    // UNIQUE KEY / INDEX
    const ukMatch = trimmed.match(/^\s*UNIQUE\s+(?:KEY|INDEX)\s+`?(\w+)`?\s*\(([^)]+)\)/i)
    if (ukMatch) {
      indexes.push({ name: ukMatch[1], columns: parseColumnList(ukMatch[2]), unique: true, type: 'unique' })
      continue
    }

    // KEY / INDEX
    const idxMatch = trimmed.match(/^\s*(?:KEY|INDEX)\s+`?(\w+)`?\s*\(([^)]+)\)/i)
    if (idxMatch) {
      indexes.push({ name: idxMatch[1], columns: parseColumnList(idxMatch[2]), unique: false, type: 'index' })
      continue
    }

    // Column definition
    const col = parseColumnDefinition(trimmed, primaryColumns)
    if (col) columns.push(col)
  }

  if (columns.length === 0) return null

  return { name: tableName, columns, indexes, relations }
}
