<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaSnapshot extends Model
{
    protected $fillable = [
        'connection_id',
        'label',
        'schema_data',
    ];

    protected function casts(): array
    {
        return [
            'schema_data' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Connection\Models\Connection::class);
    }
}
