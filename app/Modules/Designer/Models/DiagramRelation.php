<?php

declare(strict_types=1);

namespace App\Modules\Designer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramRelation extends Model
{
    use HasUlids;

    protected $table = 'diagram_relations';

    protected $fillable = [
        'diagram_id',
        'from_table',
        'from_column',
        'to_table',
        'to_column',
        'type',
        'name',
    ];

    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }
}
