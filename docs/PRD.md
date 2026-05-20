# PRD — AetherDB AI
## AI-Powered Database Intelligence Desktop Application

**Version:** 1.0.0  
**Status:** Draft  
**Author:** Product Team  
**Last Updated:** 2026-05-20  
**Target Release (MVP):** Q3 2026

---

## Table of Contents

1. [Product Overview](#1-product-overview)
2. [Core Vision](#2-core-vision)
3. [Target Users](#3-target-users)
4. [Problem Statement](#4-problem-statement)
5. [Main Features](#5-main-features)
6. [Technical Architecture](#6-technical-architecture)
7. [UI/UX Direction](#7-uiux-direction)
8. [Application Modules](#8-application-modules)
9. [AI Agent System](#9-ai-agent-system)
10. [Security](#10-security)
11. [Desktop Features (Tauri)](#11-desktop-features-tauri)
12. [MVP Scope](#12-mvp-scope)
13. [Future Roadmap](#13-future-roadmap)
14. [Success Metrics](#14-success-metrics)
15. [Suggested License](#15-suggested-license)

---

## 1. Product Overview

**AetherDB AI** adalah aplikasi desktop berbasis web yang dirancang sebagai *AI Copilot untuk Database*. Aplikasi ini membantu developer, DevOps, DBA, dan software engineer untuk:

- Membaca dan memvisualisasikan struktur database secara interaktif
- Membuat ERD (Entity Relationship Diagram) otomatis dan graph schema
- Menganalisis kesehatan database secara real-time
- Mendapatkan rekomendasi optimasi dari AI (indexing, query, schema)
- Menemukan bottleneck query dan slow query dengan cepat
- Membuat insight otomatis dari struktur dan performa database

### Runtime Architecture

| Layer | Technology |
|-------|------------|
| Backend API | Laravel 13 + PHP 8.4+ |
| Frontend | Vue 3 + Inertia.js + TailwindCSS + shadcn/ui |
| Desktop Wrapper | Tauri v2 (Rust) |
| AI Agent Engine | Multi-agent architecture (OpenAI, Claude, Ollama) |
| Database Driver | MySQL, MariaDB (PostgreSQL - Phase 2) |

---

## 2. Core Vision

> **"AI Copilot untuk Database"**

AetherDB AI bukan sekadar database viewer biasa. Ini adalah platform observability + AI analyst yang:

- **Memahami struktur database** — tabel, kolom, relasi, tipe data, constraint
- **Memahami relasi bisnis** — menginferensi domain bisnis dari naming convention dan foreign key
- **Memahami performa** — slow query, index coverage, table scan, lock contention
- **Memberi saran optimasi otomatis** — missing index, query rewrite, normalization suggestion
- **Menjadi observability tool** — dashboard health, metrics, active connections

---

## 3. Target Users

### Primary Users

| Role | Use Case |
|------|----------|
| Full-stack Developer | Memahami schema project, debug query lambat |
| Backend Engineer | Optimasi query dan indexing |
| DevOps Engineer | Monitoring koneksi dan performa database |
| Database Administrator | Health audit, security scan, dokumentasi otomatis |
| Startup CTO | Review arsitektur database, scalability check |
| System Analyst | Analisis relasi tabel dan business domain mapping |

### Secondary Users

- QA Engineer — validasi struktur data dan constraint
- Infrastructure Engineer — capacity planning dan disk usage monitoring
- Data Engineer — pemahaman schema sebelum pipeline ETL

---

## 4. Problem Statement

### Pain Points yang Diselesaikan

**Problem 1 — Database tidak terdokumentasi**  
Developer kesulitan memahami schema database legacy yang tidak terdokumentasi. AetherDB AI membaca dan mendokumentasikan otomatis.

**Problem 2 — Query lambat tanpa tahu sebabnya**  
Engineer tidak tahu query mana yang menyebabkan bottleneck. AetherDB AI mendeteksi slow query dan memberikan rekomendasi perbaikan.

**Problem 3 — Index management yang manual**  
DBA harus manual mengecek missing index. AetherDB AI mendeteksi otomatis dan merekomendasikan index yang optimal.

**Problem 4 — Tidak ada visibility performa database**  
Tidak ada tool yang menggabungkan schema visualization + performance monitoring + AI recommendation dalam satu aplikasi desktop yang ringan.

---

## 5. Main Features

### 5.1 Database Connection Manager

Fitur manajemen koneksi database yang aman dan fleksibel.

**Capabilities:**
- Add, edit, delete database connections
- Secure credential storage (encrypted local vault via Tauri)
- SSH Tunnel support
- SSL/TLS connection support
- Multi-database management (kelola banyak koneksi sekaligus)
- Connection health testing
- Auto-reconnect on disconnect

**Supported Databases (Phase 1):**
- MySQL 5.7+
- MariaDB 10.x+

**Supported Databases (Phase 2):**
- PostgreSQL 14+
- SQLite

---

### 5.2 Database Structure Reader

AI membaca dan mem-parsing seluruh struktur database secara komprehensif.

**Data yang Dibaca:**
- Tables, columns, data types
- Constraints (PRIMARY KEY, UNIQUE, NOT NULL, CHECK)
- Indexes (single, composite, fulltext)
- Foreign keys dan relasi
- Triggers dan stored procedures
- Views
- Event schedulers

**Output:**
- Interactive schema tree
- Entity relation graph (ERD)
- Dependency graph antar objek
- Table usage analysis (estimasi frekuensi akses)

---

### 5.3 Visual Database Graph

Graph interaktif untuk memvisualisasikan relasi antar tabel.

**Features:**
- Drag & drop node positioning
- Zoom in/out canvas (scroll + pinch)
- Auto relation mapping dari foreign key
- Smart layout engine (force-directed / hierarchical)
- Relation highlighting on hover
- AI-generated grouping (clustering tabel berdasarkan domain bisnis)
- Table cluster coloring

**Technology:**
- Vue Flow (node-based graph engine)
- SVG path renderer untuk relation lines
- Framer Motion untuk smooth animation

**Design Inspiration:** n8n, Linear, Supabase Studio, Vercel

---

### 5.4 AI Database Analyst

Core AI feature — engine analisis berbasis multi-agent.

#### AI Schema Understanding
AI memahami:
- Struktur dan hierarki tabel
- Naming convention (snake_case, camelCase, prefix/suffix patterns)
- Hubungan bisnis yang terinferensikan dari relasi tabel
- Kemungkinan fungsi bisnis tiap tabel

#### AI Suggestions
AI memberikan rekomendasi:

| Kategori | Rekomendasi |
|----------|-------------|
| Index | Missing index detection, duplicate index detection |
| Query | Slow query optimization, query rewrite suggestion |
| Schema | Bad schema detection, normalization suggestion |
| Maintenance | Unused table detection, column redundancy detection |
| Security | Weak permissions, exposed sensitive tables |

#### AI Health Scoring

AetherDB AI menghasilkan skor kesehatan database (0–100) dalam 4 dimensi:

| Dimensi | Deskripsi |
|---------|-----------|
| Structure Quality | Normalitas schema, konsistensi naming, constraint coverage |
| Performance Quality | Query efficiency, index coverage, slow query ratio |
| Index Quality | Missing index, duplicate index, index bloat |
| Security Quality | Permission hygiene, sensitive column exposure |

---

### 5.5 Query Performance Monitor

Monitoring dan analisis performa query secara mendalam.

**Features:**
- Slow query log reader (dari MySQL slow query log / Performance Schema)
- EXPLAIN / EXPLAIN ANALYZE visualizer
- Query execution plan tree visualization
- Index usage checker per query
- Query bottleneck heatmap

**Metrics yang Dipantau:**

| Metric | Deskripsi |
|--------|-----------|
| Query latency | Waktu eksekusi per query |
| Table scan detection | Full scan vs index scan |
| Lock contention | Lock wait time, deadlock detection |
| Missing index | Query yang tidak memanfaatkan index |
| Query frequency | Top N query paling sering dieksekusi |

---

### 5.6 AI Chat Assistant

Interface chat natural language untuk interaksi dengan database melalui AI.

**Contoh Pertanyaan yang Bisa Diajukan:**
- *"Kenapa query ini lambat?"*
- *"Table mana yang tidak dipakai?"*
- *"Buat index terbaik untuk table orders"*
- *"Jelaskan relasi antara table users dan table subscriptions"*
- *"Apa bottleneck terbesar di database ini?"*
- *"Apakah schema ini scalable untuk 10 juta rows?"*
- *"Generate dokumentasi markdown untuk semua table"*

**Context-Aware:**  
AI Chat memiliki akses ke schema context yang sudah di-parse, sehingga jawaban spesifik terhadap database yang sedang dibuka.

---

### 5.7 Database Health Dashboard

Dashboard overview real-time kondisi database.

**Dashboard Widgets:**

| Widget | Data |
|--------|------|
| Active Connections | Jumlah koneksi aktif vs max |
| Query Throughput | QPS (queries per second) |
| Table Growth | Estimasi pertumbuhan ukuran tabel |
| Disk Usage | Data size, index size, free space |
| Cache Hit Ratio | Buffer pool hit ratio |
| Index Health | Index fragmentation, unused indexes |
| AI Recommendation Panel | Top 3 rekomendasi AI hari ini |

---

## 6. Technical Architecture

### System Flow

```
Tauri Desktop App
       ↓
  Vue 3 Frontend (Inertia.js)
       ↓
  Laravel 13 API Layer
       ↓
  AI Agent Orchestrator
       ↓
  Database Analyzer Engine (Doctrine DBAL + Native PDO)
       ↓
  MySQL / MariaDB
```

### Backend Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 13 |
| Runtime | PHP 8.4+ |
| Realtime | Laravel Reverb (WebSocket) |
| Async Jobs | Laravel Queue (Redis) |
| Scheduler | Laravel Scheduler |
| AI SDK | OpenAI PHP SDK, LangChain PHP |
| DB Introspection | Doctrine DBAL + Native PDO |
| Vector Memory | pgvector / SQLite VSS (future) |
| Protocol | MCP (Model Context Protocol) support |

### Frontend Stack

| Layer | Technology |
|-------|------------|
| Framework | Vue 3 + TypeScript |
| SPA Bridge | Inertia.js |
| Build Tool | Vite |
| UI Kit | TailwindCSS + shadcn/ui |
| Animation | Framer Motion |
| Icons | Lucide Icons |
| Graph Engine | Vue Flow |
| Optional Chart | D3.js |

### Desktop Stack

| Layer | Technology |
|-------|------------|
| Desktop Wrapper | Tauri v2 |
| System Language | Rust |
| Secret Storage | Tauri secure store (OS keychain) |
| Auto Updater | Tauri updater plugin |
| Tray | Tauri system tray |

### AI Model Recommendation

| Task | Recommended Model |
|------|-------------------|
| Deep Reasoning | GPT-5 / Claude Opus |
| SQL Analysis | Claude (Anthropic) |
| Embedding | text-embedding-3-large |
| Fast Suggestions | GPT-4o-mini |
| Local Optional | Ollama (Llama 3, Qwen) |

### AI Provider Support

- OpenAI
- Anthropic (Claude)
- Google Gemini (future)
- OpenRouter
- Ollama (local)
- LM Studio (local)

---

## 7. UI/UX Direction

### Design Philosophy

AetherDB AI menggunakan **minimalist monochrome style** yang terinspirasi dari:
- **Linear** — dense information layout, keyboard-first
- **Vercel** — clean, flat, monospace aesthetic
- **Raycast** — dark theme, command palette UX
- **n8n** — node-based graph interaction

### Global Theme System

```css
/* Typography */
--font-mono: 'Geist Mono', monospace;
--font-sans: 'Geist Mono', monospace;

/* Shape */
--radius: 0rem; /* sharp corners, no rounding */
```

### Design Principles

- Flat UI — no heavy drop shadows, no skeuomorphic elements
- Monospace typography — konsisten di seluruh aplikasi
- Dense information layout — maksimalkan informasi per pixel
- Keyboard-first navigation — semua action bisa dilakukan tanpa mouse
- Neutral grayscale palette — minimal warna, aksen minimal
- No glow / no gradient abuse

### Responsive Behavior

Target utama adalah **developer workstation** dan **ultrawide monitor**. Responsive behavior:
- Collapsible sidebar
- Zoomable graph canvas
- Adaptive panel layout (split pane)

---

## 8. Application Modules

| Module | Description |
|--------|-------------|
| Auth Module | Login, session management, local auth |
| Workspace Module | Multi-project workspace management |
| Connection Module | Database connection manager + vault |
| Schema Engine | Database structure parser dan mapper |
| AI Engine | AI orchestration layer (multi-agent) |
| Monitoring Engine | Metrics collector dan real-time monitor |
| Graph Engine | Visual relation graph (Vue Flow) |
| Recommendation Engine | AI optimization engine |
| Export Engine | PDF / Markdown documentation export |

### Suggested Page Structure

| Page | Path | Function |
|------|------|----------|
| Dashboard | `/dashboard` | Overview metrics & AI highlights |
| Connections | `/connections` | Manage database connections |
| Database Graph | `/graph/:connectionId` | Visual ERD schema |
| AI Insights | `/insights/:connectionId` | AI recommendations & scoring |
| Query Analyzer | `/queries/:connectionId` | Slow query analysis |
| Monitoring | `/monitoring/:connectionId` | Health metrics dashboard |
| Settings | `/settings` | App configuration |

### Suggested Folder Structure

```
resources/
 └── js/
     ├── components/
     │   ├── ui/           # shadcn/ui components + custom primitives
     │   ├── graph/        # Vue Flow nodes, edges, canvas
     │   ├── database/     # Schema tree, table viewer
     │   ├── ai/           # AI chat, insight cards, recommendation
     │   └── monitoring/   # Dashboard widgets, metrics
     ├── pages/            # Inertia page components
     ├── layouts/          # App shell, sidebar, header
     ├── composables/      # useConnection, useSchema, useAI, etc.
     └── stores/           # Pinia stores
```

---

## 9. AI Agent System

### Agent Architecture

AetherDB AI menggunakan multi-agent architecture dengan 5 specialized agents:

#### Agent 1 — Schema Agent
**Responsibilities:**
- Membaca seluruh struktur database (DDL introspection)
- Memahami relasi antar tabel
- Menginferensi domain bisnis dari schema
- Membangun context map untuk agent lain

#### Agent 2 — Optimization Agent
**Responsibilities:**
- Analisis missing index
- Query rewrite recommendation
- Schema improvement suggestion
- Indexing strategy recommendation

#### Agent 3 — Security Agent
**Responsibilities:**
- Deteksi weak permission (user grants)
- Exposed sensitive tables / columns (PII detection)
- Risky user privilege analysis
- Security scoring

#### Agent 4 — Monitoring Agent
**Responsibilities:**
- Performance metric collection
- Slow query analysis dan pattern detection
- Cache hit ratio monitoring
- Connection pool analysis

#### Agent 5 — Documentation Agent
**Responsibilities:**
- Auto-generate database documentation
- Generate Markdown docs per tabel / schema
- Generate ERD explanation in natural language
- Export ke format PDF / Markdown

### AI Workflow

```
User Database
      ↓
Schema Scanner (Doctrine DBAL)
      ↓
AI Parsing Engine (Schema Agent)
      ↓
Relationship Mapper
      ↓
Optimization Engine (Optimization Agent)
      ↓
Security Scan (Security Agent)
      ↓
Recommendation Generator
      ↓
Interactive Dashboard
```

---

## 10. Security

### Security Features

| Feature | Implementation |
|---------|----------------|
| Encrypted credentials | AES-256 via Tauri secure store (OS keychain) |
| Local secure vault | Credentials tidak pernah keluar dari device |
| Read-only mode | Koneksi bisa di-set sebagai read-only |
| SSH tunneling | SSH tunnel untuk koneksi ke remote DB |
| RBAC system | Role-based access (future: team workspace) |
| Audit logs | Log semua aksi AI dan user terhadap database |

### Security Principles
- **Zero cloud credential storage** — semua credential disimpan lokal
- **No query execution by default** — AI hanya membaca schema, tidak mengeksekusi query destruktif
- **Sandboxed AI execution** — AI tidak bisa langsung menulis ke database

---

## 11. Desktop Features (Tauri)

| Feature | Description |
|---------|-------------|
| Offline mode | Schema yang sudah di-cache bisa diakses offline |
| Native notifications | Alert slow query, health warning |
| System tray | Background monitoring dari tray icon |
| Background sync | Periodic metric collection di background |
| Auto updater | Tauri updater untuk distribusi update |
| File system access | Export dokumentasi ke lokal filesystem |
| Secure secret storage | OS keychain via Tauri plugin |

---

## 12. MVP Scope

### Phase 1 — MVP (Q3 2026)

**Included:**
- Database connection manager (MySQL + MariaDB)
- Schema reader dan parser
- Visual relation graph (ERD)
- AI recommendation (missing index, slow query, schema quality)
- Slow query analyzer
- AI chat assistant
- Basic health dashboard
- Documentation export (Markdown)

**Excluded from MVP:**
- Team collaboration / multi-user
- Cloud sync
- PostgreSQL dan SQLite support
- Real-time live monitoring (WebSocket)
- AI auto-fix (one-click apply)
- Enterprise RBAC

---

## 13. Future Roadmap

### Phase 2 — Growth (Q4 2026)
- PostgreSQL support
- SQLite support
- Team workspace (multi-user)
- Cloud sync (optional, user-controlled)
- AI migration planner
- Real-time monitoring (WebSocket)

### Phase 3 — Scale (Q1 2027)
- Kubernetes database monitoring integration
- AI auto-fix (apply rekomendasi langsung ke database)
- Real-time query optimization
- Full multi-agent orchestration dengan long-term memory
- Enterprise edition (SSO, RBAC, audit trail)

---

## 14. Success Metrics

### Technical Metrics

| Metric | Target |
|--------|--------|
| Query analysis latency | < 3 detik |
| Schema parsing time (100 tables) | < 10 detik |
| Graph rendering | 60 FPS |
| App startup time | < 3 detik |
| Memory usage (idle) | < 200 MB |

### Product Metrics

| Metric | Target |
|--------|--------|
| Time-to-first-insight | < 2 menit setelah connect |
| AI recommendation accuracy | > 80% actionable |
| Reduced DBA workload | 40% waktu lebih hemat untuk health audit |
| Schema documentation coverage | 100% tabel ter-dokumentasi otomatis |

---

## 15. Suggested License

| Edition | License Model |
|---------|--------------|
| Personal | Free (limited connections) |
| Professional | Commercial desktop license (one-time / annual) |
| Enterprise | Enterprise license (multi-seat, SSO, audit) |
| SaaS (Future) | Subscription-based cloud edition |

---

*Document version 1.0.0 — AetherDB AI PRD*
