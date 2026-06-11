# 📋 PLAN v0.2.0: Visual Schema Engine Integration
**Project:** LangkaDB (AetherDB AI)
**Status:** ✅ Complete — 11 Juni 2026
**Base Analysis:** `ondoz03/erd-builder-pro`

---

## 1. Pendahuluan
Meningkatkan kapabilitas LangkaDB dari sekedar manajemen database menjadi alat **Database Architect** yang komprehensif. Mengintegrasikan fitur visualisasi dari `erd-builder-pro` untuk membantu user mendesain, mendokumentasikan, dan menganalisis skema database secara visual.

---

## 2. Phase 1: Core Logic & SQL Parser (Backend Foundations)
**Objective:** Memungkinkan LangkaDB memproses file `.sql` mentah menjadi data terstruktur (JSON).

- [x] **Extraction SQL Parser:**
    - Frontend SQL Parser: `resources/js/lib/parsers/SqlParser.ts` (BARU)
    - Backend SQL Parser: `app/Modules/Schema/Services/SqlImportService.php` (SUDAH ADA)
    - API endpoint: `POST /api/schema/import-sql` (SUDAH ADA)
- [x] **Database Migration (SQLite):**
    - Tabel `diagrams`: id, name, description, connection_id, layout_data, timestamps (BARU)
    - Tabel `diagram_nodes`: id, diagram_id, table_name, x_pos, y_pos, metadata, timestamps (BARU)
- [x] **API Endpoints (Laravel):**
    - `POST /api/designer/diagrams` — Create diagram
    - `GET /api/designer/diagrams` — List diagrams
    - `GET /api/designer/diagrams/{id}` — Show diagram
    - `PUT /api/designer/diagrams/{id}` — Update diagram
    - `DELETE /api/designer/diagrams/{id}` — Delete diagram
    - `GET /api/connections/{cid}/designer/diagrams` — By connection
    - `POST /api/designer/save-layout` — Save node positions
    - Module: `app/Modules/Designer/` (Controllers, Services, DTOs, Repositories, Models)

---

## 3. Phase 2: Visual Designer UI (Canvas Interaktif)
**Objective:** Implementasi UI modern berbasis Node-Graph untuk manipulasi skema.

- [x] **Library Integration:**
    - `@vue-flow/core`, `@vue-flow/background`, `@vue-flow/controls` (SUDAH ADA)
- [x] **Custom Components:**
    - `TableNode.vue` — Header tabel, list kolom, flags PK/FK (SUDAH ADA)
    - `RelationEdge.vue` — Relasi interaktif (SUDAH ADA)
- [x] **Feature Canvas:**
    - Auto-layout dengan Dagre (SUDAH ADA)
    - Mini-map navigasi (SUDAH ADA)
    - **Schema Toolbox sidebar** — `resources/js/components/graph/SchemaToolbox.vue` (BARU)
    - **Create Table Dialog** — `resources/js/components/graph/CreateTableDialog.vue` (BARU)

---

## 4. Phase 3: Documentation & Export Engine
**Objective:** Memberikan output profesional bagi pengembang.

- [x] **Data Dictionary Generator:**
    - Export ke `.docx` menggunakan `docx` npm package — `useExport.ts::exportDocx()` (BARU)
    - Mencakup: Nama tabel, deskripsi kolom, tipe data, indexes
- [x] **Visual Export:**
    - Export canvas ke PNG / SVG via `html-to-image` (SUDAH ADA)
- [ ] **Metadata Enrichment (PENDING — v0.3.0):**
    - Menambah comment/keterangan pada kolom dari UI Designer — belum diimplementasi

---

## 5. Phase 4: AI Schema Advisor (Hermes Power)
**Objective:** Menggunakan AI untuk memastikan kualitas desain database.

- [x] **Normalization Checker:**
    - Rule-based detection di `OptimizationAgent.php` — 1NF (repeating groups), JSON denormalization, denormalized prefixes
    - Terintegrasi dengan AI Analysis di `/insights`
- [x] **Indexing Suggestion:**
    - SUDAH ADA sejak Phase 4 utama (missing index, duplicate index, FK index)
- [x] **Natural Language to Schema (Prompt to ERD):**
    - Backend: `POST /api/ai/generate-schema` — `AIController::generateSchema()` (BARU)
    - Fallback: 7 domain template (users, products, orders, categories, posts, payments, items)
    - Frontend: `resources/js/components/graph/PromptToErdDialog.vue` (BARU)
    - Terintegrasi di Graph page via tombol "AI Schema"

---

## 6. Technical Stack Update
- **Frontend:** Vue 3, Inertia.js, Vue-Flow, Tailwind CSS, shadcn/ui, docx
- **Backend:** Laravel 13, Doctrine DBAL
- **Desktop:** Tauri v2

---

## 7. Timeline Realisasi
| Minggu | Target | Realisasi |
|--------|--------|-----------|
| 1 | Parser & DB Schema | ✅ 11 Juni 2026 |
| 2 | Canvas & UI Design | ✅ 11 Juni 2026 |
| 3 | Export & AI Integration | ✅ 11 Juni 2026 |

---

## 8. Backlog / Next (v0.3.0)
- [ ] **Frontend Save/Load Layout** — UI untuk simpan & muat diagram (API sudah siap)
- [ ] **Metadata Enrichment** — Edit komentar kolom dari UI
- [ ] **Drag-drop dari Toolbox ke Canvas** — proper drag-and-drop (saat ini click-to-add)
- [ ] **Undo/Redo** — history management untuk designer canvas
- [ ] **Table color coding** — custom warna per tabel/cluster

---
*Executed on 2026-06-11 by OpenCode Agent.*