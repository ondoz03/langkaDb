#!/bin/bash

# =============================================================================
# skill.sh — AetherDB AI Custom Skills for OpenCode
# =============================================================================
# Register custom skill commands untuk OpenCode AI agent
# Usage: bash docs/skill.sh
# Kemudian gunakan skill di OpenCode dengan: /skill <nama-skill>
# =============================================================================

set -e

# ── Colors ────────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log()     { echo -e "${BLUE}[skill]${NC} $1"; }
success() { echo -e "${GREEN}[✓]${NC} $1"; }
warn()    { echo -e "${YELLOW}[!]${NC} $1"; }

echo -e "\n${CYAN}  AetherDB AI — OpenCode Skill Registration${NC}\n"

# ── Skill Directory ───────────────────────────────────────────────────────────
SKILL_DIR=".opencode/skills"
mkdir -p "$SKILL_DIR"
log "Skill directory: $SKILL_DIR"

# =============================================================================
# SKILL 1: analyze-schema
# Perintah: /skill analyze-schema
# =============================================================================
cat > "$SKILL_DIR/analyze-schema.md" << 'SKILL'
# Skill: analyze-schema

## Trigger
Gunakan skill ini ketika diminta untuk menganalisis schema database.

## Instruksi untuk Agent

1. Baca file `docs/PRD.md` section 5.2 (Database Structure Reader)
2. Baca file `app/Modules/Schema/` jika sudah ada
3. Jalankan introspection menggunakan Doctrine DBAL:

```php
// Referensi: app/Modules/Schema/Services/SchemaScanner.php
$schemaManager = $connection->createSchemaManager();
$tables = $schemaManager->listTables();
```

4. Parse output menjadi `SchemaContextDTO`
5. Simpan ke Redis cache dengan key: `schema:{connectionId}:{hash}`
6. Return structured JSON sesuai format di PLAN.md section 5.2

## Output yang Diharapkan
- List semua tabel dengan kolom, tipe, constraint, index, FK
- Relasi mapping antar tabel
- Estimasi row count dan ukuran tabel
- AI-ready context JSON

## File yang Relevan
- `app/Modules/Schema/Services/SchemaScanner.php`
- `app/Modules/Schema/Services/SchemaParser.php`
- `app/Modules/Schema/DTOs/SchemaContextDTO.php`
- `app/Modules/Schema/DTOs/TableDTO.php`
SKILL
success "Skill registered: analyze-schema"

# =============================================================================
# SKILL 2: make-module
# Perintah: /skill make-module <NamaModule>
# =============================================================================
cat > "$SKILL_DIR/make-module.md" << 'SKILL'
# Skill: make-module

## Trigger
Gunakan skill ini ketika diminta membuat Laravel module baru di `app/Modules/`.

## Instruksi untuk Agent

Buat struktur folder module baru sesuai arsitektur di PLAN.md:

```
app/Modules/{NamaModule}/
├── Controllers/
│   └── {NamaModule}Controller.php
├── Services/
│   └── {NamaModule}Service.php
├── Repositories/
│   └── {NamaModule}Repository.php
├── DTOs/
│   └── {NamaModule}DTO.php
├── Models/
│   └── {NamaModule}.php
└── routes.php
```

## Template Controller
```php
<?php

declare(strict_types=1);

namespace App\Modules\{NamaModule}\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class {NamaModule}Controller extends Controller
{
    public function __construct(
        private readonly {NamaModule}Service $service,
    ) {}
}
```

## Template Service
```php
<?php

declare(strict_types=1);

namespace App\Modules\{NamaModule}\Services;

class {NamaModule}Service
{
    public function __construct(
        private readonly {NamaModule}Repository $repository,
    ) {}
}
```

## Setelah Membuat Module
1. Daftarkan routes di `routes/api.php`
2. Daftarkan Service Provider jika diperlukan
3. Buat migration jika ada model baru
4. Buat unit test di `tests/Unit/{NamaModule}Test.php`

## Naming Convention
- Module folder: PascalCase (contoh: `AIAgent`, `Connection`, `Schema`)
- Controller: `{NamaModule}Controller`
- Service: `{NamaModule}Service`
- Repository: `{NamaModule}Repository`
- DTO: `{NamaModule}DTO` atau lebih spesifik: `TableDTO`, `ConnectionDTO`
SKILL
success "Skill registered: make-module"

# =============================================================================
# SKILL 3: make-vue-page
# Perintah: /skill make-vue-page <NamaPage>
# =============================================================================
cat > "$SKILL_DIR/make-vue-page.md" << 'SKILL'
# Skill: make-vue-page

## Trigger
Gunakan skill ini ketika diminta membuat halaman Vue baru (Inertia page).

## Instruksi untuk Agent

Buat file di `resources/js/pages/{NamaPage}.vue` dengan template:

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

interface Props {
  // definisikan props dari Laravel controller
}

const props = defineProps<Props>()
</script>

<template>
  <Head title="{NamaPage}" />

  <AppLayout>
    <div class="flex flex-col gap-4 p-6">
      <!-- konten halaman -->
    </div>
  </AppLayout>
</template>
```

## Checklist Setelah Membuat Page
1. Tambahkan route di `routes/web.php`
2. Buat method di Controller yang me-return `Inertia::render('{NamaPage}')`
3. Tambahkan link di sidebar navigation jika perlu
4. Buat composable di `resources/js/composables/use{NamaPage}.ts` jika ada logic
5. Gunakan komponen dari `resources/js/components/ui/` untuk UI elements

## Design Rules (sesuai PRD theme)
- Gunakan `font-mono` untuk semua text
- Tidak ada rounded corners (`rounded-none` atau tidak pakai `rounded-*`)
- Background: `bg-background`, text: `text-foreground`
- Border: `border-border`
- Mengikuti desain Linear/Vercel/Raycast
SKILL
success "Skill registered: make-vue-page"

# =============================================================================
# SKILL 4: make-ai-agent
# Perintah: /skill make-ai-agent <NamaAgent>
# =============================================================================
cat > "$SKILL_DIR/make-ai-agent.md" << 'SKILL'
# Skill: make-ai-agent

## Trigger
Gunakan skill ini ketika diminta membuat AI Agent baru di sistem multi-agent AetherDB AI.

## Instruksi untuk Agent

Buat file di `app/Modules/AIAgent/Agents/{NamaAgent}.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;

class {NamaAgent} implements AgentInterface
{
    public function __construct(
        private readonly AIRouter $router,
    ) {}

    public function analyze(SchemaContextDTO $context): AgentResultDTO
    {
        $prompt = $this->buildPrompt($context);

        $response = $this->router->route(
            task: '{task_type}',   // 'reasoning', 'sql_analysis', 'quick'
            prompt: $prompt,
            systemPrompt: $this->systemPrompt(),
        );

        return $this->parseResponse($response);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
        You are AetherDB AI, specialized in {domain}.
        Always respond in valid JSON format only.
        PROMPT;
    }

    private function buildPrompt(SchemaContextDTO $context): string
    {
        return <<<PROMPT
        Database: {$context->database}
        Schema:
        {$context->toJson()}

        Task: {task description}

        Respond ONLY with JSON in this format:
        {
          "findings": [...],
          "recommendations": [...],
          "score": 0-100
        }
        PROMPT;
    }

    private function parseResponse(string $response): AgentResultDTO
    {
        $data = json_decode($response, true);
        return new AgentResultDTO(
            findings: $data['findings'] ?? [],
            recommendations: $data['recommendations'] ?? [],
            score: $data['score'] ?? 0,
        );
    }
}
```

## Checklist Setelah Membuat Agent
1. Buat Prompt class di `app/Modules/AIAgent/Prompts/{NamaAgent}Prompt.php`
2. Daftarkan agent di `app/Modules/AIAgent/Orchestrator.php`
3. Buat API endpoint di `AgentController.php`
4. Tambahkan unit test di `tests/Unit/Agents/{NamaAgent}Test.php`
5. Cache hasil dengan Redis: `ai:{agent_name}:{connectionId}:{schemaHash}` TTL: 1800

## AI Router Task Types
- `reasoning` → DeepSeek R1 (complex analysis)
- `sql_analysis` → Claude (SQL + schema specialist)
- `quick` → DeepSeek V3 (fast response)
- `embedding` → text-embedding-3-large
SKILL
success "Skill registered: make-ai-agent"

# =============================================================================
# SKILL 5: run-tests
# Perintah: /skill run-tests
# =============================================================================
cat > "$SKILL_DIR/run-tests.md" << 'SKILL'
# Skill: run-tests

## Trigger
Gunakan skill ini untuk menjalankan test suite AetherDB AI.

## Instruksi untuk Agent

### Run All Tests
```bash
php artisan test
```

### Run Specific Module Tests
```bash
# Schema module
php artisan test --filter Schema

# Connection module
php artisan test --filter Connection

# AI Agent module
php artisan test --filter AIAgent

# Query analyzer
php artisan test --filter Query
```

### Run dengan Coverage
```bash
php artisan test --coverage --min=80
```

### Run Frontend Tests
```bash
npm run test          # Vitest watch mode
npm run test:run      # Vitest single run
npm run test:coverage # Vitest dengan coverage
```

### Jika Ada Test Gagal
1. Baca error message dengan teliti
2. Identifikasi apakah unit test atau feature test yang gagal
3. Cek apakah ada migration yang belum dijalankan: `php artisan migrate`
4. Cek apakah ada dependency yang kurang
5. Fix minimal — jangan ubah test, ubah implementasinya

## Test Database
AetherDB AI menggunakan SQLite in-memory untuk testing:
```env
# .env.testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

## Coverage Target per Module
- Schema Parser: 90%
- Connection Encryptor: 100%
- AI Agents: 80%
- API Controllers: 85%
SKILL
success "Skill registered: run-tests"

# =============================================================================
# SKILL 6: build-graph
# Perintah: /skill build-graph
# =============================================================================
cat > "$SKILL_DIR/build-graph.md" << 'SKILL'
# Skill: build-graph

## Trigger
Gunakan skill ini ketika mengerjakan fitur Visual Database Graph (Vue Flow).

## Instruksi untuk Agent

### Komponen yang Perlu Dibuat
```
resources/js/components/graph/
├── SchemaGraph.vue        # Root canvas
├── TableNode.vue          # Custom node untuk tabel
├── RelationEdge.vue       # Custom edge untuk FK relation
├── GraphToolbar.vue       # Zoom, layout, filter controls
└── GraphMinimap.vue       # Overview minimap
```

### Struktur Data Graph
```typescript
// Node (satu tabel = satu node)
interface TableNodeData {
  tableName: string
  columns: ColumnDTO[]
  rowCount: number
  sizeKb: number
  cluster: string          // Domain cluster dari AI
  clusterColor: string     // Warna border cluster
  hasWarning: boolean      // Ada AI warning?
}

// Edge (satu FK = satu edge)
interface RelationEdgeData {
  fromTable: string
  toTable: string
  fromColumn: string
  toColumn: string
  relationType: 'one-to-one' | 'one-to-many' | 'many-to-many'
}
```

### Auto Layout dengan Dagre
```typescript
import dagre from '@dagrejs/dagre'

function applyDagreLayout(nodes, edges) {
  const g = new dagre.graphlib.Graph()
  g.setDefaultEdgeLabel(() => ({}))
  g.setGraph({ rankdir: 'LR', nodesep: 80, ranksep: 160, marginx: 50, marginy: 50 })

  nodes.forEach(node => {
    g.setNode(node.id, { width: 280, height: 200 })
  })

  edges.forEach(edge => {
    g.setEdge(edge.source, edge.target)
  })

  dagre.layout(g)

  return nodes.map(node => {
    const pos = g.node(node.id)
    return { ...node, position: { x: pos.x - 140, y: pos.y - 100 } }
  })
}
```

### Design Rules untuk Graph
- Node background: `bg-card` (`hsl(0 0% 7%)`)
- Node border: `border-border` default, cluster color untuk domain highlight
- Node header: tabel name dalam `font-mono font-bold`
- Column list: max 8 kolom ditampilkan, sisanya collapsed
- Edge: SVG path, warna `hsl(0 0% 40%)` default, `hsl(0 0% 98%)` saat hover
- Arrow: directional arrow di ujung edge (menunjukkan arah FK)

### Cluster Colors (AI Domain)
```typescript
const CLUSTER_COLORS = {
  auth:       '#3b82f6', // blue
  commerce:   '#10b981', // emerald
  finance:    '#f59e0b', // amber
  logistics:  '#8b5cf6', // violet
  content:    '#ec4899', // pink
  system:     '#6b7280', // gray (default)
}
```
SKILL
success "Skill registered: build-graph"

# =============================================================================
# SKILL 7: check-health
# Perintah: /skill check-health
# =============================================================================
cat > "$SKILL_DIR/check-health.md" << 'SKILL'
# Skill: check-health

## Trigger
Gunakan skill ini untuk mengecek status environment development AetherDB AI.

## Instruksi untuk Agent

Jalankan pengecekan berikut dan laporkan hasilnya:

### 1. Laravel Status
```bash
php artisan about              # App info
php artisan db:show            # DB connection status
php artisan queue:monitor      # Queue status
```

### 2. Redis Status
```bash
redis-cli ping                 # Harus return PONG
redis-cli info server          # Redis version & status
```

### 3. Node / Vite Status
```bash
node -v                        # Harus >= 20
npm -v
cat package.json | grep "@vue-flow"   # Cek Vue Flow installed
cat package.json | grep "shadcn"      # Cek shadcn installed
```

### 4. Tauri Status
```bash
npx tauri info                 # Tauri environment info
rustc -V                       # Rust version
cargo -V                       # Cargo version
```

### 5. Test Suite Status
```bash
php artisan test --stop-on-failure   # Run tests
```

### Format Laporan
```
🟢 Laravel: OK (v13.x)
🟢 Database: Connected (MySQL 8.0)
🟢 Redis: Running (v7.x)
🟢 Queue: Ready (redis driver)
🟢 Node: v20.x
🟢 Vue Flow: installed
🟢 shadcn/ui: installed
🟡 Tauri: installed (belum di-init)
🟢 Rust: stable 1.7x
🔴 Tests: 2 failing (lihat output di atas)
```
SKILL
success "Skill registered: check-health"

# =============================================================================
# SKILL 8: generate-docs
# Perintah: /skill generate-docs
# =============================================================================
cat > "$SKILL_DIR/generate-docs.md" << 'SKILL'
# Skill: generate-docs

## Trigger
Gunakan skill ini ketika diminta menggenerate dokumentasi database otomatis.

## Instruksi untuk Agent

Documentation Agent bertugas menghasilkan:

### 1. Per-Table Documentation (Markdown)
```markdown
## Table: orders

**Purpose:** Menyimpan data transaksi pembelian pelanggan

**Estimated Rows:** 1,500,000  
**Size:** 234 MB

### Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| id | BIGINT UNSIGNED | NO | auto_increment | Primary identifier |
| user_id | BIGINT UNSIGNED | NO | — | FK ke users.id |
| status | ENUM | NO | 'pending' | Status order |
| total_amount | DECIMAL(15,2) | NO | 0.00 | Total harga order |
| created_at | TIMESTAMP | YES | NULL | Waktu order dibuat |

### Indexes
- PRIMARY KEY: `id`
- INDEX: `user_id` (untuk JOIN ke users)
- INDEX: `status, created_at` (untuk filter query umum)

### Relations
- BELONGS TO: `users` via `user_id`
- HAS MANY: `order_items` via `order_id`
- HAS ONE: `payments` via `order_id`

### AI Notes
⚠️ Missing index on `created_at` — sering digunakan di WHERE clause tanpa index
```

### 2. Full Schema Summary
```markdown
# Database Documentation: {db_name}

Generated by AetherDB AI — {tanggal}

## Overview
- Total Tables: 45
- Total Indexes: 120
- Estimated Total Size: 2.3 GB

## Domain Clusters
- **Auth** (3 tables): users, roles, permissions
- **Commerce** (12 tables): orders, products, categories, ...
- **Finance** (8 tables): invoices, payments, ...

## Health Score: 78/100 (B)
...
```

### Output Locations
- Per-table: `storage/app/docs/{connectionId}/tables/{table_name}.md`
- Full schema: `storage/app/docs/{connectionId}/schema.md`
- PDF export: `storage/app/docs/{connectionId}/schema.pdf`

### AI Prompt untuk Documentation Agent
System prompt harus menekankan:
- Inferensi tujuan bisnis dari nama kolom dan relasi
- Bahasa Indonesia untuk deskripsi (sesuai target user)
- Highlight kolom yang mungkin menyimpan PII (nama, email, phone, address)
- Flag tabel yang terlihat unused (tidak ada FK ke manapun)
SKILL
success "Skill registered: generate-docs"

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}══════════════════════════════════════${NC}"
echo -e "${CYAN}  Skills Registered Successfully!${NC}"
echo -e "${CYAN}══════════════════════════════════════${NC}\n"

echo -e "Available skills for OpenCode:\n"

SKILLS=(
    "analyze-schema   — Parse dan analisis struktur database"
    "make-module       — Buat Laravel module baru di app/Modules/"
    "make-vue-page     — Buat Inertia Vue page baru"
    "make-ai-agent     — Buat AI Agent baru di sistem multi-agent"
    "run-tests         — Jalankan test suite (PHP + Frontend)"
    "build-graph       — Panduan membuat Visual Graph Engine"
    "check-health      — Cek status seluruh environment"
    "generate-docs     — Generate dokumentasi database otomatis"
)

for skill in "${SKILLS[@]}"; do
    echo -e "  ${CYAN}/skill${NC} ${skill}"
done

echo ""
echo -e "Skills disimpan di: ${YELLOW}.opencode/skills/${NC}"
echo -e "Untuk menggunakan: ketik ${CYAN}/skill <nama-skill>${NC} di OpenCode\n"
