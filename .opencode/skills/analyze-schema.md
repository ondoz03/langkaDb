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
