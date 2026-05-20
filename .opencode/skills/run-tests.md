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
