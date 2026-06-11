<?php

declare(strict_types=1);

namespace App\Modules\Designer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramNode extends Model
{
    use HasUlids;

    protected $table = 'diagram_nodes';

    protected $fillable = [
        'diagram_id',
        'table_name',
        'x_pos',
        'y_pos',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'x_pos' => 'float',
            'y_pos' => 'float',
            'metadata' => 'array',
        ];
    }

    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }
}
