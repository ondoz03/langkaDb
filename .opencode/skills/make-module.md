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
