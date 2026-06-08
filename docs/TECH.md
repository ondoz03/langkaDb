# Technology Stack — AetherDB AI

## Arsitektur Overview

```
┌──────────────────────────────────────────────────────────┐
│                    Tauri v2 (Desktop Shell)                │
│  ┌────────────────────────────────────────────────────┐  │
│  │           Laravel 13 (Backend API)                  │  │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │  │
│  │  │Connection│  │  Schema  │  │   AI Agent       │  │  │
│  │  │  Module  │  │  Module  │  │   Module         │  │  │
│  │  └──────────┘  └──────────┘  └──────────────────┘  │  │
│  └────────────────────────────────────────────────────┘  │
│                          │ HTTP/JSON                      │
│  ┌────────────────────────────────────────────────────┐  │
│  │         Vue 3 + Inertia.js (Frontend SPA)          │  │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │  │
│  │  │  Pages   │  │Components│  │   Composables     │  │  │
│  │  │(Inertia) │  │ (shadcn) │  │   (Logic)         │  │  │
│  │  └──────────┘  └──────────┘  └──────────────────┘  │  │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │  │
│  │  │  Graph   │  │  Pinia   │  │   Wayfinder      │  │  │
│  │  │(Vue Flow)│  │ (Stores) │  │   (Routes)       │  │  │
│  │  └──────────┘  └──────────┘  └──────────────────┘  │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

---

## 1. Backend

### Laravel 13
Framework PHP untuk backend API, auth, database, queue, dan orchestrator AI.

**Kenapa Laravel?**
- Ekosistem lengkap (auth, queue, cache, session bawaan)
- Eloquent ORM untuk database
- Package manager Composer
- Inertia.js integration first-class
- Laravel Pennant, Reverb, Horizon dll

**File penting:**
- `app/Models/` — Eloquent models
- `app/Modules/` — Domain modules (Connection, Schema, AIAgent)
- `app/Http/Controllers/` — HTTP controllers
- `routes/` — Route definitions
- `config/` — Konfigurasi aplikasi
- `database/migrations/` — Database migrations

### Doctrine DBAL
Library untuk database introspection (membaca struktur database yang sudah ada).

**Kegunaan di project:**
- `SchemaScanner` — Connect ke database user via Doctrine DBAL
- `listTables()` — Mendapatkan daftar semua tabel
- `getColumns()` — Membaca kolom, tipe data, nullable, default
- `getIndexes()` — Membaca index (primary, unique, regular)
- `getForeignKeys()` — Membaca foreign key constraints
- `SchemaParser` — Transform hasil introspection ke DTO

**Kompatibilitas:**
- MySQL, MariaDB via driver `pdo_mysql`
- SQLite via driver `pdo_sqlite`
- PostgreSQL via driver `pdo_pgsql`

### Laravel Fortify
Backend authentication scaffolding.

- Login, Register, Logout
- Email verification
- Password reset
- Two-factor authentication
- Passkeys (WebAuthn)

### Laravel Inertia
Bridge antara Laravel (server) dan Vue (client) — tanpa API endpoint manual.

**Cara kerja:**
1. Route di Laravel return `inertia('PageName', ['prop' => $data])`
2. Inertia render page component Vue + kirim props sebagai JSON
3. Client-side navigation tanpa reload (SPA)
4. Semua state management via Pinia di frontend

---

## 2. Frontend

### Vue 3 + TypeScript
Framework frontend utama.

**Pattern yang dipakai:**
- `<script setup lang="ts">` — Composition API
- `defineProps<{...}>()` — Props typed
- `defineEmits<{...}>()` — Events typed
- `ref()` / `computed()` — Reactive state
- `onMounted()`, `onBeforeMount()` — Lifecycle hooks

### Inertia.js Vue 3
Client-side routing SPA tanpa Vue Router.

```
User klik link → Inertia intercept → GET ke server → 
Server return JSON {component, props} → 
Vue render component baru → URL berubah (history API)
```

- `usePage()` — Akses shared props (auth user, dll)
- `router.visit()` — Navigasi programatik
- `router.reload()` — Reload halaman saat ini

### Pinia
State management untuk Vue 3 (pengganti Vuex).

**Store yang ada:**
| Store | Fungsi |
|-------|--------|
| `connection.ts` | Daftar koneksi database, active connection |
| `schema.ts` | Data schema (tables, columns) |
| `ai.ts` | AI chat messages, recommendation |
| `ui.ts` | UI state (sidebar, command palette) |

Pattern: `defineStore('name', () => { ... })` — Composition API style.

### Vite
Build tool untuk frontend.

- HMR (Hot Module Replacement) — instant update saat develop
- Vue plugin (`@vitejs/plugin-vue`)
- TypeScript via `vue-tsc`
- TailwindCSS v4 via `@tailwindcss/vite`
- Wayfinder plugin untuk auto-generate typed routes

### TypeScript 5
Type checking untuk JavaScript.

**Konfigurasi (`tsconfig.json`):**
- `strict: true` — Semua strict check ON
- `target: ESNext` — Compile ke JS terbaru
- `moduleResolution: bundler` — Sesuai Vite
- `paths: { "@/*": ["./resources/js/*"] }` — Path alias

### shadcn/ui + Reka UI
UI component library untuk Vue.

**Komponen yang dipakai:**
| Komponen | Kegunaan |
|----------|----------|
| Button | Tombol aksi |
| Dialog | Modal form tambah/edit koneksi |
| Sheet | Side panel detail tabel |
| Input | Form input |
| Select | Dropdown driver, connection selector |
| Checkbox | SSL/SSH toggle |
| Label | Form label |
| Spinner | Loading state |
| Sonner (Toaster) | Toast notification |
| Command | Command palette (Cmd+K) |

**Styling:** `class-variance-authority` (cva) + `tailwind-merge` (cn utility)

### Vue Flow
Library untuk interactive graph/node editor.

**Komponen yang dibuat:**
| Komponen | Fungsi |
|----------|--------|
| `SchemaGraph.vue` | Root canvas, Vue Flow wrapper |
| `TableNode.vue` | Custom node (tabel) |
| `RelationEdge.vue` | Custom edge (foreign key relation) |
| `GraphToolbar.vue` | Zoom, layout, filter controls |
| `GraphMinimap.vue` | Overview minimap |

**Fitur:**
- Nodes/edges dari data API schema
- Auto-layout dengan Dagre (top-to-bottom)
- Drag node, zoom, pan
- Minimap, background grid
- Controls (zoom +/- , fit view)
- Hover highlight edges
- Click node → side panel detail

### Dagre
Auto-layout engine untuk directed graph.

```
nodes → dagre.layout() → posisi x,y untuk setiap node
```

- `rankdir: 'TB'` — Top-to-bottom layout
- `nodesep`, `ranksep` — Jarak antar node
- Node width/height dinamis sesuai jumlah kolom

### TailwindCSS v4
Utility-first CSS framework.

**Custom theme:**
```css
:root {
  --background: 0 0% 3.9%;    /* hitam */
  --foreground: 0 0% 98%;    /* putih */
  --card: 0 0% 7%;
  --border: 0 0% 14%;
  --primary: 0 0% 98%;
  --muted: 0 0% 14%;
}

.font-mono { font-family: 'Geist Mono', monospace; }
```

- Dark mode via class `dark` di `<html>`
- `@tailwindcss/vite` plugin untuk Vite
- Utility: `bg-background`, `text-foreground`, `border-border`, `font-mono`

### Lucide Vue
Icons untuk Vue.

- `lucide-vue-next` — Package icons
- Import: `import { Database, Server, ... } from 'lucide-vue-next'`

### Vue Sonner
Toast notification.

- `vue-sonner` — Package
- `<Toaster />` — Komponen di layout
- `toast.success()`, `toast.error()`, `toast.info()` — Panggil dari mana aja

### @vueuse/core
Collection of Vue Composition API utilities.

- `@vueuse/core` — Package
- `useEventListener`, `useIntervalFn`, dll — Tambahan composable

---

## 3. Tooling & Infrastructure

### Wayfinder (@laravel/vite-plugin-wayfinder)
Auto-generate typed route helpers dari Laravel ke TypeScript.

**Cara kerja:**
1. Vite scan semua route Laravel
2. Generate file di `resources/js/routes/`
3. Setiap route jadi function typed: `login()`, `dashboard()`, dll

**Keuntungan:**
- Type safety — URL string diganti function call
- Auto-sync — Route berubah, tinggal rebuild
- Method-aware — Tau method HTTP (GET/POST/PUT/DELETE)

```typescript
// Tanpa wayfinder (hardcoded)
<a href="/login">Login</a>

// Dengan wayfinder (typed)
import { login } from '@/routes'
<a :href="login()">Login</a>
```

### PHP 8.4
- `readonly class` — Immutable DTO
- `declare(strict_types=1)` — Strict type
- Constructor property promotion
- Named arguments
- Match expression

### Pest PHP
Testing framework untuk PHP (alternatif PHPUnit).

```bash
php artisan test
php artisan test --filter ConnectionTest
```

### ESLint + Prettier
Code quality untuk TypeScript/Vue.

```bash
npm run lint        # Fix otomatis
npm run lint:check  # Cek aja
npm run format      # Prettier format
```

### vue-tsc
Type checker untuk Vue + TypeScript (alternatif `tsc`).

```bash
npm run types:check
```

### GitHub Actions
CI/CD pipeline.

- `.github/workflows/lint.yml` — Lint check
- `.github/workflows/tests.yml` — PHPUnit tests

### Composer
PHP package manager.

```bash
composer require package/name
composer install
composer update
```

### NPM
Node.js package manager.

```bash
npm install package-name
npm run dev     # Vite dev server
npm run build   # Build production
```

---

## 4. Database & Storage

### SQLite
Database default untuk development.

- File: `database/database.sqlite`
- Session, cache, queue via SQLite (development)
- Production: MySQL/MariaDB

### Migration
Version control untuk database schema.

```bash
php artisan make:migration nama_table
php artisan migrate
php artisan migrate:rollback
```

### Session Driver
- Development: `database` (SQLite)
- Session stored di tabel `sessions`

### Queue Driver
- Development: `database` (SQLite)
- Production: Redis

### Async Job System (AI Analysis)

**Alur:** User klik "Analyze" → Backend dispatch job ke queue → Queue worker proses → Frontend polling progress.

**File kunci:**
| File | Fungsi |
|------|--------|
| `app/Jobs/AIAnalysisJob.php` | Queue job — handle async AI analysis |
| `app/Modules/AIAgent/Controllers/AsyncJobController.php` | REST endpoints: dispatch, status, result |
| `app/Modules/AIAgent/Services/Orchestrator.php` | Multi-agent orchestration dengan `onProgress` callback |
| `resources/js/composables/useAsyncJob.ts` | Frontend polling (setInterval 2s) |
| `database/migrations/..._create_ai_job_results_table.php` | Tabel monitoring job (status, progress, result) |

**Tabel `ai_job_results`:**
```sql
CREATE TABLE ai_job_results (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  connection_id CHAR(36),
  job_class     VARCHAR(255),
  status        VARCHAR(20) DEFAULT 'pending',   -- pending|processing|completed|failed
  progress      INTEGER DEFAULT 0,               -- 0-100
  input         TEXT,                              -- Job input JSON
  result        TEXT,                              -- Job result JSON
  error         TEXT,                              -- Error message if failed
  created_at    TIMESTAMP,
  updated_at    TIMESTAMP
);
```

**Progress update flow (rule-based provider):**
1. `AIAnalysisJob::handle()` → update `progress = 5, status = 'processing'`
2. Context built → update `progress = 20`
3. Pass `onProgress` callback ke `Orchestrator::analyzeFull()`
4. Orchestrator panggil callback tiap agent selesai: 25, 50, 65, 80
5. Data disimpan → update `progress = 80`
6. Selesai → update `status = 'completed', progress = 100`
7. Gagal → catch → update `status = 'failed', progress = 0, error = message`

**Menjalankan worker:**
```bash
# Satu job saja (testing)
php artisan queue:work --once --queue=default

# Continuous (development)
php artisan queue:work --queue=default

# Production (via Supervisor)
# lihat config/supervisor/queue.conf
```

**Peringatan Doctrine DBAL:** `SchemaParser::parseTable()` menghasilkan array index asosiatif dari Doctrine (`['PRIMARY' => IndexDTO, ...]`). Jangan akses dengan index numerik tanpa `array_values()`.

---

## 5. Arsitektur Module

### Pattern: DDD-lite
```
app/Modules/{NamaModule}/
├── Controllers/    # HTTP request/response
├── Services/       # Business logic
├── Repositories/   # Database queries
├── DTOs/           # Data transfer objects
├── Models/         # Eloquent models (optional)
└── routes.php      # Route definitions (optional)
```

**Alur data:**
```
HTTP Request → Controller → Service → Repository → Model
                          ↓
                        DTO
                          ↓
                    JSON Response
```

### DTO Pattern (Data Transfer Object)
- `readonly class` — Immutable setelah dibuat
- `fromArray()` — Factory dari data array
- `toArray()` — Serialize ke array (via Arrayable interface)
- `Arrayable` — Biar Collection/json_encode pake `toArray()` langsung

**Contoh (`ConnectionDTO`):**
```php
readonly class ConnectionDTO implements Arrayable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $driver,
        // ...
    ) {}

    public static function fromArray(array $data): self { ... }
    public function toArray(): array { ... }
}
```

### Repository Pattern
- Semua query database di Repository
- Service gak langsung pake Eloquent
- Controller panggil Service, bukan Repository langsung

---

## 6. Flowchart Aplikasi

```
                        ┌─────────────┐
                        │  Browser    │
                        │ (User Akses)│
                        └──────┬──────┘
                               │
                        ┌──────▼──────┐
                        │  Valet/Her  │
                        │  nginx      │
                        └──────┬──────┘
                               │
                        ┌──────▼──────┐
                        │  Laravel    │
                        │  Routes     │
                        └──────┬──────┘
                               │
                    ┌──────────┼──────────┐
                    │          │          │
            ┌───────▼───┐ ┌───▼────┐ ┌───▼───────┐
            │ Inertia   │ │ Auth   │ │ API       │
            │ Page      │ │ Fortify│ │ /api/*    │
            └───────┬───┘ └────────┘ └───┬───────┘
                    │                     │
            ┌───────▼───┐        ┌───────▼───────┐
            │ Vue 3     │        │ Controllers   │
            │ Component │        │ (Modules)     │
            └───────┬───┘        └───────┬───────┘
                    │                     │
            ┌───────▼───┐        ┌───────▼───────┐
            │ Pinia     │        │ Services      │
            │ Store     │◄───────│ (Business     │
            └───────────┘        │  Logic)       │
                                 └───────┬───────┘
                                         │
                                 ┌───────▼───────┐
                                 │  Repositories │
                                 │  (DB Queries) │
                                 └───────┬───────┘
                                         │
                                 ┌───────▼───────┐
                                 │  Eloquent     │
                                 │  Models       │
                                 └───────┬───────┘
                                         │
                                 ┌───────▼───────┐
                                 │  Database     │
                                 │  (SQLite/MySQL)│
                                 └───────────────┘
```

### Flow untuk Schema Graph

```
User klik "Graph" menu
         │
         ▼
Inertia GET /graph
         │
         ▼
Page Graph/Index.vue mount
         │
         ▼
onMounted → fetchConnections() (kalau store kosong)
         │
         ▼
loadSchema(connectionId)
         │
         ▼
GET /api/connections/{id}/schema
         │
         ▼
SchemaScanner → Doctrine DBAL → listTables()
         │
         ▼
SchemaParser → TableDTO, ColumnDTO, IndexDTO
         │
         ▼
RelationMapper → RelationDTO (foreign keys)
         │
         ▼
ContextBuilder → SchemaContextDTO (JSON)
         │
         ▼
Frontend → buildGraph()
    ├── Dagre auto-layout (posisi nodes)
    └── Vue Flow render (canvas + edges)
         │
         ▼
User interacts:
├── Hover node → highlight connected edges
├── Click node → side panel detail tabel
├── Drag node → reposition
├── Scroll → zoom in/out
└── Minimap → overview navigation
```

---

## 7. Commands Penting

```bash
# Backend
php artisan serve              # Start Laravel dev server
php artisan migrate            # Run migration
php artisan tinker             # PHP REPL
php artisan route:list         # List semua routes
php artisan test               # Run tests
php artisan config:clear       # Clear config cache
php artisan optimize:clear     # Clear all cache

# Frontend
npm run dev                    # Vite dev server
npm run build                  # Build production
npm run lint                   # ESLint fix
npm run types:check            # vue-tsc check

# Code Quality
./vendor/bin/pint              # Laravel Pint (PHP formatter)
npm run format                 # Prettier (frontend)

# Database
php artisan make:migration nama  # Buat migration
php artisan migrate:fresh        # Reset DB + migrate ulang (HATI-HATI)
```

---

## 8. Key Files Reference

### Backend
| Path | Fungsi |
|------|--------|
| `app/Modules/Connection/` | Connection management module |
| `app/Modules/Schema/` | Schema parser engine |
| `app/Modules/AIAgent/` | AI multi-agent system |
| `app/Jobs/AIAnalysisJob.php` | Async queue job untuk AI analysis |
| `app/Modules/AIAgent/Controllers/AsyncJobController.php` | REST endpoints untuk async job (dispatch, status, result) |
| `app/Modules/AIAgent/Services/Orchestrator.php` | Multi-agent orchestration (progress callback) |
| `routes/web.php` | Web routes (Inertia pages) |
| `routes/api.php` | API routes (JSON endpoints) |
| `config/inertia.php` | Inertia config (SSR, pages) |
| `bootstrap/app.php` | Laravel app config (middleware) |

### Frontend
| Path | Fungsi |
|------|--------|
| `resources/js/pages/` | Inertia page components |
| `resources/js/components/` | Reusable Vue components |
| `resources/js/composables/` | Vue composables (logic) |
| `resources/js/composables/useAsyncJob.ts` | Frontend polling untuk async job status |
| `resources/js/stores/` | Pinia stores |
| `resources/js/types/` | TypeScript type definitions |
| `resources/js/layouts/` | Layout components |
| `resources/js/routes/` | Wayfinder auto-generated routes |
| `resources/js/lib/` | Utilities (cn, toast) |

### Database
| Path | Fungsi |
|------|--------|
| `database/migrations/..._create_ai_job_results_table.php` | Tabel monitoring async job (status, progress, result) |

### Konfigurasi
| Path | Fungsi |
|------|--------|
| `tsconfig.json` | TypeScript configuration |
| `vite.config.ts` | Vite configuration |
| `tailwind.config.ts` | TailwindCSS configuration |
| `eslint.config.js` | ESLint configuration |
| `package.json` | NPM dependencies & scripts |
| `composer.json` | PHP dependencies |
| `.env` | Environment variables (JANGAN COMMIT) |

---

*Dokumen ini sebagai referensi teknologi yang dipakai di AetherDB AI.*
*Terakhir diupdate: 2026-06-08*
