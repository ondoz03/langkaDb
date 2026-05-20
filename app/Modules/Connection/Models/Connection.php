<?php

declare(strict_types=1);

namespace App\Modules\Connection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Connection extends Model
{
    use HasUlids;

    protected $table = 'connections';

    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'ssl_enabled',
        'ssh_enabled',
        'ssh_host',
        'ssh_port',
        'ssh_user',
        'ssh_key',
        'status',
    ];

    protected $hidden = [
        'password',
        'ssh_key',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'ssl_enabled' => 'boolean',
            'ssh_enabled' => 'boolean',
            'ssh_port' => 'integer',
        ];
    }
}
