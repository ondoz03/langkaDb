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
