<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Services;

use App\Modules\AIAgent\Agents\SchemaAgent;
use App\Modules\Schema\DTOs\SchemaContextDTO;

class Orchestrator
{
    public function __construct(
        private readonly SchemaAgent $schemaAgent,
    ) {}

    public function analyzeSchema(SchemaContextDTO $context): array
    {
        $result = $this->schemaAgent->analyze($context);

        return $result->toArray();
    }
}
