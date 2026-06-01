# AetherDB AI

**AI-Powered Database Intelligence Desktop App**

Built with Laravel 13 + Vue 3 + Inertia.js + Tauri v2 + AI Multi-Agent.

## 🚀 Tech Stack

| Layer | Tech |
|-------|------|
| **Backend** | Laravel 13, PHP 8.4, MySQL/MariaDB |
| **Frontend** | Vue 3, TypeScript, Inertia.js, TailwindCSS v4 |
| **Desktop** | Tauri v2 (Rust), cross-platform |
| **AI** | Multi-agent (Schema, Optimization, Security, Monitoring, Documentation) |
| **Queue** | Laravel Queue + database driver |
| **Cache** | Redis / database with AICacheService (TTL-based) |

## ✨ Features

- 🔗 **Multi-connection** — manage multiple MySQL/MariaDB databases
- 📊 **Schema Visualizer** — interactive graph with Vue Flow
- 🤖 **AI-Powered Analysis** — schema insights, optimization, security audit, monitoring
- 💬 **AI Chat** — natural language database queries with schema context
- 🔍 **Query Analyzer** — EXPLAIN visualizer + slow query reader
- 📈 **Health Scoring** — 4-dimension algorithm (structure, performance, index, security)
- 📋 **Documentation Generator** — auto-document tables and columns
- 📦 **Desktop App** — Tauri v2 with credential vault, SSH tunnels, notifications

## 🔧 Development

```bash
# Backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Frontend
npm install
npm run dev

# Desktop (Tauri)
npm run tauri:dev      # Development mode
npm run tauri:build    # Production build
```

## 🐳 Docker

```bash
docker compose -f docker/docker-compose.yml up -d
```

## 🧪 Testing

```bash
# Backend tests
php artisan test

# Specific AI tests
php artisan test tests/Unit/AICacheServiceTest.php
php artisan test tests/Unit/SecurityAgentTest.php

# Frontend type check
npm run types:check
```

## 📦 Build

```bash
# Build for production (Linux: .deb, .AppImage)
npm run tauri:build

# Output: src-tauri/target/release/bundle/
#   - deb/     → AetherDB-AI_0.1.0_amd64.deb
#   - appimage/ → AetherDB-AI_0.1.0_amd64.AppImage
```
