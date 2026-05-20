<?php

declare(strict_types=1);

namespace App\Modules\Connection\Services;

use Illuminate\Support\Facades\Crypt;

class ConnectionEncryptor
{
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function decrypt(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}
