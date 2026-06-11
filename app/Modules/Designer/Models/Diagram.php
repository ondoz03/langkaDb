<?php

declare(strict_types=1);

namespace App\Modules\Designer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diagram extends Model
{
    use HasUlids;

    protected $table = 'diagrams';

    protected $fillable = [
        'name',
        'description',
        'connection_id',
        'layout_data',
    ];

    protected function casts(): array
    {
        return [
            'layout_data' => 'array',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(DiagramNode::class);
    }
}
